<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::PROCESSING => 'En cours',
            self::COMPLETED => 'Complété',
            self::FAILED => 'Échoué',
            self::CANCELLED => 'Annulé',
            self::REFUNDED => 'Remboursé',
        };
    }
}
