<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';           // En attente
    case ACCEPTED = 'accepted';         // Acceptée
    case PREPARING = 'preparing';       // En préparation
    case READY = 'ready';              // Prête
    case IN_DELIVERY = 'in_delivery';  // En livraison
    case DELIVERED = 'delivered';      // Livrée
    case COMPLETED = 'completed';      // Complétée
    case CANCELLED = 'cancelled';      // Annulée
    case RETURNED = 'returned';        // Retournée

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::ACCEPTED => 'Acceptée',
            self::PREPARING => 'En préparation',
            self::READY => 'Prête',
            self::IN_DELIVERY => 'En cours de livraison',
            self::DELIVERED => 'Livrée',
            self::COMPLETED => 'Complétée',
            self::CANCELLED => 'Annulée',
            self::RETURNED => 'Retournée',
        };
    }

    public function getEmoji(): string
    {
        return match ($this) {
            self::PENDING => '⏳',
            self::ACCEPTED => '✅',
            self::PREPARING => '👨‍🍳',
            self::READY => '📦',
            self::IN_DELIVERY => '🚚',
            self::DELIVERED => '🏠',
            self::COMPLETED => '✨',
            self::CANCELLED => '❌',
            self::RETURNED => '↩️',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED, self::RETURNED], true);
    }
}
