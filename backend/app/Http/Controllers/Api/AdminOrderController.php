<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use App\Services\OrderService;
use App\Services\OrderWatchService;
use Illuminate\Http\Request;

/**
 * La salle de controle.
 *
 * Tout ce qui est ici existait deja en base et n'etait lisible nulle part :
 * le journal des transitions avec son auteur, la position du livreur remontee
 * toutes les 45 secondes, les coordonnees du preparateur en charge. Cet ecran
 * ne collecte rien de neuf — il donne enfin acces a ce qu'on ecrivait.
 */
class AdminOrderController extends Controller
{
    public function __construct(
        private OrderWatchService $watch,
        private OrderService $orders
    ) {}

    /**
     * Les commandes en cours, et surtout celles qui ne bougent pas.
     */
    public function index(Request $request)
    {
        $query = Order::query()
            ->with([
                'client:id,name,phone',
                // Le personnel est identifie ET joignable : l'interet de cet
                // ecran est de pouvoir appeler la personne concernee.
                'preparateur:id,name,phone,market_id',
                'preparateur.market:id,name',
                'livreur:id,name,phone',
                'deliveryAddress:id,quartier,landmark,city',
                'deliverySlot:id,display_label,time_start',
                'delivery:id,order_id,status,current_latitude,current_longitude,last_location_update',
                'items:id,order_id,collection_status',
            ]);

        // Par defaut : ce qui est vivant, quel que soit le jour. Une commande
        // d'hier restee en plan doit rester sous les yeux.
        if ($request->filled('status')) {
            $query->whereIn('status', explode(',', $request->string('status')));
        } else {
            $query->whereIn('status', $this->watch->liveStatuses());
        }

        if ($request->filled('date')) {
            $query->whereDate('delivery_date', $request->date('date'));
        }

        $orders = $query->orderBy('delivery_date')->orderBy('delivery_slot_time')->limit(200)->get();

        $rows = $orders->map(fn (Order $order) => $this->row($order))->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'total' => $rows->count(),
                'urgent' => $rows->where('attention.level', 'urgent')->count(),
                'warning' => $rows->where('attention.level', 'warning')->count(),
                'unassigned' => $rows->whereNull('preparateur')->count(),
                'market_hours' => $this->watch->marketHours(),
            ],
        ]);
    }

    /** La fiche complete : le journal, les preuves, les contacts, la position. */
    public function show($id)
    {
        $order = Order::with([
            'client:id,name,phone,email',
            'preparateur:id,name,phone,market_id',
            'preparateur.market:id,name,city',
            'livreur:id,name,phone',
            'deliveryAddress',
            'deliverySlot',
            'payment',
            'delivery.livreur:id,name,phone',
            'items.product:id,name,price_type',
            'items.supplier:id,name,stall',
            'items.replacementProduct:id,name',
            // Le journal, dans l'ordre. Chaque ligne porte son auteur : c'est
            // ce qui permet de dire « rendue par Aissatou a 11 h 05 » plutot
            // que « rendue ».
            'events.actor:id,name,role',
        ])->find($id);

        if (! $order) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order' => $order,
                'attention' => $this->watch->attention($order),
                'minutes_to_slot' => $this->watch->minutesToSlot($order),
                'journal' => $order->events->map(fn (OrderEvent $event) => [
                    'id' => $event->id,
                    'event' => $event->event,
                    'from_status' => $event->from_status,
                    'to_status' => $event->to_status,
                    'reason' => $event->reason,
                    'context' => $event->context,
                    'at' => $event->created_at->toIso8601String(),
                    'actor' => $event->actor?->only(['id', 'name']),
                    'actor_role' => $event->actor_role,
                ]),
            ],
        ]);
    }

    /**
     * Libere une commande prise puis abandonnee.
     *
     * Meme geste que le preparateur qui rend ce qu'il ne peut pas honorer ;
     * la trace dit que c'est l'administration qui a tranche.
     */
    public function release(Request $request, $id)
    {
        $order = Order::with('items')->find($id);

        if (! $order) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        if (! $order->preparateur_id) {
            return response()->json([
                'success' => false,
                'error' => 'not_assigned',
                'message' => 'Cette commande n’est prise par personne.',
            ], 422);
        }

        if (in_array($order->status, [OrderStatus::READY, OrderStatus::IN_DELIVERY], true)) {
            return response()->json([
                'success' => false,
                'error' => 'already_ready',
                'message' => 'Le panier est déjà au dépôt : le libérer ferait repartir un retrait déjà fait.',
            ], 422);
        }

        $data = $request->validate([
            'reason' => 'required|string|max:300',
        ], ['reason.required' => 'Indiquez pourquoi vous libérez cette commande.']);

        $this->orders->releaseToPool($order, $data['reason'], $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Commande libérée. Elle est de nouveau disponible pour le marché.',
        ]);
    }

    /**
     * Confie la commande a un autre preparateur.
     *
     * Le marche du destinataire doit desservir la zone de livraison : sinon on
     * envoie quelqu'un chercher des articles a l'autre bout de la ville, et il
     * ne verra meme pas la commande sur son propre ecran.
     */
    public function reassign(Request $request, $id)
    {
        $order = Order::with('deliveryAddress', 'items')->find($id);

        if (! $order) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $data = $request->validate([
            'preparateur_id' => 'required|exists:users,id',
            'reason' => 'nullable|string|max:300',
        ]);

        $target = User::with('market.zones:id')->find($data['preparateur_id']);

        if (! $target?->isPreparateur() || $target->status !== 'active') {
            return response()->json([
                'success' => false,
                'error' => 'not_a_preparateur',
                'message' => 'Ce compte n’est pas un préparateur actif.',
            ], 422);
        }

        $zoneId = $order->deliveryAddress?->delivery_zone_id;

        if ($zoneId && ! $target->market?->zones->pluck('id')->contains($zoneId)) {
            return response()->json([
                'success' => false,
                'error' => 'market_does_not_serve_zone',
                'message' => 'Le marché de ce préparateur ne dessert pas la zone de livraison.',
            ], 422);
        }

        $previous = $order->preparateur_id;

        $order->update([
            'preparateur_id' => $target->id,
            'status' => $order->status === OrderStatus::PENDING ? OrderStatus::ACCEPTED : $order->status,
            'accepted_at' => $order->accepted_at ?? now(),
        ]);

        OrderEvent::record($order, 'order.reassigned', reason: $data['reason'] ?? null, context: [
            'from_preparateur_id' => $previous,
            'to_preparateur_id' => $target->id,
            'by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Commande confiée à {$target->name}.",
            'data' => $order->fresh()->load('preparateur:id,name,phone'),
        ]);
    }

    /** Annule, avec motif obligatoire. */
    public function cancel(Request $request, $id)
    {
        $order = Order::find($id);

        if (! $order) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $data = $request->validate([
            'reason' => 'required|string|max:300',
        ], ['reason.required' => 'Un motif est obligatoire : le client sera prévenu.']);

        try {
            $this->orders->cancelOrder($order, $data['reason']);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'cannot_cancel',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json(['success' => true, 'message' => 'Commande annulée.']);
    }

    /**
     * Acte qu'une course ne reviendra pas.
     *
     * L'issue qui manquait. Une commande partie en livraison ne pouvait ni etre
     * liberee ni etre annulee — deux refus justes quand la course se deroule,
     * une impasse quand elle ne se deroule pas. Le client restait sans nouvelle
     * ni remboursement, et le compte du livreur ne pouvait plus etre suspendu
     * puisqu'un panier lui restait attribue.
     *
     * A n'employer qu'apres avoir essaye de joindre la personne : c'est la
     * salle de controle qui donne son numero.
     */
    public function abandonDelivery(Request $request, $id)
    {
        $order = Order::with('delivery', 'payment')->find($id);

        if (! $order) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        if ($order->status->isTerminal()) {
            return response()->json([
                'success' => false,
                'error' => 'already_closed',
                'message' => 'Cette commande est déjà close.',
            ], 422);
        }

        if (! $order->delivery || ! $order->livreur_id) {
            return response()->json([
                'success' => false,
                'error' => 'not_in_delivery',
                'message' => 'Aucun livreur n’a pris cette commande. Libérez-la plutôt.',
            ], 422);
        }

        $data = $request->validate([
            'reason' => 'required|string|max:300',
        ], [
            'reason.required' => 'Un motif est obligatoire : le client sera remboursé et prévenu.',
        ]);

        $this->orders->abandonDelivery($order, $data['reason'], $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Course close. Le client est remboursé et prévenu.',
        ]);
    }

    /**
     * Preparateurs pouvant recevoir cette commande.
     *
     * Filtres sur les marches qui desservent la zone : proposer les autres
     * reviendrait a offrir un choix qui ne marche pas.
     */
    public function candidates($id)
    {
        $order = Order::with('deliveryAddress')->find($id);

        if (! $order) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $zoneId = $order->deliveryAddress?->delivery_zone_id;

        $candidates = User::query()
            ->where('role', \App\Enums\UserRole::PREPARATEUR)
            ->where('status', 'active')
            ->where('id', '!=', $order->preparateur_id)
            ->when($zoneId, fn ($query) => $query->whereHas(
                'market.zones',
                fn ($zones) => $zones->where('delivery_zones.id', $zoneId)
            ))
            ->with('market:id,name,city')
            ->withCount(['preparerOrders as active_orders' => fn ($query) => $query
                ->whereIn('status', [OrderStatus::ACCEPTED, OrderStatus::PREPARING])])
            ->orderBy('active_orders')
            ->get(['id', 'name', 'phone', 'market_id']);

        return response()->json(['success' => true, 'data' => $candidates]);
    }

    /** Une ligne de la vue du jour. */
    private function row(Order $order): array
    {
        $items = $order->items;
        $picked = $items->where('collection_status', '!=', 'pending')->count();

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'status_label' => $order->status->getLabel(),
            'payment_status' => $order->payment_status,
            'total' => \App\Support\Money::round((float) $order->total),
            'delivery_date' => $order->delivery_date?->toDateString(),
            'slot' => $this->watch->slotLabel($order),
            'minutes_to_slot' => $this->watch->minutesToSlot($order),
            'quartier' => $order->deliveryAddress?->quartier,
            'client' => $order->client?->only(['id', 'name', 'phone']),
            'preparateur' => $order->preparateur
                ? $order->preparateur->only(['id', 'name', 'phone']) + [
                    'market' => $order->preparateur->market?->name,
                ]
                : null,
            'livreur' => $order->livreur?->only(['id', 'name', 'phone']),
            'progress' => ['picked' => $picked, 'total' => $items->count()],
            // Position remontee pendant le trajet : jusqu'ici seul le client
            // pouvait la voir.
            'position' => $order->delivery && $order->delivery->current_latitude !== null
                ? [
                    'lat' => (float) $order->delivery->current_latitude,
                    'lng' => (float) $order->delivery->current_longitude,
                    'at' => $order->delivery->last_location_update?->toIso8601String(),
                ]
                : null,
            'attention' => $this->watch->attention($order),
        ];
    }
}
