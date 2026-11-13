"use client";

import { useEffect, useState } from 'react';
import livreurService from '@/app/services/livreurService';
import { useAuth } from '@/app/contexts/AuthContext';

export default function LivreurDeliveries() {
  const { user, loading } = useAuth();
  const [deliveries, setDeliveries] = useState([]);

  useEffect(() => { if (!loading) fetch(); }, [loading]);

  const fetch = async () => {
    try { const res = await livreurService.getDeliveries(); setDeliveries(res.data); } catch (e) { console.error(e); }
  };

  const accept = async (id) => { await livreurService.acceptDelivery(id); fetch(); };
  const confirm = async (id) => {
    // simple confirm without photo
    await livreurService.confirmDelivery(id, {});
    fetch();
  };

  if (loading) return <div>Chargement...</div>;
  if (!user || user.role !== 'livreur') return <div>Accès refusé</div>;

  return (
    <div className="p-4">
      <h1 className="text-2xl mb-4">Mes livraisons</h1>
      <div className="space-y-3">
        {deliveries.map(d => (
          <div key={d.id} className="p-3 border rounded">
            <div className="flex justify-between">
              <div>
                <div className="font-semibold">Commande {d.order?.order_number} — {d.status}</div>
                <div className="text-sm">Adresse: {d.order?.deliveryAddress?.label}</div>
              </div>
              <div className="flex gap-2">
                {d.status !== 'pickup' && <button className="px-2 py-1 border" onClick={() => accept(d.id)}>Accepter</button>}
                {d.status === 'pickup' && <button className="px-2 py-1 bg-green-600 text-white" onClick={() => confirm(d.id)}>Confirmer livraison</button>}
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
