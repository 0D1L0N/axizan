'use client';

import { useCart } from '@/app/contexts/CartContext';
import { Plus, ShoppingBasket, Sparkles } from 'lucide-react';
import { getCategoryById } from '@/app/data/beninCatalog';

export default function ProductCard({ product }) {
  const { addItem } = useCart();
  const category = getCategoryById(product.category_key);
  const price = Number(product.base_price) || 0;

  const handleAddToCart = () => {
    addItem({
      product_id: product.id,
      type: 'product',
      name: product.name,
      price,
      image: product.image_url,
      quantity: 1,
      price_type: product.price_type,
    });
  };

  return (
    <article className="group overflow-hidden rounded-[28px] border border-black/5 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-2xl">
      <div className="relative h-56 overflow-hidden bg-gray-200">
        <img
          src={product.image_url || 'https://via.placeholder.com/300x200'}
          alt={product.name}
          className="h-full w-full object-cover transition duration-700 group-hover:scale-110"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-black/55 via-black/5 to-transparent" />
        <div className="absolute left-4 top-4 rounded-full bg-white/92 px-3 py-1 text-xs font-black text-[#12372A] shadow-sm">
          {category?.emoji || '🛒'} {category?.name || product.category?.name || 'Marché'}
        </div>
        {product.is_bestseller && (
          <div className="absolute right-4 top-4 inline-flex items-center gap-1 rounded-full bg-[#FFB000] px-3 py-1 text-xs font-black text-[#1C140D] shadow-sm">
            <Sparkles size={13} /> Populaire
          </div>
        )}
        <div className="absolute bottom-4 left-4 right-4">
          <h3 className="text-2xl font-black leading-tight text-white drop-shadow">{product.name}</h3>
          <p className="mt-1 line-clamp-2 text-sm font-medium text-white/90">{product.description}</p>
        </div>
      </div>

      <div className="p-5">
        <div className="flex items-end justify-between gap-4">
          <div>
            <p className="text-3xl font-black tracking-tight text-[#12372A]">
              {price.toLocaleString('fr-FR')} XOF
            </p>
            <p className="mt-1 text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">
              {product.price_type === 'kg'
                ? 'par kg'
                : product.price_type === 'bag'
                  ? 'par sac'
                  : product.price_type === 'bottle'
                    ? 'par bouteille'
                    : product.price_type === 'cup'
                      ? 'par mesure'
                      : 'par pièce'}
            </p>
          </div>
          <button
            onClick={handleAddToCart}
            disabled={!product.is_available}
            className="inline-flex h-12 items-center gap-2 rounded-full bg-[#12372A] px-4 text-sm font-black text-white shadow-sm transition hover:bg-[#0f2f24] disabled:cursor-not-allowed disabled:bg-gray-400"
          >
            {product.is_available ? (
              <>
                <Plus size={17} /> Ajouter
              </>
            ) : (
              <>
                <ShoppingBasket size={17} /> Indispo
              </>
            )}
          </button>
        </div>
      </div>
    </article>
  );
}
