'use client';

import { useState } from 'react';

const input = 'border rounded px-3 py-2 w-full';

export default function ZoneForm({ initial = {}, onSubmit, onCancel }) {
  const [form, setForm] = useState({
    name: initial.name || '',
    base_delivery_fee: initial.base_delivery_fee ?? 500,
    min_order_amount: initial.min_order_amount ?? 0,
    small_order_fee: initial.small_order_fee ?? 0,
    small_order_action: initial.small_order_action || 'apply_fee',
    free_delivery_threshold: initial.free_delivery_threshold ?? '',
    is_active: initial.is_active ?? true,
  });

  const handle = (e) => {
    const { name, value, type, checked } = e.target;
    setForm((s) => ({ ...s, [name]: type === 'checkbox' ? checked : value }));
  };

  const submit = (e) => {
    e.preventDefault();
    // Champ vide = pas de seuil de livraison gratuite.
    onSubmit({ ...form, free_delivery_threshold: form.free_delivery_threshold === '' ? null : form.free_delivery_threshold });
  };

  return (
    <form onSubmit={submit} className="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <label className="sm:col-span-2 text-sm font-semibold">
        Nom de la zone
        <input required name="name" value={form.name} onChange={handle} className={input} />
      </label>
      <label className="text-sm font-semibold">
        Frais de livraison (XOF)
        <input required name="base_delivery_fee" type="number" min="0" value={form.base_delivery_fee} onChange={handle} className={input} />
      </label>
      <label className="text-sm font-semibold">
        Livraison offerte dès (XOF)
        <input name="free_delivery_threshold" type="number" min="0" placeholder="Jamais" value={form.free_delivery_threshold} onChange={handle} className={input} />
      </label>
      <label className="text-sm font-semibold">
        Minimum de commande (XOF)
        <input required name="min_order_amount" type="number" min="0" value={form.min_order_amount} onChange={handle} className={input} />
      </label>
      <label className="text-sm font-semibold">
        Sous le minimum
        <select name="small_order_action" value={form.small_order_action} onChange={handle} className={input}>
          <option value="apply_fee">Ajouter des frais</option>
          <option value="block">Refuser la commande</option>
        </select>
      </label>
      {form.small_order_action === 'apply_fee' && (
        <label className="text-sm font-semibold">
          Frais petite commande (XOF)
          <input required name="small_order_fee" type="number" min="0" value={form.small_order_fee} onChange={handle} className={input} />
        </label>
      )}
      <label className="inline-flex items-center gap-2 text-sm font-semibold sm:col-span-2">
        <input type="checkbox" name="is_active" checked={form.is_active} onChange={handle} />
        Zone active
      </label>
      <div className="sm:col-span-2 flex gap-2">
        <button type="submit" className="bg-primary text-white px-4 py-2 rounded font-semibold">Enregistrer</button>
        <button type="button" onClick={onCancel} className="border px-4 py-2 rounded">Annuler</button>
      </div>
    </form>
  );
}
