'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { useToast } from '@/app/contexts/ToastContext';
import { orderService } from '@/app/services';
import { formatPrice } from '@/app/lib/units';
import { Check, Loader2, Smartphone } from 'lucide-react';

const OPERATORS = [
  { value: 'mtn', label: 'MTN MoMo' },
  { value: 'moov', label: 'Moov Money' },
  { value: 'celtiis', label: 'Celtiis Cash' },
];

/** Cadence de sondage pendant que le client compose son code. */
const POLL_MS = 4000;

/** Au-delà, la demande a expiré chez l'opérateur — inutile d'attendre encore. */
const GIVE_UP_MS = 3 * 60 * 1000;

/**
 * Paiement Mobile Money par demande poussée.
 *
 * Le client saisit son numéro, son téléphone sonne, il tape son code. Pas de
 * page de paiement à charger, pas de formulaire de carte : c'est le parcours
 * que tout le monde connaît ici, et le seul qui tienne sur un téléphone
 * d'entrée de gamme avec une connexion capricieuse.
 *
 * Rien de ce qui se passe sur cet écran ne vaut preuve de paiement. On envoie
 * la demande, puis on redemande au serveur où il en est — c'est lui qui
 * interroge l'opérateur.
 */
export default function MomoPushForm({ order, sandbox, onSettled }) {
  const toast = useToast();

  const [provider, setProvider] = useState(order.payment?.payment_provider || 'mtn');
  const [phone, setPhone] = useState(order.payment?.phone_number || '');
  const [sending, setSending] = useState(false);
  const [waiting, setWaiting] = useState(false);

  const startedAt = useRef(null);

  const send = async (event) => {
    event.preventDefault();
    setSending(true);
    try {
      await orderService.requestPayment(order.id, provider, phone.trim());
      startedAt.current = Date.now();
      setWaiting(true);
      toast.info('Demande envoyée. Confirmez sur votre téléphone.', { duration: 10000 });
    } catch (err) {
      toast.fromApi(err, 'La demande n’a pas pu être envoyée.');
    } finally {
      setSending(false);
    }
  };

  const check = useCallback(async () => {
    try {
      const res = await orderService.getPaymentStatus(order.id);

      if (res.data.data.payment_status === 'completed') {
        setWaiting(false);
        toast.success('Paiement confirmé. Nous partons chercher vos articles.');
        onSettled?.();
        return;
      }

      // Passé ce délai la demande a expiré côté opérateur. Continuer à
      // tourner laisserait le client devant une roue qui ne s'arrête jamais.
      if (Date.now() - startedAt.current > GIVE_UP_MS) {
        setWaiting(false);
        toast.error(
          'Nous n’avons pas reçu la confirmation. Si votre compte a été débité, votre commande se débloquera seule ; sinon, réessayez.',
          { duration: 15000 }
        );
      }
    } catch {
      // Un sondage raté n'est rien : le suivant repartira.
    }
  }, [order.id, toast, onSettled]);

  useEffect(() => {
    if (!waiting) return undefined;

    const timer = setInterval(check, POLL_MS);
    return () => clearInterval(timer);
  }, [waiting, check]);

  if (waiting) {
    return (
      <div className="rounded-card bg-cream-100 p-6 text-center">
        <Loader2 size={30} className="mx-auto animate-spin text-forest-600" />
        <p className="mt-4 font-display text-lg font-semibold text-forest-800">
          Regardez votre téléphone
        </p>
        <p className="mt-2 leading-7 text-ink-500">
          Une demande de <strong className="text-ink-900">{formatPrice(order.total)}</strong> vient
          d’être envoyée au {phone}. Saisissez votre code secret pour la valider.
        </p>
        <button
          onClick={() => setWaiting(false)}
          className="mt-5 text-sm font-semibold text-ink-500 underline underline-offset-4"
        >
          Changer de numéro
        </button>
      </div>
    );
  }

  return (
    <form onSubmit={send} className="space-y-4">
      <div className="grid grid-cols-3 gap-2">
        {OPERATORS.map((operator) => (
          <button
            key={operator.value}
            type="button"
            onClick={() => setProvider(operator.value)}
            aria-pressed={provider === operator.value}
            className={`rounded-xl border-2 px-2 py-3 text-sm font-bold transition ${
              provider === operator.value
                ? 'border-forest-800 bg-forest-800 text-cream'
                : 'border-cream-300 bg-white text-ink-500 hover:border-forest-600'
            }`}
          >
            {operator.label}
          </button>
        ))}
      </div>

      <label className="block">
        <span className="text-sm font-bold text-ink-700">Numéro à débiter</span>
        <input
          type="tel"
          required
          inputMode="tel"
          value={phone}
          onChange={(e) => setPhone(e.target.value)}
          placeholder="01 97 00 00 00"
          className="mt-2 w-full rounded-card border border-ink-900/12 bg-white px-4 py-3.5 text-lg font-semibold tracking-wide text-ink-900 outline-none transition placeholder:text-ink-300 focus:border-forest-600"
        />
      </label>

      <button
        type="submit"
        disabled={sending || !phone.trim()}
        className="inline-flex h-16 w-full items-center justify-center gap-3 rounded-full bg-gold-500 text-lg font-bold text-forest-950 shadow-glow transition hover:bg-gold-400 disabled:bg-ink-300 disabled:text-white disabled:shadow-none"
      >
        {sending ? (
          <>
            <Loader2 size={20} className="animate-spin" /> Envoi…
          </>
        ) : (
          <>
            <Smartphone size={20} /> Payer {formatPrice(order.total)}
          </>
        )}
      </button>

      <p className="flex items-start gap-2.5 text-sm leading-6 text-ink-500">
        <Check size={17} className="mt-0.5 shrink-0 text-forest-600" />
        <span>
          Votre téléphone sonnera pour confirmer. Vos courses partent dès que l’opérateur valide.
          {sandbox && (
            <strong className="text-clay-600">
              {' '}
              Mode test : 66000001 réussit, 66000000 échoue.
            </strong>
          )}
        </span>
      </p>
    </form>
  );
}
