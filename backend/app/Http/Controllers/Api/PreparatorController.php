<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\OrderService;
use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Espace preparateur.
 *
 * Modele metier : Axizan a des contrats avec des fournisseurs. Le preparateur
 * se presente, se sert, et ne paie rien — le reglement se fait ensuite entre
 * Axizan et chaque fournisseur. Il ne saisit donc aucun prix.
 *
 * Ce qu'il enregistre, ligne par ligne : ce qu'il a recupere, et CHEZ QUI.
 */
class PreparatorController extends Controller
{
    public function __construct(private OrderService $orderService)
    {}

    /**
     * Commandes payees disponibles, limitees aux zones desservies par le
     * marche du preparateur.
     *
     * Sans ce filtre, un preparateur d'Allada verrait des commandes de
     * Cotonou qu'il ne peut pas honorer.
     */
    public function index(Request $request)
    {
        if ($denied = $this->denyUnlessPreparateur($request)) {
            return $denied;
        }

        $user = $request->user();

        if (! $user->market_id) {
            return response()->json([
                'success' => false,
                'error' => 'no_market',
                'message' => 'Aucun marché ne vous est rattaché. Contactez un administrateur.',
            ], 409);
        }

        $zoneIds = $user->market?->zones()->pluck('delivery_zones.id') ?? collect();

        $orders = Order::where('status', OrderStatus::PENDING)
            ->where('payment_status', 'completed')
            ->whereNull('preparateur_id')
            ->whereHas('deliveryAddress', fn ($query) => $query->whereIn('delivery_zone_id', $zoneIds))
            ->with('client:id,name,phone', 'items.product', 'deliveryAddress', 'deliverySlot')
            // Le creneau le plus proche d'abord : c'est l'ordre d'urgence reel,
            // pas la date de commande.
            ->orderBy('delivery_date')
            ->paginate($request->get('limit', 20));

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'meta' => [
                'market' => $user->market?->only(['id', 'name', 'city']),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Commandes en cours du preparateur.
     */
    public function mine(Request $request)
    {
        if ($denied = $this->denyUnlessPreparateur($request)) {
            return $denied;
        }

        $user = $request->user();

        $orders = $user->preparerOrders()
            ->whereIn('status', [OrderStatus::ACCEPTED, OrderStatus::PREPARING])
            ->with(
                'client:id,name,phone',
                'items.product.category:id,name',
                'items.supplier:id,name,stall',
                'items.replacementProduct:id,name,price_type',
                'deliveryAddress',
                'deliverySlot'
            )
            ->orderBy('delivery_date')
            ->get();

        $this->suggestSuppliers($orders, $user->market_id);

        return response()->json([
            'success' => true,
            'data' => $orders,
            'meta' => [
                // Le preparateur doit savoir OU deposer avant de partir.
                'depot' => $user->market?->depot?->only(['id', 'name', 'address', 'city', 'landmark', 'phone']),
            ],
        ]);
    }

    /**
     * Fournisseurs du marche de rattachement.
     */
    public function suppliers(Request $request)
    {
        if ($denied = $this->denyUnlessPreparateur($request)) {
            return $denied;
        }

        $suppliers = Supplier::active()
            ->where('market_id', $request->user()->market_id)
            ->orderBy('speciality')
            ->orderBy('name')
            ->get(['id', 'name', 'stall', 'speciality']);

        return response()->json(['success' => true, 'data' => $suppliers]);
    }

    /**
     * Enregistre ce qui a ete fait d'une ligne : recuperee, remplacee, retiree.
     */
    public function updateItem(Request $request, $orderId, $itemId)
    {
        $order = $this->findOwnedOrder($orderId, $request);

        if ($order instanceof \Illuminate\Http\JsonResponse) {
            return $order;
        }

        $item = OrderItem::where('order_id', $order->id)->find($itemId);

        if (! $item) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $data = $request->validate([
            'collection_status' => 'required|in:pending,collected,replaced,removed',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'replacement_product_id' => 'nullable|exists:products,id',
        ]);

        // Un fournisseur d'un autre marche serait une erreur de saisie : le
        // preparateur n'y a pas acces.
        if (! empty($data['supplier_id'])) {
            $belongs = Supplier::where('id', $data['supplier_id'])
                ->where('market_id', $request->user()->market_id)
                ->exists();

            if (! $belongs) {
                return response()->json([
                    'success' => false,
                    'error' => 'supplier_outside_market',
                    'message' => 'Ce fournisseur n’appartient pas à votre marché.',
                ], 422);
            }
        }

        if ($data['collection_status'] === 'replaced') {
            if (empty($data['replacement_product_id'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'replacement_required',
                    'message' => 'Indiquez le produit pris à la place.',
                ], 422);
            }

            // Le client a peut-etre demande le retrait plutot que le
            // remplacement : sa consigne prime sur le choix du preparateur.
            if ($item->substitution_preference === 'remove') {
                return response()->json([
                    'success' => false,
                    'error' => 'replacement_refused',
                    'message' => 'Ce client a demandé de retirer cet article, pas de le remplacer.',
                ], 422);
            }
        }

        $collected = in_array($data['collection_status'], ['collected', 'replaced'], true);

        $item->update(array_merge([
            'collection_status' => $data['collection_status'],
            'supplier_id' => $data['supplier_id'] ?? $item->supplier_id,
            'replacement_product_id' => $data['collection_status'] === 'replaced'
                ? $data['replacement_product_id']
                : null,
            'collected_at' => $collected ? now() : null,
        ], $this->supplierCost($item, $data, $collected)));

        return response()->json([
            'success' => true,
            'data' => $item->fresh()->load('product', 'supplier:id,name,stall', 'replacementProduct:id,name,price_type'),
            'meta' => $this->progressFor($order),
        ]);
    }

    /**
     * Estampille sur la ligne ce que l'article coute chez ce fournisseur.
     *
     * Le preparateur ne saisit aucun prix — il retire en compte. Le montant
     * vient du tarif convenu entre Axizan et le partenaire, et il est fige ICI,
     * au moment du retrait. Renegocier un tarif la semaine prochaine ne doit
     * pas faire bouger un point deja regle.
     *
     * Sans tarif renseigne, on laisse a null plutot que d'inventer un zero :
     * l'ecran d'administration signale alors l'article a tarifer, au lieu de
     * presenter une dette sous-evaluee comme si elle etait complete.
     *
     * C'est le produit REELLEMENT pris qui est tarife : sur un remplacement,
     * c'est l'article de substitution qui a ete retire chez le fournisseur.
     */
    private function supplierCost(OrderItem $item, array $data, bool $collected): array
    {
        if (! $collected) {
            return ['actual_unit_price' => null, 'actual_total_price' => null];
        }

        $supplierId = $data['supplier_id'] ?? $item->supplier_id;
        $productId = $data['collection_status'] === 'replaced'
            ? ($data['replacement_product_id'] ?? null)
            : $item->product_id;

        if (! $supplierId || ! $productId) {
            return ['actual_unit_price' => null, 'actual_total_price' => null];
        }

        $unit = \App\Models\SupplierPrice::priceFor($supplierId, $productId);

        if ($unit === null) {
            return ['actual_unit_price' => null, 'actual_total_price' => null];
        }

        return [
            'actual_unit_price' => $unit,
            'actual_total_price' => \App\Support\Money::lineTotal($unit, (float) $item->quantity),
        ];
    }

    public function accept($id, Request $request)
    {
        if ($denied = $this->denyUnlessPreparateur($request)) {
            return $denied;
        }

        $user = $request->user();
        $order = Order::with('deliveryAddress')->find($id);

        if (! $order) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        // Meme filtre qu'a l'affichage : sinon un identifiant devine permettrait
        // de prendre une commande d'un autre marche.
        $zoneIds = $user->market?->zones()->pluck('delivery_zones.id') ?? collect();

        if (! $zoneIds->contains($order->deliveryAddress?->delivery_zone_id)) {
            return response()->json([
                'success' => false,
                'error' => 'outside_market',
                'message' => 'Cette commande ne relève pas de votre marché.',
            ], 403);
        }

        try {
            $order = $this->orderService->acceptOrder($order, $user);

            return response()->json(['success' => true, 'data' => $order]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Rendre une commande qu'on ne peut pas honorer.
     *
     * Sans cette porte de sortie, une moto en panne ou un preparateur malade
     * bloquait la commande pour toujours : preparateur_id reste pose, personne
     * d'autre ne peut la prendre, et le client attend une commande que plus
     * personne ne traite.
     *
     * Les lignes deja pointees sont REMISES A ZERO. Le preparateur suivant doit
     * repartir d'une liste vierge : ce qui a ete recupere est physiquement chez
     * le precedent, le laisser coche ferait croire que le panier est a moitie
     * pret alors qu'il est ailleurs.
     */
    public function release($id, Request $request)
    {
        $order = $this->findOwnedOrder($id, $request);

        if ($order instanceof \Illuminate\Http\JsonResponse) {
            return $order;
        }

        $data = $request->validate([
            'reason' => 'required|string|max:300',
        ], [
            'reason.required' => 'Indiquez pourquoi vous rendez cette commande.',
        ]);

        $order->load('items');
        $alreadyPicked = $order->items->where('collection_status', '!=', 'pending');

        // Le geste est le meme que celui de l'administrateur qui debloque une
        // commande abandonnee : une seule implementation, et la trace dit
        // lequel des deux l'a fait.
        $this->orderService->releaseToPool($order, $data['reason'], $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Commande rendue. Elle est de nouveau disponible pour votre marché.',
            'meta' => ['items_reset' => $alreadyPicked->count()],
        ]);
    }

    private static function statusName($status): ?string
    {
        return $status instanceof \BackedEnum ? $status->value : $status;
    }

    public function start($id, Request $request)
    {
        $order = $this->findOwnedOrder($id, $request);

        if ($order instanceof \Illuminate\Http\JsonResponse) {
            return $order;
        }

        try {
            $order = $this->orderService->startPreparing($order, $request->get('notes'));

            return response()->json(['success' => true, 'data' => $order]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Marquer prete. Toutes les lignes doivent avoir ete traitees.
     */
    public function ready($id, Request $request)
    {
        $order = $this->findOwnedOrder($id, $request);

        if ($order instanceof \Illuminate\Http\JsonResponse) {
            return $order;
        }

        $order->load('items');
        $pending = $order->items->where('collection_status', 'pending');

        if ($pending->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'error' => 'items_pending',
                'message' => $pending->count().' article(s) ne sont pas encore traités.',
                'meta' => $this->progressFor($order),
            ], 422);
        }

        // Sans fournisseur renseigne, le point avec les partenaires devient
        // impossible — et l'information ne se retrouve pas apres coup.
        $unsourced = $order->items
            ->whereIn('collection_status', ['collected', 'replaced'])
            ->whereNull('supplier_id');

        if ($unsourced->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'error' => 'supplier_missing',
                'message' => 'Indiquez le fournisseur pour chaque article récupéré.',
            ], 422);
        }

        // La photo du panier est OBLIGATOIRE.
        //
        // Le client paie d'avance des courses qu'il ne voit pas faire : c'est
        // la promesse de qualite qui distingue Axizan d'une simple course.
        // Cette photo est ce qui la rend verifiable — et la seule preuve
        // d'Axizan si la qualite est contestee plus tard. Facultative, elle
        // n'aurait ete prise que les jours calmes.
        $request->validate([
            'photo' => 'required|image|max:5120',
            'notes' => 'nullable|string|max:500',
        ], [
            'photo.required' => 'Photographiez le panier avant de le déposer : c’est ce que le client verra.',
            'photo.image' => 'Le fichier doit être une image.',
            'photo.max' => 'Photo trop lourde (5 Mo maximum).',
        ]);

        $path = $request->file('photo')->store('preparations/'.$order->id, 'public');
        $order->prep_photo_url = Storage::disk('public')->url($path);
        $order->save();

        $removed = $order->items->where('collection_status', 'removed');

        if ($removed->isNotEmpty()) {
            // Le client a paye ces articles : la commande part avec un
            // manque. On le journalise pour que l'admin puisse trancher le
            // remboursement — le montant n'est pas ajuste automatiquement.
            OrderEvent::record(
                $order,
                'items.removed',
                reason: $removed->count().' article(s) indisponibles, retirés à la demande du client',
                context: ['items' => $removed->pluck('product_id')->all()]
            );
        }

        $order = $this->orderService->markReady($order, $request->get('notes'));

        return response()->json([
            'success' => true,
            'data' => $order->load('items.product', 'items.supplier:id,name'),
            'meta' => ['removed_count' => $removed->count()],
        ]);
    }

    /**
     * Propose un fournisseur par ligne, et l'ordre de parcours du marche.
     *
     * La specialite d'un fournisseur porte le nom d'une categorie produit :
     * « Legumes frais », « Tubercules »... Le rapprochement est donc direct, et
     * il transforme le travail du preparateur — au lieu de choisir un
     * fournisseur sur chaque ligne, il confirme.
     *
     * `pick_order` regroupe ensuite les lignes par etal : il traverse le marche
     * une fois au lieu de faire des allers-retours entre les allees.
     */
    private function suggestSuppliers($orders, ?int $marketId): void
    {
        if (! $marketId) {
            return;
        }

        // Ordre de parcours stable : les etals dans l'ordre ou ils ont ete
        // enregistres, ce qui suit en general la disposition du marche.
        $suppliers = Supplier::active()
            ->where('market_id', $marketId)
            ->with('categories:id')
            ->orderBy('id')
            ->get();

        // Une categorie est servie par le PREMIER etal qui la couvre : deux
        // vendeuses proposent souvent les memes condiments, et renvoyer le
        // preparateur tantot chez l'une tantot chez l'autre lui ferait
        // traverser le marche deux fois pour rien.
        $byCategory = [];

        foreach ($suppliers as $rank => $supplier) {
            foreach ($supplier->categories as $category) {
                $byCategory[$category->id] ??= ['supplier' => $supplier->id, 'rank' => $rank];
            }
        }

        foreach ($orders as $orderModel) {
            foreach ($orderModel->items as $item) {
                $match = $byCategory[$item->product?->category_id] ?? null;

                $item->setAttribute('suggested_supplier_id', $match['supplier'] ?? null);
                // Les lignes sans correspondance passent en fin de liste.
                $item->setAttribute('pick_order', $match['rank'] ?? 999);
            }
        }
    }

    /** Avancement d'une commande, pour la barre de progression. */
    private function progressFor(Order $order): array
    {
        $items = $order->items()->get();

        return [
            'total' => $items->count(),
            'done' => $items->where('collection_status', '!=', 'pending')->count(),
        ];
    }

    private function denyUnlessPreparateur(Request $request)
    {
        if (! $request->user()->isPreparateur()) {
            return response()->json(['success' => false, 'error' => 'forbidden'], 403);
        }

        return null;
    }

    private function findOwnedOrder($id, Request $request)
    {
        if ($denied = $this->denyUnlessPreparateur($request)) {
            return $denied;
        }

        $order = Order::find($id);

        if (! $order || $order->preparateur_id !== $request->user()->id) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        return $order;
    }
}
