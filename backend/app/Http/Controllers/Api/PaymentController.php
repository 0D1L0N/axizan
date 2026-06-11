<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $paymentService)
    {}

    /**
     * Initier paiement Mobile Money
     */
    public function initiate(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'phone_number' => 'required|string|regex:/^\+?[0-9]{10,}$/',
        ]);

        $user = $request->user();
        $order = $user->orders()->find($validated['order_id']);

        if (!$order) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        try {
            $payment = $this->paymentService->initiateMobileMoneyPayment(
                $order,
                $validated['phone_number']
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_id' => $payment->id,
                    'status' => $payment->status,
                    'message' => 'Veuillez confirmer le paiement sur votre téléphone',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Vérifier statut paiement
     */
    public function checkStatus($paymentId, Request $request)
    {
        $payment = Payment::find($paymentId);

        if (!$payment || $payment->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'completed_at' => $payment->completed_at,
            ],
        ]);
    }

    /**
     * Historique paiements
     */
    public function history(Request $request)
    {
        $payments = $request->user()
            ->payments()
            ->with('order')
            ->latest()
            ->paginate($request->get('limit', 20));

        return response()->json([
            'success' => true,
            'data' => $payments->items(),
            'pagination' => [
                'total' => $payments->total(),
                'per_page' => $payments->perPage(),
                'current_page' => $payments->currentPage(),
            ],
        ]);
    }
}
