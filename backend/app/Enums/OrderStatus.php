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

    public static function getLabel(OrderStatus $status): string
    {
        return match ($status) {
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

    public static function getEmoji(OrderStatus $status): string
    {
        return match ($status) {
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

    public static function isTerminal(OrderStatus $status): bool
    {
        return in_array($status, [self::COMPLETED, self::CANCELLED, self::RETURNED]);
    }
}
