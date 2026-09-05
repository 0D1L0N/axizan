'use client';

import { useState } from 'react';
import ProductImage from '@/app/components/ProductImage';
import { formatQuantity } from '@/app/lib/units';
import { Check, Loader2, Replace, X } from 'lucide-react';

/**
 * Une ligne de la liste de retrait.
 *
 * Contraintes de terrain : le preparateur est debout chez le fournisseur, une
 * main tenant un panier, en plein soleil, avec du reseau par intermittence.
 * D'ou : cibles tactiles larges, etats lisibles au premier coup d'oeil, aucune
 * saisie au clavier sauf pour choisir un remplacement.
 *
 * Il ne saisit aucun prix — il retire en compte. En revanche le fournisseur
 * est obligatoire : sans lui, le point avec les partenaires est impossible.
 */
export default function PickListItem({ item, suppliers, products, onUpdate, disabled }) {
  const [busy, setBusy] = useState(null);
  const [choosingReplacement, setChoosingReplacement] = useState(false);
  const [search, setSearch] = useState('');

  const status = item.collection_status || 'pending';
  const canReplace = item.substitution_preference !== 'remove';

  // Le serveur propose un fournisseur en rapprochant la catégorie du produit
  // de la spécialité de l'étal. Le préparateur confirme au lieu de choisir.
  const suggestedId = item.suggested_supplier_id ? String(item.suggested_supplier_id) : '';
  const supplierId = item.supplier_id ? String(item.supplier_id) : suggestedId;
  const isSuggestion = !item.supplier_id && Boolean(suggestedId);

  const apply = async (payload, key) => {
    setBusy(key);
    try {
      await onUpdate(item.id, payload);
      setChoosingReplacement(false);
      setSearch('');
    } finally {
      setBusy(null);
    }
  };

  const surface = {
    pending: 'border-ink-900/12 bg-white',
    collected: 'border-forest-600/45 bg-forest-500/8',
    replaced: 'border-gold-500/60 bg-gold-500/10',
    removed: 'border-ink-900/12 bg-cream-200 opacity-70',
  }[status];

  const matches = search.trim().length > 0
    ? products.filter((p) => p.name.toLowerCase().includes(search.trim().toLowerCase())).slice(0, 8)
    : products.slice(0, 8);

  return (
    <li className={`rounded-panel border-2 p-4 transition ${surface}`}>
      <div className="flex items-start gap-3.5">
        <ProductImage
          product={item.product || { name: 'Article' }}
          size="thumb"
          emojiSize="text-2xl"
          className="h-16 w-16 shrink-0 rounded-card"
          sizes="64px"
        />

        <div className="min-w-0 flex-1">
          <p className="text-lg font-bold leading-tight text-ink-900">
            {item.product?.name || `Article #${item.product_id}`}
          </p>
          <p className="mt-0.5 text-2xl font-black tabular-nums text-forest-800">
            {formatQuantity(item.quantity, item.product?.price_type)}
          </p>

          {status === 'replaced' && item.replacement_product && (
            <p className="mt-1.5 inline-flex items-center gap-1.5 rounded-full bg-gold-500/25 px-3 py-1 text-sm font-bold text-forest-800">
              <Replace size={14} /> Remplacé par {item.replacement_product.name}
            </p>
          )}

          {status === 'removed' &&
            // Distinguer les deux : si le client a refusé le remplacement, le
            // préparateur a le produit en main et doit le reposer.
            (item.substitution_response === 'refused' ? (
              <p className="mt-1.5 inline-flex items-center gap-1.5 rounded-full bg-clay-200 px-3 py-1 text-sm font-bold text-clay-600">
                <X size={14} strokeWidth={3} /> Le client a refusé — reposez-le
              </p>
            ) : (
              <p className="mt-1.5 text-sm font-bold text-ink-500">Retiré — indisponible</p>
            ))}

          {/* La consigne du client, visible avant de decider. */}
          {status === 'pending' && (
            <p className="mt-1.5 text-sm font-semibold text-ink-500">
              Si absent&nbsp;: {canReplace ? 'remplacer' : 'retirer'}
            </p>
          )}
        </div>
      </div>

      {/* ---- Fournisseur : obligatoire des que l'article est pris ---- */}
      {status !== 'removed' && (
        <label className="mt-4 block">
          <span className="text-xs font-bold uppercase tracking-[0.18em] text-ink-500">
            Fournisseur {isSuggestion && <span className="text-forest-600">· proposé</span>}
          </span>
          <select
            value={supplierId}
            disabled={disabled || busy !== null}
            onChange={(e) =>
              apply(
                { collection_status: status === 'pending' ? 'pending' : status, supplier_id: e.target.value || null,
                  ...(status === 'replaced' ? { replacement_product_id: item.replacement_product_id } : {}) },
                'supplier'
              )
            }
            className={`mt-1.5 h-14 w-full rounded-card border-2 bg-white px-4 text-base font-bold text-ink-900 outline-none ${
              !supplierId && status !== 'pending' ? 'border-clay-500' : 'border-ink-900/15'
            }`}
          >
            <option value="">— chez qui ? —</option>
            {suppliers.map((supplier) => (
              <option key={supplier.id} value={supplier.id}>
                {supplier.name}{supplier.stall ? ` · ${supplier.stall}` : ''}
              </option>
            ))}
          </select>
          {!supplierId && status !== 'pending' && (
            <span className="mt-1 block text-sm font-bold text-clay-600">
              Obligatoire pour marquer la commande prête.
            </span>
          )}
        </label>
      )}

      {/* ---- Choix du remplacement ---- */}
      {choosingReplacement && (
        <div className="mt-4 rounded-card border-2 border-gold-500/50 bg-white p-3">
          <input
            autoFocus
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Chercher un produit…"
            className="h-14 w-full rounded-card border-2 border-ink-900/15 px-4 text-base font-semibold outline-none"
          />
          <ul className="mt-2 max-h-64 space-y-1.5 overflow-y-auto">
            {matches.map((product) => (
              <li key={product.id}>
                <button
                  onClick={() =>
                    apply(
                      {
                        collection_status: 'replaced',
                        supplier_id: supplierId || null,
                        replacement_product_id: product.id,
                      },
                      'replace'
                    )
                  }
                  className="w-full rounded-card px-4 py-3.5 text-left text-base font-bold text-ink-900 transition hover:bg-cream-100"
                >
                  {product.name}
                </button>
              </li>
            ))}
            {matches.length === 0 && (
              <li className="px-4 py-3 text-ink-500">Aucun produit trouvé.</li>
            )}
          </ul>
          <button
            onClick={() => setChoosingReplacement(false)}
            className="mt-2 w-full rounded-card py-3 font-bold text-ink-500"
          >
            Annuler
          </button>
        </div>
      )}

      {/* ---- Actions : trois cibles larges, pas de menu cache ---- */}
      {!choosingReplacement && (
        <div className="mt-4 grid grid-cols-3 gap-2">
          <button
            disabled={disabled || busy !== null}
            onClick={() => apply({ collection_status: 'collected', supplier_id: supplierId || null }, 'collect')}
            className={`flex h-16 flex-col items-center justify-center gap-1 rounded-card border-2 text-sm font-bold transition disabled:opacity-50 ${
              status === 'collected'
                ? 'border-forest-800 bg-forest-800 text-cream'
                : 'border-ink-900/15 bg-white text-ink-900'
            }`}
          >
            {busy === 'collect' ? <Loader2 size={20} className="animate-spin" /> : <Check size={20} strokeWidth={3} />}
            Pris
          </button>

          <button
            disabled={disabled || busy !== null || !canReplace}
            onClick={() => setChoosingReplacement(true)}
            title={canReplace ? undefined : 'Le client a demandé de retirer, pas de remplacer'}
            className={`flex h-16 flex-col items-center justify-center gap-1 rounded-card border-2 text-sm font-bold transition disabled:opacity-40 ${
              status === 'replaced'
                ? 'border-gold-600 bg-gold-500 text-forest-950'
                : 'border-ink-900/15 bg-white text-ink-900'
            }`}
          >
            <Replace size={20} />
            Remplacer
          </button>

          <button
            disabled={disabled || busy !== null}
            onClick={() => apply({ collection_status: 'removed' }, 'remove')}
            className={`flex h-16 flex-col items-center justify-center gap-1 rounded-card border-2 text-sm font-bold transition disabled:opacity-50 ${
              status === 'removed'
                ? 'border-clay-600 bg-clay-500 text-white'
                : 'border-ink-900/15 bg-white text-ink-900'
            }`}
          >
            {busy === 'remove' ? <Loader2 size={20} className="animate-spin" /> : <X size={20} strokeWidth={3} />}
            Absent
          </button>
        </div>
      )}
    </li>
  );
}
