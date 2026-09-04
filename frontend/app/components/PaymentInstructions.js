'use client';

import { useState } from 'react';
import { useToast } from '@/app/contexts/ToastContext';
import { orderService } from '@/app/services';
import { formatPrice } from '@/app/lib/units';
import { Check, Copy, Loader2, ShieldCheck } from 'lucide-react';
import FedapayButton from '@/app/components/FedapayButton';

/**
 * Paiement en ligne : le client règle dans le module FedaPay, l'opérateur
 * confirme, et la commande part sans intervention humaine.
 */
function OnlinePayment({ order, fedapay, onSettled }) {
  return (
    <section className="overflow-hidden rounded-panel border-2 border-gold-500/50 bg-white shadow-card">
      <div className="border-b border-ink-900/8 bg-gold-500/10 px-6 py-4">
        <p className="eyebrow text-clay-500">Paiement en attente</p>
        <h2 className="mt-2 font-display text-2xl font-semibold text-forest-800">
          Réglez pour lancer vos courses
        </h2>
        <p className="mt-1.5 text-sm leading-6 text-ink-500">
          Nous n’allons chercher vos articles qu’une fois le paiement confirmé par l’opérateur.
        </p>
      </div>

      <div className="p-6">
        <FedapayButton
          order={order}
          publicKey={fedapay.public_key}
          sandbox={fedapay.sandbox}
          onSettled={onSettled}
        />
      </div>
    </section>
  );
}

export default function PaymentInstructions({ order, methods, delay, fedapay, onDeclared }) {
  const toast = useToast();
  const payment = order.payment;

  const [reference, setReference] = useState('');
  const [provider, setProvider] = useState(payment?.payment_provider || '');
  const [phone, setPhone] = useState(payment?.phone_number || '');
  const [saving, setSaving] = useState(false);
  const [copied, setCopied] = useState(null);

  const available = methods.filter((m) => m.enabled);
  const chosen = available.find((m) => m.code === provider) || available[0];
  const declared = Boolean(payment?.payer_declared_reference);

  /*
   * Deux régimes, exclusifs. Afficher les deux embrouillerait : on ne montre
   * que celui qui s'applique.
   *
   *  - FedaPay actif : l'opérateur confirme, rien à saisir.
   *  - Sinon : mode déclaratif — le client envoie l'argent au numéro marchand
   *    et déclare sa référence, qu'un administrateur vérifie.
   *
   * Le test vient APRÈS les hooks : les appeler conditionnellement casserait
   * l'ordre attendu par React d'un rendu à l'autre.
   */
  if (fedapay?.public_key) {
    return <OnlinePayment order={order} fedapay={fedapay} onSettled={onDeclared} />;
  }

  const copy = async (text, key) => {
    try {
      await navigator.clipboard.writeText(text);
      setCopied(key);
      setTimeout(() => setCopied(null), 2000);
    } catch {
      // Presse-papiers refuse (contexte non securise) : le texte reste
      // selectionnable a la main, rien de casse.
      toast.info('Copie impossible ici. Sélectionnez le texte pour le copier.');
    }
  };

  const submit = async (event) => {
    event.preventDefault();
    setSaving(true);
    try {
      await orderService.declarePayment(order.id, {
        reference: reference.trim(),
        payment_provider: chosen?.code,
        payer_phone: phone || undefined,
      });
      toast.success('Référence enregistrée. Nous vérifions et validons votre commande.');
      setReference('');
      onDeclared?.();
    } catch (err) {
      toast.fromApi(err, 'La référence n’a pas pu être enregistrée.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <section className="overflow-hidden rounded-panel border-2 border-gold-500/50 bg-white shadow-card">
      <div className="border-b border-ink-900/8 bg-gold-500/10 px-6 py-4">
        <p className="eyebrow text-clay-500">Paiement en attente</p>
        <h2 className="mt-2 font-display text-2xl font-semibold text-forest-800">
          Envoyez {formatPrice(order.total)} pour lancer vos courses
        </h2>
        <p className="mt-1.5 text-sm leading-6 text-ink-500">
          Nous n’achetons au marché qu’une fois le paiement vérifié.
        </p>
      </div>

      <div className="space-y-6 p-6">
        {available.length > 1 && (
          <div className="flex flex-wrap gap-2">
            {available.map((method) => (
              <button
                key={method.code}
                type="button"
                onClick={() => setProvider(method.code)}
                className={`rounded-full border px-4 py-2 text-sm font-bold transition ${
                  chosen?.code === method.code
                    ? 'border-forest-800 bg-forest-800 text-cream'
                    : 'border-ink-900/12 text-ink-700 hover:bg-cream-100'
                }`}
              >
                {method.label}
              </button>
            ))}
          </div>
        )}

        {chosen && (
          <ol className="space-y-4">
            <li className="flex gap-3.5">
              <span className="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-forest-800 text-sm font-bold text-cream">
                1
              </span>
              <div className="min-w-0 flex-1">
                <p className="font-bold text-ink-900">Composez {chosen.ussd} sur votre téléphone</p>
                <p className="mt-0.5 text-sm text-ink-500">Puis choisissez « Transfert d’argent ».</p>
              </div>
            </li>

            <li className="flex gap-3.5">
              <span className="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-forest-800 text-sm font-bold text-cream">
                2
              </span>
              <div className="min-w-0 flex-1">
                <p className="font-bold text-ink-900">Envoyez au numéro Axizán</p>
                <div className="mt-2 space-y-2">
                  {[
                    ['Numéro', chosen.merchant_number, 'number'],
                    ['Montant', String(Math.round(Number(order.total))), 'amount'],
                    ['Motif', order.order_number, 'ref'],
                  ].map(([label, val, key]) => (
                    <div
                      key={key}
                      className="flex items-center justify-between gap-3 rounded-card bg-cream-100 px-4 py-2.5"
                    >
                      <span className="text-sm font-semibold text-ink-500">{label}</span>
                      <span className="flex items-center gap-2 font-mono text-sm font-bold text-ink-900">
                        {key === 'amount' ? formatPrice(val) : val}
                        <button
                          type="button"
                          onClick={() => copy(val, key)}
                          aria-label={`Copier ${label}`}
                          className="grid h-7 w-7 place-items-center rounded-full text-ink-300 transition hover:bg-white hover:text-forest-800"
                        >
                          {copied === key ? <Check size={14} strokeWidth={3} /> : <Copy size={14} />}
                        </button>
                      </span>
                    </div>
                  ))}
                </div>
                <p className="mt-2 text-sm text-ink-500">
                  Compte au nom de <strong className="text-ink-900">{chosen.merchant_name}</strong>.
                </p>
              </div>
            </li>

            <li className="flex gap-3.5">
              <span className="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-forest-800 text-sm font-bold text-cream">
                3
              </span>
              <div className="min-w-0 flex-1">
                <p className="font-bold text-ink-900">Donnez-nous la référence du SMS</p>

                {declared ? (
                  <p className="mt-2 flex items-start gap-2.5 rounded-card bg-forest-500/10 px-4 py-3 text-sm leading-6 text-forest-700">
                    <ShieldCheck size={17} className="mt-0.5 shrink-0" />
                    <span>
                      Référence <strong className="font-mono">{payment.payer_declared_reference}</strong> reçue.
                      Nous vérifions{delay ? ` — ${delay}` : ''}. Vous serez prévenu dès la validation.
                    </span>
                  </p>
                ) : (
                  <form onSubmit={submit} className="mt-2 space-y-3">
                    <div className="grid gap-3 sm:grid-cols-2">
                      <input
                        required
                        value={reference}
                        onChange={(e) => setReference(e.target.value)}
                        placeholder="Référence de la transaction"
                        className="w-full rounded-card border border-ink-900/12 bg-white px-4 py-3 font-medium text-ink-900 outline-none transition placeholder:text-ink-300 focus:border-forest-600"
                      />
                      <input
                        value={phone}
                        onChange={(e) => setPhone(e.target.value)}
                        placeholder="Numéro qui a payé (facultatif)"
                        className="w-full rounded-card border border-ink-900/12 bg-white px-4 py-3 font-medium text-ink-900 outline-none transition placeholder:text-ink-300 focus:border-forest-600"
                      />
                    </div>
                    <button
                      type="submit"
                      disabled={saving}
                      className="inline-flex items-center gap-2 rounded-full bg-gold-500 px-6 py-3 font-bold text-forest-950 transition hover:bg-gold-400 disabled:opacity-60"
                    >
                      {saving && <Loader2 size={16} className="animate-spin" />}
                      J’ai payé
                    </button>
                  </form>
                )}
              </div>
            </li>
          </ol>
        )}
      </div>
    </section>
  );
}
