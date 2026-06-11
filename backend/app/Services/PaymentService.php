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
     * Initier un paiement Mobile Money
     */
    public function initiateMobileMoneyPayment(Order $order, $phoneNumber): Payment
    {
        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $order->client_id,
            'amount' => $order->total,
            'currency' => 'XOF',
            'payment_method' => 'mobile_money',
            'payment_provider' => 'mtn', // ou 'moov', 'celtiis'
            'status' => PaymentStatus::PROCESSING,
            'phone_number' => $phoneNumber,
        ]);

        // TODO: Appel API Mobile Money
        // $response = $this->callMobileMoneyAPI($phoneNumber, $order->total);
        // $payment->mobile_money_transaction_id = $response['transaction_id'];
        // $payment->save();

        Log::info("Payment initiated for order {$order->order_number}");

        return $payment;
    }

    /**
     * Confirmer un paiement
     */
    public function confirmPayment(Payment $payment, $transactionId = null): Payment
    {
        $payment->update([
            'status' => PaymentStatus::COMPLETED,
            'transaction_id' => $transactionId ?? $payment->transaction_id,
            'completed_at' => now(),
        ]);

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

    /**
     * Générer facture (TODO: Implémenter avec PDF generation)
     */
    public function generateInvoice(Order $order)
    {
        // TODO: Implémenter avec Laravel PDF
    }

    /**
     * Appel API Mobile Money (TODO)
     */
    private function callMobileMoneyAPI($phoneNumber, $amount)
    {
        // TODO: Intégrer avec API Mobile Money
        // Exemples:
        // - MTN Mobile Money API
        // - Moov Money API
        // - Celtiis Cash API
        return [];
    }
}
