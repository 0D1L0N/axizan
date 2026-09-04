'use client';

import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'next/navigation';
import Link from 'next/link';
import { orderService } from '@/app/services';
import { withAuth } from '@/app/lib/auth-hoc';
import { formatQuantity } from '@/app/lib/units';
import Logo from '@/app/components/Logo';
import { AlertTriangle, ArrowLeft, Loader2, Printer } from 'lucide-react';

const XOF = (amount) => `${Number(amount || 0).toLocaleString('fr-FR')} XOF`;

const dateLong = (iso) =>
  iso
    ? new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
    : '—';

const PROVIDERS = { mtn: 'MTN MoMo', moov: 'Moov Money', celtiis: 'Celtiis Cash' };

const LINE_STATUS = {
  collected: null,
  pending: null,
  replaced: { label: 'Remplacé', tone: 'text-gold-600' },
  removed: { label: 'Non livré', tone: 'text-clay-600' },
};

/**
 * Facture d'une commande livrée.
 *
 * Pensée pour l'impression autant que pour l'écran : au Bénin, un ménage
 * fonctionnaire qui doit justifier une dépense a besoin d'un document sur
 * papier ou d'un PDF, pas d'une page web. D'où la feuille de style `@media
 * print` — le navigateur fait le PDF, sans qu'on embarque une bibliothèque
 * de rendu côté serveur.
 */
function InvoicePage() {
  const { id } = useParams();

  const [invoice, setInvoice] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    try {
      const res = await orderService.getInvoice(id);
      setInvoice(res.data.data);
    } catch (err) {
      setError(
        err.response?.data?.message ||
          'Cette facture est introuvable.'
      );
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    load();
  }, [load]);

  if (loading) {
    return (
      <main className="grid min-h-screen place-items-center bg-cream">
        <Loader2 className="animate-spin text-forest-600" size={28} />
      </main>
    );
  }

  if (error) {
    return (
      <main className="grid min-h-screen place-items-center bg-cream p-4">
        <div className="max-w-md rounded-panel border border-cream-300 bg-white p-8 text-center shadow-card">
          <AlertTriangle className="mx-auto text-clay-500" size={30} />
          <p className="mt-4 leading-7 text-ink-700">{error}</p>
          <Link
            href={`/orders/${id}`}
            className="mt-6 inline-flex items-center gap-2 font-bold text-forest-800 hover:underline"
          >
            <ArrowLeft size={17} /> Retour à la commande
          </Link>
        </div>
      </main>
    );
  }

  const { seller, buyer, lines, totals, payment, delivery } = invoice;
  const owed = totals.undelivered > 0;

  return (
    <main className="min-h-screen bg-cream py-8 print:bg-white print:py-0">
      {/* Barre d'outils : elle ne s'imprime pas. */}
      <div className="mx-auto mb-5 flex max-w-[820px] items-center justify-between gap-4 px-4 print:hidden">
        <Link
          href={`/orders/${id}`}
          className="inline-flex items-center gap-2 font-bold text-forest-800 hover:underline"
        >
          <ArrowLeft size={17} /> Ma commande
        </Link>
        <button
          onClick={() => window.print()}
          className="inline-flex items-center gap-2 rounded-full bg-forest-800 px-5 py-2.5 text-sm font-bold text-cream transition hover:bg-forest-700"
        >
          <Printer size={16} /> Imprimer ou enregistrer en PDF
        </button>
      </div>

      <article className="mx-auto max-w-[820px] bg-white px-6 py-10 shadow-card print:max-w-none print:px-0 print:py-0 print:shadow-none sm:px-12">
        <header className="flex flex-wrap items-start justify-between gap-6 border-b-2 border-forest-800 pb-6">
          <div>
            <Logo size={40} tagline={false} />
            <p className="mt-3 text-sm leading-6 text-ink-500">
              {seller.tagline}
              <br />
              {seller.city}
              {seller.phone && (
                <>
                  <br />
                  {seller.phone}
                </>
              )}
              {seller.email && (
                <>
                  <br />
                  {seller.email}
                </>
              )}
            </p>
          </div>

          <div className="text-right">
            <p className="eyebrow text-clay-500">Facture</p>
            <p className="mt-1 font-mono text-lg font-bold text-ink-900">{invoice.number}</p>
            <p className="mt-2 text-sm text-ink-500">
              Émise le {dateLong(invoice.issued_at)}
              <br />
              Commande {invoice.order_number}
            </p>
          </div>
        </header>

        <section className="mt-7 grid gap-6 sm:grid-cols-2">
          <div>
            <p className="eyebrow text-ink-300">Livré à</p>
            <p className="mt-1.5 font-semibold text-ink-900">{buyer.name}</p>
            <p className="text-sm leading-6 text-ink-500">
              {buyer.address.join(' — ')}
              {buyer.phone && (
                <>
                  <br />
                  {buyer.phone}
                </>
              )}
            </p>
          </div>

          <div className="sm:text-right">
            <p className="eyebrow text-ink-300">Livraison</p>
            <p className="mt-1.5 font-semibold text-ink-900">{dateLong(delivery.delivered_at)}</p>
            {delivery.slot && <p className="text-sm text-ink-500">Créneau {delivery.slot}</p>}
          </div>
        </section>

        {/* Le tableau défile seul sur petit écran : la page, elle, ne bouge pas. */}
        <div className="mt-8 overflow-x-auto">
          <table className="w-full min-w-[520px] border-collapse text-sm">
            <thead>
              <tr className="border-b border-cream-300 text-left">
                <th className="pb-2 font-bold text-ink-500">Article</th>
                <th className="pb-2 text-right font-bold text-ink-500">Quantité</th>
                <th className="pb-2 text-right font-bold text-ink-500">Prix unitaire</th>
                <th className="pb-2 text-right font-bold text-ink-500">Montant</th>
              </tr>
            </thead>
            <tbody>
              {lines.map((line, index) => {
                const flag = LINE_STATUS[line.status];

                return (
                  <tr key={index} className="border-b border-cream-200 align-top">
                    <td className="py-3 pr-3">
                      <span className={line.billed ? 'text-ink-900' : 'text-ink-500 line-through'}>
                        {line.name}
                      </span>
                      {line.delivered_as && (
                        <span className="block text-xs text-ink-500">
                          Livré : {line.delivered_as}
                        </span>
                      )}
                      {flag && (
                        <span className={`block text-xs font-bold ${flag.tone}`}>{flag.label}</span>
                      )}
                    </td>
                    <td className="py-3 text-right tabular-nums text-ink-700">
                      {formatQuantity(line.quantity, line.price_type)}
                    </td>
                    <td className="py-3 text-right tabular-nums text-ink-700">{XOF(line.unit_price)}</td>
                    <td className="py-3 text-right font-semibold tabular-nums text-ink-900">
                      {XOF(line.total)}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        <section className="mt-6 flex justify-end">
          <dl className="w-full max-w-xs space-y-2 text-sm">
            <div className="flex justify-between">
              <dt className="text-ink-500">Sous-total</dt>
              <dd className="tabular-nums text-ink-900">{XOF(totals.subtotal)}</dd>
            </div>
            <div className="flex justify-between">
              <dt className="text-ink-500">Livraison</dt>
              <dd className="tabular-nums text-ink-900">{XOF(totals.delivery_fee)}</dd>
            </div>
            <div className="flex justify-between">
              <dt className="text-ink-500">Taxe (10 %)</dt>
              <dd className="tabular-nums text-ink-900">{XOF(totals.tax)}</dd>
            </div>
            <div className="flex justify-between border-t-2 border-forest-800 pt-2.5">
              <dt className="font-display text-base font-semibold text-forest-800">Total payé</dt>
              <dd className="font-display text-base font-semibold tabular-nums text-forest-800">
                {XOF(totals.total)}
              </dd>
            </div>
          </dl>
        </section>

        {/*
          Un article payé mais jamais remis est une dette, pas un détail de
          mise en page : il apparaît en clair plutôt que d'être effacé du
          décompte.
        */}
        {owed && (
          <p className="mt-6 rounded-card border border-clay-200 bg-clay-200/40 px-4 py-3 text-sm leading-6 text-clay-600">
            <strong>{XOF(totals.undelivered)}</strong> d’articles n’ont pas pu être trouvés au marché et
            ne vous ont pas été livrés. Ce montant vous est dû — notre équipe vous recontacte pour le
            remboursement.
          </p>
        )}

        <footer className="mt-8 border-t border-cream-300 pt-5 text-sm leading-6 text-ink-500">
          <p>
            <span className="font-semibold text-ink-700">Paiement</span> —{' '}
            {PROVIDERS[payment.provider] || payment.method || 'Mobile Money'}
            {payment.reference && (
              <>
                , référence <span className="font-mono text-ink-700">{payment.reference}</span>
              </>
            )}
            {payment.paid_at && <> le {dateLong(payment.paid_at)}</>}.
          </p>
          <p className="mt-2">
            Montants en francs CFA. Merci de votre confiance — {seller.name}.
          </p>
        </footer>
      </article>

      {/*
        Impression : marges de page, aucune ombre, et surtout `print-color-adjust`
        pour que les aplats et filets survivent — sans lui, la plupart des
        navigateurs impriment un document décoloré où plus rien ne se distingue.
      */}
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
    </main>
  );
}

export default withAuth(InvoicePage);
