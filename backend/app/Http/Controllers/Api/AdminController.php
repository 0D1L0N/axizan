<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    public function __construct(private PaymentService $paymentService)
    {}

    /**
     * Commandes en attente de validation de paiement
     */
    public function pendingPayments(Request $request)
    {
        $orders = Order::where('payment_status', 'pending')
            ->with('client', 'payment')
            ->latest()
            ->paginate($request->get('limit', 20));

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
            ],
        ]);
    }

    /**
     * Valider manuellement le paiement d'une commande
     */
    public function validatePayment(Request $request, $orderId)
    {
        $order = Order::with('payment')->find($orderId);

        if (!$order || !$order->payment) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        if ($order->payment->status === PaymentStatus::COMPLETED) {
            return response()->json([
                'success' => false,
                'error' => 'already_validated',
                'message' => 'Ce paiement a déjà été validé.',
            ], 422);
        }

        // La reference est obligatoire : sans elle, une contestation
        // « j'ai payé » / « non » ne peut pas etre tranchee.
        $validated = $request->validate([
            'reference_number' => 'required|string|max:120',
            'payment_provider' => 'nullable|in:mtn,moov,celtiis,sandbox',
            'phone_number' => 'nullable|string|max:30',
        ], [
            'reference_number.required' => 'Indiquez la référence de la transaction : sans elle, le paiement n’est pas traçable.',
        ]);

        $payment = $this->paymentService->confirmPayment(
            $order->payment,
            reference: $validated['reference_number'],
            validator: $request->user(),
            extra: array_filter([
                'payment_provider' => $validated['payment_provider'] ?? null,
                'phone_number' => $validated['phone_number'] ?? null,
            ])
        );

        return response()->json([
            'success' => true,
            'data' => $payment->load('order', 'validator:id,name'),
            'message' => 'Paiement validé',
        ]);
    }

    /**
     * Créer un compte préparateur ou livreur
     */
    public function createStaff(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'phone' => 'required|string|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:preparateur,livreur',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'data' => $user,
            'message' => 'Compte créé',
        ], 201);
    }
}
