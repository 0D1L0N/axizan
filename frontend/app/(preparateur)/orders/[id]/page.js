"use client";

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import preparateurService from '@/app/services/preparateurService';
import { useAuth } from '@/app/contexts/AuthContext';

export default function OrderDetail({ params }) {
  const { id } = params;
  const { user, loading } = useAuth();
  const [order, setOrder] = useState(null);
  const [action, setAction] = useState('call');
  const [notes, setNotes] = useState('');

  useEffect(() => { if (!loading) fetchOrder(); }, [loading]);

  const fetchOrder = async () => {
    try {
      const res = await preparateurService.getOrder(id);
      setOrder(res.data);
    } catch (e) { console.error(e); }
  };

  const submitSub = async (itemId) => {
    try {
      await preparateurService.substitute(order.id, { item_id: itemId, action, replacement_product_id: null, notes });
      alert('Demande enregistrée');
      fetchOrder();
    } catch (e) { console.error(e); alert('Erreur'); }
  };

  if (loading) return <div>Chargement...</div>;
  if (!user || user.role !== 'preparateur') return <div>Accès refusé</div>;
  if (!order) return <div>Chargement commande…</div>;

  return (
    <div className="p-4">
      <h2 className="text-xl font-bold">Commande {order.order_number} — {order.status}</h2>
      <div className="mt-3">
        <h3 className="font-semibold">Articles</h3>
        <ul className="mt-2">
          {order.items.map(it => (
            <li key={it.id} className="py-2 border-b">
              <div>{it.quantity} x {it.product_id} — {it.total_price}</div>
              <div className="mt-1 flex gap-2">
                <select value={action} onChange={(e) => setAction(e.target.value)} className="border p-1">
                  <option value="call">Appeler client</option>
                  <option value="replace">Remplacer</option>
                  <option value="remove">Retirer</option>
                </select>
                <input placeholder="notes" value={notes} onChange={(e) => setNotes(e.target.value)} className="border p-1" />
                <button className="px-2 py-1 border" onClick={() => submitSub(it.id)}>Enregistrer</button>
              </div>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}
