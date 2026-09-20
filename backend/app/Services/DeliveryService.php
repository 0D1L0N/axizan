<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeliveryService
{
    public function __construct(
        private OrderService $orderService,
        private NotificationService $notificationService
    ) {}

    /**
     * Le livreur prend le panier au depot.
     *
     * `orders.livreur_id` est pose ICI, et c'etait le trou : le seul chemin qui
     * l'ecrivait — OrderService::assignDelivery() — n'etait appele de nulle
     * part. La colonne restait vide pour TOUTES les commandes, et la salle de
     * controle affichait un contact « Livreur » systematiquement absent.
     * L'ecran fait pour joindre la personne en charge ne pouvait pas la nommer,
     * exactement au moment ou on en a besoin : le client appelle, personne
     * n'arrive, et l'administrateur n'a aucun numero a composer.
     *
     * L'unicite de `deliveries.order_id` sert de garde-fou : deux livreurs qui
     * touchent « Accepter » a la meme seconde ne peuvent pas ouvrir deux
     * courses sur le meme panier. Le second est refuse par la base ; le
     * controleur traduit ce refus en phrase.
     */
    public function assignOrderToLivreur(Order $order, User $livreur): Delivery
    {
        if ($order->status !== \App\Enums\OrderStatus::READY) {
            throw new \Exception("Seules les commandes prêtes peuvent être livrées");
        }

        return DB::transaction(function () use ($order, $livreur) {
            $delivery = Delivery::create([
                'order_id' => $order->id,
                'livreur_id' => $livreur->id,
                'status' => \App\Enums\DeliveryStatus::ASSIGNED,
            ]);

            $order->update(['livreur_id' => $livreur->id]);

            // Le statut de la commande ne change pas ici : le journal ne
            // l'enregistrerait donc pas de lui-meme. Or « qui est parti avec ce
            // panier, et quand » est precisement ce qu'on vient y chercher
            // apres coup.
            OrderEvent::record($order, 'delivery.assigned', context: [
                'livreur_id' => $livreur->id,
            ]);

            Log::info("Delivery assigned for order {$order->order_number} to {$livreur->name}");

            return $delivery;
        });
    }

    /**
     * Livreur récupère la commande
     */
    public function pickupDelivery(Delivery $delivery): Delivery
    {
        $delivery->update([
            'status' => \App\Enums\DeliveryStatus::PICKED_UP,
            'pickup_time' => now(),
        ]);

        Log::info("Delivery {$delivery->id} picked up");

        return $delivery;
    }

    /**
     * Mettre à jour position GPS en temps réel
     */
    public function updateLocation(Delivery $delivery, $latitude, $longitude): Delivery
    {
        $delivery->updateLocation($latitude, $longitude);

        // TODO: Broadcaster la position via WebSocket aux clients intéressés
        // event(new DeliveryLocationUpdated($delivery));

        return $delivery;
    }

    /**
     * Marquer comme en transit
     */
    /**
     * Le livreur a recupere le panier au depot et part chez le client.
     *
     * Un seul geste pour deux faits : il prend le panier et s'en va dans le
     * meme mouvement. Deux boutons pour un seul moment n'apporteraient rien
     * sur le terrain, et pickup_time reste renseigne pour le depot.
     */
    public function startDelivery(Delivery $delivery): Delivery
    {
        $delivery->update([
            'status' => \App\Enums\DeliveryStatus::IN_TRANSIT,
            'pickup_time' => $delivery->pickup_time ?? now(),
        ]);

        // Mettre à jour le statut de la commande
        $delivery->order->update([
            'status' => \App\Enums\OrderStatus::IN_DELIVERY,
            'delivery_started_at' => now(),
        ]);

        /*
         * Le SMS le plus utile de tout le parcours, et il ne partait pas.
         *
         * config/notifications.php declare ORDER_OUT_FOR_DELIVERY en SMS depuis
         * le debut, avec cette justification : « le client doit etre la ».
         * Aucun chemin ne l'emettait — son unique appelant etait le code mort
         * OrderService::assignDelivery(). Le client decouvrait le livreur a sa
         * porte, ou ne l'entendait pas sonner.
         *
         * C'est ici que ca se joue, pas a l'acceptation : au moment ou il prend
         * le panier, le livreur est encore au depot. Le client n'a rien a faire
         * de cette information-la. Il en a besoin quand quelqu'un roule vers
         * chez lui.
         */
        $this->notificationService->sendDeliveryStartedNotification(
            $delivery->order,
            $delivery->livreur
        );

        return $delivery;
    }

    /**
     * Confirmer livraison avec photo preuve
     */
    public function confirmDelivery(Delivery $delivery, $photoFile = null, $signatureData = null, $notes = null): Delivery
    {
        $photoUrl = null;

        if ($photoFile) {
            // Disque 'public' explicite : le disque par defaut est 'local',
            // c'est-a-dire storage/app, qui n'est servi par aucune URL. La
            // photo y etait ecrite puis referencee par un lien mort — produite,
            // stockee, et injoignable.
            //
            // Necessite `php artisan storage:link` (voir README).
            $path = $photoFile->store('deliveries/' . $delivery->id, 'public');
            $photoUrl = Storage::disk('public')->url($path);
        }

        $delivery->update([
            'status' => \App\Enums\DeliveryStatus::DELIVERED,
            'delivery_time' => now(),
            'proof_photo_url' => $photoUrl,
            'signature_data' => $signatureData,
            'delivery_notes' => $notes,
        ]);

        // Mettre à jour la commande puis la compléter (paiement déjà validé en amont)
        $delivery->order->update([
            'status' => \App\Enums\OrderStatus::DELIVERED,
            'delivered_at' => now(),
        ]);
        $this->orderService->completeOrder($delivery->order);

        Log::info("Delivery {$delivery->id} confirmed");

        return $delivery;
    }

    /**
     * Échec livraison
     */
    public function failDelivery(Delivery $delivery, $reason = null): Delivery
    {
        $delivery->update([
            'status' => \App\Enums\DeliveryStatus::FAILED,
            'delivery_notes' => $reason,
        ]);

        Log::warning("Delivery {$delivery->id} failed: {$reason}");

        return $delivery;
    }

    /**
     * Enregistrer note client + rating
     */
    public function recordClientFeedback(Delivery $delivery, $rating, $feedback = null): Delivery
    {
        $delivery->update([
            'client_rating' => $rating,
            'client_feedback' => $feedback,
        ]);

        // Calculer nouvelle moyenne de note du livreur
        $avgRating = $delivery->livreur
            ->deliveries()
            ->whereNotNull('client_rating')
            ->avg('client_rating');

        return $delivery;
    }

    /**
     * Calculer distance entre deux points (lat/lon)
     */
    public static function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
