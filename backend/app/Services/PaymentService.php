<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(private NotificationService $notificationService)
    {}

    /**
     * Créer un paiement en attente de validation manuelle (admin)
     */
    public function createManualPayment(Order $order, ?string $provider = null, ?string $payerPhone = null): Payment
    {
        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $order->client_id,
            'amount' => $order->total,
            'currency' => 'XOF',
            'payment_method' => 'manual',
            'status' => PaymentStatus::PENDING,
            // Operateur choisi et numero payeur : l'administrateur sait ou
            // chercher la transaction au lieu de fouiller trois comptes.
            'payment_provider' => $provider,
            'phone_number' => $payerPhone,
        ]);

        Log::info("Manual payment created for order {$order->order_number}, awaiting admin validation");

        return $payment;
    }

    /**
     * Confirmer un paiement
     */
    public function confirmPayment(
        Payment $payment,
        $transactionId = null,
        ?string $reference = null,
        ?\App\Models\User $validator = null,
        array $extra = []
    ): Payment {
        $payment->update(array_merge([
            'status' => PaymentStatus::COMPLETED,
            'transaction_id' => $transactionId ?? $payment->transaction_id,
            'completed_at' => now(),
            // Trace de la validation manuelle : qui, quand, sur quelle preuve.
            'reference_number' => $reference ?? $payment->reference_number,
            'validated_by' => $validator?->id ?? $payment->validated_by,
            'validated_at' => $validator ? now() : $payment->validated_at,
        ], $extra));

        // Mettre à jour le statut de la commande
        $payment->order->update([
            'payment_status' => 'completed',
        ]);

        // Envoyer notification
        $this->notificationService->sendPaymentConfirmedNotification($payment->order);

        Log::info("Payment confirmed for order {$payment->order->order_number}");

        return $payment;
    }

    /**
     * Échec du paiement
     */
    public function failPayment(Payment $payment, $reason = null): Payment
    {
        $payment->update([
            'status' => PaymentStatus::FAILED,
            'error_message' => $reason,
        ]);

        Log::warning("Payment failed for order {$payment->order->order_number}: {$reason}");

        return $payment;
    }

    /**
     * Rembourser un paiement
     */
    public function refundPayment(Payment $payment): Payment
    {
        if ($payment->status !== PaymentStatus::COMPLETED) {
            throw new \Exception("Seuls les paiements complétés peuvent être remboursés");
        }

        $payment->update([
            'status' => PaymentStatus::REFUNDED,
        ]);

        // TODO: Appel API Mobile Money pour remboursement
        // $this->callMobileMoneyRefundAPI($payment);

        Log::info("Payment refunded for order {$payment->order->order_number}");

        return $payment;
    }
}
