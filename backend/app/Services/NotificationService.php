<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;

class NotificationService
{
    /**
     * Envoyer notification commande créée
     */
    public function sendOrderCreatedNotification(Order $order)
    {
        $this->notify(
            $order->client,
            NotificationType::ORDER_ACCEPTED,
            "Commande #{$order->order_number}",
            "Votre commande a été créée. En attente de confirmation.",
            ['order_id' => $order->id]
        );
    }

    /**
     * Notification commande acceptée
     */
    public function sendOrderAcceptedNotification(Order $order)
    {
        $this->notify(
            $order->client,
            NotificationType::ORDER_ACCEPTED,
            "✅ Commande acceptée",
            "Votre commande #{$order->order_number} a été acceptée par {$order->preparateur->name}",
            ['order_id' => $order->id]
        );
    }

    /**
     * Notification commande en préparation
     */
    public function sendOrderPreparingNotification(Order $order)
    {
        $this->notify(
            $order->client,
            NotificationType::ORDER_PREPARING,
            "👨‍🍳 Commande en préparation",
            "Votre commande est en cours de préparation",
            ['order_id' => $order->id]
        );
    }

    /**
     * Notification commande prête
     */
    public function sendOrderReadyNotification(Order $order)
    {
        $this->notify(
            $order->client,
            NotificationType::ORDER_READY,
            "📦 Commande prête",
            "Votre commande est prête pour la livraison!",
            ['order_id' => $order->id]
        );
    }

    /**
     * Notification livraison assignée
     */
    public function sendDeliveryAssignedNotification(Order $order, User $livreur)
    {
        $this->notify(
            $order->client,
            NotificationType::ORDER_OUT_FOR_DELIVERY,
            "🚚 En cours de livraison",
            "{$livreur->name} est en route pour vous livrer!",
            ['order_id' => $order->id, 'livreur_id' => $livreur->id]
        );

        $this->notify(
            $livreur,
            NotificationType::DELIVERY_ASSIGNED,
            "📦 Nouvelle livraison",
            "Commande #{$order->order_number} assignée pour {$order->client->name}",
            ['order_id' => $order->id]
        );
    }

    /**
     * Notification commande livrée
     */
    public function sendOrderDeliveredNotification(Order $order)
    {
        $this->notify(
            $order->client,
            NotificationType::ORDER_DELIVERED,
            "✨ Commande livrée",
            "Votre commande a été livrée avec succès!",
            ['order_id' => $order->id]
        );
    }

    /**
     * Notification paiement confirmé
     */
    public function sendPaymentConfirmedNotification(Order $order)
    {
        $this->notify(
            $order->client,
            NotificationType::PAYMENT_CONFIRMED,
            "💳 Paiement confirmé",
            "Votre paiement de {$order->total} XOF a été confirmé",
            ['order_id' => $order->id, 'amount' => $order->total]
        );
    }

    /**
     * Demande d'avis
     */
    public function sendReviewRequestNotification(Order $order)
    {
        $this->notify(
            $order->client,
            NotificationType::REVIEW_REQUEST,
            "⭐ Donnez votre avis",
            "Comment s'est passée votre livraison? Aidez-nous à nous améliorer!",
            ['order_id' => $order->id]
        );
    }

    /**
     * Notification commande annulée
     */
    public function sendOrderCancelledNotification(Order $order)
    {
        $this->notify(
            $order->client,
            NotificationType::ORDER_ACCEPTED,
            "❌ Commande annulée",
            "Votre commande #{$order->order_number} a été annulée",
            ['order_id' => $order->id]
        );
    }

    /**
     * Créer une notification générique
     */
    public function notify(User $user, NotificationType $type, $title, $message, $data = [])
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'is_sent_in_app' => true,
        ]);

        // TODO: Envoyer SMS/Email selon préférences utilisateur
        // $this->sendSMS($user, $title, $message);
        // $this->sendEmail($user, $title, $message);

        return $notification;
    }

    /**
     * Envoyer SMS (TODO: Intégrer Twilio/provider local)
     */
    private function sendSMS(User $user, $title, $message)
    {
        // Implementation SMS
    }

    /**
     * Envoyer Email (TODO)
     */
    private function sendEmail(User $user, $title, $message)
    {
        // Implementation Email
    }
}
