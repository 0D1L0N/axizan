'use client';

import { useState } from 'react';

const input = 'border rounded px-3 py-2 w-full';
const hhmm = (time) => (time ? time.slice(0, 5) : '');

export default function SlotForm({ zones, initial = {}, onSubmit, onCancel }) {
  const [form, setForm] = useState({
    zone_id: initial.zone_id ?? zones[0]?.id ?? '',
    time_start: hhmm(initial.time_start) || '09:00',
    time_end: hhmm(initial.time_end) || '11:00',
    display_label: initial.display_label || '',
    max_orders_per_slot: initial.max_orders_per_slot ?? 20,
    additional_fee: initial.additional_fee ?? 0,
    is_active: initial.is_active ?? true,
  });

  const handle = (e) => {
    const { name, value, type, checked } = e.target;
    setForm((s) => ({ ...s, [name]: type === 'checkbox' ? checked : value }));
  };

  const submit = (e) => {
    e.preventDefault();
    onSubmit({ ...form, display_label: form.display_label || `${form.time_start} - ${form.time_end}` });
  };

  return (
    <form onSubmit={submit} className="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <label className="text-sm font-semibold">
        Zone
        <select required name="zone_id" value={form.zone_id} onChange={handle} className={input}>
          {zones.map((z) => <option key={z.id} value={z.id}>{z.name}</option>)}
        </select>
      </label>
      <label className="text-sm font-semibold">
        Libellé
        <input name="display_label" placeholder={`${form.time_start} - ${form.time_end}`} value={form.display_label} onChange={handle} className={input} />
      </label>
      <label className="text-sm font-semibold">
        Début
        <input required type="time" name="time_start" value={form.time_start} onChange={handle} className={input} />
      </label>
      <label className="text-sm font-semibold">
        Fin
        <input required type="time" name="time_end" value={form.time_end} onChange={handle} className={input} />
      </label>
      <label className="text-sm font-semibold">
        Commandes max. par jour
        <input required type="number" min="1" name="max_orders_per_slot" value={form.max_orders_per_slot} onChange={handle} className={input} />
      </label>
      <label className="text-sm font-semibold">
        Supplément (XOF)
        <input required type="number" min="0" name="additional_fee" value={form.additional_fee} onChange={handle} className={input} />
      </label>
      <label className="inline-flex items-center gap-2 text-sm font-semibold sm:col-span-2">
        <input type="checkbox" name="is_active" checked={form.is_active} onChange={handle} />
        Créneau actif
      </label>
      <div className="sm:col-span-2 flex gap-2">
        <button type="submit" className="bg-primary text-white px-4 py-2 rounded font-semibold">Enregistrer</button>
        <button type="button" onClick={onCancel} className="border px-4 py-2 rounded">Annuler</button>
      </div>
    </form>
  );
}
