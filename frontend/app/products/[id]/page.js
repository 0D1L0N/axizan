'use client';

import { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import Link from 'next/link';
import Header from '@/app/components/Header';
import Footer from '@/app/components/Footer';
import { useCart } from '@/app/contexts/CartContext';
import { productService } from '@/app/services';
import { unitLabel, quantityStep, minQuantity, normalizeQuantity, formatQuantity, formatPrice } from '@/app/lib/units';
import { productImage } from '@/app/lib/images';
import ProductImage from '@/app/components/ProductImage';
import { ArrowLeft, Minus, Plus, ShoppingBasket, Sparkles } from 'lucide-react';

export default function ProductDetailPage() {
  const { id } = useParams();
  const router = useRouter();
  const { addItem } = useCart();

  const [product, setProduct] = useState(null);
  const [quantity, setQuantity] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [added, setAdded] = useState(false);

  useEffect(() => {
    productService
      .getProduct(id)
      .then((res) => {
        const data = res.data.data;
        setProduct(data);
        setQuantity(minQuantity(data.price_type));
      })
      .catch(() => setError('Ce produit est introuvable.'))
      .finally(() => setLoading(false));
  }, [id]);

  const handleAdd = () => {
    addItem({
      product_id: product.id,
      type: 'product',
      name: product.name,
      price: Number(product.base_price),
      image: productImage(product, 'thumb'),
      quantity,
      price_type: product.price_type,
    });
    setAdded(true);
    setTimeout(() => setAdded(false), 2500);
  };

  if (loading) {
    return (
      <>
        <Header />
        <main className="mx-auto max-w-5xl px-4 py-10">
          <div className="h-96 animate-pulse rounded-[30px] bg-white" />
        </main>
      </>
    );
  }

  if (error || !product) {
    return (
      <>
        <Header />
        <main className="mx-auto max-w-5xl px-4 py-10">
          <div className="rounded-[30px] bg-white p-10 text-center shadow-sm">
            <p className="font-bold text-gray-700">{error || 'Produit introuvable'}</p>
            <Link href="/products" className="mt-4 inline-block font-black text-[#12372A] hover:underline">
              ← Retour au catalogue
            </Link>
          </div>
        </main>
      </>
    );
  }

  const price = Number(product.base_price);
  const step = quantityStep(product.price_type);

  return (
    <>
      <Header />
      <main className="min-h-screen bg-[#FBF7EF]">
        <div className="mx-auto max-w-6xl px-4 py-8">
          <button
            onClick={() => router.back()}
            className="mb-6 inline-flex items-center gap-2 font-black text-[#12372A] hover:underline"
          >
            <ArrowLeft size={18} /> Retour
          </button>

          <div className="grid gap-8 overflow-hidden rounded-[36px] border border-black/5 bg-white p-6 shadow-sm lg:grid-cols-2 lg:p-8">
            <div className="relative overflow-hidden rounded-[28px] bg-gray-100">
              <ProductImage
                product={product}
                size="full"
                emojiSize="text-7xl"
                className="h-full min-h-[320px] max-h-[460px] w-full object-cover"
              />
              {product.is_bestseller && (
                <div className="absolute right-4 top-4 inline-flex items-center gap-1 rounded-full bg-[#FFB000] px-3 py-1 text-xs font-black text-[#1C140D] shadow-sm">
                  <Sparkles size={13} /> Populaire
                </div>
              )}
            </div>

            <div className="flex flex-col">
              {product.category && (
                <p className="text-sm font-black uppercase tracking-[0.22em] text-[#D62828]">
                  {product.category.icon_emoji} {product.category.name}
                </p>
              )}
              <h1 className="mt-3 text-4xl font-black tracking-tight text-[#12372A]">{product.name}</h1>
              <p className="mt-4 leading-7 text-gray-600">{product.description}</p>

              <div className="mt-6 border-t border-gray-100 pt-6">
                <p className="text-4xl font-black text-[#12372A]">{formatPrice(price)}</p>
                <p className="mt-1 text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">
                  {unitLabel(product.price_type)}
                </p>
              </div>

              <div className="mt-6">
                <label className="mb-2 block text-sm font-black text-gray-700">
                  Quantité {product.price_type === 'kg' ? '(par pas de 0,5 kg)' : ''}
                </label>
                <div className="flex items-center gap-4">
                  <div className="flex items-center rounded-full border border-gray-300 bg-white">
                    <button
                      onClick={() => setQuantity((q) => normalizeQuantity(Math.max(minQuantity(product.price_type), q - step), product.price_type))}
                      className="grid h-12 w-12 place-items-center rounded-full hover:bg-gray-100"
                      aria-label="Diminuer"
                    >
                      <Minus size={17} />
                    </button>
                    <span className="min-w-28 text-center font-black text-[#12372A]">
                      {formatQuantity(quantity, product.price_type)}
                    </span>
                    <button
                      onClick={() => setQuantity((q) => normalizeQuantity(q + step, product.price_type))}
                      className="grid h-12 w-12 place-items-center rounded-full hover:bg-gray-100"
                      aria-label="Augmenter"
                    >
                      <Plus size={17} />
                    </button>
                  </div>
                  <p className="text-lg font-black text-gray-700">= {formatPrice(price * quantity)}</p>
                </div>
              </div>

              <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                <button
                  onClick={handleAdd}
                  disabled={!product.is_available}
                  className="inline-flex h-14 flex-1 items-center justify-center gap-2 rounded-full bg-[#12372A] px-6 font-black text-white shadow-sm transition hover:bg-[#0f2f24] disabled:cursor-not-allowed disabled:bg-gray-400"
                >
                  <ShoppingBasket size={19} />
                  {product.is_available ? `Ajouter ${formatQuantity(quantity, product.price_type)}` : 'Indisponible'}
                </button>
                <Link
                  href="/cart"
                  className="inline-flex h-14 items-center justify-center rounded-full border border-gray-300 px-6 font-black text-[#12372A] hover:bg-gray-50"
                >
                  Voir le panier
                </Link>
              </div>

              {added && (
                <p className="mt-4 rounded-2xl bg-green-50 px-4 py-3 font-semibold text-green-700">
                  Ajouté au panier ✓
                </p>
              )}

              <p className="mt-6 text-sm leading-6 text-gray-500">
                Nous achetons vos articles au marché le jour de la livraison. Si un produit est
                introuvable, vous pourrez indiquer au moment de la commande s’il faut le remplacer
                par un équivalent ou le retirer.
              </p>
            </div>
          </div>
        </div>
      </main>
      <Footer />
    </>
  );
}
