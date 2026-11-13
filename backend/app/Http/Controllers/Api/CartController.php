<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Panier serveur du client connecte.
 *
 * Le panier local du navigateur reste la reference pour les visiteurs
 * anonymes ; a la connexion, il est fusionne ici (voir merge()).
 */
class CartController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->itemsFor($request),
        ]);
    }

    /**
     * Ajoute ou met a jour une ligne.
     *
     * updateOrCreate sur (user, product) : ajouter deux fois le meme article
     * fixe la quantite, ca ne cree pas de doublon.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0.001|max:999',
        ]);

        CartItem::updateOrCreate(
            ['user_id' => $request->user()->id, 'product_id' => $data['product_id']],
            ['quantity' => $data['quantity']]
        );

        return response()->json(['success' => true, 'data' => $this->itemsFor($request)]);
    }

    public function destroy(Request $request, $productId)
    {
        CartItem::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->delete();

        return response()->json(['success' => true, 'data' => $this->itemsFor($request)]);
    }

    public function clear(Request $request)
    {
        CartItem::where('user_id', $request->user()->id)->delete();

        return response()->json(['success' => true, 'data' => []]);
    }

    /**
     * Fusion du panier local a la connexion.
     *
     * On garde la quantite la plus elevee plutot que de les additionner : si le
     * meme panier a ete synchronise puis rejoue, additionner doublerait la
     * commande. Le maximum est stable si l'operation se repete.
     */
    public function merge(Request $request)
    {
        $data = $request->validate([
            'items' => 'present|array|max:100',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001|max:999',
        ]);

        DB::transaction(function () use ($data, $request) {
            foreach ($data['items'] as $item) {
                $existing = CartItem::where('user_id', $request->user()->id)
                    ->where('product_id', $item['product_id'])
                    ->first();

                $quantity = $existing
                    ? max((float) $existing->quantity, (float) $item['quantity'])
                    : (float) $item['quantity'];

                CartItem::updateOrCreate(
                    ['user_id' => $request->user()->id, 'product_id' => $item['product_id']],
                    ['quantity' => $quantity]
                );
            }
        });

        return response()->json(['success' => true, 'data' => $this->itemsFor($request)]);
    }

    /**
     * Le panier renvoie le produit a jour : prix et disponibilite peuvent
     * avoir change depuis l'ajout, et c'est le prix du jour qui compte.
     */
    private function itemsFor(Request $request)
    {
        return CartItem::with('product:id,name,base_price,price_type,image_url,thumbnail_url,is_available,category_id')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->filter(fn (CartItem $item) => $item->product !== null)
            ->values();
    }
}
