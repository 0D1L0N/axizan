<?php

namespace App\Http\Controllers\Api;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Le personnel : preparateurs et livreurs.
 *
 * Le trou que cet ecran ferme : un livreur qui rend son tablier gardait son
 * acces a vie. Le verrou existait pourtant depuis la gestion des clients —
 * `users.status` coupe la connexion et detruit les jetons — mais rien ne
 * permettait de l'actionner sur quelqu'un du personnel. La serrure etait posee,
 * sans poignee.
 *
 * Deux regles encadrent la suspension, et elles ne sont pas symetriques parce
 * que les deux metiers ne le sont pas. Voir setStatus().
 */
class AdminStaffController extends Controller
{
    /**
     * Sans nouvelle depuis ce nombre de jours, un compte est dit dormant.
     *
     * C'est le signal qui repond au probleme d'origine : personne ne vient
     * declarer qu'il arrete. Il cesse simplement de venir, et son acces reste
     * ouvert. Quatorze jours passent deux week-ends et un empechement
     * ordinaire — en deca, on signalerait des gens en conge.
     */
    private const DORMANT_AFTER_DAYS = 14;

    public function __construct(private OrderService $orders)
    {}

    /** La liste, triee par ce qui demande une decision. */
    public function index(Request $request)
    {
        $query = User::query()->whereIn('role', [UserRole::PREPARATEUR, UserRole::LIVREUR]);

        if ($request->filled('search')) {
            $term = $request->string('search')->toString();
            $query->where(function ($sub) use ($term) {
                $sub->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        match ($request->string('filter')->toString()) {
            'preparateur' => $query->where('role', UserRole::PREPARATEUR),
            'livreur' => $query->where('role', UserRole::LIVREUR),
            'suspended' => $query->where('status', '!=', 'active'),
            'dormant' => $this->scopeDormant($query),
            default => null,
        };

        $staff = $query
            ->with('market:id,name,city')
            ->orderByRaw("CASE WHEN status = 'active' THEN 1 ELSE 0 END")
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->map(fn (User $person) => $this->row($person));

        $all = User::whereIn('role', [UserRole::PREPARATEUR, UserRole::LIVREUR]);

        return response()->json([
            'success' => true,
            'data' => $staff,
            'meta' => [
                'total' => (clone $all)->count(),
                'preparateurs' => (clone $all)->where('role', UserRole::PREPARATEUR)->count(),
                'livreurs' => (clone $all)->where('role', UserRole::LIVREUR)->count(),
                'suspended' => (clone $all)->where('status', '!=', 'active')->count(),
                'dormant' => $this->scopeDormant(clone $all)->count(),
                'dormant_after_days' => self::DORMANT_AFTER_DAYS,
            ],
        ]);
    }

    /** La fiche : ce qu'on ouvre avant de decider. */
    public function show($id)
    {
        $person = $this->findStaff($id);

        if (! $person) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $person->load('market:id,name,city');

        return response()->json([
            'success' => true,
            'data' => [
                'person' => $person->only(['id', 'name', 'email', 'phone', 'status', 'created_at'])
                    + ['role' => $person->role->value, 'role_label' => UserRole::getLabel($person->role)]
                    + ['market' => $person->market?->only(['id', 'name', 'city'])],
                'in_hand' => $this->inHand($person),
                'stats' => $this->stats($person),
                'history' => $this->history($person),
            ],
        ]);
    }

    /**
     * Suspend un compte du personnel, ou le retablit.
     *
     * Les deux metiers ne se suspendent pas de la meme facon, parce que ce
     * qu'ils tiennent dans les mains n'est pas la meme chose.
     *
     * UN PREPARATEUR : ses commandes en cours REPARTENT AU MARCHE. Rien n'est
     * physiquement engage — il n'a pas encore depose au depot, et les lignes
     * deja pointees sont remises a zero par releaseToPool() parce que la
     * marchandise est chez lui. Un autre preparateur reprend depuis une liste
     * vierge. Suspendre sans liberer bloquerait ces commandes pour toujours :
     * preparateur_id reste pose, personne d'autre ne peut les prendre, et le
     * client attend une commande que plus personne ne traite.
     *
     * Liberer d'office plutot que d'exiger le menage a la main : une suspension
     * est souvent une urgence — vol, abandon de poste — et le moment ou il faut
     * couper l'acces est le pire pour imposer six clics prealables.
     *
     * UN LIVREUR EN COURSE : c'est REFUSE. La marchandise est physiquement dans
     * sa sacoche. Lui couper l'application n'amenerait pas le panier chez le
     * client — ca supprimerait seulement le seul moyen de suivre ou il est.
     * Rien ne permet aujourd'hui de rendre une course entamee ; tant que ce
     * n'est pas le cas, la commande doit d'abord etre traitee dans la salle de
     * controle. L'ecran dit laquelle.
     *
     * Les paniers deja deposes au depot ne retiennent pas un preparateur : son
     * travail sur ces commandes-la est fini, elles appartiennent au livreur.
     */
    public function setStatus(Request $request, $id)
    {
        $person = $this->findStaff($id);

        if (! $person) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $data = $request->validate([
            'suspended' => 'required|boolean',
            'reason' => 'required_if:suspended,true|nullable|string|max:300',
        ], [
            'reason.required_if' => 'Indiquez pourquoi ce compte est suspendu.',
        ]);

        if (! $data['suspended']) {
            $person->update(['status' => 'active']);

            return response()->json([
                'success' => true,
                'message' => "{$person->name} a de nouveau accès à son compte.",
                'data' => $person->fresh()->only(['id', 'name', 'status']),
            ]);
        }

        if ($person->isLivreur()) {
            $enRoute = $this->activeDeliveries($person)->get();

            if ($enRoute->isNotEmpty()) {
                $numbers = $enRoute->map(fn (Delivery $d) => $d->order?->order_number)->filter()->values();

                return response()->json([
                    'success' => false,
                    'error' => 'delivery_in_progress',
                    'message' => $numbers->count() === 1
                        ? "Le panier de la commande {$numbers->first()} est dans sa sacoche."
                            .' Appelez-le. S’il ne revient pas, close la course depuis la salle de'
                            .' contrôle — le client sera remboursé — puis suspendez le compte.'
                        : "{$numbers->count()} paniers sont encore dans sa sacoche ({$numbers->join(', ')})."
                            .' Appelez-le. S’il ne revient pas, close ces courses depuis la salle de'
                            .' contrôle — les clients seront remboursés — puis suspendez le compte.',
                    'orders' => $numbers,
                ], 422);
            }
        }

        $released = DB::transaction(function () use ($person, $data, $request) {
            $released = collect();

            if ($person->isPreparateur()) {
                $held = Order::where('preparateur_id', $person->id)
                    ->whereIn('status', [OrderStatus::ACCEPTED, OrderStatus::PREPARING])
                    ->get();

                foreach ($held as $order) {
                    $this->orders->releaseToPool(
                        $order,
                        "Compte suspendu : {$data['reason']}",
                        $request->user()
                    );
                    $released->push($order->order_number);
                }
            }

            $person->update(['status' => 'suspended']);

            // Les jetons Sanctum vivent trente jours : bloquer la seule
            // connexion laisserait le compte travailler tout ce temps.
            $person->tokens()->delete();

            return $released;
        });

        $message = "{$person->name} ne peut plus se connecter.";

        if ($released->isNotEmpty()) {
            $message .= $released->count() === 1
                ? " La commande {$released->first()} est repartie au marché."
                : " {$released->count()} commandes sont reparties au marché ({$released->join(', ')}).";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'released' => $released,
            'data' => $person->fresh()->only(['id', 'name', 'status']),
        ]);
    }

    // -----------------------------------------------------------------
    // Lecture
    // -----------------------------------------------------------------

    private function findStaff($id): ?User
    {
        return User::whereIn('role', [UserRole::PREPARATEUR, UserRole::LIVREUR])->find($id);
    }

    /**
     * Sans nouvelle depuis DORMANT_AFTER_DAYS.
     *
     * Les comptes crees recemment sont exclus : quelqu'un embauche avant-hier
     * n'a pas encore d'activite, et le signaler comme parti serait absurde.
     */
    private function scopeDormant($query)
    {
        $cutoff = now()->subDays(self::DORMANT_AFTER_DAYS);

        return $query
            ->where('status', 'active')
            ->where('users.created_at', '<', $cutoff)
            ->where(function ($sub) use ($cutoff) {
                $sub->where(function ($prep) use ($cutoff) {
                    $prep->where('role', UserRole::PREPARATEUR)
                        ->whereDoesntHave('preparerOrders', fn ($orders) => $orders
                            ->where('orders.created_at', '>=', $cutoff));
                })->orWhere(function ($liv) use ($cutoff) {
                    $liv->where('role', UserRole::LIVREUR)
                        ->whereDoesntHave('deliveries', fn ($deliveries) => $deliveries
                            ->where('deliveries.created_at', '>=', $cutoff));
                });
            });
    }

    /** Courses encore dans la sacoche d'un livreur. */
    private function activeDeliveries(User $livreur)
    {
        return Delivery::with('order:id,order_number')
            ->where('livreur_id', $livreur->id)
            ->whereIn('status', [
                DeliveryStatus::ASSIGNED,
                DeliveryStatus::PICKED_UP,
                DeliveryStatus::IN_TRANSIT,
            ]);
    }

    /**
     * Ce que la personne tient en main a l'instant.
     *
     * C'est le chiffre qui decide : il dit ce qui se passera si on suspend.
     */
    private function inHand(User $person): array
    {
        if ($person->isPreparateur()) {
            $orders = Order::where('preparateur_id', $person->id)
                ->whereIn('status', [OrderStatus::ACCEPTED, OrderStatus::PREPARING])
                ->orderBy('delivery_date')
                ->get(['id', 'order_number', 'status', 'delivery_date']);

            return $orders->map(fn (Order $order) => [
                'order_number' => $order->order_number,
                'status_label' => $order->status->getLabel(),
                'delivery_date' => $order->delivery_date?->toDateString(),
                // Ces commandes-la repartiront au marche a la suspension.
                'blocks_suspension' => false,
            ])->all();
        }

        return $this->activeDeliveries($person)->get()->map(fn (Delivery $delivery) => [
            'order_number' => $delivery->order?->order_number,
            'status_label' => $delivery->status->getLabel(),
            'delivery_date' => null,
            // Le panier est chez lui : la suspension attend.
            'blocks_suspension' => true,
        ])->all();
    }

    private function stats(User $person): array
    {
        $today = now()->toDateString();

        if ($person->isPreparateur()) {
            $base = fn () => Order::where('preparateur_id', $person->id);

            return [
                'handled' => $base()->count(),
                'today' => $base()->whereDate('delivery_date', $today)->count(),
                'completed' => $base()->where('status', OrderStatus::COMPLETED)->count(),
                'last_active_at' => $base()->max('created_at'),
            ];
        }

        $base = fn () => Delivery::where('livreur_id', $person->id);

        return [
            'handled' => $base()->count(),
            'today' => $base()->whereDate('created_at', $today)->count(),
            'completed' => $base()->where('status', DeliveryStatus::DELIVERED)->count(),
            'last_active_at' => $base()->max('created_at'),
        ];
    }

    /** Les vingt derniers passages, pour juger sur pieces. */
    private function history(User $person): array
    {
        if ($person->isPreparateur()) {
            return Order::where('preparateur_id', $person->id)
                ->latest()
                ->limit(20)
                ->get(['id', 'order_number', 'status', 'delivery_date', 'created_at'])
                ->map(fn (Order $order) => [
                    'order_number' => $order->order_number,
                    'status_label' => $order->status->getLabel(),
                    'delivery_date' => $order->delivery_date?->toDateString(),
                    'at' => $order->created_at->toIso8601String(),
                ])->all();
        }

        return Delivery::with('order:id,order_number,delivery_date')
            ->where('livreur_id', $person->id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Delivery $delivery) => [
                'order_number' => $delivery->order?->order_number,
                'status_label' => $delivery->status->getLabel(),
                'delivery_date' => $delivery->order?->delivery_date?->toDateString(),
                'at' => $delivery->created_at->toIso8601String(),
            ])->all();
    }

    private function row(User $person): array
    {
        $stats = $this->stats($person);
        $lastActive = $stats['last_active_at'];

        return [
            'id' => $person->id,
            'name' => $person->name,
            'role' => $person->role->value,
            'role_label' => UserRole::getLabel($person->role),
            'phone' => $person->phone,
            'email' => $person->email,
            'status' => $person->status,
            'market' => $person->market?->name,
            'city' => $person->market?->city,
            'in_hand' => count($this->inHand($person)),
            'handled' => $stats['handled'],
            'today' => $stats['today'],
            'last_active_at' => $lastActive,
            'dormant' => $person->status === 'active'
                && $person->created_at->lt(now()->subDays(self::DORMANT_AFTER_DAYS))
                && (! $lastActive || \Carbon\Carbon::parse($lastActive)->lt(now()->subDays(self::DORMANT_AFTER_DAYS))),
            'member_since' => $person->created_at->toIso8601String(),
        ];
    }
}
