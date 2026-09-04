<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Money;

/**
 * Facture d'une commande livree.
 *
 * Deux partis pris expliquent la forme de ce service.
 *
 * 1. La facture est CALCULEE, jamais stockee. Les montants vivent deja sur la
 *    commande (subtotal, delivery_fee, tax, total) et sur ses lignes ; les
 *    recopier ailleurs creerait une seconde verite qui pourrait diverger. Ce
 *    qui doit rester stable — le numero — se derive du numero de commande,
 *    lui-meme immuable.
 *
 * 2. Elle dit ce qui a ete LIVRE, pas ce qui a ete commande. Un article
 *    indisponible que le preparateur a retire a bien ete paye par le client :
 *    le passer sous silence facturerait une marchandise jamais remise. Il
 *    apparait donc explicitement, avec son montant a rembourser.
 */
class InvoiceService
{
    /**
     * Une facture n'existe qu'une fois la marchandise remise.
     *
     * Avant, il n'y a rien a facturer — seulement une commande en cours, dont
     * le contenu peut encore changer chez le fournisseur.
     */
    public function isAvailableFor(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::DELIVERED, OrderStatus::COMPLETED], true);
    }

    /**
     * Numero de facture, derive du numero de commande.
     *
     * AXZ-20260904-00012 devient FA-20260904-00012. Stable, unique, et lisible
     * des deux cotes : au telephone, le client donne l'un, l'equipe retrouve
     * l'autre sans table de correspondance.
     */
    public function numberFor(Order $order): string
    {
        return 'FA-'.preg_replace('/^AXZ-/', '', $order->order_number);
    }

    public function build(Order $order): array
    {
        $order->loadMissing(
            'client:id,name,email,phone',
            'items.product:id,name,price_type',
            'items.replacementProduct:id,name,price_type',
            'deliveryAddress',
            'delivery',
            'payment'
        );

        $lines = $order->items->map(fn (OrderItem $item) => $this->line($item))->values();

        return [
            'number' => $this->numberFor($order),
            'issued_at' => ($order->delivered_at ?? $order->completed_at ?? now())->toIso8601String(),
            'order_number' => $order->order_number,

            'seller' => [
                'name' => config('payments.merchant_name', 'Axizán'),
                'tagline' => 'Le marché, choisi pour vous',
                'city' => 'Cotonou, Bénin',
                'phone' => config('payments.support_phone'),
                'email' => config('payments.support_email'),
            ],

            'buyer' => [
                'name' => $order->client?->name,
                'phone' => $order->client?->phone,
                'email' => $order->client?->email,
                'address' => array_filter([
                    $order->deliveryAddress?->quartier,
                    $order->deliveryAddress?->landmark,
                    $order->deliveryAddress?->city,
                ]),
            ],

            'lines' => $lines,

            'totals' => [
                'subtotal' => Money::round((float) $order->subtotal),
                'delivery_fee' => Money::round((float) $order->delivery_fee),
                'tax' => Money::round((float) $order->tax),
                'total' => Money::round((float) $order->total),
                'currency' => 'XOF',
                // Ce qui a ete paye mais pas remis. Zero dans le cas nominal ;
                // non nul, c'est une somme que l'equipe doit au client.
                'undelivered' => Money::round(
                    $order->items
                        ->where('collection_status', 'removed')
                        ->sum(fn (OrderItem $item) => (float) $item->total_price)
                ),
            ],

            'payment' => [
                'status' => $order->payment_status,
                'method' => $order->payment?->payment_method ?? $order->payment_method,
                'provider' => $order->payment?->payment_provider,
                // La reference constatee par Axizan, jamais celle declaree par
                // le client : une facture ne reprend pas une affirmation.
                'reference' => $order->payment?->reference_number,
                'paid_at' => $order->payment?->completed_at?->toIso8601String(),
            ],

            'delivery' => [
                'delivered_at' => $order->delivered_at?->toIso8601String(),
                'slot' => $order->delivery_slot_time,
                'proof_photo_url' => $order->delivery?->proof_photo_url,
                'prep_photo_url' => $order->prep_photo_url,
            ],
        ];
    }

    /**
     * Une ligne de facture.
     *
     * `status` porte l'information que le montant seul ne dit pas : ce produit
     * a-t-il ete remis tel quel, remplace par un autre, ou pas remis du tout ?
     */
    private function line(OrderItem $item): array
    {
        $status = $item->collection_status ?: 'collected';
        $replaced = $status === 'replaced' && $item->replacementProduct;

        return [
            'name' => $item->product?->name ?? 'Article retiré du catalogue',
            'delivered_as' => $replaced ? $item->replacementProduct->name : null,
            'status' => $status,
            'quantity' => (float) $item->quantity,
            'price_type' => $item->product?->price_type,
            'unit_price' => Money::round((float) $item->unit_price),
            // Une ligne retiree n'est pas facturee a zero : elle est facturee,
            // et signalee comme due. Mettre zero effacerait la dette.
            'total' => Money::round((float) $item->total_price),
            'billed' => $status !== 'removed',
        ];
    }
}
