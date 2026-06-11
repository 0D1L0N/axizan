<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeliveryService
{
    /**
     * Assigner une commande à un livreur
     */
    public function assignOrderToLivreur(Order $order, User $livreur): Delivery
    {
        if ($order->status !== \App\Enums\OrderStatus::READY) {
            throw new \Exception("Seules les commandes prêtes peuvent être livrées");
        }

        $delivery = Delivery::create([
            'order_id' => $order->id,
            'livreur_id' => $livreur->id,
            'status' => \App\Enums\DeliveryStatus::ASSIGNED,
        ]);

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
        $photoUrl = null;

        if ($photoFile) {
            // Stocker la photo de preuve
            $path = $photoFile->store('deliveries/' . $delivery->id, 's3');
            $photoUrl = Storage::disk('s3')->url($path);
        }

        $delivery->update([
            'status' => \App\Enums\DeliveryStatus::DELIVERED,
            'delivery_time' => now(),
            'proof_photo_url' => $photoUrl,
            'signature_data' => $signatureData,
            'delivery_notes' => $notes,
        ]);

        // Mettre à jour la commande
        $delivery->order->update([
            'status' => \App\Enums\OrderStatus::DELIVERED,
            'delivered_at' => now(),
        ]);

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
