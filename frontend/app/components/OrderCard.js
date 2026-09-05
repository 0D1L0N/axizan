'use client';

import Link from 'next/link';
import { ORDER_STEPS } from '@/app/components/OrderStatusTimeline';
import { formatPrice } from '@/app/lib/units';
import { ChevronRight, CreditCard } from 'lucide-react';

const TERMINAL = {
  cancelled: { emoji: '❌', label: 'Annulée' },
  returned: { emoji: '↩️', label: 'Retournée' },
};

export default function OrderCard({ order }) {
  const terminal = TERMINAL[order.status];
  const stepIndex = ORDER_STEPS.findIndex((step) => step.key === order.status);
  const step = ORDER_STEPS[stepIndex];
  const progress = terminal ? 0 : ((stepIndex + 1) / ORDER_STEPS.length) * 100;
  const itemCount = order.items?.length ?? 0;

  return (
    <Link href={`/orders/${order.id}`} className="block">
      <div className="card-lift rounded-card border border-cream-300 bg-white p-5 shadow-card">
        {/* Le statut d'abord : c'est la seule question qu'on se pose ici. */}
        <div className="flex items-center gap-4">
          <span className="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-cream-100 text-2xl">
            {terminal ? terminal.emoji : step?.emoji}
          </span>
          <div className="min-w-0 flex-1">
            <p className="font-display text-xl font-semibold leading-tight text-forest-800">
              {terminal ? terminal.label : step?.label}
            </p>
            <p className="mt-0.5 truncate text-sm text-ink-500">
              {terminal ? `Commande ${order.order_number}` : step?.hint}
            </p>
          </div>
          <ChevronRight className="shrink-0 text-ink-300" size={22} />
        </div>

        {!terminal && (
          <div className="mt-4 h-1.5 overflow-hidden rounded-full bg-cream-200">
            <div className="h-full rounded-full bg-gold-500 transition-all" style={{ width: `${progress}%` }} />
          </div>
        )}

        <div className="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-cream-200 pt-3 text-sm text-ink-500">
          <span className="font-mono font-semibold">{order.order_number}</span>
          <span>
            {itemCount} article{itemCount > 1 ? 's' : ''}
            {order.delivery_date &&
              ` · livraison le ${new Date(order.delivery_date).toLocaleDateString('fr-FR')}`}
          </span>
          <span className="font-display text-lg font-semibold tabular-nums text-forest-800">
            {formatPrice(order.total)}
          </span>
        </div>

        {/*
          Un appel à l'action, pas un constat. Le client doit comprendre que
          rien ne bougera tant qu'il n'aura pas payé : « en attente de
          validation » le laissait attendre quelqu'un d'autre.
        */}
        {order.payment_status === 'pending' && !terminal && (
          <p className="mt-3 flex items-center gap-2 rounded-xl bg-gold-500 px-3 py-2 text-sm font-bold text-forest-950">
            <CreditCard size={15} /> À payer — vos courses n’ont pas encore démarré
          </p>
        )}
      </div>
    </Link>
  );
}
