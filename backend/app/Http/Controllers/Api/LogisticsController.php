<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliverySlot;
use App\Models\DeliveryZone;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LogisticsController extends Controller
{
    /**
     * Lister les zones de livraison actives
     */
    public function zones()
    {
        return response()->json([
            'success' => true,
            'data' => DeliveryZone::active()->orderBy('name')->get(),
        ]);
    }

    /**
     * Creneaux de livraison pour une date donnee.
     *
     * Chaque creneau est renvoye avec son etat plutot que filtre en silence :
     * le client doit voir que le creneau de 9h existe mais qu'il est passe,
     * sinon il ne comprend pas pourquoi sa journee commence a 14h.
     */
    public function slots(Request $request)
    {
        $request->validate([
            'zone_id' => 'nullable|exists:delivery_zones,id',
            'date' => 'nullable|date',
        ]);

        $date = $request->filled('date')
            ? Carbon::parse($request->date)->startOfDay()
            : Carbon::today();

        if (! DeliverySlot::isDateWithinHorizon($date)) {
            return response()->json([
                'success' => false,
                'error' => 'date_out_of_range',
                'message' => sprintf(
                    'Choisissez une date entre aujourd’hui et %d jours.',
                    DeliverySlot::BOOKING_HORIZON_DAYS
                ),
            ], 422);
        }

        $query = DeliverySlot::active()->with('zone');

        if ($request->filled('zone_id')) {
            $query->where('zone_id', $request->zone_id);
        }

        $booked = DeliverySlot::bookedCountsOn($date);

        $slots = $query
            ->orderBy('time_start')
            ->get()
            ->map(function (DeliverySlot $slot) use ($date, $booked) {
                $taken = $booked[$slot->id] ?? 0;
                $spots = max(0, $slot->max_orders_per_slot - $taken);
                $inTime = $slot->isBookableOn($date);

                return [
                    'id' => $slot->id,
                    'zone_id' => $slot->zone_id,
                    'time_start' => $slot->time_start,
                    'time_end' => $slot->time_end,
                    'display_label' => $slot->display_label,
                    'additional_fee' => $slot->additional_fee,
                    'available_spots' => $spots,
                    'is_bookable' => $inTime && $spots > 0,
                    'unavailable_reason' => match (true) {
                        ! $inTime => 'too_soon',
                        $spots <= 0 => 'full',
                        default => null,
                    },
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $slots,
            'meta' => [
                'date' => $date->toDateString(),
                'lead_time_minutes' => DeliverySlot::LEAD_TIME_MINUTES,
                'market_hours' => DeliverySlot::MARKET_OPENS_AT.'–'.DeliverySlot::MARKET_CLOSES_AT,
                'horizon_days' => DeliverySlot::BOOKING_HORIZON_DAYS,
                'has_bookable' => $slots->contains('is_bookable', true),
            ],
        ]);
    }
}
