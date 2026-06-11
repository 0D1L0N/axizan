<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case ASSIGNED = 'assigned';
    case PICKED_UP = 'picked_up';
    case IN_TRANSIT = 'in_transit';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';

    public static function getLabel(DeliveryStatus $status): string
    {
        return match ($status) {
            self::ASSIGNED => 'Assignée',
            self::PICKED_UP => 'Récupérée',
            self::IN_TRANSIT => 'En transit',
            self::DELIVERED => 'Livrée',
            self::FAILED => 'Échouée',
        };
    }
}
