<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
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
            $deliveryFee = $data['delivery_fee'] ?? 0;
            $tax = $this->calculateTax($subtotal);
            $promoDiscount = 0;

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
                'payment_method' => $data['payment_method'] ?? 'mobile_money',
                'payment_status' => 'pending',
                'client_notes' => $data['client_notes'] ?? null,
            ]);

            // Ajouter les items
            foreach ($cartItems as $item) {
                $this->addOrderItem($order, $item);
            }

            // Réserver le créneau
            if ($data['delivery_slot_id']) {
                $order->deliverySlot->incrementOrderCount();
            }

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

            // Libérer le créneau
            if ($order->delivery_slot_id) {
                $order->deliverySlot->decrementOrderCount();
            }

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
            'unit_price' => $product->base_price,
            'total_price' => $product->base_price * $item['quantity'],
            'special_instructions' => $item['special_instructions'] ?? null,
            'removed_ingredients' => isset($item['removed_ingredients']) ? json_encode($item['removed_ingredients']) : null,
        ]);
    }

    /**
     * Calculer sous-total
     */
    private function calculateSubtotal(array $cartItems): float
    {
        $subtotal = 0;

        foreach ($cartItems as $item) {
            if ($item['type'] === 'product') {
                $product = Product::find($item['product_id']);
                $subtotal += $product->base_price * $item['quantity'];
            } elseif ($item['type'] === 'menu') {
                $menu = \App\Models\Menu::find($item['menu_id']);
                $subtotal += ($menu->discounted_price ?? $menu->base_price) * $item['quantity'];
            }
        }

        return $subtotal;
    }

    /**
     * Calculer taxes (10%)
     */
    private function calculateTax(float $subtotal): float
    {
        return $subtotal * 0.10;
    }

    /**
     * Appliquer promotion
     */
    private function applyPromotion(User $client, $promoId, float $orderTotal): float
    {
        $promo = Promotion::find($promoId);

        if (!$promo || !$promo->isValid()) {
            return 0;
        }

        if (!$promo->canBeUsedBy($client)) {
            return 0;
        }

        if ($orderTotal < $promo->min_order_amount) {
            return 0;
        }

        return $promo->calculateDiscount($orderTotal);
    }
}
