<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'amount',
        'currency',
        'payment_method',
        'payment_provider',
        'status',
        'transaction_id',
        'reference_number',
        'phone_number',
        'mobile_money_transaction_id',
        'error_message',
        'completed_at',
        'payer_declared_reference',
        'payer_declared_at',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => PaymentStatus::class,
        'completed_at' => 'datetime',
        'validated_at' => 'datetime',
        'payer_declared_at' => 'datetime',
    ];

    /** Qui a validé ce paiement manuellement. */
    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByStatus($query, PaymentStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', PaymentStatus::COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', PaymentStatus::FAILED);
    }

    // Helpers
    public function markAsCompleted($transactionId = null)
    {
        $this->update([
            'status' => PaymentStatus::COMPLETED,
            'transaction_id' => $transactionId ?? $this->transaction_id,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed($errorMessage = null)
    {
        $this->update([
            'status' => PaymentStatus::FAILED,
            'error_message' => $errorMessage,
        ]);
    }
}
