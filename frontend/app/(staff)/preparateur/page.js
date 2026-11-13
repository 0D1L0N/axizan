'use client';

import { useEffect, useState } from 'react';
import { preparatorService } from '@/app/services';
import { withRole } from '@/app/lib/auth-hoc';
import Header from '@/app/components/Header';

function PreparatorPage() {
  const [available, setAvailable] = useState([]);
  const [mine, setMine] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = async () => {
    setLoading(true);
    try {
      const [availableRes, mineRes] = await Promise.all([
        preparatorService.getAvailable(),
        preparatorService.getMine(),
      ]);
      setAvailable(availableRes.data.data);
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

  return (
    <>
      <Header />
      <main className="max-w-5xl mx-auto px-4 py-8 space-y-8">
        <h1 className="text-3xl font-bold">👨‍🍳 Espace Préparateur</h1>
        {error && <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{error}</div>}

        <section>
          <h2 className="text-xl font-bold mb-4">Commandes disponibles ({available.length})</h2>
          {loading ? <p>Chargement...</p> : available.length === 0 ? (
            <p className="text-gray-500">Aucune commande payée en attente pour l'instant.</p>
          ) : (
            <div className="space-y-3">
              {available.map((order) => (
                <div key={order.id} className="bg-white rounded-lg shadow p-4 flex items-center justify-between">
                  <div>
                    <p className="font-semibold">{order.order_number} — {order.client?.name}</p>
                    <p className="text-sm text-gray-500">{order.items?.length ?? 0} article(s)</p>
                  </div>
                  <button
                    onClick={() => runAction(() => preparatorService.accept(order.id))}
                    className="bg-primary text-white px-4 py-2 rounded font-semibold"
                  >
                    Accepter
                  </button>
                </div>
              ))}
            </div>
          )}
        </section>

        <section>
          <h2 className="text-xl font-bold mb-4">Mes commandes en cours ({mine.length})</h2>
          {mine.length === 0 ? (
            <p className="text-gray-500">Aucune commande en cours.</p>
          ) : (
            <div className="space-y-3">
              {mine.map((order) => (
                <div key={order.id} className="bg-white rounded-lg shadow p-4">
                  <div className="flex items-start justify-between gap-4">
                  <div>
                    <p className="font-semibold">{order.order_number}</p>
                    <p className="text-sm text-gray-500 capitalize">{order.status}</p>
                  </div>
                  <div className="flex gap-2">
                    {order.status === 'accepted' && (
                      <button
                        onClick={() => runAction(() => preparatorService.start(order.id))}
                        className="bg-purple-600 text-white px-4 py-2 rounded font-semibold"
                      >
                        Démarrer préparation
                      </button>
                    )}
                    {order.status === 'preparing' && (
                      <button
                        onClick={() => runAction(() => preparatorService.ready(order.id))}
                        className="bg-green-600 text-white px-4 py-2 rounded font-semibold"
                      >
                        Marquer prête
                      </button>
                    )}
                  </div>
                  </div>

                  {/* Liste de courses : ce que le préparateur doit acheter au marché */}
                  <ul className="mt-4 border-t border-gray-100 pt-3 space-y-1">
                    {(order.items || []).map((item) => (
                      <li key={item.id} className="flex items-center justify-between gap-4 text-sm">
                        <span className="font-medium">
                          {item.product?.name || `Produit #${item.product_id}`} — {Number(item.quantity)} {item.quantity_unit}
                        </span>
                        <span className={`shrink-0 rounded-full px-2 py-1 text-xs font-semibold ${
                          item.substitution_preference === 'remove'
                            ? 'bg-orange-100 text-orange-800'
                            : 'bg-blue-100 text-blue-800'
                        }`}>
                          {item.substitution_preference === 'remove' ? 'si absent : retirer' : 'si absent : remplacer'}
                        </span>
                      </li>
                    ))}
                  </ul>
                </div>
              ))}
            </div>
          )}
        </section>
      </main>
    </>
  );
}

export default withRole(PreparatorPage, ['preparateur']);
