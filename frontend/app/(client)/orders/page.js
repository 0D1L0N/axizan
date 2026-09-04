'use client';

import { useCallback, useEffect, useState } from 'react';
import { orderService } from '@/app/services';
import OrderCard from '@/app/components/OrderCard';
import Header from '@/app/components/Header';
import Footer from '@/app/components/Footer';
import Link from 'next/link';
import { withAuth } from '@/app/lib/auth-hoc';
import { useToast } from '@/app/contexts/ToastContext';
import { PackageSearch } from 'lucide-react';

/**
 * Filtres, en français et regroupés par ce qu'on cherche réellement.
 *
 * « En cours » recouvre plusieurs statuts : quand on revient sur cette page,
 * la question est « où en sont mes commandes ? », pas « laquelle est au statut
 * `preparing` ? ».
 */
const FILTERS = [
  { key: 'all', label: 'Toutes', status: null },
  { key: 'active', label: 'En cours', status: 'pending,accepted,preparing,ready,in_delivery' },
  { key: 'delivered', label: 'Livrées', status: 'delivered,completed' },
  { key: 'cancelled', label: 'Annulées', status: 'cancelled,returned' },
];

function OrdersPage() {
  const toast = useToast();

  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all');

  const loadOrders = useCallback(async () => {
    setLoading(true);
    try {
      const status = FILTERS.find((f) => f.key === filter)?.status;
      const response = await orderService.getOrders(status ? { status } : {});
      setOrders(response.data.data);
    } catch (err) {
      toast.fromApi(err, 'Impossible de charger vos commandes.');
    } finally {
      setLoading(false);
    }
  }, [filter, toast]);

  useEffect(() => {
    loadOrders();
  }, [loadOrders]);

  return (
    <>
      <Header />
      <main className="min-h-screen bg-cream">
        <div className="mx-auto max-w-3xl px-4 py-10">
          <p className="eyebrow text-clay-500">Votre historique</p>
          <h1 className="mt-2 font-display text-display-sm text-forest-800">Mes commandes</h1>

          <div className="mt-7 flex flex-wrap gap-2">
            {FILTERS.map((option) => (
              <button
                key={option.key}
                onClick={() => setFilter(option.key)}
                aria-pressed={filter === option.key}
                className={`rounded-full px-4 py-2 text-sm font-bold transition ${
                  filter === option.key
                    ? 'bg-forest-800 text-cream'
                    : 'border border-cream-300 bg-white text-ink-500 hover:border-forest-600'
                }`}
              >
                {option.label}
              </button>
            ))}
          </div>

          <div className="mt-6">
            {loading ? (
              <div className="space-y-3">
                {[1, 2, 3].map((i) => (
                  <div key={i} className="h-36 animate-pulse rounded-card bg-white" />
                ))}
              </div>
            ) : orders.length === 0 ? (
              <div className="rounded-panel border border-dashed border-cream-300 bg-white p-12 text-center">
                <PackageSearch size={30} className="mx-auto text-ink-300" />
                <p className="mt-4 font-display text-lg font-semibold text-ink-900">
                  {filter === 'all' ? 'Aucune commande pour le moment' : 'Rien dans cette catégorie'}
                </p>
                {filter === 'all' && (
                  <Link
                    href="/products"
                    className="mt-5 inline-flex rounded-full bg-forest-800 px-6 py-3 font-bold text-cream transition hover:bg-forest-700"
                  >
                    Composer un premier panier
                  </Link>
                )}
              </div>
            ) : (
              <div className="space-y-3">
                {orders.map((order) => (
                  <OrderCard key={order.id} order={order} />
                ))}
              </div>
            )}
          </div>
        </div>
      </main>
      <Footer />
    </>
  );
}

export default withAuth(OrdersPage);
