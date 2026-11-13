'use client';

import { useCart } from '@/app/contexts/CartContext';
import { quantityStep, unitShort, formatQuantity, formatPrice } from '@/app/lib/units';
import { PLACEHOLDER_IMAGE } from '@/app/lib/images';
import RemoteImage from '@/app/components/RemoteImage';

export default function CartItem({ item }) {
  const { updateQuantity, removeItem } = useCart();
  const step = quantityStep(item.price_type);

  return (
    <div className="flex items-center gap-4 bg-white p-4 rounded-lg border border-gray-200">
      {/* Image */}
      <div className="relative h-20 w-20 shrink-0 overflow-hidden rounded">
        <RemoteImage
          src={item.image || PLACEHOLDER_IMAGE}
          alt={item.name}
          fill
          sizes="80px"
          className="object-cover"
        />
      </div>

      {/* Info */}
      <div className="flex-1">
        <h3 className="font-semibold">{item.name}</h3>
        <p className="text-sm text-gray-600">
          {formatPrice(item.price)} / {unitShort(item.price_type)}
        </p>
      </div>

      {/* Quantity */}
      <div className="flex flex-col items-center gap-1">
        <div className="flex items-center gap-2 border border-gray-300 rounded">
          <button
            onClick={() => updateQuantity(item.id, Number(item.quantity) - step)}
            className="px-3 py-1 hover:bg-gray-100"
            aria-label="Diminuer la quantité"
          >
            −
          </button>
          <span className="px-3 py-1 font-semibold whitespace-nowrap">
            {formatQuantity(item.quantity, item.price_type)}
          </span>
          <button
            onClick={() => updateQuantity(item.id, Number(item.quantity) + step)}
            className="px-3 py-1 hover:bg-gray-100"
            aria-label="Augmenter la quantité"
          >
            +
          </button>
        </div>
      </div>

      {/* Total */}
      <div className="text-right">
        <p className="font-semibold text-lg">{formatPrice(item.price * item.quantity)}</p>
        <button
          onClick={() => removeItem(item.id)}
          className="text-sm text-red-600 hover:text-red-800 font-semibold"
        >
          Supprimer
        </button>
      </div>
    </div>
  );
}
