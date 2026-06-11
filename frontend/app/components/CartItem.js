'use client';

import { useCart } from '@/app/contexts/CartContext';

export default function CartItem({ item }) {
  const { updateQuantity, removeItem } = useCart();

  return (
    <div className="flex items-center gap-4 bg-white p-4 rounded-lg border border-gray-200">
      {/* Image */}
      <img
        src={item.image || 'https://via.placeholder.com/80'}
        alt={item.name}
        className="w-20 h-20 object-cover rounded"
      />

      {/* Info */}
      <div className="flex-1">
        <h3 className="font-semibold">{item.name}</h3>
        <p className="text-sm text-gray-600">
          {item.price.toFixed(0)} XOF
        </p>
      </div>

      {/* Quantity */}
      <div className="flex items-center gap-2 border border-gray-300 rounded">
        <button
          onClick={() => updateQuantity(item.id, item.quantity - 1)}
          className="px-3 py-1 hover:bg-gray-100"
        >
          −
        </button>
        <span className="px-3 py-1 font-semibold">{item.quantity}</span>
        <button
          onClick={() => updateQuantity(item.id, item.quantity + 1)}
          className="px-3 py-1 hover:bg-gray-100"
        >
          +
        </button>
      </div>

      {/* Total */}
      <div className="text-right">
        <p className="font-semibold text-lg">
          {(item.price * item.quantity).toFixed(0)} XOF
        </p>
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
