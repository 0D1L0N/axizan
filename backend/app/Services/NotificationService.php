<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderUpdateNotification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Envoyer notification commande créée
     */
    public function sendOrderCreatedNotification(Order $order)
    {
        $this->notify(
            $order->client,
            NotificationType::ORDER_CREATED,
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
            NotificationType::ORDER_CANCELLED,
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

        // L'in-app est toujours ecrit ; mail et SMS suivent le routage par
        // type (config/notifications.php) et les preferences du client.
        $channels = config('notifications.channels.'.$type->value, []);
        $order = isset($data['order_id']) ? Order::find($data['order_id']) : null;

        if (in_array('mail', $channels, true) && $this->allows($user, $type, 'email')) {
            $notification->is_sent_email = $this->sendEmail($user, $type, $title, $message, $order);
        }

        if (in_array('sms', $channels, true) && $this->allows($user, $type, 'sms')) {
            $notification->is_sent_sms = $this->sendSMS($user, $type, $message, $order);
        }

        $notification->save();

        return $notification;
    }

    /**
     * Le client accepte-t-il ce canal ?
     *
     * Les notifications transactionnelles — argent, livraison imminente — ne
     * se coupent pas : les desactiver ferait perdre des commandes payees.
     */
    private function allows(User $user, NotificationType $type, string $channel): bool
    {
        if (in_array($type->value, config('notifications.transactional', []), true)) {
            return true;
        }

        $preferences = $user->notification_preferences ?? [];

        return (bool) ($preferences[$channel] ?? true);
    }

    /**
     * SMS. La passerelle par defaut ecrit dans les journaux : aucun
     * agregateur n'est encore contractualise (voir config/sms.php).
     */
    private function sendSMS(User $user, NotificationType $type, string $message, ?Order $order): bool
    {
        if (blank($user->phone)) {
            return false;
        }

        // Un SMS se paie au segment de 160 caracteres : on va droit au but,
        // avec la seule chose qui compte — le numero de commande.
        $text = $order
            ? "Axizán : {$message} (commande {$order->order_number})"
            : "Axizán : {$message}";

        try {
            return app(SmsService::class)->send($user->phone, $text);
        } catch (\Throwable $e) {
            // Un envoi rate ne doit jamais faire echouer la commande qui l'a
            // declenche.
            Log::warning('Envoi SMS impossible', ['user' => $user->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * E-mail, mis en file : un SMTP lent ne doit pas retarder la commande.
     */
    private function sendEmail(User $user, NotificationType $type, string $title, string $message, ?Order $order): bool
    {
        if (blank($user->email)) {
            return false;
        }

        try {
            $user->notify(new OrderUpdateNotification($type, $title, $message, $order));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Envoi e-mail impossible', ['user' => $user->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

}
