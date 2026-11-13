<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Models\Order;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\DeliverySlot;
use App\Models\IdempotencyKey;
use App\Models\OrderEvent;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService)
    {}

    /**
     * Créer une commande
     */
    public function store(Request $request, OrderService $orderService)
    {
        $user = $request->user();

        $validated = $request->validate([
            'delivery_address_id' => 'required|exists:addresses,id',
            'delivery_slot_id' => 'required|exists:delivery_slots,id',
            // Livraison le jour meme comprise : c'est meme le cas nominal, on
            // achete au marche le jour de la livraison.
            'delivery_date' => 'required|date|after_or_equal:today',
            'payment_method' => 'in:manual,mobile_money',
            // Opérateur choisi par le client. Aucune API n'est branchée : on
            // enregistre le choix pour guider le paiement et la vérification.
            'payment_provider' => 'nullable|in:mtn,moov,celtiis',
            'payer_phone' => 'nullable|string|max:30',
            'promo_code_id' => 'nullable|exists:promotions,id',
            'promo_code' => 'nullable|string|max:50',
            'client_notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.type' => 'required|in:product,menu',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.menu_id' => 'nullable|exists:menus,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.quantity_unit' => 'nullable|string|max:20',
            'items.*.special_instructions' => 'nullable|string|max:255',
            'items.*.substitution_preference' => 'nullable|in:replace,remove',
        ]);

        foreach ($validated['items'] as $item) {
            $missingProduct = $item['type'] === 'product' && empty($item['product_id']);
            $missingMenu = $item['type'] === 'menu' && empty($item['menu_id']);

            if ($missingProduct || $missingMenu) {
                return response()->json([
                    'success' => false,
                    'error' => 'invalid_items',
                    'message' => 'Chaque item doit fournir product_id (type=product) ou menu_id (type=menu)',
                ], 422);
            }
        }

        // Le delai minimum et la capacite se verifient ici, pas seulement dans
        // l'interface : sinon n'importe quel appel direct reserve un creneau
        // deja passe.
        if ($slotError = $this->slotUnavailableResponse($validated)) {
            return $slotError;
        }

        // Idempotence : sur un reseau instable, le client qui ne voit pas la
        // reponse arriver retouche le bouton. La meme cle doit renvoyer la
        // commande deja creee, pas en creer une seconde.
        $idempotencyKey = $request->header('Idempotency-Key');

        if ($idempotencyKey) {
            $hash = IdempotencyKey::hashPayload($validated);

            $existing = IdempotencyKey::where('user_id', $user->id)
                ->where('endpoint', 'orders.store')
                ->where('key', $idempotencyKey)
                ->first();

            if ($existing) {
                if ($existing->payload_hash !== $hash) {
                    return response()->json([
                        'success' => false,
                        'error' => 'idempotency_key_reused',
                        'message' => 'Cette clé a déjà servi pour une commande différente.',
                    ], 409);
                }

                $order = Order::with('items', 'client', 'deliveryAddress')->find($existing->resource_id);

                if ($order) {
                    return response()->json([
                        'success' => true,
                        'data' => $order,
                        'message' => 'Commande déjà enregistrée',
                    ], 200);
                }
            }
        }

        try {
            $cartItems = $validated['items'];

            $order = $orderService->createOrder($user, $cartItems, $validated);

            if ($idempotencyKey) {
                IdempotencyKey::updateOrCreate(
                    ['user_id' => $user->id, 'endpoint' => 'orders.store', 'key' => $idempotencyKey],
                    ['payload_hash' => IdempotencyKey::hashPayload($validated), 'resource_id' => $order->id]
                );
            }

            return response()->json([
                'success' => true,
                'data' => $order->load('items', 'client', 'deliveryAddress'),
                'message' => 'Commande créée avec succès',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'order_creation_failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Verifie que le creneau demande est reservable a la date demandee.
     *
     * @return \Illuminate\Http\JsonResponse|null null si tout va bien
     */
    private function slotUnavailableResponse(array $validated)
    {
        $slot = DeliverySlot::find($validated['delivery_slot_id']);
        $date = $validated['delivery_date'];

        if (! $slot || ! $slot->is_active) {
            return response()->json([
                'success' => false,
                'error' => 'slot_unavailable',
                'message' => 'Ce créneau de livraison n’est plus proposé.',
            ], 422);
        }

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

        if (! $slot->isBookableOn($date)) {
            return response()->json([
                'success' => false,
                'error' => 'slot_too_soon',
                'message' => sprintf(
                    'Il faut au moins %d minutes entre la commande et le début du créneau. Choisissez un créneau plus tard.',
                    DeliverySlot::LEAD_TIME_MINUTES
                ),
            ], 422);
        }

        if ($slot->bookedCountOn($date) >= $slot->max_orders_per_slot) {
            return response()->json([
                'success' => false,
                'error' => 'slot_full',
                'message' => 'Ce créneau est complet pour cette date.',
            ], 422);
        }

        return null;
    }

    /**
     * Lister les commandes du client
     */
    public function index(Request $request)
    {
        $query = Order::byClient($request->user()->id);

        // Filtrer par statut
        if ($request->has('status')) {
            $statuses = explode(',', $request->status);
            $query->whereIn('status', $statuses);
        }

        $orders = $query
            // items.product : sans lui, la liste ne peut pas afficher les noms
            // d'articles sans declencher une requete par ligne.
            ->with('items.product:id,name,price_type,image_url', 'client', 'deliveryAddress', 'delivery')
            ->latest()
            ->paginate($this->perPage($request, 10));

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    /**
     * Le client declare avoir paye, et donne la reference recue par SMS.
     *
     * Cela ne valide RIEN : c'est une affirmation, pas une preuve. Un
     * administrateur verifie ensuite sur le compte marchand. Les deux valeurs
     * vivent dans des colonnes distinctes pour qu'on ne puisse jamais prendre
     * l'une pour l'autre.
     */
    public function declarePayment(Request $request, $id)
    {
        $data = $request->validate([
            'reference' => 'required|string|max:120',
            'payment_provider' => 'nullable|in:mtn,moov,celtiis',
            'payer_phone' => 'nullable|string|max:30',
        ], [
            'reference.required' => 'Saisissez la référence reçue par SMS après votre envoi.',
        ]);

        $order = Order::with('payment')->find($id);

        if (! $order || $order->client_id !== $request->user()->id) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        if (! $order->payment) {
            return response()->json(['success' => false, 'error' => 'no_payment'], 404);
        }

        if ($order->payment->status === PaymentStatus::COMPLETED) {
            return response()->json([
                'success' => false,
                'error' => 'already_validated',
                'message' => 'Ce paiement a déjà été validé. Rien à déclarer.',
            ], 422);
        }

        $order->payment->update(array_filter([
            'payer_declared_reference' => $data['reference'],
            'payer_declared_at' => now(),
            'payment_provider' => $data['payment_provider'] ?? $order->payment->payment_provider,
            'phone_number' => $data['payer_phone'] ?? $order->payment->phone_number,
        ]));

        OrderEvent::record(
            $order,
            'payment.declared',
            reason: 'Référence déclarée par le client',
            context: ['reference' => $data['reference']]
        );

        return response()->json([
            'success' => true,
            'data' => $order->payment->fresh(),
            'message' => 'Merci. Nous vérifions votre paiement et validons la commande.',
        ]);
    }

    /**
     * Recommander : recharge le panier avec les articles d'une commande passee.
     *
     * Les prix ne sont pas repris de la commande — ils viennent du catalogue du
     * jour. Un prix d'il y a un mois n'engage personne, surtout sur un marche.
     */
    public function reorder(Request $request, $id)
    {
        $order = Order::with('items.product')->find($id);

        if (! $order || $order->client_id !== $request->user()->id) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $skipped = [];

        DB::transaction(function () use ($order, $request, &$skipped) {
            foreach ($order->items as $item) {
                // Un article indisponible est signale, pas ignore : le client
                // doit savoir ce qui manque avant de commander.
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
            'data' => CartItem::with('product:id,name,base_price,price_type,image_url,thumbnail_url,is_available,category_id')
                ->where('user_id', $request->user()->id)
                ->latest()
                ->get(),
            'meta' => ['skipped' => $skipped],
        ]);
    }

    /**
     * Détail commande
     */
    public function show($id, Request $request)
    {
        // Le livreur est restreint a son identite visible : seul 'password' est
        // masque sur le modele User, charger la relation entiere exposerait son
        // e-mail et son numero personnel au client.
        $order = Order::with(
            'items.product',
            'client',
            'deliveryAddress',
            'deliverySlot',
            'delivery.livreur:id,name,avatar_url',
            'payment'
        )
            ->find($id);

        if (!$order || ($order->client_id !== $request->user()->id && !$request->user()->isAdmin())) {
            return response()->json([
                'success' => false,
                'error' => 'not_found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * Annuler commande
     */
    public function cancel($id, Request $request)
    {
        $order = Order::find($id);

        if (!$order || $order->client_id !== $request->user()->id) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        try {
            $this->orderService->cancelOrder($order, $request->get('reason'));

            return response()->json([
                'success' => true,
                'data' => $order,
                'message' => 'Commande annulée',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Mettre à jour statut (admin)
     */
    public function updateStatus($id, Request $request)
    {
        // Vérifier permission admin
        if (!$request->user()->isAdmin()) {
            return response()->json(['success' => false, 'error' => 'forbidden'], 403);
        }

        $order = Order::find($id);

        if (!$order) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        // Un statut hors enum faisait planter la relecture du modele (500).
        $validated = $request->validate([
            'status' => ['required', \Illuminate\Validation\Rule::enum(OrderStatus::class)],
        ]);

        $order->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }
}
