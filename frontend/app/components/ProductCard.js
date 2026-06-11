'use client';

import { useCart } from '@/app/contexts/CartContext';
import Link from 'next/link';

export default function ProductCard({ product }) {
  const { addItem } = useCart();

  const handleAddToCart = () => {
    addItem({
      product_id: product.id,
      type: 'product',
      name: product.name,
      price: Number(product.base_price) || 0,
      image: product.image_url,
      quantity: 1,
      price_type: product.price_type,
    });
  };

  return (
    <div className="bg-white rounded-lg shadow hover:shadow-lg transition-shadow overflow-hidden">
      {/* Image */}
      <div className="relative h-48 bg-gray-200 overflow-hidden">
        <img
          src={product.image_url || 'https://via.placeholder.com/300x200'}
          alt={product.name}
          className="w-full h-full object-cover hover:scale-105 transition-transform"
        />
        {product.is_bestseller && (
          <div className="absolute top-2 right-2 bg-red-500 text-white px-3 py-1 rounded-full text-sm font-semibold">
            ⭐ Bestseller
          </div>
        )}
      </div>

      {/* Content */}
      <div className="p-4">
        <h3 className="font-semibold text-lg truncate">{product.name}</h3>
        <p className="text-gray-600 text-sm mt-1 line-clamp-2">{product.description}</p>

        {/* Category */}
        <div className="mt-2">
          <span className="text-xs bg-gray-100 px-2 py-1 rounded">
            {product.category?.name || 'Non catégorisé'}
          </span>
        </div>

        {/* Price */}
        <div className="mt-4 flex items-center justify-between">
          <div>
            <p className="text-2xl font-bold text-primary">
              { (Number(product.base_price) || 0).toFixed(0) } XOF
            </p>
            <p className="text-xs text-gray-500">
              {product.price_type === 'kg' ? 'par kg' : 'par pièce'}
            </p>
          </div>
          <button
            onClick={handleAddToCart}
            disabled={!product.is_available}
            className="bg-primary text-white px-4 py-2 rounded font-semibold hover:bg-red-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition"
          >
            {product.is_available ? '➕' : '❌ Indisponible'}
          </button>
        </div>
      </div>
    </div>
  );
}
