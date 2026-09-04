'use client';

import { Check, Info } from 'lucide-react';

/**
 * Choix de l'operateur Mobile Money.
 *
 * Aucune API n'est branchee : le client choisit son operateur, puis enverra
 * l'argent au numero marchand. On le dit ouvertement plutot que de laisser
 * croire a un prelevement automatique.
 */
export default function PaymentMethodPicker({ methods, value, onChange, delay }) {
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
              className={`relative rounded-card border p-4 text-left transition ${
                disabled
                  ? 'cursor-not-allowed border-ink-900/8 bg-cream-100 opacity-60'
                  : selected
                    ? 'border-forest-800 bg-cream-100 ring-2 ring-forest-800'
                    : 'border-ink-900/12 bg-white hover:border-forest-600'
              }`}
            >
              <span
                className="block h-2 w-10 rounded-full"
                style={{ backgroundColor: method.color }}
                aria-hidden="true"
              />
              <span className="mt-3 block font-bold text-ink-900">{method.label}</span>
              <span className="mt-0.5 block text-sm text-ink-500">
                {disabled ? 'Bientôt disponible' : method.ussd}
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
        <span>
          Rien n’est prélevé automatiquement. Après votre commande, vous recevrez le numéro où
          envoyer le montant, puis vous nous transmettrez la référence du SMS. Nous vérifions et
          validons&nbsp;{delay ? `en ${delay}` : 'dans la foulée'}.
        </span>
      </p>
    </div>
  );
}
