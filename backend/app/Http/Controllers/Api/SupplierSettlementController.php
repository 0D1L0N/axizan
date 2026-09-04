<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierPrice;
use App\Services\SupplierSettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Le point avec les fournisseurs partenaires — cote administration.
 *
 * Ces ecrans repondent a une question que personne ne pouvait poser jusqu'ici :
 * combien Axizan doit-il a chaque etal, et sur quoi exactement. La donnee
 * s'accumulait depuis la premiere commande sans qu'aucune interface ne sache
 * la lire.
 */
class SupplierSettlementController extends Controller
{
    public function __construct(private SupplierSettlementService $settlements)
    {}

    /** Ce qui reste a regler, fournisseur par fournisseur. */
    public function index(Request $request)
    {
        $rows = $this->settlements->outstanding($request->integer('market_id') ?: null);

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'total_due' => array_sum(array_column($rows, 'amount')),
                'supplier_count' => count($rows),
                // Nombre de partenaires qu'on ne peut pas encore regler
                // completement : un tarif leur manque.
                'blocked_count' => count(array_filter($rows, fn ($row) => $row['untariffed_count'] > 0)),
            ],
        ]);
    }

    /** Le releve d'un fournisseur, pret a etre lu avec lui. */
    public function statement(Request $request, $supplierId)
    {
        $supplier = Supplier::with('market:id,name,city')->find($supplierId);

        if (! $supplier) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->settlements->statement(
                $supplier,
                $request->query('from'),
                $request->query('to')
            ),
        ]);
    }

    /** Acte un reglement et rattache les lignes couvertes. */
    public function settle(Request $request, $supplierId)
    {
        $supplier = Supplier::find($supplierId);

        if (! $supplier) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'method' => 'nullable|in:especes,mtn,moov,celtiis,virement',
            'reference' => 'nullable|string|max:120',
            'note' => 'nullable|string|max:500',
        ]);

        $result = $this->settlements->settle($supplier, $request->user(), $data);

        return response()->json([
            'success' => $result['ok'],
            'error' => $result['error'],
            'message' => $result['message'],
            'data' => $result['settlement'],
        ], $result['ok'] ? 201 : 422);
    }

    /**
     * Tarifs convenus avec ce fournisseur.
     *
     * La reponse porte aussi les articles DEJA RETIRES chez lui et encore sans
     * tarif : ce sont eux qui bloquent le reglement, et les chercher dans un
     * catalogue de cinquante produits serait absurde.
     */
    public function prices($supplierId)
    {
        $supplier = Supplier::find($supplierId);

        if (! $supplier) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $prices = SupplierPrice::where('supplier_id', $supplier->id)
            ->with('product:id,name,price_type')
            ->get()
            ->map(fn (SupplierPrice $price) => [
                'product_id' => $price->product_id,
                'name' => $price->product?->name,
                'price_type' => $price->product?->price_type,
                'unit_price' => (int) round((float) $price->unit_price),
            ]);

        $missing = OrderItem::where('supplier_id', $supplier->id)
            ->whereNull('settlement_id')
            ->whereNull('actual_total_price')
            ->whereIn('collection_status', ['collected', 'replaced'])
            ->with('product:id,name,price_type', 'replacementProduct:id,name,price_type')
            ->get()
            ->map(fn (OrderItem $item) => $item->effectiveProduct())
            ->filter()
            ->unique('id')
            ->map(fn (Product $product) => [
                'product_id' => $product->id,
                'name' => $product->name,
                'price_type' => $product->price_type,
                'unit_price' => null,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => ['agreed' => $prices->values(), 'missing' => $missing],
        ]);
    }

    /**
     * Enregistre ou met a jour les tarifs convenus.
     *
     * Les lignes deja retirees et non reglees sont RETARIFEES au passage :
     * sans cela, renseigner un tarif oublie ne debloquerait rien, et
     * l'administrateur devrait comprendre seul qu'il faut aussi recalculer.
     * Les lignes deja reglees ne bougent evidemment pas.
     */
    public function updatePrices(Request $request, $supplierId)
    {
        $supplier = Supplier::find($supplierId);

        if (! $supplier) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $data = $request->validate([
            'prices' => 'required|array|min:1',
            'prices.*.product_id' => 'required|exists:products,id',
            'prices.*.unit_price' => 'required|numeric|min:0',
        ]);

        $repriced = DB::transaction(function () use ($supplier, $data) {
            $count = 0;

            foreach ($data['prices'] as $row) {
                SupplierPrice::updateOrCreate(
                    ['supplier_id' => $supplier->id, 'product_id' => $row['product_id']],
                    ['unit_price' => $row['unit_price']]
                );

                $count += $this->repriceUnsettled($supplier->id, (int) $row['product_id'], (float) $row['unit_price']);
            }

            return $count;
        });

        return response()->json([
            'success' => true,
            'message' => 'Tarifs enregistrés.',
            'meta' => ['repriced_items' => $repriced],
        ]);
    }

    /**
     * Applique un tarif aux lignes non reglees de cet article.
     *
     * On vise le produit REELLEMENT pris : sur un remplacement, c'est
     * l'article de substitution qui est sorti de l'etal, pas celui commande.
     */
    private function repriceUnsettled(int $supplierId, int $productId, float $unitPrice): int
    {
        $items = OrderItem::where('supplier_id', $supplierId)
            ->whereNull('settlement_id')
            ->whereIn('collection_status', ['collected', 'replaced'])
            ->where(function ($query) use ($productId) {
                $query->where('replacement_product_id', $productId)
                    ->orWhere(fn ($sub) => $sub->whereNull('replacement_product_id')->where('product_id', $productId));
            })
            ->get();

        foreach ($items as $item) {
            $item->update([
                'actual_unit_price' => $unitPrice,
                'actual_total_price' => \App\Support\Money::lineTotal($unitPrice, (float) $item->quantity),
            ]);
        }

        return $items->count();
    }
}
