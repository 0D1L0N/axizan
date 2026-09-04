<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\RecurringBasket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecurringBasketController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->basketsFor($request),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'frequency' => 'required|in:'.implode(',', array_keys(RecurringBasket::FREQUENCIES)),
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001|max:999',
        ]);

        $basket = DB::transaction(function () use ($data, $request) {
            $basket = RecurringBasket::create([
                'user_id' => $request->user()->id,
                'name' => $data['name'],
                'frequency' => $data['frequency'],
                // Le premier rappel tombe a la premiere echeance, pas
                // immediatement : le client vient de composer son panier.
                'next_reminder_at' => now()->addDays(RecurringBasket::FREQUENCIES[$data['frequency']]),
            ]);

            foreach ($data['items'] as $item) {
                $basket->items()->updateOrCreate(
                    ['product_id' => $item['product_id']],
                    ['quantity' => $item['quantity']]
                );
            }

            return $basket;
        });

        return response()->json([
            'success' => true,
            'data' => $basket->load('items.product:id,name,base_price,price_type,image_url,thumbnail_url'),
        ], 201);
    }

    public function update(Request $request, RecurringBasket $recurringBasket)
    {
        abort_unless($recurringBasket->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'name' => 'sometimes|string|max:80',
            'frequency' => 'sometimes|in:'.implode(',', array_keys(RecurringBasket::FREQUENCIES)),
            'is_active' => 'sometimes|boolean',
        ]);

        // Changer la cadence reprogramme l'echeance : sinon le panier resterait
        // sur l'ancien rythme jusqu'au prochain rappel.
        if (isset($data['frequency']) && $data['frequency'] !== $recurringBasket->frequency) {
            $data['next_reminder_at'] = now()->addDays(RecurringBasket::FREQUENCIES[$data['frequency']]);
        }

        $recurringBasket->update($data);

        return response()->json(['success' => true, 'data' => $recurringBasket->fresh()->load('items.product')]);
    }

    public function destroy(Request $request, RecurringBasket $recurringBasket)
    {
        abort_unless($recurringBasket->user_id === $request->user()->id, 404);
        $recurringBasket->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Recharge le panier courant a partir du panier recurrent.
     *
     * Les articles disparus du catalogue sont signales plutot qu'ignores : le
     * client doit savoir ce qui manque avant de commander.
     */
    public function load(Request $request, RecurringBasket $recurringBasket)
    {
        abort_unless($recurringBasket->user_id === $request->user()->id, 404);

        $skipped = [];

        DB::transaction(function () use ($recurringBasket, $request, &$skipped) {
            foreach ($recurringBasket->items()->with('product')->get() as $item) {
                if (! $item->product || ! $item->product->is_available) {
                    $skipped[] = $item->product?->name ?? 'Article retiré du catalogue';
                    continue;
                }

                CartItem::updateOrCreate(
                    ['user_id' => $request->user()->id, 'product_id' => $item->product_id],
                    ['quantity' => $item->quantity]
                );
            }
        });

        return response()->json([
            'success' => true,
            'data' => $this->cartFor($request),
            'meta' => ['skipped' => $skipped],
        ]);
    }

    private function basketsFor(Request $request)
    {
        return RecurringBasket::with('items.product:id,name,base_price,price_type,image_url,thumbnail_url,is_available')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();
    }

    private function cartFor(Request $request)
    {
        return CartItem::with('product:id,name,base_price,price_type,image_url,thumbnail_url,is_available,category_id')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();
    }
}
