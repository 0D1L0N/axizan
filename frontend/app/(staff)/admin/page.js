'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { adminService } from '@/app/services';
import { withRole } from '@/app/lib/auth-hoc';
import Header from '@/app/components/Header';

function AdminPage() {
  const [pending, setPending] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const [staffForm, setStaffForm] = useState({
    name: '', email: '', phone: '', password: '', password_confirmation: '', role: 'preparateur',
  });

  const load = async () => {
    setLoading(true);
    try {
      const res = await adminService.getPendingPayments();
      setPending(res.data.data);
    } catch (err) {
      setError(err.response?.data?.message || 'Erreur de chargement');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  // Reference constatee par l'admin sur le compte marchand, par commande.
  // Le backend l'exige : sans elle, le paiement n'est pas tracable.
  const [references, setReferences] = useState({});

  const handleValidate = async (orderId) => {
    setError('');
    const reference = (references[orderId] || '').trim();
    if (!reference) {
      setError('Saisissez la référence de la transaction vue sur le compte marchand.');
      return;
    }
    try {
      await adminService.validatePayment(orderId, { reference_number: reference });
      setReferences((current) => {
        const next = { ...current };
        delete next[orderId];
        return next;
      });
      await load();
    } catch (err) {
      setError(err.response?.data?.message || 'La validation a échoué');
    }
  };

  const handleCreateStaff = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    try {
      await adminService.createStaff(staffForm);
      setSuccess(`Compte ${staffForm.role} créé pour ${staffForm.email}`);
      setStaffForm({ name: '', email: '', phone: '', password: '', password_confirmation: '', role: 'preparateur' });
    } catch (err) {
      setError(err.response?.data?.message || 'La création du compte a échoué');
    }
  };

  return (
    <>
      <Header />
      <main className="max-w-5xl mx-auto px-4 py-8 space-y-8">
        <h1 className="text-3xl font-bold">🛠️ Espace Admin</h1>
        <nav className="flex flex-wrap gap-2">
          <Link href="/admin/zones" className="border rounded px-4 py-2 font-semibold hover:bg-gray-100">🗺️ Zones et créneaux</Link>
          <Link href="/admin/kyc" className="border rounded px-4 py-2 font-semibold hover:bg-gray-100">🪪 Vérification des livreurs</Link>
        </nav>
        {error && <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{error}</div>}
        {success && <div className="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">{success}</div>}

        <section>
          <h2 className="text-xl font-bold mb-4">Paiements en attente de validation ({pending.length})</h2>
          {loading ? <p>Chargement...</p> : pending.length === 0 ? (
            <p className="text-gray-500">Aucun paiement en attente.</p>
          ) : (
            <div className="space-y-3">
              {pending.map((order) => (
                <div key={order.id} className="bg-white rounded-lg shadow p-4 flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p className="font-semibold">{order.order_number} — {order.client?.name}</p>
                    <p className="text-sm text-gray-500">
                      {Number(order.total).toFixed(0)} XOF
                      {order.payment?.payment_provider && ` · ${order.payment.payment_provider.toUpperCase()}`}
                      {order.payment?.phone_number && ` · ${order.payment.phone_number}`}
                    </p>
                    <p className="text-sm text-gray-500">
                      Référence déclarée par le client : {order.payment?.payer_declared_reference || 'aucune'}
                    </p>
                  </div>
                  <div className="flex gap-2">
                    <input
                      placeholder="Référence vérifiée"
                      className="border rounded px-3 py-2"
                      value={references[order.id] || ''}
                      onChange={(e) => setReferences({ ...references, [order.id]: e.target.value })}
                    />
                    <button
                      onClick={() => handleValidate(order.id)}
                      className="bg-primary text-white px-4 py-2 rounded font-semibold"
                    >
                      Valider le paiement
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>

        <section className="bg-white rounded-lg shadow p-6">
          <h2 className="text-xl font-bold mb-4">Créer un compte préparateur / livreur</h2>
          <form onSubmit={handleCreateStaff} className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <input required placeholder="Nom complet" className="border rounded px-3 py-2"
              value={staffForm.name} onChange={(e) => setStaffForm({ ...staffForm, name: e.target.value })} />
            <select className="border rounded px-3 py-2" value={staffForm.role}
              onChange={(e) => setStaffForm({ ...staffForm, role: e.target.value })}>
              <option value="preparateur">👨‍🍳 Préparateur</option>
              <option value="livreur">🚚 Livreur</option>
            </select>
            <input required type="email" placeholder="Email" className="border rounded px-3 py-2"
              value={staffForm.email} onChange={(e) => setStaffForm({ ...staffForm, email: e.target.value })} />
            <input required placeholder="Téléphone" className="border rounded px-3 py-2"
              value={staffForm.phone} onChange={(e) => setStaffForm({ ...staffForm, phone: e.target.value })} />
            <input required type="password" placeholder="Mot de passe" className="border rounded px-3 py-2"
              value={staffForm.password} onChange={(e) => setStaffForm({ ...staffForm, password: e.target.value })} />
            <input required type="password" placeholder="Confirmer le mot de passe" className="border rounded px-3 py-2"
              value={staffForm.password_confirmation} onChange={(e) => setStaffForm({ ...staffForm, password_confirmation: e.target.value })} />
            <button type="submit" className="sm:col-span-2 bg-primary text-white py-2 rounded font-semibold">
              Créer le compte
            </button>
          </form>
        </section>
      </main>
    </>
  );
}

export default withRole(AdminPage, ['admin']);
