"use client";

import { useEffect, useState } from 'react';
import preparateurService from '@/app/services/preparateurService';
import { useAuth } from '@/app/contexts/AuthContext';

export default function PreparateurOrders() {
  const { user, loading } = useAuth();
  const [orders, setOrders] = useState([]);

  useEffect(() => {
    if (!loading) fetchOrders();
  }, [loading]);

  const fetchOrders = async () => {
    try {
      const res = await preparateurService.getOrders();
      setOrders(res.data);
    } catch (e) {
      console.error(e);
    }
  };

  const accept = async (id) => {
    await preparateurService.acceptOrder(id);
    fetchOrders();
  };

  const start = async (id) => {
    await preparateurService.startPreparing(id);
    fetchOrders();
  };

  const ready = async (id) => {
    await preparateurService.markReady(id);
    fetchOrders();
  };

  if (loading) return <div>Chargement...</div>;
  if (!user || user.role !== 'preparateur') return <div>Accès refusé</div>;

  return (
    <div className="p-4">
      <h1 className="text-2xl mb-4">Commandes à préparer</h1>
      <div className="space-y-3">
        {orders.map((o) => (
          <div key={o.id} className="p-3 border rounded">
            <div className="flex justify-between">
              <div>
                <div className="font-semibold">{o.order_number} — {o.status}</div>
                <div className="text-sm">Client: {o.client_id} — Adresse: {o.deliveryAddress?.label}</div>
              </div>
              <div className="flex gap-2">
                {o.status === 'pending' && <button onClick={() => accept(o.id)} className="px-2 py-1 border">Accepter</button>}
                {o.status === 'accepted' && <button onClick={() => start(o.id)} className="px-2 py-1 border">Démarrer préparation</button>}
                {o.status === 'preparing' && <button onClick={() => ready(o.id)} className="px-2 py-1 bg-green-600 text-white">Marquer prêt</button>}
              </div>
            </div>
            <div className="mt-2 text-sm">
              <ul>
                {o.items?.map((it) => (
                  <li key={it.id}>{it.quantity} x {it.product_id} — {it.total_price}</li>
                ))}
              </ul>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
