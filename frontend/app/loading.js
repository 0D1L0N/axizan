/**
 * Ecran d'attente pendant le chargement d'un segment.
 *
 * Des blocs de la forme du contenu a venir plutot qu'un tourniquet : la page
 * ne saute pas quand les donnees arrivent.
 */
export default function Loading() {
  return (
    <main className="min-h-screen bg-cream" aria-busy="true" aria-live="polite">
      <span className="sr-only">Chargement en cours…</span>

      <div className="h-64 animate-pulse bg-forest-900/90" />

      <div className="mx-auto max-w-7xl px-4 py-12">
        <div className="h-8 w-64 animate-pulse rounded-card bg-white" />
        <div className="mt-4 h-5 w-full max-w-xl animate-pulse rounded-card bg-white/70" />

        <div className="mt-10 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
          {[1, 2, 3, 4, 5, 6].map((i) => (
            <div key={i} className="h-96 animate-pulse rounded-panel bg-white" />
          ))}
        </div>
      </div>
    </main>
  );
}
