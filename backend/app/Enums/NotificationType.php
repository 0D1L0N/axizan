<?php

namespace App\Enums;

enum NotificationType: string
{
    case ORDER_ACCEPTED = 'order_accepted';
    case ORDER_PREPARING = 'order_preparing';
    case ORDER_READY = 'order_ready';
    case ORDER_OUT_FOR_DELIVERY = 'order_out_for_delivery';
    case ORDER_DELIVERED = 'order_delivered';
    case PAYMENT_CONFIRMED = 'payment_confirmed';
    case PROMO_AVAILABLE = 'promo_available';
    case NEW_PRODUCT = 'new_product';
    case DELIVERY_ASSIGNED = 'delivery_assigned';
    case REVIEW_REQUEST = 'review_request';
    case ADMIN_ALERT = 'admin_alert';
    case SYSTEM_NOTIFICATION = 'system_notification';

    public static function getLabel(NotificationType $type): string
    {
        return match ($type) {
            self::ORDER_ACCEPTED => 'Commande acceptée',
            self::ORDER_PREPARING => 'Commande en préparation',
            self::ORDER_READY => 'Commande prête',
            self::ORDER_OUT_FOR_DELIVERY => 'Commande en cours de livraison',
            self::ORDER_DELIVERED => 'Commande livrée',
            self::PAYMENT_CONFIRMED => 'Paiement confirmé',
            self::PROMO_AVAILABLE => 'Nouvelle promotion disponible',
            self::NEW_PRODUCT => 'Nouveau produit',
            self::DELIVERY_ASSIGNED => 'Livraison assignée',
            self::REVIEW_REQUEST => 'Demande d\'avis',
            self::ADMIN_ALERT => 'Alerte administrative',
            self::SYSTEM_NOTIFICATION => 'Notification système',
        };
    }
}
