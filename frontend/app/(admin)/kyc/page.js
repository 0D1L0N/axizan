"use client";

import { useEffect, useState } from 'react';
import adminService from '@/app/services/adminService';
import { useAuth } from '@/app/contexts/AuthContext';

export default function KycAdminPage() {
  const { user, loading } = useAuth();
  const [list, setList] = useState([]);

  useEffect(() => { if (!loading) fetch(); }, [loading]);

  const fetch = async () => {
    try { const res = await adminService.getPendingKyc(); setList(res.data); } catch (e) { console.error(e); }
  };

  const approve = async (id) => { await adminService.approveKyc(id); fetch(); };
  const reject = async (id) => { const reason = prompt('Raison du rejet'); await adminService.rejectKyc(id, { reason }); fetch(); };

  if (loading) return <div>Chargement...</div>;
  if (!user || user.role !== 'admin') return <div>Accès refusé</div>;

  return (
    <div className="p-4">
      <h1 className="text-2xl mb-4">KYC - Livreurs en attente</h1>
      <div className="space-y-3">
        {list.map(u => (
          <div key={u.id} className="p-3 border rounded flex justify-between items-center">
            <div>
              <div className="font-semibold">{u.name} ({u.phone})</div>
              <div className="text-sm">Document: <a href={u.kyc_document_url} target="_blank">Voir</a></div>
            </div>
            <div className="flex gap-2">
              <button className="px-2 py-1 bg-green-600 text-white" onClick={() => approve(u.id)}>Approuver</button>
              <button className="px-2 py-1 border text-red-600" onClick={() => reject(u.id)}>Rejeter</button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
