'use client';

import { Check } from 'lucide-react';

/**
 * Etapes du parcours d'une commande. Chaque etape est associee a la colonne
 * d'horodatage correspondante en base, ce qui permet d'afficher l'heure reelle.
 */
export const ORDER_STEPS = [
  { key: 'pending', emoji: '🧾', label: 'Commande reçue', at: 'created_at', hint: 'En attente de validation du paiement' },
  { key: 'accepted', emoji: '👍', label: 'Acceptée', at: 'accepted_at', hint: 'Un préparateur a pris votre commande' },
  { key: 'preparing', emoji: '🧺', label: 'Achat au marché', at: 'preparing_started_at', hint: 'Vos articles sont achetés au marché' },
  { key: 'ready', emoji: '📦', label: 'Prête', at: 'ready_at', hint: 'Le panier est prêt à partir' },
  { key: 'in_delivery', emoji: '🛵', label: 'En route', at: 'delivery_started_at', hint: 'Le livreur est en chemin' },
  { key: 'delivered', emoji: '🏠', label: 'Livrée', at: 'delivered_at', hint: 'Commande remise' },
  { key: 'completed', emoji: '✨', label: 'Terminée', at: 'completed_at', hint: 'Merci pour votre confiance' },
];

const TERMINAL_STATES = {
  cancelled: { emoji: '❌', label: 'Commande annulée', tone: 'bg-red-50 text-red-800 border-red-200' },
  returned: { emoji: '↩️', label: 'Commande retournée', tone: 'bg-orange-50 text-orange-800 border-orange-200' },
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
      <div className={`rounded-2xl border p-5 ${terminal.tone}`}>
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
      <div className="rounded-2xl bg-[#12372A] p-5 text-white">
        <div className="flex items-center gap-4">
          <span className="text-4xl">{currentStep.emoji}</span>
          <div className="min-w-0">
            <p className="text-xs font-black uppercase tracking-[0.22em] text-[#FFB000]">
              Étape {activeIndex + 1} sur {ORDER_STEPS.length}
            </p>
            <p className="mt-1 text-2xl font-black leading-tight">{currentStep.label}</p>
            <p className="mt-1 text-sm font-medium text-white/75">{currentStep.hint}</p>
          </div>
        </div>

        <div className="mt-5 h-2 overflow-hidden rounded-full bg-white/15">
          <div
            className="h-full rounded-full bg-[#FFB000] transition-all duration-500"
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
                        ? 'bg-[#12372A] text-white'
                        : current
                          ? 'bg-[#FFB000] text-[#1C140D] ring-4 ring-[#FFB000]/25'
                          : 'bg-gray-100 text-gray-400'
                    }`}
                  >
                    {done ? <Check size={18} /> : step.emoji}
                  </span>
                  {index < ORDER_STEPS.length - 1 && (
                    <span className={`w-0.5 flex-1 ${index < activeIndex ? 'bg-[#12372A]' : 'bg-gray-200'}`} />
                  )}
                </div>

                <div className={`pb-6 ${index === ORDER_STEPS.length - 1 ? 'pb-0' : ''}`}>
                  <p className={`font-black ${current ? 'text-[#12372A]' : done ? 'text-gray-800' : 'text-gray-400'}`}>
                    {step.label}
                  </p>
                  {time ? (
                    <p className="text-sm font-medium text-gray-500">{time}</p>
                  ) : (
                    <p className="text-sm font-medium text-gray-300">À venir</p>
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
