'use client';

import { useRouter } from 'next/navigation';
import { useCart } from '@/app/contexts/CartContext';
import CartItem from '@/app/components/CartItem';
import Header from '@/app/components/Header';
import Footer from '@/app/components/Footer';
import Link from 'next/link';
import { formatPrice } from '@/app/lib/units';
import { ArrowRight, ShoppingBasket, Trash2 } from 'lucide-react';

export default function CartPage() {
  const { items, subtotal, clearCart } = useCart();
  const router = useRouter();

  const count = items.length;

  return (
    <>
      <Header />
      <main className="min-h-screen bg-cream">
        <div className="mx-auto max-w-6xl px-4 py-10">
          <p className="eyebrow text-clay-500">Votre panier</p>
          <h1 className="mt-2 font-display text-display-sm text-forest-800">
            {count === 0 ? 'Rien dedans, pour l’instant' : `${count} article${count > 1 ? 's' : ''} choisi${count > 1 ? 's' : ''}`}
          </h1>

          {count === 0 ? (
            <div className="mt-8 rounded-panel border border-dashed border-cream-300 bg-white p-12 text-center">
              <ShoppingBasket size={30} className="mx-auto text-ink-300" />
              <p className="mt-4 leading-7 text-ink-500">
                Ajoutez des produits au catalogue, puis choisissez un créneau de livraison.
              </p>
              <Link
                href="/products"
                className="mt-6 inline-flex items-center gap-2 rounded-full bg-forest-800 px-6 py-3 font-bold text-cream transition hover:bg-forest-700"
              >
                Parcourir le catalogue <ArrowRight size={17} />
              </Link>
            </div>
          ) : (
            <div className="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_340px]">
              <div className="space-y-3">
                {items.map((item) => (
                  <CartItem key={item.id} item={item} />
                ))}

                <button
                  onClick={() => clearCart()}
                  className="inline-flex items-center gap-2 pt-2 text-sm font-semibold text-ink-500 transition hover:text-clay-600"
                >
                  <Trash2 size={15} /> Vider le panier
                </button>
              </div>

              <aside className="lg:sticky lg:top-6 lg:self-start">
                <div className="rounded-panel border border-cream-300 bg-white p-6 shadow-card">
                  <h2 className="font-display text-xl font-semibold text-ink-900">Récapitulatif</h2>

                  <div className="mt-5 flex items-baseline justify-between">
                    <span className="text-ink-500">Total des articles</span>
                    <span className="font-display text-2xl font-semibold tabular-nums text-forest-800">
                      {formatPrice(subtotal)}
                    </span>
                  </div>

                  {/*
                    Ni frais de livraison ni taxe ici : ils dépendent de la zone
                    de l'adresse, qu'on choisit à l'étape suivante. Annoncer un
                    total maintenant reviendrait à l'inventer.
                  */}
                  <p className="mt-3 rounded-xl bg-cream-100 px-4 py-3 text-sm leading-6 text-ink-500">
                    Les frais de livraison et la taxe s’ajoutent une fois votre adresse et votre créneau
                    choisis.
                  </p>

                  <button
                    onClick={() => router.push('/checkout')}
                    className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-full bg-gold-500 px-6 py-4 font-bold text-forest-900 shadow-glow transition hover:bg-gold-400"
                  >
                    Procéder au paiement <ArrowRight size={18} />
                  </button>

                  <Link
                    href="/products"
                    className="mt-4 block text-center text-sm font-semibold text-forest-800 hover:underline"
                  >
                    Continuer mes achats
                  </Link>
                </div>
              </aside>
            </div>
          )}
        </div>
      </main>
      <Footer />
    </>
  );
}
