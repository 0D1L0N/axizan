<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\DeliverySlot;
use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * Ce qui ne bouge pas.
 *
 * Une liste de commandes dit ce qui existe ; elle ne dit pas ce qui coince.
 * Or c'est la seule chose qu'un administrateur doit voir sans la chercher :
 * une commande payee que personne n'a prise, un panier qui vieillit au depot,
 * un retrait entame puis abandonne.
 *
 * Le signal le plus juste n'est pas une duree fixe mais le TEMPS QUI RESTE
 * AVANT LE CRENEAU. Une commande acceptee depuis deux heures pour une
 * livraison demain ne pose aucun probleme ; la meme, pour un creneau qui
 * commence dans quarante minutes, en pose un grave. Un seuil en heures aurait
 * alerte sur la premiere et laisse passer la seconde.
 */
class OrderWatchService
{
    /** En deca, le creneau est trop proche pour l'etape en cours. */
    private const TIGHT_MINUTES = 60;

    /** Marge au-dela de laquelle la commande est deja perdue pour son creneau. */
    private const LATE_MINUTES = 0;

    /** Un retrait entame et jamais fini : le preparateur a decroche. */
    private const PREPARING_STALE_MINUTES = 180;

    /**
     * Le motif d'alerte d'une commande, ou null si tout va bien.
     *
     * @return array{level: string, reason: string}|null
     */
    public function attention(Order $order, ?Carbon $now = null): ?array
    {
        $now ??= now();

        if ($order->status->isTerminal() || $order->status === OrderStatus::DELIVERED) {
            return null;
        }

        // Rien n'est attendu de personne tant que le paiement n'est pas la :
        // c'est le client qu'on attend, pas l'equipe.
        if ($order->payment_status !== 'completed') {
            return $this->unpaidAttention($order, $now);
        }

        $minutes = $this->minutesToSlot($order, $now);

        return match (true) {
            $order->status === OrderStatus::PENDING && $order->preparateur_id === null
                => $this->pending($minutes),

            $order->status === OrderStatus::ACCEPTED
                => $this->accepted($order, $minutes, $now),

            $order->status === OrderStatus::PREPARING
                => $this->preparing($order, $minutes, $now),

            $order->status === OrderStatus::READY
                => $this->ready($order, $minutes, $now),

            $order->status === OrderStatus::IN_DELIVERY
                => $this->inDelivery($minutes),

            default => null,
        };
    }

    /**
     * Minutes restantes avant le debut du creneau, ou null si on ne sait pas.
     *
     * Negatif : le creneau a commence, la commande est en retard.
     */
    public function minutesToSlot(Order $order, ?Carbon $now = null): ?int
    {
        $now ??= now();
        $slot = $order->deliverySlot;

        if (! $slot || ! $order->delivery_date) {
            return null;
        }

        return (int) round($now->diffInMinutes($slot->startsOn($order->delivery_date), false));
    }

    private function unpaidAttention(Order $order, Carbon $now): ?array
    {
        $minutes = $this->minutesToSlot($order, $now);

        // Un creneau depasse sans paiement : la commande ne partira jamais et
        // encombre la file. Elle appelle une relance, ou une annulation.
        if ($minutes !== null && $minutes < self::LATE_MINUTES) {
            return [
                'level' => 'warning',
                'reason' => 'Créneau dépassé, toujours pas payée — à relancer ou annuler.',
            ];
        }

        return null;
    }

    private function pending(?int $minutes): ?array
    {
        if ($minutes === null) {
            return null;
        }

        if ($minutes < self::LATE_MINUTES) {
            return ['level' => 'urgent', 'reason' => 'Payée, créneau dépassé, aucun préparateur.'];
        }

        if ($minutes < self::TIGHT_MINUTES) {
            return [
                'level' => 'urgent',
                'reason' => sprintf('Payée depuis un moment, personne ne l’a prise — créneau dans %d min.', $minutes),
            ];
        }

        return null;
    }

    private function accepted(Order $order, ?int $minutes, Carbon $now): ?array
    {
        // Prise mais jamais demarree : le preparateur l'a reservee et n'est
        // pas parti. La commande a l'air suivie alors qu'elle dort.
        $waiting = $order->accepted_at
            ? (int) round($order->accepted_at->diffInMinutes($now, false))
            : 0;

        if ($minutes !== null && $minutes < self::TIGHT_MINUTES) {
            return [
                'level' => 'urgent',
                'reason' => sprintf('Acceptée mais le retrait n’a pas commencé — créneau dans %d min.', $minutes),
            ];
        }

        if ($waiting > self::PREPARING_STALE_MINUTES) {
            return [
                'level' => 'warning',
                'reason' => sprintf('Acceptée il y a %d h, retrait jamais commencé.', intdiv($waiting, 60)),
            ];
        }

        return null;
    }

    private function preparing(Order $order, ?int $minutes, Carbon $now): ?array
    {
        $since = $order->preparing_started_at
            ? (int) round($order->preparing_started_at->diffInMinutes($now, false))
            : 0;

        if ($since > self::PREPARING_STALE_MINUTES) {
            return [
                'level' => 'urgent',
                'reason' => sprintf('Retrait en cours depuis %d h, jamais déposé au dépôt.', intdiv($since, 60)),
            ];
        }

        if ($minutes !== null && $minutes < 30) {
            return [
                'level' => 'warning',
                'reason' => sprintf('Encore au marché, créneau dans %d min.', max($minutes, 0)),
            ];
        }

        return null;
    }

    private function ready(Order $order, ?int $minutes, Carbon $now): ?array
    {
        // Le panier est au depot et personne ne vient le chercher. C'est de
        // la marchandise fraiche qui attend.
        $waiting = $order->ready_at
            ? (int) round($order->ready_at->diffInMinutes($now, false))
            : 0;

        if ($minutes !== null && $minutes < 30) {
            return [
                'level' => 'urgent',
                'reason' => sprintf('Au dépôt, aucun livreur — créneau dans %d min.', max($minutes, 0)),
            ];
        }

        if ($waiting > 45) {
            return [
                'level' => 'warning',
                'reason' => sprintf('Au dépôt depuis %d min, aucun livreur ne l’a prise.', $waiting),
            ];
        }

        return null;
    }

    private function inDelivery(?int $minutes): ?array
    {
        if ($minutes !== null && $minutes < -30) {
            return [
                'level' => 'warning',
                'reason' => sprintf('En route, créneau dépassé de %d min.', abs($minutes)),
            ];
        }

        return null;
    }

    /** Horizon de surveillance : ce qui est en cours, quel que soit le jour. */
    public function liveStatuses(): array
    {
        return [
            OrderStatus::PENDING,
            OrderStatus::ACCEPTED,
            OrderStatus::PREPARING,
            OrderStatus::READY,
            OrderStatus::IN_DELIVERY,
        ];
    }

    /** Reservable ou non : sert a savoir si un creneau est encore tenable. */
    public function slotLabel(Order $order): ?string
    {
        return $order->deliverySlot?->display_label ?? $order->delivery_slot_time;
    }

    /** Rappel de la fenetre de marche, pour l'affichage. */
    public function marketHours(): array
    {
        return ['opens' => DeliverySlot::MARKET_OPENS_AT, 'closes' => DeliverySlot::MARKET_CLOSES_AT];
    }
}
