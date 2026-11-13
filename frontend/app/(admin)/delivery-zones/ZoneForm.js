"use client";

import { useState } from 'react';

export default function ZoneForm({ initial = {}, onSubmit, onCancel }) {
  const [form, setForm] = useState({
    name: initial.name || '',
    base_delivery_fee: initial.base_delivery_fee ?? 0,
    extra_fee_per_km: initial.extra_fee_per_km ?? 0,
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

  return (
    <form onSubmit={(e) => { e.preventDefault(); onSubmit(form); }} className="space-y-2">
      <div>
        <label className="block text-sm font-medium">Nom</label>
        <input name="name" value={form.name} onChange={handle} className="border p-1 w-full" />
      </div>

      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="block text-sm">Frais de base</label>
          <input name="base_delivery_fee" type="number" value={form.base_delivery_fee} onChange={handle} className="border p-1 w-full" />
        </div>
        <div>
          <label className="block text-sm">Frais par km</label>
          <input name="extra_fee_per_km" type="number" value={form.extra_fee_per_km} onChange={handle} className="border p-1 w-full" />
        </div>
      </div>

      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="block text-sm">Min commande</label>
          <input name="min_order_amount" type="number" value={form.min_order_amount} onChange={handle} className="border p-1 w-full" />
        </div>
        <div>
          <label className="block text-sm">Frais petite commande</label>
          <input name="small_order_fee" type="number" value={form.small_order_fee} onChange={handle} className="border p-1 w-full" />
        </div>
      </div>

      <div>
        <label className="block text-sm">Action petite commande</label>
        <select name="small_order_action" value={form.small_order_action} onChange={handle} className="border p-1 w-full">
          <option value="apply_fee">Appliquer frais</option>
          <option value="block">Bloquer commande</option>
        </select>
      </div>

      <div>
        <label className="block text-sm">Seuil livraison gratuite</label>
        <input name="free_delivery_threshold" type="number" value={form.free_delivery_threshold} onChange={handle} className="border p-1 w-full" />
      </div>

      <div className="flex items-center gap-4">
        <label className="inline-flex items-center">
          <input type="checkbox" name="is_active" checked={form.is_active} onChange={handle} />
          <span className="ml-2">Actif</span>
        </label>
      </div>

      <div className="flex gap-2">
        <button className="px-3 py-1 bg-blue-600 text-white rounded" type="submit">Enregistrer</button>
        <button type="button" className="px-3 py-1 border rounded" onClick={onCancel}>Annuler</button>
      </div>
    </form>
  );
}
