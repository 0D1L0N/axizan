'use client';

import { useEffect, useState } from 'react';
import { deliveryService } from '@/app/services';
import { withRole } from '@/app/lib/auth-hoc';
import Header from '@/app/components/Header';
import KycCard from '@/app/components/KycCard';

function LivreurPage() {
  const [ready, setReady] = useState([]);
  const [mine, setMine] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notesByDelivery, setNotesByDelivery] = useState({});

  const load = async () => {
    setLoading(true);
    try {
      const [readyRes, mineRes] = await Promise.all([
        deliveryService.getReadyOrders(),
        deliveryService.getMine(),
      ]);
      setReady(readyRes.data.data);
      setMine(mineRes.data.data);
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur de chargement');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const runAction = async (fn) => {
    setError('');
    try {
      await fn();
      await load();
    } catch (err) {
      setError(err.response?.data?.message || "L'action a échoué");
    }
  };

  const handleConfirm = (delivery) => {
    const formData = new FormData();
    if (notesByDelivery[delivery.id]) {
      formData.append('delivery_notes', notesByDelivery[delivery.id]);
    }
    runAction(() => deliveryService.confirmDelivery(delivery.id, formData));
  };

  return (
    <>
      <Header />
      <main className="max-w-5xl mx-auto px-4 py-8 space-y-8">
        <h1 className="text-3xl font-bold">🚚 Espace Livreur</h1>
        <KycCard />
        {error && <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{error}</div>}

        <section>
          <h2 className="text-xl font-bold mb-4">Commandes prêtes ({ready.length})</h2>
          {loading ? <p>Chargement...</p> : ready.length === 0 ? (
            <p className="text-gray-500">Aucune commande prête pour l'instant.</p>
          ) : (
            <div className="space-y-3">
              {ready.map((order) => (
                <div key={order.id} className="bg-white rounded-lg shadow p-4 flex items-center justify-between">
                  <div>
                    <p className="font-semibold">{order.order_number} — {order.client?.name}</p>
                    <p className="text-sm text-gray-500">{order.deliveryAddress?.quartier}</p>
                  </div>
                  <button
                    onClick={() => runAction(() => deliveryService.acceptDelivery(order.id))}
                    className="bg-primary text-white px-4 py-2 rounded font-semibold"
                  >
                    Accepter la livraison
                  </button>
                </div>
              ))}
            </div>
          )}
        </section>

        <section>
          <h2 className="text-xl font-bold mb-4">Mes livraisons en cours ({mine.length})</h2>
          {mine.length === 0 ? (
            <p className="text-gray-500">Aucune livraison en cours.</p>
          ) : (
            <div className="space-y-3">
              {mine.map((delivery) => (
                <div key={delivery.id} className="bg-white rounded-lg shadow p-4 space-y-3">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="font-semibold">{delivery.order?.order_number}</p>
                      <p className="text-sm text-gray-500 capitalize">{delivery.status.replace('_', ' ')}</p>
                    </div>
                    {delivery.status === 'picked_up' && (
                      <button
                        onClick={() => runAction(() => deliveryService.startDelivery(delivery.id))}
                        className="bg-indigo-600 text-white px-4 py-2 rounded font-semibold"
                      >
                        Démarrer
                      </button>
                    )}
                  </div>
                  {delivery.status === 'in_transit' && (
                    <div className="flex gap-2 items-center">
                      <input
                        type="text"
                        placeholder="Note de livraison (optionnel)"
                        className="border rounded px-3 py-2 flex-1"
                        value={notesByDelivery[delivery.id] || ''}
                        onChange={(e) => setNotesByDelivery({ ...notesByDelivery, [delivery.id]: e.target.value })}
                      />
                      <button
                        onClick={() => handleConfirm(delivery)}
                        className="bg-emerald-600 text-white px-4 py-2 rounded font-semibold"
                      >
                        Confirmer livraison
                      </button>
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </section>
      </main>
    </>
  );
}

export default withRole(LivreurPage, ['livreur']);
