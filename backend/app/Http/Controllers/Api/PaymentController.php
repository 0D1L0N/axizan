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
            ->paginate($this->perPage($request, 20));

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
