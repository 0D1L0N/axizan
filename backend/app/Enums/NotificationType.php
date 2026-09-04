<?php

namespace App\Enums;

/**
 * Types de notification reellement emis par l'application.
 *
 * Chaque cas correspond a un appel dans NotificationService et a une entree
 * dans config/notifications.php. Un type que personne n'emet ne fait
 * qu'entretenir l'illusion d'une fonctionnalite : les avis, promotions et
 * alertes generiques ont ete retires avec le code auquel ils se rattachaient.
 */
enum NotificationType: string
{
    case ORDER_CREATED = 'order_created';
    case ORDER_ACCEPTED = 'order_accepted';
    case ORDER_CANCELLED = 'order_cancelled';
    case ORDER_PREPARING = 'order_preparing';
    case ORDER_READY = 'order_ready';
    case ORDER_OUT_FOR_DELIVERY = 'order_out_for_delivery';
    case ORDER_DELIVERED = 'order_delivered';
    case PAYMENT_CONFIRMED = 'payment_confirmed';
    case DELIVERY_ASSIGNED = 'delivery_assigned';

    /** Un panier vient d'etre depose au depot : il attend un livreur. */
    case BASKET_AT_DEPOT = 'basket_at_depot';

    public function getLabel(): string
    {
        return match ($this) {
            self::ORDER_CREATED => 'Commande reçue',
            self::ORDER_ACCEPTED => 'Commande acceptée',
            self::ORDER_CANCELLED => 'Commande annulée',
            self::ORDER_PREPARING => 'Commande en préparation',
            self::ORDER_READY => 'Commande prête',
            self::ORDER_OUT_FOR_DELIVERY => 'Commande en cours de livraison',
            self::ORDER_DELIVERED => 'Commande livrée',
            self::PAYMENT_CONFIRMED => 'Paiement confirmé',
            self::DELIVERY_ASSIGNED => 'Livraison assignée',
            self::BASKET_AT_DEPOT => 'Panier au dépôt',
        };
    }
}
