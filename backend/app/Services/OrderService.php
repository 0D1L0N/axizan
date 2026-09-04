<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    /** Taux appliqué au sous-total, en pourcentage. */
    private const TAX_RATE_PERCENT = 10.0;

    /** Frais retenus quand l'adresse n'est rattachée à aucune zone. */
    private const DEFAULT_DELIVERY_FEE = 500;

    public function __construct(
        private NotificationService $notificationService,
        private PaymentService $paymentService
    ) {}

    /**
     * Créer une nouvelle commande
     */
    public function createOrder(User $client, array $cartItems, array $data): Order
    {
        return DB::transaction(function () use ($client, $cartItems, $data) {
            /*
             * Axizán ne détient aucun stock : les articles sont retirés chez des
             * fournisseurs partenaires, au moment de la commande.
             *
             * `is_available` est donc le SEUL verrou — un interrupteur que
             * l'administration bascule quand un produit disparaît du marché
             * national. `stock_quantity` reste un simple signal indicatif,
             * jamais une limite : refuser 50 kg d'ignames parce qu'une colonne
             * affiche 45 n'aurait aucun sens quand le fournisseur en a des sacs.
             */
            foreach ($cartItems as $item) {
                $product = Product::find($item['product_id']);

                if (! $product) {
                    throw new \Exception('Produit introuvable.');
                }

                if (! $product->is_available) {
                    throw new \Exception("Produit non disponible : {$product->name}");
                }
            }

            // Calculer les totaux
            $subtotal = $this->calculateSubtotal($cartItems);
            $deliveryFee = $this->calculateDeliveryFee($data['delivery_address_id']);
            $tax = $this->calculateTax($subtotal);

            $total = $subtotal + $deliveryFee + $tax;

            // Créer la commande
            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'client_id' => $client->id,
                'delivery_address_id' => $data['delivery_address_id'],
                'delivery_slot_id' => $data['delivery_slot_id'],
                'delivery_date' => $data['delivery_date'],
                'status' => OrderStatus::PENDING,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'tax' => $tax,
                // Aucune promotion n'est en place ; la colonne reste à zéro et
                // sert de place pour un geste commercial futur.
                'discount_amount' => 0,
                'total' => $total,
                'payment_method' => $data['payment_method'] ?? 'manual',
                'payment_status' => 'pending',
                'client_notes' => $data['client_notes'] ?? null,
            ]);

            // Ajouter les items
            foreach ($cartItems as $item) {
                $this->addOrderItem($order, $item);
            }

            // Le créneau n'a plus de compteur à incrémenter : sa capacité se
            // déduit des commandes de la date visée (DeliverySlot::bookedCountsOn).

            // Créer le paiement en attente de validation manuelle (admin)
            $this->paymentService->createManualPayment(
                $order,
                $data['payment_provider'] ?? null,
                $data['payer_phone'] ?? null
            );

            // Créer la notification
            $this->notificationService->sendOrderCreatedNotification($order);

            Log::info("Order created: {$order->order_number} by user {$client->id}");

            return $order;
        });
    }

    /**
     * Accepter une commande (par préparateur)
     */
    public function acceptOrder(Order $order, User $preparateur): Order
    {
        if (!$order->canBeAccepted()) {
            throw new \Exception("Cette commande ne peut pas être acceptée");
        }

        $order->update([
            'status' => OrderStatus::ACCEPTED,
            'preparateur_id' => $preparateur->id,
            'accepted_at' => now(),
        ]);

        $this->notificationService->sendOrderAcceptedNotification($order);
        Log::info("Order {$order->order_number} accepted by {$preparateur->name}");

        return $order;
    }

    /**
     * Marquer commande en préparation
     */
    public function startPreparing(Order $order, $notes = null): Order
    {
        if (!$order->canBePrepared()) {
            throw new \Exception("Cette commande ne peut pas être préparée");
        }

        $order->update([
            'status' => OrderStatus::PREPARING,
            'preparing_started_at' => now(),
            'preparateur_notes' => $notes,
        ]);

        $this->notificationService->sendOrderPreparingNotification($order);

        return $order;
    }

    /**
     * Marquer commande prête
     */
    public function markReady(Order $order, $notes = null): Order
    {
        $order->update([
            'status' => OrderStatus::READY,
            'ready_at' => now(),
            'preparateur_notes' => $notes,
        ]);

        $order->loadMissing('preparateur.market.depot', 'deliveryAddress');

        $this->notificationService->sendOrderReadyNotification($order);

        // Le panier est au depot et n'attend plus que quelqu'un pour le
        // prendre : c'est le moment ou les livreurs doivent l'apprendre.
        $this->notificationService->sendBasketAtDepotNotification($order);

        Log::info("Order {$order->order_number} marked as ready");

        return $order;
    }

    /**
     * Assigner livraison
     */
    public function assignDelivery(Order $order, User $livreur): Order
    {
        if ($order->status !== OrderStatus::READY) {
            throw new \Exception("Seules les commandes prêtes peuvent être livrées");
        }

        $order->update([
            'livreur_id' => $livreur->id,
            'status' => OrderStatus::IN_DELIVERY,
            'delivery_started_at' => now(),
        ]);

        $this->notificationService->sendDeliveryAssignedNotification($order, $livreur);

        return $order;
    }

    /**
     * Marquer commande livrée
     */
    public function markDelivered(Order $order): Order
    {
        $order->update([
            'status' => OrderStatus::DELIVERED,
            'delivered_at' => now(),
        ]);

        $this->notificationService->sendOrderDeliveredNotification($order);

        return $order;
    }

    /**
     * Compléter commande (après paiement)
     */
    public function completeOrder(Order $order): Order
    {
        $order->update([
            'status' => OrderStatus::COMPLETED,
            'completed_at' => now(),
        ]);

        return $order;
    }

    /**
     * Annuler commande
     */
    /**
     * Renvoie la commande au pool du marche.
     *
     * Deux voies y menent : le preparateur qui rend ce qu'il ne peut pas
     * honorer, et l'administrateur qui debloque une commande prise puis
     * abandonnee. Le geste est le meme, la trace dit lequel des deux.
     *
     * La remise a zero couvre AUSSI les prix estampilles : une ligne rendue a
     * ete deposee au pool sans avoir ete retiree, la facturer au fournisseur
     * reviendrait a payer une marchandise jamais sortie de l'etal.
     */
    public function releaseToPool(Order $order, string $reason, User $actor): Order
    {
        $order->loadMissing('items');
        $alreadyPicked = $order->items->where('collection_status', '!=', 'pending');

        DB::transaction(function () use ($order, $reason, $actor, $alreadyPicked) {
            OrderEvent::record(
                $order,
                'order.released',
                $order->status instanceof \BackedEnum ? $order->status->value : (string) $order->status,
                OrderStatus::PENDING->value,
                $reason,
                [
                    'released_by' => $actor->id,
                    'released_by_role' => $actor->role instanceof \BackedEnum
                        ? $actor->role->value
                        : (string) $actor->role,
                    'preparateur_id' => $order->preparateur_id,
                    // On garde la trace de ce qui avait ete pointe : c'est ce
                    // qui permettra de retrouver la marchandise.
                    'items_already_picked' => $alreadyPicked->pluck('product_id')->all(),
                ]
            );

            $order->items()->update([
                'collection_status' => 'pending',
                'supplier_id' => null,
                'replacement_product_id' => null,
                'collected_at' => null,
                'actual_unit_price' => null,
                'actual_total_price' => null,
            ]);

            $order->update([
                'status' => OrderStatus::PENDING,
                'preparateur_id' => null,
                'accepted_at' => null,
                'preparing_started_at' => null,
            ]);
        });

        return $order->fresh();
    }

    public function cancelOrder(Order $order, $reason = null): Order
    {
        if (!$order->canBeCancelled()) {
            throw new \Exception("Cette commande ne peut pas être annulée");
        }

        DB::transaction(function () use ($order, $reason) {
            $order->update([
                'status' => OrderStatus::CANCELLED,
                'cancellation_reason' => $reason,
            ]);

            // Rien à libérer : une commande annulée est exclue du décompte de
            // capacité, la place se rouvre d'elle-même.

            /*
             * Rembourser quand il y a effectivement quelque chose a rembourser.
             *
             * Le test porte sur le PAIEMENT, pas sur `orders.payment_status` :
             * ce sont deux colonnes distinctes, et rien ne garantit qu'elles
             * disent la meme chose. Se fier a la seconde faisait echouer
             * l'annulation entiere des qu'elles divergeaient — un client
             * bloque avec une commande qu'on ne peut plus annuler, pour une
             * incoherence interne qui ne le regarde pas.
             *
             * L'annulation prime : elle ne doit jamais echouer a cause de
             * l'ecriture comptable qui la suit.
             */
            if ($order->payment?->status === \App\Enums\PaymentStatus::COMPLETED) {
                $this->paymentService->refundPayment($order->payment);
            }
        });

        $this->notificationService->sendOrderCancelledNotification($order);
        Log::info("Order {$order->order_number} cancelled. Reason: {$reason}");

        return $order;
    }

    /**
     * Ajouter un item à la commande
     */
    private function addOrderItem(Order $order, array $item): OrderItem
    {
        $product = Product::find($item['product_id']);

        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
            'quantity_unit' => $item['quantity_unit'] ?? 'piece',
            'unit_price' => Money::round($product->base_price),
            'total_price' => Money::lineTotal($product->base_price, $item['quantity']),
            'special_instructions' => $item['special_instructions'] ?? null,
            'removed_ingredients' => $item['removed_ingredients'] ?? null,
            'substitution_preference' => $item['substitution_preference'] ?? 'replace',
        ]);
    }

    /**
     * Calculer les frais de livraison depuis la zone de l'adresse
     */
    private function calculateDeliveryFee($addressId): int
    {
        $address = \App\Models\Address::with('zone')->find($addressId);

        return Money::round($address?->zone?->base_delivery_fee ?? self::DEFAULT_DELIVERY_FEE);
    }

    /**
     * Calculer sous-total
     */
    private function calculateSubtotal(array $cartItems): float
    {
        $subtotal = 0;

        // Chaque ligne est arrondie AVANT la somme, exactement comme dans
        // addOrderItem() : sinon le sous-total affiche ne correspond pas a la
        // somme des lignes que le client a sous les yeux.
        foreach ($cartItems as $item) {
            $product = Product::find($item['product_id']);
            $subtotal += Money::lineTotal($product->base_price, $item['quantity']);
        }

        return $subtotal;
    }

    /**
     * Calculer taxes (10%), au franc entier.
     */
    private function calculateTax(float $subtotal): int
    {
        return Money::percentOf($subtotal, self::TAX_RATE_PERCENT);
    }

}
