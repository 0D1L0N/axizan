'use client';

import { Check, Info } from 'lucide-react';
import OperatorMark from '@/app/components/OperatorMark';

/**
 * Choix de l'opérateur Mobile Money, au checkout.
 *
 * Le texte d'explication dépend du régime de paiement, et ce n'est pas un
 * détail de rédaction : avec FedaPay actif le téléphone du client sonne et le
 * compte est débité ; sans API, le client envoie l'argent lui-même. Annoncer
 * l'un pendant que l'application fait l'autre, c'est la meilleure façon de
 * perdre un paiement — ou la confiance.
 */
export default function PaymentMethodPicker({ methods, value, onChange, delay, online = false }) {
  if (methods.length === 0) return null;

  return (
    <div>
      <div className="grid gap-3 sm:grid-cols-3">
        {methods.map((method) => {
          const selected = value === method.code;
          const disabled = !method.enabled;

          return (
            <button
              key={method.code}
              type="button"
              disabled={disabled}
              onClick={() => onChange(method.code)}
              aria-pressed={selected}
              className={`relative flex flex-col items-start gap-3 rounded-card border p-4 text-left transition ${
                disabled
                  ? 'cursor-not-allowed border-ink-900/8 bg-cream-100'
                  : selected
                    ? 'border-forest-800 bg-cream-100 ring-2 ring-forest-800'
                    : 'border-ink-900/12 bg-white hover:border-forest-600'
              }`}
            >
              <OperatorMark code={method.code} size={44} muted={disabled} />

              <span className="block">
                <span className={`block font-bold ${disabled ? 'text-ink-300' : 'text-ink-900'}`}>
                  {method.label}
                </span>
                <span className="mt-0.5 block text-sm text-ink-500">
                  {disabled ? 'Bientôt disponible' : online ? 'Paiement direct' : method.ussd}
                </span>
              </span>

              {selected && (
                <Check size={17} strokeWidth={3} className="absolute right-3 top-3 text-forest-800" />
              )}
            </button>
          );
        })}
      </div>

      <p className="mt-4 flex items-start gap-2.5 rounded-card bg-cream-100 px-4 py-3 text-sm leading-6 text-ink-500">
        <Info size={17} className="mt-0.5 shrink-0 text-forest-600" />
        {online ? (
          <span>
            Après avoir validé votre commande, vous saisirez le numéro à débiter. Votre téléphone
            sonnera&nbsp;: tapez votre code secret et vos courses partent aussitôt.
          </span>
        ) : (
          <span>
            Rien n’est prélevé automatiquement. Après votre commande, vous recevrez le numéro où
            envoyer le montant, puis vous nous transmettrez la référence du SMS. Nous vérifions et
            validons&nbsp;{delay ? `en ${delay}` : 'dans la foulée'}.
          </span>
        )}
      </p>
    </div>
  );
}
