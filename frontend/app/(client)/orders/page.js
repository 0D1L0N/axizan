'use client';

import { useEffect, useState } from 'react';
import { useAuth } from '@/app/contexts/AuthContext';
import { orderService } from '@/app/services';
import OrderCard from '@/app/components/OrderCard';
import Header from '@/app/components/Header';
import Footer from '@/app/components/Footer';
import { withAuth } from '@/app/lib/auth-hoc';

function OrdersPage() {
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all');

  const { user } = useAuth();

  useEffect(() => {
    loadOrders();
  }, [filter]);

  const loadOrders = async () => {
    try {
      setLoading(true);
      let params = {};

      if (filter !== 'all') {
        params.status = filter;
      }

      const response = await orderService.getOrders(params);
      setOrders(response.data.data);
    } catch (error) {
      console.error('Failed to load orders:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <Header />
      <main className="max-w-7xl mx-auto px-4 py-8">
        <h1 className="text-3xl font-bold mb-8">📦 Mes Commandes</h1>

        {/* Filtres */}
        <div className="mb-6 flex flex-wrap gap-2">
          {['all', 'pending', 'preparing', 'ready', 'in_delivery', 'delivered', 'completed'].map((status) => (
            <button
              key={status}
              onClick={() => setFilter(status)}
              className={`px-4 py-2 rounded-lg font-semibold transition ${
                filter === status
                  ? 'bg-primary text-white'
                  : 'bg-gray-100 text-gray-800 hover:bg-gray-200'
              }`}
            >
              {status === 'all' ? 'Toutes' : status.replace('_', ' ').toUpperCase()}
            </button>
          ))}
        </div>

        {/* Commandes */}
        {loading ? (
          <div className="flex items-center justify-center py-12">
            <div className="text-gray-600">Chargement...</div>
          </div>
        ) : orders.length === 0 ? (
          <div className="bg-gray-100 rounded-lg p-8 text-center">
            <p className="text-gray-600">Aucune commande pour le moment</p>
          </div>
        ) : (
          <div className="space-y-4">
            {orders.map((order) => (
              <OrderCard key={order.id} order={order} />
            ))}
          </div>
        )}
      </main>
      <Footer />
    </>
  );
}

export default withAuth(OrdersPage);
