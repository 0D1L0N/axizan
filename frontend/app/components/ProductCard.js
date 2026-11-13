'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useCart } from '@/app/contexts/CartContext';
import { useToast } from '@/app/contexts/ToastContext';
import { Plus, ShoppingBasket } from 'lucide-react';
import { unitLabel, minQuantity, formatPrice } from '@/app/lib/units';
import { productImage } from '@/app/lib/images';
import ProductImage from '@/app/components/ProductImage';

export default function ProductCard({ product }) {
  const { addItem } = useCart();
  const toast = useToast();
  const router = useRouter();
  const price = Number(product.base_price) || 0;
  const category = product.category;

  const handleAddToCart = (e) => {
    e.preventDefault();
    addItem({
      product_id: product.id,
      type: 'product',
      name: product.name,
      price,
      image: productImage(product, 'thumb'),
      quantity: minQuantity(product.price_type),
      price_type: product.price_type,
    });

    toast.success(`${product.name} ajouté au panier`, {
      action: { label: 'Voir le panier', onClick: () => router.push('/cart') },
    });
  };

  return (
    <article className="group flex flex-col overflow-hidden rounded-panel border border-ink-900/8 bg-white shadow-card card-lift">
      <Link href={`/products/${product.id}`} className="block">
        <div className="relative h-52 overflow-hidden bg-cream-200">
          <ProductImage
            product={product}
            size="thumb"
            className="h-full w-full object-cover transition duration-700 group-hover:scale-105"
          />
          <div className="absolute left-4 top-4 rounded-full bg-cream/90 px-3 py-1 text-xs font-bold text-forest-800 shadow-card backdrop-blur">
            {category?.icon_emoji || '🛒'} {category?.name || 'Marché'}
          </div>
          {product.is_bestseller && (
            <div className="absolute right-4 top-4 rounded-full bg-clay-500 px-3 py-1 text-xs font-bold text-white shadow-card">
              Populaire
            </div>
          )}
        </div>
      </Link>

      {/* Le texte sort de l'image : sur une photo claire, la superposition rendait
          le nom illisible et forcait une ombre portee sur tout. */}
      <div className="flex flex-1 flex-col p-5">
        <Link href={`/products/${product.id}`}>
          <h3 className="font-display text-2xl font-semibold leading-tight text-forest-800 transition group-hover:text-forest-600">
            {product.name}
          </h3>
        </Link>
        <p className="mt-2 line-clamp-2 text-sm leading-6 text-ink-500">{product.description}</p>

        <div className="mt-5 flex items-end justify-between gap-4 border-t border-ink-900/8 pt-5">
          <div>
            <p className="font-display text-3xl font-semibold text-forest-800">{formatPrice(price)}</p>
            <p className="eyebrow mt-1 text-ink-300">{unitLabel(product.price_type)}</p>
          </div>
          <button
            onClick={handleAddToCart}
            disabled={!product.is_available}
            className="inline-flex h-12 items-center gap-2 rounded-full bg-forest-800 px-5 text-sm font-bold text-cream transition hover:bg-forest-700 disabled:cursor-not-allowed disabled:bg-ink-300"
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
