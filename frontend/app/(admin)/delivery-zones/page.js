"use client";

import { useEffect, useState } from 'react';
import ZoneForm from './ZoneForm';
import adminService from '@/app/services/adminService';
import { useAuth } from '@/app/contexts/AuthContext';

export default function AdminZonesPage() {
  const { user, loading } = useAuth();
  const [zones, setZones] = useState([]);
  const [editing, setEditing] = useState(null);
  const [showForm, setShowForm] = useState(false);

  useEffect(() => {
    if (!loading) fetchZones();
  }, [loading]);

  const fetchZones = async () => {
    try {
      const res = await adminService.getZones();
      setZones(res.data);
    } catch (e) {
      console.error(e);
    }
  };

  const handleCreate = () => {
    setEditing(null);
    setShowForm(true);
  };

  const handleEdit = (zone) => {
    setEditing(zone);
    setShowForm(true);
  };

  const handleDelete = async (id) => {
    if (!confirm('Supprimer cette zone ?')) return;
    await adminService.deleteZone(id);
    fetchZones();
  };

  const handleSubmit = async (data) => {
    try {
      if (editing) {
        await adminService.updateZone(editing.id, data);
      } else {
        await adminService.createZone(data);
      }
      setShowForm(false);
      fetchZones();
    } catch (e) {
      console.error(e);
      alert('Erreur lors de la sauvegarde');
    }
  };

  if (loading) return <div>Chargement...</div>;
  if (!user || user.role !== 'admin') return <div>Accès refusé</div>;

  return (
    <div className="p-4">
      <h1 className="text-2xl font-bold mb-4">Gestion des zones de livraison</h1>
      <div className="mb-4">
        <button className="px-3 py-1 bg-green-600 text-white rounded" onClick={handleCreate}>Nouvelle zone</button>
      </div>

      {showForm && (
        <div className="mb-4 border p-4 rounded">
          <ZoneForm initial={editing || {}} onSubmit={handleSubmit} onCancel={() => setShowForm(false)} />
        </div>
      )}

      <div className="grid gap-3">
        {zones.map((z) => (
          <div key={z.id} className="p-3 border rounded flex justify-between items-center">
            <div>
              <div className="font-semibold">{z.name}</div>
              <div className="text-sm">Base: {z.base_delivery_fee} — Min: {z.min_order_amount}</div>
            </div>
            <div className="flex gap-2">
              <button className="px-2 py-1 border" onClick={() => handleEdit(z)}>Editer</button>
              <button className="px-2 py-1 border text-red-600" onClick={() => handleDelete(z.id)}>Supprimer</button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
