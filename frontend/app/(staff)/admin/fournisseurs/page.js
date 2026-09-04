'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { adminService } from '@/app/services';
import { withRole } from '@/app/lib/auth-hoc';
import { useToast } from '@/app/contexts/ToastContext';
import Header from '@/app/components/Header';
import { formatPrice, formatQuantity } from '@/app/lib/units';
import {
  AlertTriangle,
  ArrowLeft,
  Check,
  Loader2,
  Printer,
  RefreshCw,
  Store,
  Tags,
} from 'lucide-react';

const METHODS = [
  { value: 'especes', label: 'Espèces' },
  { value: 'mtn', label: 'MTN MoMo' },
  { value: 'moov', label: 'Moov Money' },
  { value: 'celtiis', label: 'Celtiis Cash' },
  { value: 'virement', label: 'Virement' },
];

const dateShort = (iso) =>
  iso ? new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' }) : '—';

/** « depuis 6 j » — l'ancienneté de la dette est ce qui fait agir. */
function waitingSince(iso) {
  if (!iso) return null;
  const days = Math.floor((Date.now() - new Date(iso).getTime()) / 86400000);
  if (days < 1) return 'aujourd’hui';
  return `depuis ${days} j`;
}

/**
 * Les tarifs manquants, saisis sur place.
 *
 * C'est ce qui débloque un règlement : tant qu'un article retiré n'a pas de
 * prix convenu, le total est incomplet et le point ne peut pas se faire.
 */
function TariffForm({ supplierId, onSaved }) {
  const toast = useToast();

  const [prices, setPrices] = useState(null);
  const [draft, setDraft] = useState({});
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    try {
      const res = await adminService.getSupplierPrices(supplierId);
      setPrices(res.data.data);
      setDraft(
        Object.fromEntries(res.data.data.agreed.map((row) => [row.product_id, String(row.unit_price)]))
      );
    } catch (err) {
      toast.fromApi(err, 'Tarifs illisibles.');
    }
  }, [supplierId, toast]);

  useEffect(() => {
    load();
  }, [load]);

  const submit = async (event) => {
    event.preventDefault();
    const payload = Object.entries(draft)
      .filter(([, value]) => value !== '' && Number(value) >= 0)
      .map(([productId, value]) => ({ product_id: Number(productId), unit_price: Number(value) }));

    if (payload.length === 0) return;

    setSaving(true);
    try {
      const res = await adminService.updateSupplierPrices(supplierId, payload);
      const repriced = res.data.meta?.repriced_items ?? 0;
      toast.success(
        repriced > 0
          ? `Tarifs enregistrés. ${repriced} ligne(s) déjà retirée(s) ont été chiffrées.`
          : 'Tarifs enregistrés.'
      );
      await load();
      onSaved?.();
    } catch (err) {
      toast.fromApi(err, 'Les tarifs n’ont pas pu être enregistrés.');
    } finally {
      setSaving(false);
    }
  };

  if (!prices) {
    return (
      <p className="flex items-center gap-2 text-ink-500">
        <Loader2 size={16} className="animate-spin" /> Chargement…
      </p>
    );
  }

  const rows = [...prices.missing, ...prices.agreed];

  if (rows.length === 0) {
    return <p className="text-sm text-ink-500">Aucun article retiré chez ce fournisseur pour l’instant.</p>;
  }

  return (
    <form onSubmit={submit} className="space-y-3">
      {rows.map((row) => {
        const missing = row.unit_price === null && !draft[row.product_id];

        return (
          <label key={row.product_id} className="flex items-center justify-between gap-4">
            <span className="min-w-0 flex-1">
              <span className="block truncate font-semibold text-ink-900">{row.name}</span>
              {missing && (
                <span className="text-xs font-bold text-clay-600">Tarif à convenir</span>
              )}
            </span>
            <span className="flex shrink-0 items-center gap-2">
              <input
                type="number"
                min={0}
                step={1}
                inputMode="numeric"
                value={draft[row.product_id] ?? ''}
                onChange={(e) => setDraft({ ...draft, [row.product_id]: e.target.value })}
                placeholder="0"
                className={`w-28 rounded-xl border px-3 py-2 text-right font-semibold tabular-nums text-ink-900 ${
                  missing ? 'border-clay-500' : 'border-cream-300'
                }`}
              />
              <span className="w-16 text-sm text-ink-500">XOF</span>
            </span>
          </label>
        );
      })}

      <button
        type="submit"
        disabled={saving}
        className="inline-flex w-full items-center justify-center gap-2 rounded-full bg-forest-800 px-5 py-3 text-sm font-bold text-cream transition hover:bg-forest-700 disabled:opacity-50"
      >
        {saving ? <Loader2 size={16} className="animate-spin" /> : <Tags size={16} />}
        Enregistrer les tarifs
      </button>
    </form>
  );
}

/** Le relevé d'un fournisseur, tel qu'on le lit avec lui. */
function Statement({ supplier, onBack, onSettled }) {
  const toast = useToast();

  const [data, setData] = useState(null);
  const [settling, setSettling] = useState(false);
  const [form, setForm] = useState({ method: 'especes', reference: '', note: '' });

  const load = useCallback(async () => {
    try {
      const res = await adminService.getSupplierStatement(supplier.id);
      setData(res.data.data);
    } catch (err) {
      toast.fromApi(err, 'Relevé illisible.');
    }
  }, [supplier.id, toast]);

  useEffect(() => {
    load();
  }, [load]);

  const settle = async () => {
    setSettling(true);
    try {
      await adminService.settleSupplier(supplier.id, form);
      toast.success(`Règlement enregistré pour ${supplier.name}.`);
      onSettled();
    } catch (err) {
      toast.fromApi(err, 'Le règlement a échoué.');
    } finally {
      setSettling(false);
    }
  };

  if (!data) {
    return (
      <div className="flex items-center gap-2 rounded-card border border-cream-300 bg-white p-8 text-ink-500">
        <Loader2 size={18} className="animate-spin" /> Chargement du relevé…
      </div>
    );
  }

  const blocked = data.untariffed_count > 0;

  return (
    <div className="space-y-6">
      <button
        onClick={onBack}
        className="inline-flex items-center gap-2 font-bold text-forest-800 hover:underline print:hidden"
      >
        <ArrowLeft size={17} /> Tous les fournisseurs
      </button>

      <article className="rounded-panel border border-cream-300 bg-white p-6 shadow-card sm:p-8">
        <div className="flex flex-wrap items-start justify-between gap-4 border-b-2 border-forest-800 pb-5">
          <div>
            <p className="eyebrow text-clay-500">Relevé fournisseur</p>
            <h2 className="mt-2 font-display text-2xl font-semibold text-ink-900">{supplier.name}</h2>
            <p className="mt-1 text-sm text-ink-500">
              {[supplier.stall, data.supplier.market?.name, supplier.phone].filter(Boolean).join(' · ')}
            </p>
          </div>
          <div className="text-right">
            <p className="eyebrow text-ink-300">À régler</p>
            <p className="font-display text-3xl font-semibold tabular-nums text-forest-800">
              {formatPrice(data.amount)}
            </p>
            <p className="mt-1 text-sm text-ink-500">
              {dateShort(data.period.from)} — {dateShort(data.period.to)}
            </p>
          </div>
        </div>

        <div className="mt-6 overflow-x-auto">
          <table className="w-full min-w-[520px] border-collapse text-sm">
            <thead>
              <tr className="border-b border-cream-300 text-left">
                <th className="pb-2 font-bold text-ink-500">Article</th>
                <th className="pb-2 text-right font-bold text-ink-500">Quantité</th>
                <th className="pb-2 text-right font-bold text-ink-500">Prix convenu</th>
                <th className="pb-2 text-right font-bold text-ink-500">Montant</th>
              </tr>
            </thead>
            <tbody>
              {data.lines.map((line) => (
                <tr key={line.product_id} className="border-b border-cream-200">
                  <td className="py-3 pr-3">
                    <span className="font-semibold text-ink-900">{line.name}</span>
                    <span className="block text-xs text-ink-500">
                      {line.order_count} commande{line.order_count > 1 ? 's' : ''}
                    </span>
                  </td>
                  <td className="py-3 text-right tabular-nums text-ink-700">
                    {formatQuantity(line.quantity, line.price_type)}
                  </td>
                  <td className="py-3 text-right tabular-nums text-ink-700">
                    {line.tariffed ? (
                      line.unit_price === null ? (
                        <span className="text-xs text-ink-300">tarif modifié</span>
                      ) : (
                        formatPrice(line.unit_price)
                      )
                    ) : (
                      <span className="text-xs font-bold text-clay-600">à convenir</span>
                    )}
                  </td>
                  <td className="py-3 text-right font-semibold tabular-nums text-ink-900">
                    {line.tariffed ? formatPrice(line.total) : '—'}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="mt-5 flex items-baseline justify-between border-t-2 border-forest-800 pt-3">
          <span className="font-display text-lg font-semibold text-forest-800">Total</span>
          <span className="font-display text-2xl font-semibold tabular-nums text-forest-800">
            {formatPrice(data.amount)}
          </span>
        </div>
      </article>

      {blocked ? (
        <section className="rounded-panel border-2 border-clay-500/50 bg-white p-6 shadow-card print:hidden">
          <p className="flex items-start gap-2.5 font-bold text-clay-600">
            <AlertTriangle size={18} className="mt-0.5 shrink-0" />
            {data.untariffed_count} article(s) sans tarif convenu
          </p>
          <p className="mt-2 text-sm leading-6 text-ink-500">
            Le total ci-dessus est incomplet. Convenez le prix avec le fournisseur et saisissez-le
            ici&nbsp;: les lignes déjà retirées seront chiffrées automatiquement.
          </p>
          <div className="mt-5">
            <TariffForm supplierId={supplier.id} onSaved={load} />
          </div>
        </section>
      ) : (
        <section className="rounded-panel border border-cream-300 bg-white p-6 shadow-card print:hidden">
          <h3 className="font-display text-xl font-semibold text-ink-900">Enregistrer le règlement</h3>
          <p className="mt-1.5 text-sm leading-6 text-ink-500">
            Les {data.item_count} lignes couvertes seront rattachées à ce règlement et ne
            réapparaîtront plus jamais dans un point. Cette action ne s’annule pas.
          </p>

          <div className="mt-5 grid gap-3 sm:grid-cols-2">
            <label className="block">
              <span className="eyebrow text-ink-500">Moyen</span>
              <select
                value={form.method}
                onChange={(e) => setForm({ ...form, method: e.target.value })}
                className="mt-1.5 w-full rounded-xl border border-cream-300 bg-white px-3.5 py-2.5 text-[15px] text-ink-900"
              >
                {METHODS.map((method) => (
                  <option key={method.value} value={method.value}>
                    {method.label}
                  </option>
                ))}
              </select>
            </label>
            <label className="block">
              <span className="eyebrow text-ink-500">Référence</span>
              <input
                value={form.reference}
                onChange={(e) => setForm({ ...form, reference: e.target.value })}
                placeholder="Reçu, SMS, n° de transfert…"
                className="mt-1.5 w-full rounded-xl border border-cream-300 bg-white px-3.5 py-2.5 text-[15px] text-ink-900 placeholder:text-ink-300"
              />
            </label>
          </div>

          <div className="mt-4 flex flex-wrap items-center gap-3">
            <button
              onClick={settle}
              disabled={settling || data.amount === 0}
              className="inline-flex items-center gap-2 rounded-full bg-gold-500 px-6 py-3 font-bold text-forest-900 shadow-glow transition hover:bg-gold-400 disabled:bg-ink-300 disabled:text-white disabled:shadow-none"
            >
              {settling ? <Loader2 size={17} className="animate-spin" /> : <Check size={17} />}
              J’ai réglé {formatPrice(data.amount)}
            </button>
            <button
              onClick={() => window.print()}
              className="inline-flex items-center gap-2 text-sm font-semibold text-ink-500 transition hover:text-forest-800"
            >
              <Printer size={16} /> Imprimer le relevé
            </button>
          </div>
        </section>
      )}

      {data.history.length > 0 && (
        <section className="rounded-panel border border-cream-300 bg-white p-6 shadow-card print:hidden">
          <h3 className="font-display text-lg font-semibold text-ink-900">Règlements précédents</h3>
          <ul className="mt-4 divide-y divide-cream-200">
            {data.history.map((entry) => (
              <li key={entry.id} className="flex flex-wrap items-baseline justify-between gap-2 py-3">
                <span className="text-sm text-ink-500">
                  {dateShort(entry.period_start)} — {dateShort(entry.period_end)} ·{' '}
                  {entry.item_count} ligne{entry.item_count > 1 ? 's' : ''}
                  {entry.settled_by && ` · par ${entry.settled_by}`}
                </span>
                <span className="font-semibold tabular-nums text-ink-900">
                  {formatPrice(entry.amount)}
                </span>
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  );
}

/**
 * Le point avec les fournisseurs partenaires.
 *
 * Le préparateur se sert et ne paie rien : c'est ici, et nulle part ailleurs,
 * qu'on peut savoir ce qu'Axizán doit à chaque étal. Sans cet écran la dette
 * grossissait sans que personne puisse la chiffrer.
 */
function SuppliersPage() {
  const toast = useToast();

  const [suppliers, setSuppliers] = useState([]);
  const [meta, setMeta] = useState({});
  const [openId, setOpenId] = useState(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    try {
      const res = await adminService.getSuppliers();
      setSuppliers(res.data.data);
      setMeta(res.data.meta);
    } catch (err) {
      toast.fromApi(err, 'Chargement impossible.');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    load();
  }, [load]);

  const open = suppliers.find((supplier) => supplier.id === openId);

  return (
    <>
      <Header />

      <div className="relative overflow-hidden bg-forest-900 cowrie-field grain print:hidden">
        <div className="relative mx-auto max-w-5xl px-4 py-10 sm:py-12">
          <Link
            href="/admin"
            className="inline-flex items-center gap-2 text-sm font-bold text-cream/70 hover:text-cream"
          >
            <ArrowLeft size={15} /> Administration
          </Link>
          <p className="eyebrow mt-5 text-gold-500">Point fournisseur</p>
          <h1 className="mt-2 font-display text-display-sm text-cream">Ce que nous devons</h1>
          <p className="mt-3 max-w-xl text-[15px] leading-7 text-cream-300">
            Nos préparateurs se servent chez nos partenaires sans payer. Voici la contrepartie.
          </p>

          <dl className="mt-8 flex flex-wrap gap-x-10 gap-y-4">
            {[
              ['Total dû', formatPrice(meta.total_due || 0)],
              ['Fournisseurs', meta.supplier_count || 0],
              ['Bloqués par un tarif', meta.blocked_count || 0],
            ].map(([label, value]) => (
              <div key={label}>
                <dt className="eyebrow text-cream-300/70">{label}</dt>
                <dd className="mt-1 font-display text-2xl font-semibold tabular-nums text-cream">
                  {value}
                </dd>
              </div>
            ))}
          </dl>
        </div>
      </div>

      <main className="mx-auto max-w-5xl px-4 py-10">
        {open ? (
          <Statement
            supplier={open}
            onBack={() => setOpenId(null)}
            onSettled={() => {
              setOpenId(null);
              load();
            }}
          />
        ) : (
          <>
            <div className="mb-4 flex items-center justify-between gap-4">
              <h2 className="font-display text-xl font-semibold text-ink-900">Étals à régler</h2>
              <button
                onClick={load}
                className="inline-flex items-center gap-1.5 text-sm font-semibold text-ink-500 transition hover:text-forest-800"
              >
                <RefreshCw size={15} /> Actualiser
              </button>
            </div>

            {loading ? (
              <div className="flex items-center gap-2 rounded-card border border-cream-300 bg-white p-8 text-ink-500">
                <Loader2 size={18} className="animate-spin" /> Chargement…
              </div>
            ) : suppliers.length === 0 ? (
              <div className="rounded-card border border-dashed border-cream-300 bg-white p-10 text-center">
                <Check size={28} className="mx-auto text-forest-600" />
                <p className="mt-3 font-display text-lg font-semibold text-ink-900">Tout est réglé</p>
                <p className="mt-1 text-sm text-ink-500">
                  Aucun retrait en attente de règlement chez nos partenaires.
                </p>
              </div>
            ) : (
              <ul className="space-y-3">
                {suppliers.map((supplier) => (
                  <li key={supplier.id}>
                    <button
                      onClick={() => setOpenId(supplier.id)}
                      className="flex w-full flex-wrap items-center justify-between gap-4 rounded-card border border-cream-300 bg-white p-5 text-left shadow-card transition hover:border-forest-600"
                    >
                      <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="font-display text-lg font-semibold text-ink-900">
                            {supplier.name}
                          </span>
                          {supplier.untariffed_count > 0 && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-clay-200 px-2.5 py-0.5 text-xs font-bold text-clay-600">
                              <AlertTriangle size={12} /> {supplier.untariffed_count} sans tarif
                            </span>
                          )}
                        </div>
                        <p className="mt-1 flex items-center gap-1.5 text-sm text-ink-500">
                          <Store size={14} />
                          {[supplier.stall, supplier.market?.name].filter(Boolean).join(' · ')}
                          {supplier.oldest_collected_at &&
                            ` · en attente ${waitingSince(supplier.oldest_collected_at)}`}
                        </p>
                      </div>

                      <div className="text-right">
                        <p className="font-display text-xl font-semibold tabular-nums text-forest-800">
                          {formatPrice(supplier.amount)}
                        </p>
                        <p className="text-xs text-ink-500">
                          {supplier.item_count} ligne{supplier.item_count > 1 ? 's' : ''}
                        </p>
                      </div>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </>
        )}
      </main>

      {/* Le relevé se lit assis avec le fournisseur, souvent sur papier. */}
      <style jsx global>{`
        @media print {
          @page {
            margin: 16mm;
          }
          body {
            background: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
        }
      `}</style>
    </>
  );
}

export default withRole(SuppliersPage, ['admin']);
