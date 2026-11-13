'use client';

import { useEffect } from 'react';
import Link from 'next/link';
import { LogoMark } from '@/app/components/Logo';
import { RefreshCw } from 'lucide-react';

/**
 * Frontiere d'erreur globale.
 *
 * Sans elle, une exception non rattrapee — API injoignable, reponse
 * inattendue — laissait un ecran entierement blanc, sans explication ni moyen
 * de reprendre.
 */
export default function GlobalError({ error, reset }) {
  useEffect(() => {
    // En attendant un service de suivi d'erreurs (Sentry ou equivalent), au
    // moins la console : sinon l'incident ne laisse aucune trace.
    console.error('Erreur non rattrapée :', error);
  }, [error]);

  return (
    <main className="grid min-h-screen place-items-center bg-cream px-4 py-16">
      <div className="w-full max-w-lg rounded-panel border border-ink-900/8 bg-white p-8 text-center shadow-card md:p-10">
        <div className="mx-auto w-fit">
          <LogoMark size={56} />
        </div>
        <h1 className="mt-6 font-display text-3xl font-semibold text-forest-800">
          Quelque chose s’est mal passé.
        </h1>
        <p className="mt-3 leading-7 text-ink-500">
          La page n’a pas pu se charger. C’est de notre côté, pas du vôtre. Réessayez&nbsp;: le plus
          souvent, cela suffit.
        </p>

        <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
          <button
            onClick={reset}
            className="inline-flex items-center justify-center gap-2 rounded-full bg-forest-800 px-6 py-3.5 font-bold text-cream transition hover:bg-forest-700"
          >
            <RefreshCw size={17} /> Réessayer
          </button>
          <Link
            href="/"
            className="inline-flex items-center justify-center rounded-full border border-ink-900/12 px-6 py-3.5 font-bold text-ink-900 transition hover:bg-cream-100"
          >
            Retour à l’accueil
          </Link>
        </div>

        {error?.digest && (
          <p className="mt-7 text-sm text-ink-300">
            Si cela se reproduit, communiquez-nous ce code&nbsp;: <span className="font-mono">{error.digest}</span>
          </p>
        )}
      </div>
    </main>
  );
}
