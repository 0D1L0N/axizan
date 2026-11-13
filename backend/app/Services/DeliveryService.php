<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeliveryService
{
    public function __construct(private OrderService $orderService)
    {}

    /**
     * Assigner une commande à un livreur
     */
    public function assignOrderToLivreur(Order $order, User $livreur): Delivery
    {
        // Deux livreurs peuvent toucher « Accepter » au meme instant : la
        // commande est verrouillee et relue, le second recoit un refus clair
        // au lieu d'une erreur SQL sur la contrainte d'unicite.
        $delivery = DB::transaction(function () use ($order, $livreur) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if (!$locked || $locked->status !== \App\Enums\OrderStatus::READY) {
                throw new \Exception("Seules les commandes prêtes peuvent être livrées");
            }

            if (Delivery::where('order_id', $locked->id)->exists()) {
                throw new \Exception("Cette commande a déjà été prise par un autre livreur");
            }

            $locked->update(['livreur_id' => $livreur->id]);

            return Delivery::create([
                'order_id' => $locked->id,
                'livreur_id' => $livreur->id,
                'status' => \App\Enums\DeliveryStatus::ASSIGNED,
            ]);
        });

        Log::info("Delivery assigned for order {$order->order_number} to {$livreur->name}");

        return $delivery;
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
    public function startDelivery(Delivery $delivery): Delivery
    {
        if (!in_array($delivery->status, [\App\Enums\DeliveryStatus::ASSIGNED, \App\Enums\DeliveryStatus::PICKED_UP], true)) {
            throw new \Exception("Cette livraison ne peut pas être démarrée");
        }

        $delivery->update([
            'status' => \App\Enums\DeliveryStatus::IN_TRANSIT,
        ]);

        // Mettre à jour le statut de la commande
        $delivery->order->update([
            'status' => \App\Enums\OrderStatus::IN_DELIVERY,
            'delivery_started_at' => now(),
        ]);

        return $delivery;
    }

    /**
     * Confirmer livraison avec photo preuve
     */
    public function confirmDelivery(Delivery $delivery, $photoFile = null, $signatureData = null, $notes = null): Delivery
    {
        // Sans ce garde-fou, une livraison deja confirmee pouvait l'etre une
        // seconde fois (et la commande « completee » deux fois).
        if ($delivery->status !== \App\Enums\DeliveryStatus::IN_TRANSIT) {
            throw new \Exception("Démarrez la livraison avant de la confirmer");
        }

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
