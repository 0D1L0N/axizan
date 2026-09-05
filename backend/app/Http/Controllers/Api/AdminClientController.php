<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Les clients.
 *
 * Rien n'existait : ni liste, ni historique, ni moyen de suspendre un compte.
 * Le champ `users.status` etait ecrit a l'inscription et lu nulle part.
 *
 * Un mot sur les IMPAYES, parce que la definition fait tout. Une commande non
 * payee pour demain n'est pas un impaye : le client a le temps. Une commande
 * non payee dont le CRENEAU EST PASSE en est un — elle a occupe une place que
 * personne d'autre n'a pu prendre, et la capacite du creneau est le stock
 * reel d'Axizan.
 */
class AdminClientController extends Controller
{
    /** La liste, avec ce qui permet de trier les problemes du reste. */
    public function index(Request $request)
    {
        $query = User::query()->where('role', UserRole::CLIENT);

        if ($request->filled('search')) {
            $term = $request->string('search')->toString();
            $query->where(function ($sub) use ($term) {
                $sub->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        match ($request->string('filter')->toString()) {
            'unpaid' => $query->whereHas('orders', fn ($orders) => $this->scopeUnpaid($orders)),
            'suspended' => $query->where('status', '!=', 'active'),
            default => null,
        };

        $clients = $query
            ->withCount([
                'orders',
                'orders as completed_orders' => fn ($q) => $q->where('status', OrderStatus::COMPLETED),
                'orders as unpaid_orders' => fn ($q) => $this->scopeUnpaid($q),
            ])
            // Ce que le client a REELLEMENT paye : une commande annulee ou
            // impayee ne compte pas comme chiffre d'affaires.
            ->withSum(
                ['orders as spent' => fn ($q) => $q->where('payment_status', 'completed')],
                'total'
            )
            ->withMax('orders as last_order_at', 'created_at')
            ->orderByDesc('last_order_at')
            ->limit(200)
            ->get(['id', 'name', 'email', 'phone', 'status', 'created_at'])
            ->map(fn (User $client) => $this->row($client));

        return response()->json([
            'success' => true,
            'data' => $clients,
            'meta' => [
                'total' => User::where('role', UserRole::CLIENT)->count(),
                'suspended' => User::where('role', UserRole::CLIENT)->where('status', '!=', 'active')->count(),
                'with_unpaid' => User::where('role', UserRole::CLIENT)
                    ->whereHas('orders', fn ($orders) => $this->scopeUnpaid($orders))
                    ->count(),
            ],
        ]);
    }

    /** La fiche : ce qu'on ouvre quand quelqu'un appelle pour réclamer. */
    public function show($id)
    {
        $client = User::where('role', UserRole::CLIENT)->find($id);

        if (! $client) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $orders = $client->orders()
            ->with('deliverySlot:id,display_label', 'deliveryAddress:id,quartier')
            ->latest()
            ->limit(50)
            ->get();

        $paid = $orders->where('payment_status', 'completed');
        $unpaid = $orders->filter(fn (Order $order) => $this->isUnpaid($order));

        return response()->json([
            'success' => true,
            'data' => [
                'client' => $client->only(['id', 'name', 'email', 'phone', 'status', 'created_at']),
                'addresses' => $client->addresses()
                    ->with('zone:id,name')
                    ->get(['id', 'label', 'quartier', 'landmark', 'city', 'delivery_zone_id', 'is_default']),
                'stats' => [
                    'orders' => $orders->count(),
                    'completed' => $orders->where('status', OrderStatus::COMPLETED)->count(),
                    'cancelled' => $orders->where('status', OrderStatus::CANCELLED)->count(),
                    'spent' => Money::round($paid->sum('total')),
                    // Panier moyen sur ce qui a ete paye : la moyenne sur tout
                    // inclurait des commandes qui n'ont jamais rapporte un franc.
                    'average_basket' => $paid->isEmpty() ? 0 : Money::round($paid->avg('total')),
                    'unpaid' => $unpaid->count(),
                    'unpaid_amount' => Money::round($unpaid->sum('total')),
                    'first_order_at' => $orders->min('created_at')?->toIso8601String(),
                    'last_order_at' => $orders->max('created_at')?->toIso8601String(),
                ],
                'orders' => $orders->map(fn (Order $order) => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status->value,
                    'status_label' => $order->status->getLabel(),
                    'payment_status' => $order->payment_status,
                    'total' => Money::round((float) $order->total),
                    'delivery_date' => $order->delivery_date?->toDateString(),
                    'slot' => $order->deliverySlot?->display_label,
                    'quartier' => $order->deliveryAddress?->quartier,
                    'unpaid' => $this->isUnpaid($order),
                    'created_at' => $order->created_at->toIso8601String(),
                ]),
            ],
        ]);
    }

    /**
     * Suspend un compte, ou le reactive.
     *
     * La suspension mord vraiment : la connexion est refusee, et le jeton en
     * cours est detruit a la premiere requete. Sans cela, un compte suspendu
     * continuerait de commander pendant les trente jours de vie du jeton.
     */
    public function setStatus(Request $request, $id)
    {
        $client = User::where('role', UserRole::CLIENT)->find($id);

        if (! $client) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $data = $request->validate([
            'suspended' => 'required|boolean',
            'reason' => 'required_if:suspended,true|nullable|string|max:300',
        ], [
            'reason.required_if' => 'Indiquez pourquoi ce compte est suspendu.',
        ]);

        DB::transaction(function () use ($client, $data) {
            $client->update(['status' => $data['suspended'] ? 'suspended' : 'active']);

            // Les jetons existants ne survivent pas a une suspension : le
            // middleware les rejetterait, autant les retirer tout de suite.
            if ($data['suspended']) {
                $client->tokens()->delete();
            }
        });

        return response()->json([
            'success' => true,
            'message' => $data['suspended']
                ? "{$client->name} ne peut plus se connecter ni commander."
                : "{$client->name} a de nouveau accès à son compte.",
            'data' => $client->fresh()->only(['id', 'name', 'status']),
        ]);
    }

    /**
     * Une commande non payee dont le creneau est passe.
     *
     * C'est la seule definition qui isole un vrai probleme : une commande non
     * payee pour demain n'en est pas un, le client a encore le temps. Passe le
     * creneau, elle a occupe une place que personne d'autre n'a pu prendre.
     */
    private function scopeUnpaid($query)
    {
        return $query
            ->where('payment_status', '!=', 'completed')
            ->whereNotIn('status', [OrderStatus::CANCELLED, OrderStatus::COMPLETED])
            ->whereDate('delivery_date', '<', now()->toDateString());
    }

    private function isUnpaid(Order $order): bool
    {
        return $order->payment_status !== 'completed'
            && ! in_array($order->status, [OrderStatus::CANCELLED, OrderStatus::COMPLETED], true)
            && $order->delivery_date?->isBefore(now()->startOfDay());
    }

    private function row(User $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'status' => $client->status,
            'orders' => $client->orders_count,
            'completed' => $client->completed_orders,
            'unpaid' => $client->unpaid_orders,
            'spent' => Money::round((float) ($client->spent ?? 0)),
            'last_order_at' => $client->last_order_at,
            'member_since' => $client->created_at->toIso8601String(),
        ];
    }
}
