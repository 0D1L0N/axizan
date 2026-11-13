'use client';

/**
 * Vocabulaire graphique : bandes tissees et losanges, la grammaire des lisieres
 * de pagne. Aplats francs, aucune ombre, motifs repetes.
 *
 * Volontairement abstrait : aucun symbole identifiable emprunte a une lignee ou
 * a une region, seulement de la geometrie textile.
 */

/** Bande tissee facon lisiere de pagne. A poser entre deux sections. */
export function PagneBand({ className = '', height = 14 }) {
  return (
    <div
      aria-hidden="true"
      className={`w-full ${className}`}
      style={{
        height,
        backgroundImage:
          "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='28' viewBox='0 0 60 28'%3E%3Crect width='60' height='28' fill='%2312372A'/%3E%3Cpath d='M0 14 15 0 30 14 45 0 60 14 45 28 30 14 15 28z' fill='%23FFB000'/%3E%3Ccircle cx='15' cy='14' r='3' fill='%23C43C2F'/%3E%3Ccircle cx='45' cy='14' r='3' fill='%23C43C2F'/%3E%3C/svg%3E\")",
        backgroundSize: `auto ${height}px`,
        backgroundRepeat: 'repeat-x',
      }}
    />
  );
}

/**
 * Fond de section : losanges et cauris, tres discrets.
 * A poser en absolu derriere le contenu.
 */
export function AppliqueField({ className = '', opacity = 0.5 }) {
  return (
    <div
      aria-hidden="true"
      className={`pointer-events-none absolute inset-0 ${className}`}
      style={{
        opacity,
        backgroundImage:
          "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='96' height='96' viewBox='0 0 96 96'%3E%3Cg fill='none' stroke='%23FFB000' stroke-opacity='0.16' stroke-width='1.5'%3E%3Cpath d='M24 6 42 24 24 42 6 24z'/%3E%3Cpath d='M72 54 90 72 72 90 54 72z'/%3E%3Cpath d='M72 12c2 0 5 4 6 8s-2 7-6 7-7-3-6-7 4-8 6-8z'/%3E%3Cpath d='M24 60c2 0 5 4 6 8s-2 7-6 7-7-3-6-7 4-8 6-8z'/%3E%3C/g%3E%3C/svg%3E\")",
      }}
    />
  );
}

