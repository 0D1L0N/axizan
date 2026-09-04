'use client';

import { useCallback, useEffect, useState } from 'react';
import { useToast } from '@/app/contexts/ToastContext';
import { orderService } from '@/app/services';
import { formatPrice } from '@/app/lib/units';
import { Loader2, ShieldCheck, Smartphone } from 'lucide-react';

const CHECKOUT_SRC = 'https://cdn.fedapay.com/checkout.js?v=1.1.7';

/**
 * Paiement Mobile Money par le module FedaPay.
 *
 * Ce que fait ce composant : ouvrir le module, recuperer l'identifiant de
 * transaction, et le transmettre a notre API.
 *
 * Ce qu'il ne fait PAS : decider que le paiement est valide. Cette page
 * tourne sur le telephone du client, elle n'est donc pas digne de confiance.
 * C'est le serveur qui interroge FedaPay et verifie le montant.
 *
 * Le webhook confirme de son cote : si le client ferme son telephone juste
 * apres avoir paye, la commande se debloque quand meme.
 */
export default function FedapayButton({ order, publicKey, sandbox, onSettled }) {
  const toast = useToast();
  const [ready, setReady] = useState(false);
  const [busy, setBusy] = useState(false);

  // Chargement du script, une seule fois pour toute l'application.
  useEffect(() => {
    if (typeof window === 'undefined') return undefined;

    if (window.FedaPay) {
      setReady(true);
      return undefined;
    }

    const existing = document.querySelector('script[data-fedapay]');
    const script = existing || document.createElement('script');

    const onLoad = () => setReady(true);
    const onError = () =>
      toast.error('Le service de paiement est injoignable. Réessayez dans un instant.');

    script.addEventListener('load', onLoad);
    script.addEventListener('error', onError);

    if (!existing) {
      script.src = CHECKOUT_SRC;
      script.async = true;
      script.dataset.fedapay = 'true';
      document.body.appendChild(script);
    }

    return () => {
      script.removeEventListener('load', onLoad);
      script.removeEventListener('error', onError);
    };
  }, [toast]);

  const settle = useCallback(
    async (transactionId) => {
      setBusy(true);
      try {
        await orderService.confirmPayment(order.id, String(transactionId));
        toast.success('Paiement confirmé. Nous partons chercher vos articles.');
        onSettled?.();
      } catch (err) {
        // Le paiement a pu passer chez l'opérateur malgré cette erreur : le
        // webhook prendra le relais. On le dit plutôt que d'alarmer.
        toast.error(
          err.response?.data?.message ||
            'Nous n’avons pas pu confirmer tout de suite. Si le montant a été débité, votre commande se débloquera sous peu.',
          { duration: 12000 }
        );
      } finally {
        setBusy(false);
      }
    },
    [order.id, toast, onSettled]
  );

  const open = () => {
    if (!window.FedaPay) return;

    window.FedaPay.init({
      public_key: publicKey,
      environment: sandbox ? 'sandbox' : 'live',
      transaction: {
        amount: Math.round(Number(order.total)),
        // La description porte le numéro de commande : c'est ce qui permet au
        // webhook de rattacher la notification à la bonne commande.
        description: `Axizán ${order.order_number}`,
      },
      currency: { iso: 'XOF' },
      customer: {
        firstname: order.client?.name || 'Client',
        email: order.client?.email || undefined,
        phone_number: order.client?.phone
          ? { number: order.client.phone, country: 'bj' }
          : undefined,
      },
      custom_metadata: { order_number: order.order_number },
      onComplete(response) {
        // `reason` vaut CHECKOUT_COMPLETED quand le paiement aboutit ; on ne
        // s'y fie pas pour valider, seulement pour savoir quoi afficher.
        const id = response?.transaction?.id;

        if (id) {
          settle(id);
        } else {
          toast.info('Paiement non finalisé. Vous pouvez réessayer.');
        }
      },
    }).open();
  };

  return (
    <div>
      <button
        onClick={open}
        disabled={!ready || busy}
        className="inline-flex h-16 w-full items-center justify-center gap-3 rounded-full bg-gold-500 text-lg font-bold text-forest-950 shadow-glow transition hover:bg-gold-400 disabled:bg-ink-300 disabled:text-white disabled:shadow-none"
      >
        {busy ? (
          <>
            <Loader2 size={20} className="animate-spin" /> Confirmation…
          </>
        ) : !ready ? (
          'Chargement du paiement…'
        ) : (
          <>
            <Smartphone size={20} /> Payer {formatPrice(order.total)}
          </>
        )}
      </button>

      <p className="mt-3 flex items-start gap-2.5 text-sm leading-6 text-ink-500">
        <ShieldCheck size={17} className="mt-0.5 shrink-0 text-forest-600" />
        <span>
          MTN MoMo, Moov Money ou carte. Vos courses partent dès que l’opérateur confirme.
          {sandbox && (
            <strong className="text-clay-600"> Mode test : aucun argent n’est débité.</strong>
          )}
        </span>
      </p>
    </div>
  );
}
