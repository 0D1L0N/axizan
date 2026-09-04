'use client';

import Link from 'next/link';
import { ORDER_STEPS } from '@/app/components/OrderStatusTimeline';
import { formatPrice } from '@/app/lib/units';
import { ChevronRight } from 'lucide-react';

const TERMINAL = {
  cancelled: { emoji: '❌', label: 'Annulée', tone: 'bg-red-100 text-red-800' },
  returned: { emoji: '↩️', label: 'Retournée', tone: 'bg-orange-100 text-orange-800' },
};

export default function OrderCard({ order }) {
  const terminal = TERMINAL[order.status];
  const stepIndex = ORDER_STEPS.findIndex((step) => step.key === order.status);
  const step = ORDER_STEPS[stepIndex];
  const progress = terminal ? 0 : ((stepIndex + 1) / ORDER_STEPS.length) * 100;

  return (
    <Link href={`/orders/${order.id}`} className="block">
      <div className="rounded-2xl bg-white p-5 shadow transition hover:shadow-lg">
        {/* Statut en premier */}
        <div className="flex items-center gap-4">
          <span className="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-[#F4F0E8] text-2xl">
            {terminal ? terminal.emoji : step?.emoji}
          </span>
          <div className="min-w-0 flex-1">
            <p className="text-xl font-black leading-tight text-[#12372A]">
              {terminal ? terminal.label : step?.label}
            </p>
            <p className="truncate text-sm font-medium text-gray-500">
              {terminal ? `Commande ${order.order_number}` : step?.hint}
            </p>
          </div>
          <ChevronRight className="shrink-0 text-gray-300" size={22} />
        </div>

        {!terminal && (
          <div className="mt-4 h-1.5 overflow-hidden rounded-full bg-gray-100">
            <div className="h-full rounded-full bg-[#FFB000] transition-all" style={{ width: `${progress}%` }} />
          </div>
        )}

        {/* Le detail vient apres */}
        <div className="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3 text-sm text-gray-600">
          <span className="font-semibold text-gray-500">{order.order_number}</span>
          <span>
            {order.items?.length ?? 0} article{(order.items?.length ?? 0) > 1 ? 's' : ''}
            {order.delivery_date && ` · livraison le ${new Date(order.delivery_date).toLocaleDateString('fr-FR')}`}
          </span>
          <span className="text-lg font-black text-primary">{formatPrice(order.total)}</span>
        </div>

        {order.payment_status === 'pending' && !terminal && (
          <p className="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800">
            En attente de validation du paiement
          </p>
        )}
      </div>
    </Link>
  );
}
