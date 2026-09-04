'use client';

import { Check } from 'lucide-react';

/**
 * Fil d'etapes pour les ecrans de terrain.
 *
 * Compact et horizontal : il doit tenir sur un telephone sans manger la place
 * du travail. Le personnel doit savoir en un coup d'oeil ou en est la commande
 * et ce qu'on attend de lui maintenant.
 */

/** Parcours du preparateur, du moment ou il prend la commande au depot. */
export const PREP_STEPS = [
  { key: 'accepted', label: 'Prise', at: 'accepted_at', now: 'Démarrez le retrait' },
  { key: 'preparing', label: 'Retrait', at: 'preparing_started_at', now: 'Pointez chaque article' },
  { key: 'ready', label: 'Déposée', at: 'ready_at', now: 'Déposez au dépôt' },
];

/** Parcours du livreur, du retrait au depot jusqu'a la remise. */
export const DELIVERY_STEPS = [
  { key: 'assigned', label: 'Acceptée', at: 'created_at', now: 'Allez au dépôt' },
  { key: 'picked_up', label: 'Récupérée', at: 'pickup_time', now: 'Prenez le panier' },
  { key: 'in_transit', label: 'En route', at: 'pickup_time', now: 'Livrez le client' },
  { key: 'delivered', label: 'Remise', at: 'delivery_time', now: 'Terminé' },
];

const formatTime = (value) =>
  value ? new Date(value).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : null;

export default function StaffSteps({ steps, currentKey, source = {} }) {
  const currentIndex = steps.findIndex((step) => step.key === currentKey);
  // Un statut inconnu ne doit pas tout eteindre : on considere le debut.
  const index = currentIndex === -1 ? 0 : currentIndex;
  const current = steps[index];

  return (
    <div className="rounded-panel border-2 border-forest-800/15 bg-white p-4">
      <ol className="flex items-start">
        {steps.map((step, position) => {
          const done = position < index;
          const active = position === index;
          const time = formatTime(source[step.at]);

          return (
            <li key={step.key} className="flex flex-1 flex-col items-center">
              <div className="flex w-full items-center">
                {/* Trait de liaison a gauche, sauf pour la premiere etape */}
                <span
                  className={`h-1 flex-1 rounded-full ${
                    position === 0 ? 'bg-transparent' : done || active ? 'bg-forest-600' : 'bg-ink-900/12'
                  }`}
                />
                <span
                  className={`grid h-9 w-9 shrink-0 place-items-center rounded-full text-sm font-bold ${
                    done
                      ? 'bg-forest-600 text-cream'
                      : active
                        ? 'bg-gold-500 text-forest-950 ring-4 ring-gold-500/25'
                        : 'border-2 border-ink-900/15 bg-white text-ink-300'
                  }`}
                >
                  {done ? <Check size={17} strokeWidth={3} /> : position + 1}
                </span>
                <span
                  className={`h-1 flex-1 rounded-full ${
                    position === steps.length - 1 ? 'bg-transparent' : done ? 'bg-forest-600' : 'bg-ink-900/12'
                  }`}
                />
              </div>

              <span
                className={`mt-1.5 text-center text-xs font-bold leading-tight ${
                  active ? 'text-forest-800' : done ? 'text-ink-700' : 'text-ink-300'
                }`}
              >
                {step.label}
              </span>
              {time && <span className="text-[11px] tabular-nums text-ink-300">{time}</span>}
            </li>
          );
        })}
      </ol>

      {/* Ce qu'on attend de lui maintenant : la seule phrase qui compte. */}
      {current?.now && (
        <p className="mt-3 rounded-card bg-gold-500/15 px-4 py-2.5 text-center font-bold text-forest-800">
          {current.now}
        </p>
      )}
    </div>
  );
}
