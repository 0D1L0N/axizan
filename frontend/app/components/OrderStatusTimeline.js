'use client';

import { Check } from 'lucide-react';

/**
 * Etapes du parcours d'une commande. Chaque etape est associee a la colonne
 * d'horodatage correspondante en base, ce qui permet d'afficher l'heure reelle.
 */
export const ORDER_STEPS = [
  { key: 'pending', emoji: '🧾', label: 'Commande reçue', at: 'created_at', hint: 'En attente de validation du paiement' },
  { key: 'accepted', emoji: '👍', label: 'Acceptée', at: 'accepted_at', hint: 'Un préparateur a pris votre commande' },
  // Le préparateur ne paie pas au marché : il retire chez des fournisseurs
  // partenaires. Dire « achat » serait faux.
  { key: 'preparing', emoji: '🧺', label: 'Retrait en cours', at: 'preparing_started_at', hint: 'Vos articles sont réunis chez nos fournisseurs' },
  { key: 'ready', emoji: '📦', label: 'Panier prêt', at: 'ready_at', hint: 'Déposé, le livreur peut venir le chercher' },
  { key: 'in_delivery', emoji: '🛵', label: 'En route', at: 'delivery_started_at', hint: 'Le livreur est en chemin' },
  { key: 'delivered', emoji: '🏠', label: 'Livrée', at: 'delivered_at', hint: 'Commande remise' },
  { key: 'completed', emoji: '✨', label: 'Terminée', at: 'completed_at', hint: 'Merci pour votre confiance' },
];

const TERMINAL_STATES = {
  cancelled: { emoji: '❌', label: 'Commande annulée', tone: 'border-clay-200 bg-clay-200/40 text-clay-600' },
  returned: { emoji: '↩️', label: 'Commande retournée', tone: 'border-gold-200 bg-gold-200/40 text-gold-600' },
};

const formatTime = (value) =>
  value
    ? new Date(value).toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
    : null;

export default function OrderStatusTimeline({ order, compact = false }) {
  const terminal = TERMINAL_STATES[order.status];
  const currentIndex = ORDER_STEPS.findIndex((step) => step.key === order.status);
  const activeIndex = terminal ? -1 : currentIndex;
  const currentStep = ORDER_STEPS[activeIndex] || ORDER_STEPS[0];

  if (terminal) {
    return (
      <div className={`rounded-panel border p-5 ${terminal.tone}`}>
        <p className="text-3xl">{terminal.emoji}</p>
        <p className="mt-2 text-xl font-black">{terminal.label}</p>
        {order.cancellation_reason && (
          <p className="mt-1 text-sm font-medium opacity-80">Motif : {order.cancellation_reason}</p>
        )}
      </div>
    );
  }

  const progress = ((activeIndex + 1) / ORDER_STEPS.length) * 100;

  return (
    <div>
      {/* Statut courant, visible immediatement */}
      <div className="rounded-panel bg-forest-800 p-5 text-cream">
        <div className="flex items-center gap-4">
          <span className="text-4xl">{currentStep.emoji}</span>
          <div className="min-w-0">
            <p className="text-xs font-black uppercase tracking-[0.22em] text-gold-500">
              Étape {activeIndex + 1} sur {ORDER_STEPS.length}
            </p>
            <p className="mt-1 text-2xl font-black leading-tight">{currentStep.label}</p>
            <p className="mt-1 text-sm font-medium text-cream-300">{currentStep.hint}</p>
          </div>
        </div>

        <div className="mt-5 h-2 overflow-hidden rounded-full bg-white/15">
          <div
            className="h-full rounded-full bg-gold-500 transition-all duration-500"
            style={{ width: `${progress}%` }}
          />
        </div>
      </div>

      {!compact && (
        <ol className="mt-5 space-y-0">
          {ORDER_STEPS.map((step, index) => {
            const done = index < activeIndex;
            const current = index === activeIndex;
            const time = formatTime(order[step.at]);

            return (
              <li key={step.key} className="flex gap-4">
                {/* Colonne pastille + trait */}
                <div className="flex flex-col items-center">
                  <span
                    className={`grid h-10 w-10 shrink-0 place-items-center rounded-full text-lg font-black transition ${
                      done
                        ? 'bg-forest-800 text-cream'
                        : current
                          ? 'bg-gold-500 text-forest-900 ring-4 ring-gold-500/25'
                          : 'bg-cream-200 text-ink-300'
                    }`}
                  >
                    {done ? <Check size={18} /> : step.emoji}
                  </span>
                  {index < ORDER_STEPS.length - 1 && (
                    <span className={`w-0.5 flex-1 ${index < activeIndex ? 'bg-forest-800' : 'bg-cream-300'}`} />
                  )}
                </div>

                <div className={`pb-6 ${index === ORDER_STEPS.length - 1 ? 'pb-0' : ''}`}>
                  <p className={`font-black ${current ? 'text-forest-800' : done ? 'text-ink-700' : 'text-ink-300'}`}>
                    {step.label}
                  </p>
                  {time ? (
                    <p className="text-sm font-medium text-ink-500">{time}</p>
                  ) : (
                    <p className="text-sm font-medium text-ink-300">À venir</p>
                  )}
                </div>
              </li>
            );
          })}
        </ol>
      )}
    </div>
  );
}
