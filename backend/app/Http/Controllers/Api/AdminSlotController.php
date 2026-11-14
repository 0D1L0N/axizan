<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\DeliveryClosure;
use App\Models\DeliverySlot;
use App\Models\DeliveryZone;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Les creneaux : la capacite, les zones, et les jours ou l'on ne livre pas.
 *
 * Depuis le verrou pose sur la derniere place, la capacite d'un creneau est
 * defendue — mais elle n'etait reglable que dans la base. Un marche qui deborde
 * un samedi, une zone qu'on suspend le temps de trouver un preparateur, un jour
 * ferie : tout cela demandait une requete SQL a la main.
 *
 * REGLE QUI TIENT TOUT L'ECRAN : rien ici ne touche aux commandes deja posees.
 * Fermer une journee, suspendre une zone ou baisser une capacite arrete les
 * NOUVELLES reservations. Annuler douze commandes payees est une decision
 * d'argent — elle se prend une par une dans la salle de controle, avec un
 * remboursement a la cle. L'ecran dit combien sont concernees ; il ne decide
 * pas a la place de l'administrateur.
 */
class AdminSlotController extends Controller
{
    /** La semaine ouverte a la reservation, zone par zone. */
    public function index(Request $request)
    {
        $zones = DeliveryZone::with(['slots' => fn ($slots) => $slots->orderBy('time_start')])
            ->orderBy('name')
            ->get();

        $horizon = collect(range(0, DeliverySlot::BOOKING_HORIZON_DAYS))
            ->map(fn ($offset) => Carbon::today()->addDays($offset));

        // Un seul comptage par date plutot qu'un par creneau et par jour.
        $bookedByDate = $horizon->mapWithKeys(fn (Carbon $day) => [
            $day->toDateString() => DeliverySlot::bookedCountsOn($day),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'zones' => $zones->map(fn (DeliveryZone $zone) => [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'is_active' => $zone->is_active,
                    'delivery_fee' => (float) $zone->base_delivery_fee,
                    'booked_ahead' => $this->bookedAhead($zone->id),
                    'slots' => $zone->slots->map(fn (DeliverySlot $slot) => [
                        'id' => $slot->id,
                        'display_label' => $slot->display_label,
                        'time_start' => $slot->time_start,
                        'time_end' => $slot->time_end,
                        'max_orders_per_slot' => $slot->max_orders_per_slot,
                        'is_active' => $slot->is_active,
                        // La charge du creneau sur tout l'horizon : c'est ce
                        // qui dit si la capacite est bien reglee.
                        'load' => $horizon->map(fn (Carbon $day) => [
                            'date' => $day->toDateString(),
                            'booked' => $bookedByDate[$day->toDateString()][$slot->id] ?? 0,
                        ])->all(),
                    ])->all(),
                ])->all(),
                'closures' => $this->closures(),
            ],
            'meta' => [
                'horizon_days' => DeliverySlot::BOOKING_HORIZON_DAYS,
                'market_hours' => DeliverySlot::MARKET_OPENS_AT.'–'.DeliverySlot::MARKET_CLOSES_AT,
                'lead_time_minutes' => DeliverySlot::LEAD_TIME_MINUTES,
            ],
        ]);
    }

    /**
     * Regle la capacite d'un creneau, ou le retire.
     *
     * La capacite ne peut pas descendre sous ce qui est DEJA reserve sur
     * l'horizon. Le creneau afficherait un nombre de places negatif, et surtout
     * le chiffre mentirait : les commandes sont posees, quelqu'un devra les
     * preparer. Baisser la capacite sert a ne plus en prendre — pas a effacer
     * celles qu'on a prises.
     */
    public function update(Request $request, $id)
    {
        $slot = DeliverySlot::find($id);

        if (! $slot) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $data = $request->validate([
            'max_orders_per_slot' => 'nullable|integer|min:0|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        if (array_key_exists('max_orders_per_slot', $data) && $data['max_orders_per_slot'] !== null) {
            $peak = $this->peakBooked($slot);

            if ($data['max_orders_per_slot'] < $peak) {
                return response()->json([
                    'success' => false,
                    'error' => 'below_booked',
                    'message' => "Ce créneau porte déjà {$peak} commande".($peak > 1 ? 's' : '')
                        .' sur un même jour. La capacité ne peut pas descendre en dessous.',
                ], 422);
            }

            $slot->max_orders_per_slot = $data['max_orders_per_slot'];
        }

        if (array_key_exists('is_active', $data) && $data['is_active'] !== null) {
            $slot->is_active = $data['is_active'];
        }

        $slot->save();

        return response()->json([
            'success' => true,
            'message' => $slot->is_active
                ? "Créneau {$slot->display_label} : {$slot->max_orders_per_slot} places."
                : "Créneau {$slot->display_label} retiré. Les commandes déjà posées ne bougent pas.",
            'data' => $slot->only(['id', 'display_label', 'max_orders_per_slot', 'is_active']),
        ]);
    }

    /**
     * Suspend une zone, ou la retablit.
     *
     * Une zone suspendue n'est plus proposee au client, et ses creneaux ne se
     * reservent plus. Ce qui y est deja pose continue : le preparateur ira, le
     * livreur livrera. Suspendre une zone n'est pas annuler sa journee.
     */
    public function setZoneActive(Request $request, $id)
    {
        $zone = DeliveryZone::find($id);

        if (! $zone) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $data = $request->validate(['is_active' => 'required|boolean']);

        $zone->update(['is_active' => $data['is_active']]);

        $pending = $data['is_active'] ? 0 : $this->bookedAhead($zone->id);

        return response()->json([
            'success' => true,
            'message' => $data['is_active']
                ? "{$zone->name} est de nouveau desservie."
                : "{$zone->name} ne prend plus de nouvelles commandes."
                    .($pending > 0
                        ? " {$pending} commande".($pending > 1 ? 's' : '')
                            .' déjà posée'.($pending > 1 ? 's' : '').' y sera livrée normalement.'
                        : ''),
            'data' => $zone->only(['id', 'name', 'is_active']),
            'still_booked' => $pending,
        ]);
    }

    /**
     * Ferme une journee.
     *
     * La reponse porte les commandes DEJA posees ce jour-la. Elles ne sont pas
     * touchees — mais les taire serait pire que tout : l'administrateur croirait
     * la journee reglee, et douze clients attendraient une livraison que
     * personne ne fera.
     */
    public function close(Request $request)
    {
        $data = $request->validate([
            'closed_on' => 'required|date|after_or_equal:today',
            'zone_id' => 'nullable|exists:delivery_zones,id',
            'reason' => 'required|string|max:300',
        ], [
            'reason.required' => 'Indiquez pourquoi : une journée fermée sans motif se rouvre par précaution.',
            'closed_on.after_or_equal' => 'On ne ferme pas un jour déjà passé.',
        ]);

        $date = Carbon::parse($data['closed_on'])->toDateString();

        $closure = DeliveryClosure::updateOrCreate(
            ['closed_on' => $date, 'zone_id' => $data['zone_id'] ?? null],
            ['reason' => $data['reason'], 'created_by' => $request->user()->id]
        );

        $affected = $this->ordersOn($date, $data['zone_id'] ?? null);

        return response()->json([
            'success' => true,
            'message' => $affected->isEmpty()
                ? 'Journée fermée. Aucune commande n’était posée.'
                : "Journée fermée. {$affected->count()} commande".($affected->count() > 1 ? 's' : '')
                    .' déjà posée'.($affected->count() > 1 ? 's' : '')
                    .' : traitez-la'.($affected->count() > 1 ? 's' : '')
                    .' dans la salle de contrôle, le client sera remboursé.',
            'data' => $closure->fresh()->load('zone:id,name'),
            'affected_orders' => $affected,
        ], 201);
    }

    /** Rouvre une journee fermee par erreur, ou dont la raison a disparu. */
    public function reopen($id)
    {
        $closure = DeliveryClosure::find($id);

        if (! $closure) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $label = $closure->closed_on->locale('fr')->isoFormat('D MMMM');
        $closure->delete();

        return response()->json([
            'success' => true,
            'message' => "Le {$label} est de nouveau ouvert à la réservation.",
        ]);
    }

    // -----------------------------------------------------------------

    /** Les fermetures a venir, la plus proche en tete. */
    private function closures(): array
    {
        return DeliveryClosure::with('zone:id,name', 'author:id,name')
            ->whereDate('closed_on', '>=', Carbon::today())
            ->orderBy('closed_on')
            ->get()
            ->map(fn (DeliveryClosure $closure) => [
                'id' => $closure->id,
                'closed_on' => $closure->closed_on->toDateString(),
                'zone' => $closure->zone?->name,
                'zone_id' => $closure->zone_id,
                'reason' => $closure->reason,
                'by' => $closure->author?->name,
                'orders' => $this->ordersOn($closure->closed_on->toDateString(), $closure->zone_id)->count(),
            ])->all();
    }

    /** Commandes vivantes posees un jour donne, eventuellement sur une zone. */
    private function ordersOn(string $date, $zoneId)
    {
        return Order::query()
            ->whereDate('delivery_date', $date)
            ->whereNotIn('status', [OrderStatus::CANCELLED, OrderStatus::COMPLETED])
            ->when($zoneId, fn ($query) => $query->whereHas(
                'deliveryAddress',
                fn ($address) => $address->where('delivery_zone_id', $zoneId)
            ))
            ->orderBy('delivery_slot_time')
            ->get(['id', 'order_number', 'status', 'delivery_slot_time'])
            ->map(fn (Order $order) => [
                'order_number' => $order->order_number,
                'status_label' => $order->status->getLabel(),
            ]);
    }

    /** Commandes vivantes a venir sur une zone. */
    private function bookedAhead($zoneId): int
    {
        return Order::query()
            ->whereDate('delivery_date', '>=', Carbon::today())
            ->whereNotIn('status', [OrderStatus::CANCELLED, OrderStatus::COMPLETED])
            ->whereHas('deliveryAddress', fn ($address) => $address->where('delivery_zone_id', $zoneId))
            ->count();
    }

    /**
     * Le jour le plus charge de ce creneau sur l'horizon.
     *
     * C'est le plancher de la capacite : la moyenne ou le total n'auraient
     * aucun sens — ce qui compte est le jour ou il y en a le plus.
     */
    private function peakBooked(DeliverySlot $slot): int
    {
        return (int) Order::query()
            ->where('delivery_slot_id', $slot->id)
            ->whereDate('delivery_date', '>=', Carbon::today())
            ->where('status', '!=', OrderStatus::CANCELLED)
            ->selectRaw('COUNT(*) as total')
            ->groupBy('delivery_date')
            ->orderByDesc('total')
            ->value('total');
    }
}
