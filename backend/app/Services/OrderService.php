<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Promotion;
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
            // Valider le stock
            foreach ($cartItems as $item) {
                if ($item['type'] === 'product') {
                    $product = Product::find($item['product_id']);
                    if (!$product || !$product->is_available) {
                        throw new \Exception("Produit non disponible: {$product->name}");
                    }
                    if ($product->stock_quantity < $item['quantity']) {
                        throw new \Exception("Stock insuffisant pour: {$product->name}");
                    }
                }
            }

            // Calculer les totaux
            $subtotal = $this->calculateSubtotal($cartItems);
            $deliveryFee = $this->calculateDeliveryFee(
                $data['delivery_address_id'],
                $data['delivery_slot_id'] ?? null,
                $subtotal
            );
            $tax = $this->calculateTax($subtotal);
            $promoDiscount = 0;

            // Code saisi par le client : on le resout en promotion, et un code
            // refuse bloque la commande avec la raison — le client ne doit pas
            // payer en croyant beneficier d'une reduction.
            if (!empty($data['promo_code'])) {
                $promo = Promotion::byCode($data['promo_code'])->lockForUpdate()->first();

                if (!$promo) {
                    throw new \Exception('Code promo inconnu.');
                }

                if ($reason = $promo->rejectionReasonFor($client, $subtotal)) {
                    throw new \Exception($reason);
                }

                $data['promo_code_id'] = $promo->id;
            }

            // Appliquer promo si fournie
            if (isset($data['promo_code_id'])) {
                $promoDiscount = $this->applyPromotion($client, $data['promo_code_id'], $subtotal);
            }

            $total = $subtotal + $deliveryFee + $tax - $promoDiscount;

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
                'discount_amount' => $promoDiscount,
                'total' => $total,
                'promo_code_id' => $data['promo_code_id'] ?? null,
                'promo_discount' => $promoDiscount,
                'payment_method' => $data['payment_method'] ?? 'manual',
                'payment_status' => 'pending',
                'client_notes' => $data['client_notes'] ?? null,
            ]);

            // Tracer l'utilisation du code : c'est ce compteur qui fait
            // respecter les plafonds global et par client.
            if ($promoDiscount > 0) {
                Promotion::find($data['promo_code_id'])->recordUsage($client, $order, $promoDiscount);
            }

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

        $this->notificationService->sendOrderReadyNotification($order);
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

        // Demander un avis
        $this->notificationService->sendReviewRequestNotification($order);

        return $order;
    }

    /**
     * Annuler commande
     */
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

            // Rembourser si nécessaire
            if ($order->payment_status === 'completed') {
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
     * Calculer les frais de livraison : base de la zone de l'adresse,
     * supplement du creneau, puis regles de la zone (livraison offerte,
     * minimum de commande).
     */
    private function calculateDeliveryFee($addressId, $slotId, float $subtotal): int
    {
        $address = \App\Models\Address::with('zone')->find($addressId);
        $zone = $address?->zone;
        $slot = $slotId ? \App\Models\DeliverySlot::find($slotId) : null;

        $fee = (float) ($zone?->base_delivery_fee ?? self::DEFAULT_DELIVERY_FEE);
        $fee += (float) ($slot?->additional_fee ?? 0);

        if (!$zone) {
            return Money::round($fee);
        }

        if ($zone->free_delivery_threshold !== null && $subtotal >= (float) $zone->free_delivery_threshold) {
            $fee = 0;
        }

        if ($subtotal < (float) $zone->min_order_amount) {
            if ($zone->small_order_action === 'block') {
                throw new \Exception(sprintf(
                    'Le minimum de commande pour la zone %s est de %s FCFA.',
                    $zone->name,
                    number_format(Money::round($zone->min_order_amount), 0, ',', ' ')
                ));
            }

            $fee += (float) $zone->small_order_fee;
        }

        return Money::round($fee);
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
            if ($item['type'] === 'product') {
                $product = Product::find($item['product_id']);
                $subtotal += Money::lineTotal($product->base_price, $item['quantity']);
            } elseif ($item['type'] === 'menu') {
                $menu = \App\Models\Menu::find($item['menu_id']);
                $subtotal += Money::lineTotal($menu->discounted_price ?? $menu->base_price, $item['quantity']);
            }
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

    /**
     * Appliquer promotion
     */
    private function applyPromotion(User $client, $promoId, float $orderTotal): float
    {
        // Verrouille : deux commandes simultanees ne doivent pas consommer
        // ensemble la derniere utilisation autorisee.
        $promo = Promotion::whereKey($promoId)->lockForUpdate()->first();

        if (!$promo || $promo->rejectionReasonFor($client, $orderTotal) !== null) {
            return 0;
        }

        return Money::round($promo->calculateDiscount($orderTotal));
    }
}
