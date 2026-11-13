'use client';

import { useEffect, useState } from 'react';
import { kycService } from '@/app/services';

/**
 * Depot de la piece d'identite du livreur et suivi de sa verification.
 */
export default function KycCard() {
  const [status, setStatus] = useState(null);
  const [file, setFile] = useState(null);
  const [sending, setSending] = useState(false);
  const [message, setMessage] = useState('');

  const load = async () => {
    try {
      const res = await kycService.getStatus();
      setStatus(res.data.data);
    } catch {
      setStatus(null);
    }
  };

  useEffect(() => { load(); }, []);

  const submit = async (e) => {
    e.preventDefault();
    if (!file) return;
    setSending(true);
    setMessage('');
    try {
      const formData = new FormData();
      formData.append('document', file);
      const res = await kycService.submit(formData);
      setMessage(res.data.message);
      setFile(null);
      await load();
    } catch (err) {
      const errors = err.response?.data?.errors;
      setMessage(errors ? Object.values(errors).flat().join(' ') : "L'envoi a échoué");
    } finally {
      setSending(false);
    }
  };

  if (!status) return null;

  if (status.kyc_verified) {
    return (
      <section className="bg-green-50 border border-green-300 rounded-lg p-4 text-green-800 font-semibold">
        🪪 Identité vérifiée
      </section>
    );
  }

  return (
    <section className="bg-white rounded-lg shadow p-6">
      <h2 className="text-xl font-bold mb-2">🪪 Vérification d’identité</h2>
      <p className="text-sm text-gray-600 mb-4">
        {status.document_submitted
          ? 'Votre document est en cours de vérification. Vous pouvez en envoyer un nouveau si besoin.'
          : 'Envoyez une photo ou un scan de votre pièce d’identité (JPG, PNG ou PDF, 5 Mo max.).'}
      </p>
      <form onSubmit={submit} className="flex flex-wrap items-center gap-3">
        <input type="file" accept=".jpg,.jpeg,.png,.pdf" onChange={(e) => setFile(e.target.files?.[0] || null)} />
        <button type="submit" disabled={!file || sending} className="bg-primary text-white px-4 py-2 rounded font-semibold disabled:bg-gray-400">
          {sending ? 'Envoi...' : 'Envoyer'}
        </button>
      </form>
      {message && <p className="text-sm mt-3">{message}</p>}
    </section>
  );
}
