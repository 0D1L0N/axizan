'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { adminService } from '@/app/services';
import { withRole } from '@/app/lib/auth-hoc';
import Header from '@/app/components/Header';
import ZoneForm from './ZoneForm';
import SlotForm from './SlotForm';

const xof = (value) => `${Number(value || 0).toFixed(0)} XOF`;

function AdminZonesPage() {
  const [zones, setZones] = useState([]);
  const [slots, setSlots] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  // { kind: 'zone' | 'slot', item: objet edite ou null pour une creation }
  const [editing, setEditing] = useState(null);

  const load = async () => {
    setLoading(true);
    try {
      const [zonesRes, slotsRes] = await Promise.all([adminService.getZones(), adminService.getSlots()]);
      setZones(zonesRes.data.data);
      setSlots(slotsRes.data.data);
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
      setEditing(null);
      await load();
    } catch (err) {
      const errors = err.response?.data?.errors;
      setError(errors ? Object.values(errors).flat().join(' ') : err.response?.data?.message || "L'enregistrement a échoué");
    }
  };

  const saveZone = (data) => run(() => (editing.item
    ? adminService.updateZone(editing.item.id, data)
    : adminService.createZone(data)));

  const saveSlot = (data) => run(() => (editing.item
    ? adminService.updateSlot(editing.item.id, data)
    : adminService.createSlot(data)));

  const remove = (label, fn) => {
    if (confirm(`Supprimer ${label} ?`)) run(fn);
  };

  return (
    <>
      <Header />
      <main className="max-w-5xl mx-auto px-4 py-8 space-y-8">
        <div className="flex items-center justify-between">
          <h1 className="text-3xl font-bold">🗺️ Zones et créneaux</h1>
          <Link href="/admin" className="text-primary font-semibold hover:underline">← Espace admin</Link>
        </div>
        {error && <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">{error}</div>}

        {editing && (
          <section className="bg-white rounded-lg shadow p-6">
            <h2 className="text-xl font-bold mb-4">
              {editing.item ? 'Modifier' : 'Nouvelle'} {editing.kind === 'zone' ? 'zone' : 'créneau'}
            </h2>
            {editing.kind === 'zone'
              ? <ZoneForm initial={editing.item || {}} onSubmit={saveZone} onCancel={() => setEditing(null)} />
              : <SlotForm zones={zones} initial={editing.item || {}} onSubmit={saveSlot} onCancel={() => setEditing(null)} />}
          </section>
        )}

        <section>
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xl font-bold">Zones ({zones.length})</h2>
            <button onClick={() => setEditing({ kind: 'zone', item: null })} className="bg-primary text-white px-4 py-2 rounded font-semibold">
              Nouvelle zone
            </button>
          </div>
          {loading ? <p>Chargement...</p> : (
            <div className="space-y-3">
              {zones.map((zone) => (
                <div key={zone.id} className="bg-white rounded-lg shadow p-4 flex items-center justify-between gap-4">
                  <div>
                    <p className="font-semibold">{zone.name} {!zone.is_active && <span className="text-sm text-gray-500">(inactive)</span>}</p>
                    <p className="text-sm text-gray-500">
                      Livraison {xof(zone.base_delivery_fee)}
                      {zone.free_delivery_threshold && ` · offerte dès ${xof(zone.free_delivery_threshold)}`}
                      {Number(zone.min_order_amount) > 0 && ` · minimum ${xof(zone.min_order_amount)} (${zone.small_order_action === 'block' ? 'refus' : `+${xof(zone.small_order_fee)}`})`}
                    </p>
                  </div>
                  <div className="flex gap-2 shrink-0">
                    <button onClick={() => setEditing({ kind: 'zone', item: zone })} className="border px-3 py-1 rounded">Modifier</button>
                    <button onClick={() => remove(`la zone ${zone.name}`, () => adminService.deleteZone(zone.id))} className="border px-3 py-1 rounded text-red-600">Supprimer</button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>

        <section>
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xl font-bold">Créneaux ({slots.length})</h2>
            <button
              onClick={() => setEditing({ kind: 'slot', item: null })}
              disabled={zones.length === 0}
              className="bg-primary text-white px-4 py-2 rounded font-semibold disabled:bg-gray-400"
            >
              Nouveau créneau
            </button>
          </div>
          {loading ? <p>Chargement...</p> : (
            <div className="space-y-3">
              {slots.map((slot) => (
                <div key={slot.id} className="bg-white rounded-lg shadow p-4 flex items-center justify-between gap-4">
                  <div>
                    <p className="font-semibold">{slot.display_label} — {slot.zone?.name || 'Toutes zones'} {!slot.is_active && <span className="text-sm text-gray-500">(inactif)</span>}</p>
                    <p className="text-sm text-gray-500">
                      {slot.max_orders_per_slot} commandes max. par jour
                      {Number(slot.additional_fee) > 0 && ` · supplément ${xof(slot.additional_fee)}`}
                    </p>
                  </div>
                  <div className="flex gap-2 shrink-0">
                    <button onClick={() => setEditing({ kind: 'slot', item: slot })} className="border px-3 py-1 rounded">Modifier</button>
                    <button onClick={() => remove(`le créneau ${slot.display_label}`, () => adminService.deleteSlot(slot.id))} className="border px-3 py-1 rounded text-red-600">Supprimer</button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>
      </main>
    </>
  );
}

export default withRole(AdminZonesPage, ['admin']);
