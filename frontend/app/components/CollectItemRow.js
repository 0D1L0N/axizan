'use client';

import { useState } from 'react';

const STATUS_LABELS = {
  pending: { label: 'À acheter', className: 'bg-gray-100 text-gray-700' },
  collected: { label: 'Acheté', className: 'bg-green-100 text-green-800' },
  replaced: { label: 'Remplacé', className: 'bg-blue-100 text-blue-800' },
  removed: { label: 'Retiré', className: 'bg-orange-100 text-orange-800' },
};

/**
 * Une ligne de la liste de courses : le preparateur indique chez quel
 * fournisseur il a achete l'article, ou s'il l'a remplace / retire.
 * Sans cela, la commande ne peut pas etre marquee prete.
 */
export default function CollectItemRow({ item, suppliers, products, editable, onUpdate }) {
  const [supplierId, setSupplierId] = useState(item.supplier_id ? String(item.supplier_id) : '');
  const [replacing, setReplacing] = useState(false);
  const [replacementId, setReplacementId] = useState('');
  const [localError, setLocalError] = useState('');

  const status = STATUS_LABELS[item.collection_status] || STATUS_LABELS.pending;
  const canReplace = item.substitution_preference !== 'remove';

  const submit = (collectionStatus, extra = {}) => {
    setLocalError('');
    if (['collected', 'replaced'].includes(collectionStatus) && !supplierId) {
      setLocalError('Choisissez le fournisseur.');
      return;
    }
    onUpdate({ collection_status: collectionStatus, supplier_id: supplierId ? Number(supplierId) : undefined, ...extra });
    setReplacing(false);
  };

  return (
    <li className="py-2 text-sm">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <span className="font-medium">
          {item.product?.name || `Produit #${item.product_id}`} — {Number(item.quantity)} {item.quantity_unit}
          {item.collection_status === 'replaced' && item.replacement_product && (
            <span className="text-blue-700"> → {item.replacement_product.name}</span>
          )}
          {item.supplier && <span className="text-gray-500"> · {item.supplier.name}</span>}
        </span>
        <span className="flex items-center gap-2">
          <span className={`rounded-full px-2 py-1 text-xs font-semibold ${
            canReplace ? 'bg-blue-50 text-blue-700' : 'bg-orange-50 text-orange-700'
          }`}>
            {canReplace ? 'si absent : remplacer' : 'si absent : retirer'}
          </span>
          <span className={`rounded-full px-2 py-1 text-xs font-semibold ${status.className}`}>{status.label}</span>
        </span>
      </div>

      {editable && (
        <div className="mt-2 flex flex-wrap items-center gap-2">
          <select value={supplierId} onChange={(e) => setSupplierId(e.target.value)} className="border rounded px-2 py-1">
            <option value="">Fournisseur…</option>
            {suppliers.map((s) => (
              <option key={s.id} value={s.id}>{s.name}{s.stall ? ` (${s.stall})` : ''}</option>
            ))}
          </select>
          <button onClick={() => submit('collected')} className="rounded bg-green-600 px-3 py-1 font-semibold text-white">Acheté</button>
          {canReplace && (
            <button onClick={() => setReplacing((r) => !r)} className="rounded border px-3 py-1">Remplacer</button>
          )}
          <button onClick={() => submit('removed')} className="rounded border px-3 py-1 text-orange-700">Retirer</button>
          {item.collection_status !== 'pending' && (
            <button onClick={() => submit('pending')} className="text-xs text-gray-500 underline">annuler</button>
          )}
        </div>
      )}

      {editable && replacing && (
        <div className="mt-2 flex flex-wrap items-center gap-2">
          <select value={replacementId} onChange={(e) => setReplacementId(e.target.value)} className="border rounded px-2 py-1">
            <option value="">Produit pris à la place…</option>
            {products.filter((p) => p.id !== item.product_id).map((p) => (
              <option key={p.id} value={p.id}>{p.name}</option>
            ))}
          </select>
          <button
            disabled={!replacementId}
            onClick={() => submit('replaced', { replacement_product_id: Number(replacementId) })}
            className="rounded bg-blue-600 px-3 py-1 font-semibold text-white disabled:bg-gray-400"
          >
            Valider le remplacement
          </button>
        </div>
      )}

      {localError && <p className="mt-1 text-red-600">{localError}</p>}
    </li>
  );
}
