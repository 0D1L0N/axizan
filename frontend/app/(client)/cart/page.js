'use client';

import { useRouter } from 'next/navigation';
import { useCart } from '@/app/contexts/CartContext';
import CartItem from '@/app/components/CartItem';
import Header from '@/app/components/Header';
import Footer from '@/app/components/Footer';
import Link from 'next/link';

export default function CartPage() {
  const { items, subtotal, total, promoCode, setPromoCode, setPromoDiscount, clearCart } = useCart();
  const router = useRouter();

  const handleCheckout = () => {
    if (items.length === 0) {
      alert('Votre panier est vide');
      return;
    }

    router.push('/checkout');
  };

  return (
    <>
      <Header />
      <main className="max-w-7xl mx-auto px-4 py-8">
        <h1 className="text-3xl font-bold mb-8">🛒 Mon Panier</h1>

        {items.length === 0 ? (
          <div className="bg-gray-100 rounded-lg p-8 text-center">
            <p className="text-gray-600 mb-4">Votre panier est vide</p>
            <Link href="/" className="text-primary font-semibold hover:underline">
              ← Retour au catalogue
            </Link>
          </div>
        ) : (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {/* Items */}
            <div className="lg:col-span-2">
              <div className="space-y-4">
                {items.map((item) => (
                  <CartItem key={item.id} item={item} />
                ))}
              </div>
            </div>

            {/* Résumé */}
            <div className="lg:col-span-1">
              <div className="bg-white rounded-lg shadow p-6 sticky top-20">
                <h2 className="text-xl font-bold mb-6">📋 Résumé</h2>

                {/* Promo */}
                <div className="mb-6">
                  <label className="block text-sm font-semibold mb-2">Code Promo</label>
                  <input
                    type="text"
                    placeholder="BIENVENUE20"
                    className="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-primary"
                    value={promoCode || ''}
                    onChange={(e) => setPromoCode(e.target.value)}
                  />
                  <button className="w-full mt-2 bg-gray-600 text-white py-2 rounded hover:bg-gray-700">
                    Appliquer
                  </button>
                </div>

                {/* Calcul */}
                <div className="space-y-2 text-sm mb-6 border-t pt-4">
                  <div className="flex justify-between">
                    <span>Sous-total:</span>
                    <span className="font-semibold">{subtotal.toFixed(0)} XOF</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Taxes (10%):</span>
                    <span className="font-semibold">{(subtotal * 0.1).toFixed(0)} XOF</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Livraison:</span>
                    <span className="font-semibold">À déterminer</span>
                  </div>
                  {promoCode && (
                    <div className="flex justify-between text-green-600">
                      <span>Réduction:</span>
                      <span className="font-semibold">-500 XOF</span>
                    </div>
                  )}
                  <div className="flex justify-between text-lg font-bold border-t pt-2">
                    <span>Total:</span>
                    <span className="text-primary">{(total + subtotal * 0.1).toFixed(0)} XOF</span>
                  </div>
                </div>

                {/* Actions */}
                <button
                  onClick={handleCheckout}
                  className="w-full bg-primary text-white font-semibold py-3 rounded-lg hover:bg-red-700"
                >
                  Procéder à la livraison
                </button>

                <button
                  onClick={() => clearCart()}
                  className="w-full mt-2 border border-gray-300 text-gray-700 font-semibold py-3 rounded-lg hover:bg-gray-100"
                >
                  Vider le panier
                </button>

                <Link href="/" className="block text-center mt-4 text-primary font-semibold hover:underline">
                  Continuer vos achats →
                </Link>
              </div>
            </div>
          </div>
        )}
      </main>
      <Footer />
    </>
  );
}
