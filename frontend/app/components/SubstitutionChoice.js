'use client';

import { useState } from 'react';
import { orderService } from '@/app/services';
import { useToast } from '@/app/contexts/ToastContext';
import { Check, Loader2, Replace, X } from 'lucide-react';

/**
 * Le client répond à un remplacement, pendant qu'il a encore lieu.
 *
 * Le préparateur est à l'étal, il a trouvé autre chose, et il a continué sa
 * tournée sans attendre — l'immobiliser sur un réseau capricieux coûterait
 * plus cher que le problème. Le client voit sa décision ici, et peut la
 * refuser tant que le panier n'a pas quitté le marché.
 *
 * Ne rien faire est un choix valable : le remplacement tient.
 */
export default function SubstitutionChoice({ orderId, item, open, onAnswered }) {
  const toast = useToast();
  const [busy, setBusy] = useState(null);

  const replaced = item.collection_status === 'replaced';
  const answered = item.substitution_response;

  const answer = async (response) => {
    setBusy(response);
    try {
      const res = await orderService.respondToSubstitution(orderId, item.id, response);
      toast.success(res.data.message);
      onAnswered();
    } catch (err) {
      toast.fromApi(err, 'Votre réponse n’a pas pu être enregistrée.');
    } finally {
      setBusy(null);
    }
  };

  // Déjà tranché : on rappelle la décision sans la rouvrir.
  if (answered) {
    return (
      <p
        className={`mt-2 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-bold ${
          answered === 'refused' ? 'bg-cream-200 text-ink-500' : 'bg-forest-500/15 text-forest-700'
        }`}
      >
        {answered === 'refused' ? (
          <>
            <X size={13} /> Refusé — retiré de votre panier
          </>
        ) : (
          <>
            <Check size={13} /> Accepté
          </>
        )}
      </p>
    );
  }

  return (
    <div className="mt-3 rounded-card border-2 border-gold-500/60 bg-gold-200/25 p-4">
      <p className="flex items-start gap-2 font-bold text-forest-900">
        <Replace size={17} className="mt-0.5 shrink-0" />
        {replaced ? (
          <span>
            Introuvable au marché. Nous prenons{' '}
            <strong>{item.replacement_product?.name || 'un produit équivalent'}</strong> à la place.
          </span>
        ) : (
          <span>Introuvable au marché aujourd’hui — cet article ne sera pas dans votre panier.</span>
        )}
      </p>

      {!open ? (
        // La fenêtre s'arrête quand le panier quitte le marché : le
        // préparateur est parti, refuser promettrait ce qu'on ne peut tenir.
        <p className="mt-2 text-sm leading-6 text-ink-700">
          Votre panier a déjà quitté le marché : ce choix n’est plus modifiable.
        </p>
      ) : replaced ? (
        <>
          <p className="mt-2 text-sm leading-6 text-ink-700">
            Notre préparateur est encore sur place. Sans réponse de votre part, nous gardons ce
            remplacement.
          </p>
          <div className="mt-3 flex flex-wrap gap-2">
            <button
              onClick={() => answer('accepted')}
              disabled={busy !== null}
              className="inline-flex h-11 items-center gap-2 rounded-full bg-forest-800 px-5 text-sm font-bold text-cream transition hover:bg-forest-700 disabled:opacity-50"
            >
              {busy === 'accepted' ? <Loader2 size={15} className="animate-spin" /> : <Check size={15} />}
              Ça me va
            </button>
            <button
              onClick={() => answer('refused')}
              disabled={busy !== null}
              className="inline-flex h-11 items-center gap-2 rounded-full border border-ink-900/20 bg-white px-5 text-sm font-bold text-ink-900 transition hover:bg-cream-100 disabled:opacity-50"
            >
              {busy === 'refused' ? <Loader2 size={15} className="animate-spin" /> : <X size={15} />}
              Non merci, retirez-le
            </button>
          </div>
        </>
      ) : (
        <p className="mt-2 text-sm leading-6 text-ink-700">
          Vous ne le paierez pas : le montant vous sera rendu.
        </p>
      )}
    </div>
  );
}
