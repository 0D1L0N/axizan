'use client';

import { useCart } from '@/app/contexts/CartContext';
import { quantityStep, unitShort, formatQuantity, formatPrice } from '@/app/lib/units';
import { PLACEHOLDER_IMAGE } from '@/app/lib/images';
import RemoteImage from '@/app/components/RemoteImage';
import { Minus, Plus, Trash2 } from 'lucide-react';

export default function CartItem({ item }) {
  const { updateQuantity, removeItem } = useCart();
  const step = quantityStep(item.price_type);

  return (
    <div className="flex flex-wrap items-center gap-4 rounded-card border border-cream-300 bg-white p-4">
      <div className="relative h-20 w-20 shrink-0 overflow-hidden rounded-2xl bg-cream-100">
        <RemoteImage
          src={item.image || PLACEHOLDER_IMAGE}
          alt={item.name}
          fill
          sizes="80px"
          className="object-cover"
        />
      </div>

      <div className="min-w-[8rem] flex-1">
        <h3 className="font-semibold text-ink-900">{item.name}</h3>
        <p className="mt-0.5 text-sm text-ink-500">
          {formatPrice(item.price)} / {unitShort(item.price_type)}
        </p>
      </div>

      {/* Cibles larges : le panier se corrige souvent au pouce, en marchant. */}
      <div className="flex items-center rounded-full border border-cream-300 bg-cream-100">
        <button
          onClick={() => updateQuantity(item.id, Number(item.quantity) - step)}
          className="grid h-11 w-11 place-items-center rounded-full text-ink-700 transition hover:bg-cream-200"
          aria-label="Diminuer la quantité"
        >
          <Minus size={17} />
        </button>
        <span className="min-w-[4.5rem] whitespace-nowrap text-center font-semibold tabular-nums text-ink-900">
          {formatQuantity(item.quantity, item.price_type)}
        </span>
        <button
          onClick={() => updateQuantity(item.id, Number(item.quantity) + step)}
          className="grid h-11 w-11 place-items-center rounded-full text-ink-700 transition hover:bg-cream-200"
          aria-label="Augmenter la quantité"
        >
          <Plus size={17} />
        </button>
      </div>

      <div className="text-right">
        <p className="font-display text-lg font-semibold tabular-nums text-forest-800">
          {formatPrice(item.price * item.quantity)}
        </p>
        <button
          onClick={() => removeItem(item.id)}
          className="mt-0.5 inline-flex items-center gap-1 text-sm font-semibold text-clay-600 transition hover:text-clay-500"
        >
          <Trash2 size={14} /> Retirer
        </button>
      </div>
    </div>
  );
}
