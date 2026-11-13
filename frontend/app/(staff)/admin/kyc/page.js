'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { adminService } from '@/app/services';
import { withRole } from '@/app/lib/auth-hoc';
import Header from '@/app/components/Header';

function AdminKycPage() {
  const [pending, setPending] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = async () => {
    setLoading(true);
    try {
      const res = await adminService.getPendingKyc();
      setPending(res.data.data);
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur de chargement');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const run = async (fn) => {
    setError('');
    try {
      await fn();
      await load();
    } catch (err) {
      setError(err.response?.data?.message || "L'action a échoué");
    }
  };

  // Le document est servi avec le jeton d'authentification : on l'ouvre
  // depuis un blob plutot qu'avec un lien direct.
  const openDocument = async (id) => {
    setError('');
    try {
      const res = await adminService.getKycDocument(id);
      const url = URL.createObjectURL(res.data);
      window.open(url, '_blank', 'noopener');
      setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch {
      setError('Document introuvable');
    }
  };

  const reject = (id) => {
    const reason = prompt('Motif du refus (communiqué au livreur)');
    if (reason) run(() => adminService.rejectKyc(id, reason));
  };

  return (
    <>
      <Header />
      <main className="max-w-5xl mx-auto px-4 py-8 space-y-8">
        <div className="flex items-center justify-between">
          <h1 className="text-3xl font-bold">🪪 Vérification des livreurs</h1>
          <Link href="/admin" className="text-primary font-semibold hover:underline">← Espace admin</Link>
        </div>
        {error && <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{error}</div>}

        {loading ? <p>Chargement...</p> : pending.length === 0 ? (
          <p className="text-gray-500">Aucun document en attente.</p>
        ) : (
          <div className="space-y-3">
            {pending.map((livreur) => (
              <div key={livreur.id} className="bg-white rounded-lg shadow p-4 flex items-center justify-between gap-4">
                <div>
                  <p className="font-semibold">{livreur.name}</p>
                  <p className="text-sm text-gray-500">{livreur.phone} · {livreur.email}</p>
                </div>
                <div className="flex gap-2 shrink-0">
                  <button onClick={() => openDocument(livreur.id)} className="border px-3 py-1 rounded">Voir le document</button>
                  <button onClick={() => run(() => adminService.approveKyc(livreur.id))} className="bg-primary text-white px-3 py-1 rounded font-semibold">Valider</button>
                  <button onClick={() => reject(livreur.id)} className="border px-3 py-1 rounded text-red-600">Refuser</button>
                </div>
              </div>
            ))}
          </div>
        )}
      </main>
    </>
  );
}

export default withRole(AdminKycPage, ['admin']);
