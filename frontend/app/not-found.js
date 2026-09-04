import Link from 'next/link';
import { LogoMark } from '@/app/components/Logo';

export const metadata = {
  title: 'Page introuvable — Axizán',
};

export default function NotFound() {
  return (
    <main className="grid min-h-screen place-items-center bg-cream px-4 py-16">
      <div className="w-full max-w-lg rounded-panel border border-ink-900/8 bg-white p-8 text-center shadow-card md:p-10">
        <div className="mx-auto w-fit">
          <LogoMark size={56} />
        </div>
        <p className="eyebrow mt-6 text-clay-500">Erreur 404</p>
        <h1 className="mt-3 font-display text-3xl font-semibold text-forest-800">
          Ce panier-là n’existe pas.
        </h1>
        <p className="mt-3 leading-7 text-ink-500">
          La page que vous cherchez a été déplacée, ou n’a jamais existé. Le marché, lui, est toujours ouvert.
        </p>

        <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
          <Link
            href="/products"
            className="inline-flex items-center justify-center rounded-full bg-forest-800 px-6 py-3.5 font-bold text-cream transition hover:bg-forest-700"
          >
            Voir le marché
          </Link>
          <Link
            href="/"
            className="inline-flex items-center justify-center rounded-full border border-ink-900/12 px-6 py-3.5 font-bold text-ink-900 transition hover:bg-cream-100"
          >
            Retour à l’accueil
          </Link>
        </div>
      </div>
    </main>
  );
}
