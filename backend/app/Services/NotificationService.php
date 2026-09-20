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
     * Un article vient d'etre remplace ou retire au marche.
     *
     * Le client apprenait jusqu'ici a la livraison qu'un produit avait change.
     * Il le sait maintenant pendant que le preparateur est encore a l'etal, et
     * peut refuser.
     *
     * UN SEUL SMS PAR COMMANDE. Trois articles remplaces, c'est trois traces
     * in-app mais un seul message : le SMS ne sert qu'a le faire ouvrir
     * l'application, et trois messages coutent trois fois le prix pour dire la
     * meme chose. Les suivants restent in-app.
     */
    public function sendItemSubstitutedNotification(Order $order, \App\Models\OrderItem $item): void
    {
        $alreadyWarned = $order->items()
            ->whereNotNull('substitution_notified_at')
            ->where('id', '!=', $item->id)
            ->exists();

        $product = $item->product?->name ?? 'Un article';

        $message = $item->collection_status === 'replaced'
            ? "{$product} manquait au marché. Nous avons pris "
                .($item->replacementProduct?->name ?? 'un produit équivalent')
                .' à la place. Ouvrez Axizán pour accepter ou refuser.'
            : "{$product} est introuvable au marché aujourd’hui. Il ne sera pas dans votre panier.";

        $this->notify(
            $order->client,
            NotificationType::ITEM_SUBSTITUTED,
            $item->collection_status === 'replaced' ? 'Article remplacé' : 'Article introuvable',
            $message,
            ['order_id' => $order->id, 'order_item_id' => $item->id],
            // Le premier porte le SMS ; les suivants restent in-app.
            forceInAppOnly: $alreadyWarned
        );

        $item->forceFill(['substitution_notified_at' => now()])->save();
    }

    /**
     * Un panier vient d'arriver au depot : prevenir les livreurs.
     *
     * Le preparateur ne remet rien en main propre — il depose, puis repart
     * chercher la commande suivante. Sans ce signal, le panier attend qu'un
     * livreur pense a rafraichir son ecran, et des courses fraiches vieillissent
     * au depot pendant que des livreurs disponibles ne savent rien.
     *
     * La diffusion vise tous les livreurs actifs : le premier qui accepte
     * prend la course, exactement comme sur le terrain. In-app seulement
     * (voir config/notifications.php) — un SMS par livreur et par panier
     * couterait le prix de la course.
     */
    public function sendBasketAtDepotNotification(Order $order): void
    {
        $depot = $order->preparateur?->market?->depot;
        $where = $depot ? " au dépôt {$depot->name}" : ' au dépôt';

        User::query()
            ->where('role', \App\Enums\UserRole::LIVREUR)
            ->where('status', 'active')
            ->each(function (User $livreur) use ($order, $where) {
                $this->notify(
                    $livreur,
                    NotificationType::BASKET_AT_DEPOT,
                    'Panier disponible',
                    "Commande {$order->order_number} prête{$where} — quartier {$order->deliveryAddress?->quartier}.",
                    ['order_id' => $order->id]
                );
            });
    }

    /**
     * Le livreur quitte le depot avec le panier.
     *
     * Au client seulement. L'ancienne version prevenait aussi le livreur de la
     * course qu'il venait lui-meme d'accepter — un message pour lui apprendre
     * ce qu'il avait fait trois secondes plus tot.
     *
     * Le numero est dans le texte : ce SMS arrive quand le client doit etre
     * joignable et present. S'il a un empechement, il faut qu'il puisse appeler
     * sans rouvrir l'application.
     */
    public function sendDeliveryStartedNotification(Order $order, User $livreur)
    {
        $reach = $livreur->phone ? " ({$livreur->phone})" : '';

        $this->notify(
            $order->client,
            NotificationType::ORDER_OUT_FOR_DELIVERY,
            '🚚 En route chez vous',
            "{$livreur->name}{$reach} part du dépôt avec votre commande {$order->order_number}.",
            ['order_id' => $order->id, 'livreur_id' => $livreur->id]
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
    /**
     * @param bool $forceInAppOnly N'ecrit que la trace consultable, sans mail
     *                             ni SMS. Sert quand un meme evenement se
     *                             repete dans une commande et qu'un seul
     *                             message suffit a faire ouvrir l'application.
     */
    public function notify(User $user, NotificationType $type, $title, $message, $data = [], bool $forceInAppOnly = false)
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
        $channels = $forceInAppOnly ? [] : config('notifications.channels.'.$type->value, []);
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
