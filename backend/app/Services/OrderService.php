<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\DeliveryClosure;
use App\Models\DeliverySlot;
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
            // La derniere place se prend ici, verrou pose. Voir takeSlotSpot().
            $this->takeSlotSpot($data['delivery_slot_id'], $data['delivery_date']);

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

            $payment = $this->paymentService->createManualPayment(
                $order,
                $data['payment_provider'] ?? null,
                $data['payer_phone'] ?? null
            );

            /*
             * Une commande a zero franc est payee d'avance : il n'y a rien a
             * encaisser.
             *
             * La laisser attendre une confirmation serait un blocage sans
             * objet — le client fixerait un ecran lui demandant de regler zero
             * franc, et sa commande occuperait un creneau sans jamais partir.
             *
             * Le cas se presente des qu'un article est offert et que la zone ne
             * facture pas la livraison. Il sert aussi a eprouver le parcours
             * complet sans faire circuler d'argent reel.
             */
            if (Money::round((float) $order->total) === 0) {
                $this->paymentService->confirmPayment($payment, reference: 'Commande sans montant');
                $order->refresh();

                OrderEvent::record($order, 'payment.waived', reason: 'Total nul : rien à encaisser');
            }

            // Créer la notification
            $this->notificationService->sendOrderCreatedNotification($order);

            Log::info("Order created: {$order->order_number} by user {$client->id}");

            return $order;
        });
    }

    /**
     * Prend une place sur le creneau, ou refuse.
     *
     * Le controleur verifie deja la capacite avant d'arriver ici — mais il la
     * lit HORS transaction, et rend la main avant que la commande soit ecrite.
     * Deux clients qui visent la derniere place a trois secondes d'ecart
     * passaient donc tous les deux ce controle.
     *
     * Ce n'est pas un detail de robustesse : la capacite d'un creneau EST le
     * stock reel d'Axizan. Rien d'autre n'est rare ici — les ignames ne
     * manquent pas au marche, les heures si. Une place vendue deux fois, c'est
     * un preparateur qui decouvre le matin une commande de trop, et un client
     * qu'il faut rappeler pour lui reprendre son creneau.
     *
     * Le verrou porte sur la LIGNE DU CRENEAU, pas sur les commandes : c'est le
     * seul point de passage commun a tous ceux qui reservent ce creneau-la. Ils
     * s'alignent derriere, et chacun compte apres celui qui le precede. Poser
     * le verrou sur les commandes existantes ne protegerait de rien — ce qu'on
     * veut empecher, ce sont les lignes qui n'existent pas encore.
     *
     * Le controle du controleur n'est pas retire pour autant : il repond plus
     * vite, avec les autres motifs de refus (creneau ferme, delai trop court,
     * date hors horizon), et evite d'ouvrir une transaction pour rien. Celui-ci
     * est le dernier mot.
     *
     * Note : sur SQLite — la suite de tests — `lockForUpdate` ne compile rien.
     * Le verrouillage ne s'eprouve qu'en PostgreSQL. Le refus, lui, se teste.
     */
    private function takeSlotSpot($slotId, $date): void
    {
        $slot = DeliverySlot::whereKey($slotId)->lockForUpdate()->first();

        if (! $slot || ! $slot->is_active) {
            throw new SlotUnavailableException('Ce créneau de livraison n’est plus proposé.');
        }

        if (! $slot->zone?->is_active) {
            throw new SlotUnavailableException('Cette zone n’est plus desservie pour le moment.');
        }

        // Une journee peut se fermer pendant qu'un client est dans le tunnel :
        // c'est justement le cas d'une fermeture en urgence — marche en feu,
        // route coupee. Le controle d'entree l'a laisse passer il y a deux
        // minutes ; celui-ci a le dernier mot.
        if (DeliveryClosure::closes($date, $slot->zone_id)) {
            $reason = DeliveryClosure::reasonFor($date, $slot->zone_id);

            throw new SlotUnavailableException(
                'Nous ne livrons pas ce jour-là'.($reason ? " : {$reason}" : '.')
            );
        }

        if ($slot->bookedCountOn($date) >= $slot->max_orders_per_slot) {
            throw new SlotUnavailableException('Ce créneau est complet pour cette date.');
        }
    }

    /**
     * Un preparateur prend une commande du marche.
     *
     * Plusieurs preparateurs regardent la MEME liste : la commande qui apparait
     * est visible de tous ceux dont le marche dessert la zone. Deux doigts sur
     * « Accepter » a la meme seconde passaient tous les deux le controle, et le
     * second ecrasait le premier — qui etait deja parti acheter l'igname, sans
     * que rien ne le lui dise. Il revenait au depot avec un panier qui ne lui
     * appartenait plus.
     *
     * La commande est donc relue A L'INTERIEUR de la transaction, verrou pose :
     * ceux qui arrivent derriere attendent, puis constatent qu'elle est prise.
     *
     * Le verrou se relit sur une instance fraiche plutot que sur celle recue en
     * argument — celle-la a ete chargee avant, et c'est justement l'ecart entre
     * les deux moments qu'on cherche a fermer. La mise a jour passe par le
     * modele et non par le constructeur de requetes : un UPDATE de masse ne
     * declenche pas les evenements Eloquent, et le journal des transitions
     * — branche sur `updated` — perdrait l'entree.
     *
     * Note : sur SQLite (la suite de tests) `lockForUpdate` ne compile rien. Le
     * verrouillage ne s'eprouve qu'en PostgreSQL ; la relecture, elle, se teste.
     */
    public function acceptOrder(Order $order, User $preparateur): Order
    {
        $accepted = DB::transaction(function () use ($order, $preparateur) {
            $fresh = Order::whereKey($order->getKey())->lockForUpdate()->first();

            if (! $fresh) {
                throw new \Exception('Cette commande n’existe plus.');
            }

            if ($fresh->preparateur_id && $fresh->preparateur_id !== $preparateur->id) {
                throw new \Exception('Cette commande vient d’être prise par quelqu’un d’autre.');
            }

            if (! $fresh->canBeAccepted()) {
                throw new \Exception('Cette commande ne peut pas être acceptée');
            }

            $fresh->update([
                'status' => OrderStatus::ACCEPTED,
                'preparateur_id' => $preparateur->id,
                'accepted_at' => now(),
            ]);

            return $fresh;
        });

        $this->notificationService->sendOrderAcceptedNotification($accepted);
        Log::info("Order {$accepted->order_number} accepted by {$preparateur->name}");

        return $accepted;
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

    /*
     * assignDelivery() a ete retiree ici.
     *
     * Elle etait le seul endroit qui ecrivait `orders.livreur_id` et le seul
     * qui declenchait le SMS « le livreur est en route » — et elle n'etait
     * appelee de nulle part. Les deux manques se voyaient en production sans
     * qu'on sache d'ou ils venaient : contact livreur vide dans la salle de
     * controle, client jamais prevenu.
     *
     * Le vrai chemin passe par DeliveryService : assignOrderToLivreur() pose le
     * livreur sur la commande, startDelivery() previent le client. Voir la.
     */

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
     * La course ne revient pas.
     *
     * Le seul cas que rien ne savait traiter. Une commande partie en livraison
     * ne peut ni etre liberee — `release` la refuse, le retrait est deja fait —
     * ni etre annulee : `canBeCancelled()` exclut IN_DELIVERY, justement pour
     * qu'on n'annule pas sous les pieds d'un livreur qui roule.
     *
     * Ces deux refus sont bons quand la course se deroule. Ils formaient une
     * impasse quand elle ne se deroule pas : livreur injoignable, moto volee,
     * quelqu'un qui ne revient jamais. La commande restait IN_DELIVERY a vie,
     * le client sans nouvelle ni remboursement, et le compte du livreur
     * impossible a suspendre puisqu'un panier lui restait attribue.
     *
     * L'etat honnete est l'annulation. On ne peut pas la remettre au depot : le
     * panier n'y est plus. Annoncer « prete » a un autre livreur l'enverrait
     * chercher une marchandise qui n'existe plus sur place.
     *
     * La perte est pour Axizan, et elle est ecrite. Le client est rembourse et
     * prevenu — il a paye d'avance une commande qu'il ne recevra pas.
     */
    public function abandonDelivery(Order $order, string $reason, User $actor): Order
    {
        $delivery = $order->delivery;

        DB::transaction(function () use ($order, $delivery, $reason, $actor) {
            $delivery?->update([
                'status' => \App\Enums\DeliveryStatus::FAILED,
                'delivery_notes' => $reason,
            ]);

            OrderEvent::record(
                $order,
                'delivery.abandoned',
                $order->status instanceof \BackedEnum ? $order->status->value : (string) $order->status,
                OrderStatus::CANCELLED->value,
                $reason,
                [
                    'livreur_id' => $order->livreur_id,
                    'delivery_id' => $delivery?->id,
                    'by' => $actor->id,
                ]
            );

            $order->update([
                'status' => OrderStatus::CANCELLED,
                'cancellation_reason' => $reason,
            ]);

            // Meme regle que dans cancelOrder() : on se fie au PAIEMENT, pas a
            // `orders.payment_status`. Ce sont deux colonnes distinctes, et
            // l'annulation ne doit jamais echouer a cause de l'ecriture
            // comptable qui la suit.
            if ($order->payment?->status === \App\Enums\PaymentStatus::COMPLETED) {
                $this->paymentService->refundPayment($order->payment);
            }
        });

        $this->notificationService->sendOrderCancelledNotification($order);
        Log::warning("Delivery abandoned for order {$order->order_number}. Reason: {$reason}");

        return $order->fresh();
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
