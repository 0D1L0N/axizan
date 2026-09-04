<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Supplier;
use App\Models\SupplierSettlement;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Le point avec les fournisseurs partenaires.
 *
 * Axizan ne paie rien au marche : le preparateur se sert, et le reglement se
 * fait ensuite. Ce service repond a la seule question qui compte le jour du
 * point : « qu'est-ce qui a ete retire chez vous, et combien cela fait ? »
 *
 * Deux principes tiennent tout le reste.
 *
 * 1. Une ligne deja rattachee a un reglement ne reapparait jamais. Le
 *    double paiement est rendu impossible par le schema, pas par la vigilance
 *    de celui qui fait les comptes.
 *
 * 2. Un article sans tarif n'est pas compte pour zero, il est SIGNALE. Une
 *    dette sous-evaluee presentee comme complete est pire qu'une dette qu'on
 *    sait incomplete : on la regle, et l'ecart ne se decouvre jamais.
 */
class SupplierSettlementService
{
    /**
     * Ce qui reste a regler, fournisseur par fournisseur.
     *
     * @return array<int, array<string, mixed>>
     */
    public function outstanding(?int $marketId = null): array
    {
        $suppliers = Supplier::query()
            ->when($marketId, fn ($query) => $query->where('market_id', $marketId))
            ->with('market:id,name,city')
            ->orderBy('name')
            ->get();

        return $suppliers
            ->map(function (Supplier $supplier) {
                $items = $this->unsettledItems($supplier->id);

                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'stall' => $supplier->stall,
                    'speciality' => $supplier->speciality,
                    'phone' => $supplier->phone,
                    'market' => $supplier->market?->only(['id', 'name', 'city']),
                    'item_count' => $items->count(),
                    'amount' => $this->total($items),
                    // Nombre de lignes qu'on ne sait pas chiffrer. Non nul,
                    // c'est un tarif manquant a renseigner avant de regler.
                    'untariffed_count' => $items->whereNull('actual_total_price')->count(),
                    'oldest_collected_at' => $items->min('collected_at')?->toIso8601String(),
                    'last_settled_at' => SupplierSettlement::where('supplier_id', $supplier->id)
                        ->latest('settled_at')
                        ->value('settled_at')?->toIso8601String(),
                ];
            })
            // Un partenaire a qui l'on ne doit rien n'a pas besoin d'apparaitre
            // dans une liste de dettes, sauf s'il attend un tarif.
            ->filter(fn (array $row) => $row['item_count'] > 0)
            ->values()
            ->all();
    }

    /**
     * Le releve detaille d'un fournisseur : ce qui a ete retire, regroupe par
     * article, avec le detail des commandes.
     */
    public function statement(Supplier $supplier, ?string $from = null, ?string $to = null): array
    {
        $items = $this->unsettledItems($supplier->id, $from, $to);

        $lines = $items
            // Le produit reellement pris : sur un remplacement, c'est
            // l'article de substitution qui est sorti de l'etal.
            ->groupBy(fn (OrderItem $item) => $item->effectiveProduct()?->id ?? 0)
            ->map(function ($group) {
                /** @var OrderItem $first */
                $first = $group->first();
                $product = $first->effectiveProduct();

                // Un tarif renegocie en cours de periode laisse deux prix
                // differents sur des lignes du meme article. Afficher l'un des
                // deux comme « le » prix unitaire ferait mentir la
                // multiplication : on n'en affiche aucun, le total reste juste.
                $prices = $group->pluck('actual_unit_price')->unique();

                return [
                    'product_id' => $product?->id,
                    'name' => $product?->name ?? 'Article retiré du catalogue',
                    'price_type' => $product?->price_type,
                    'quantity' => round($group->sum(fn (OrderItem $item) => (float) $item->quantity), 3),
                    'unit_price' => $prices->count() === 1 && $prices->first() !== null
                        ? Money::round((float) $prices->first())
                        : null,
                    'total' => $this->total($group),
                    'order_count' => $group->pluck('order_id')->unique()->count(),
                    'tariffed' => $group->whereNull('actual_total_price')->isEmpty(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'stall' => $supplier->stall,
                'phone' => $supplier->phone,
                'market' => $supplier->market?->only(['id', 'name', 'city']),
            ],
            'period' => [
                'from' => $from ?? $items->min('collected_at')?->toDateString(),
                'to' => $to ?? $items->max('collected_at')?->toDateString(),
            ],
            'lines' => $lines,
            'item_count' => $items->count(),
            'amount' => $this->total($items),
            'untariffed_count' => $items->whereNull('actual_total_price')->count(),
            'history' => SupplierSettlement::where('supplier_id', $supplier->id)
                ->with('settledBy:id,name')
                ->latest('settled_at')
                ->limit(10)
                ->get()
                ->map(fn (SupplierSettlement $settlement) => [
                    'id' => $settlement->id,
                    'period_start' => $settlement->period_start->toDateString(),
                    'period_end' => $settlement->period_end->toDateString(),
                    'amount' => Money::round((float) $settlement->amount),
                    'item_count' => $settlement->item_count,
                    'settled_at' => $settlement->settled_at->toIso8601String(),
                    'settled_by' => $settlement->settledBy?->name,
                    'method' => $settlement->method,
                    'reference' => $settlement->reference,
                ])
                ->all(),
        ];
    }

    /**
     * Acte le reglement : cree la trace et rattache les lignes couvertes.
     *
     * Le rattachement se fait dans la meme transaction que la creation : un
     * reglement enregistre dont les lignes seraient restees libres se
     * representerait au point suivant, et serait paye deux fois.
     *
     * @return array{ok: bool, error: ?string, message: string, settlement: ?SupplierSettlement}
     */
    public function settle(Supplier $supplier, \App\Models\User $admin, array $data): array
    {
        $items = $this->unsettledItems($supplier->id, $data['from'] ?? null, $data['to'] ?? null);

        if ($items->isEmpty()) {
            return [
                'ok' => false,
                'error' => 'nothing_to_settle',
                'message' => 'Rien à régler pour ce fournisseur sur cette période.',
                'settlement' => null,
            ];
        }

        // Regler alors qu'une ligne n'est pas tarifee, c'est solder une dette
        // dont on ignore une partie. Le tarif manquant se renseigne d'abord.
        $untariffed = $items->whereNull('actual_total_price');

        if ($untariffed->isNotEmpty()) {
            return [
                'ok' => false,
                'error' => 'untariffed_items',
                'message' => $untariffed->count().' article(s) sans tarif convenu. '
                    .'Renseignez leur prix avant de régler, sinon le montant est incomplet.',
                'settlement' => null,
            ];
        }

        $settlement = DB::transaction(function () use ($supplier, $admin, $items, $data) {
            $settlement = SupplierSettlement::create([
                'supplier_id' => $supplier->id,
                'period_start' => $data['from'] ?? $items->min('collected_at')->toDateString(),
                'period_end' => $data['to'] ?? $items->max('collected_at')->toDateString(),
                'amount' => $this->total($items),
                'item_count' => $items->count(),
                'settled_by' => $admin->id,
                'settled_at' => now(),
                'method' => $data['method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            OrderItem::whereIn('id', $items->pluck('id'))
                ->update(['settlement_id' => $settlement->id]);

            return $settlement;
        });

        return [
            'ok' => true,
            'error' => null,
            'message' => 'Règlement enregistré.',
            'settlement' => $settlement->load('settledBy:id,name'),
        ];
    }

    /**
     * Lignes retirees chez ce fournisseur et pas encore reglees.
     *
     * `settlement_id` null est le seul filtre qui protege du double paiement,
     * et il n'est jamais optionnel.
     */
    private function unsettledItems(int $supplierId, ?string $from = null, ?string $to = null)
    {
        return OrderItem::query()
            ->where('supplier_id', $supplierId)
            ->whereNull('settlement_id')
            ->whereIn('collection_status', ['collected', 'replaced'])
            ->whereNotNull('collected_at')
            ->when($from, fn ($query) => $query->whereDate('collected_at', '>=', Carbon::parse($from)))
            ->when($to, fn ($query) => $query->whereDate('collected_at', '<=', Carbon::parse($to)))
            ->with('product:id,name,price_type', 'replacementProduct:id,name,price_type')
            ->get();
    }

    /** Somme des lignes chiffrables. Les autres valent zero ici, et sont comptees a part. */
    private function total($items): int
    {
        return Money::round($items->sum(fn (OrderItem $item) => (float) $item->actual_total_price));
    }
}
