'use client';

/**
 * Marques des opérateurs Mobile Money béninois.
 *
 * Ce sont des pastilles typographiques aux couleurs de chaque opérateur, pas
 * des reproductions de leurs logos. La distinction n'est pas cosmétique : un
 * logo officiel est une marque déposée, et une copie approximative dessinée à
 * la main reconnaît mal la marque tout en l'abîmant. Une pastille assumée est
 * plus honnête et, sur un écran de téléphone, tout aussi reconnaissable —
 * c'est la couleur qui fait le travail.
 *
 * Le jour où Axizán obtient les fichiers officiels auprès des opérateurs, il
 * suffira de remplacer le contenu de `MARKS` : le reste de l'interface ne
 * connaît que ce composant.
 */

/**
 * Pour passer aux vrais logos : déposez le fichier dans
 * `frontend/public/operators/` puis renseignez `logo` ci-dessous — par exemple
 * `logo: '/operators/mtn.png'`. Rien d'autre ne bouge dans l'application.
 */
const MARKS = {
  mtn: {
    // Jaune MTN sur texte sombre : le contraste inverse des deux autres,
    // exactement comme dans la vraie signalétique de l'opérateur.
    label: 'MTN',
    background: '#FFCC00',
    ink: '#1B1712',
    caption: 'MoMo',
    logo: null,
  },
  moov: {
    label: 'moov',
    background: '#0066B3',
    ink: '#FFFFFF',
    caption: 'Money',
    logo: null,
  },
  celtiis: {
    label: 'Celtiis',
    background: '#E30613',
    ink: '#FFFFFF',
    caption: 'Cash',
    logo: null,
  },
};

/** Repli neutre : un opérateur inconnu ne doit pas casser la mise en page. */
const FALLBACK = { label: '···', background: '#9C9184', ink: '#FFFFFF', caption: '' };

export default function OperatorMark({ code, size = 44, className = '', muted = false }) {
  const mark = MARKS[code] || FALLBACK;

  // Fichier officiel présent : il remplace la pastille, sur fond blanc pour
  // que le logo garde ses propres couleurs. `img` plutôt que `next/image` —
  // ce sont des fichiers locaux de quelques kilo-octets, l'optimisation
  // coûterait plus qu'elle ne rapporte.
  if (mark.logo) {
    return (
      <span
        className={`inline-grid shrink-0 place-items-center overflow-hidden rounded-xl bg-white ${className}`}
        style={{
          width: size,
          height: size,
          filter: muted ? 'grayscale(1)' : undefined,
          opacity: muted ? 0.55 : 1,
        }}
      >
        <img
          src={mark.logo}
          alt=""
          aria-hidden="true"
          width={size}
          height={size}
          className="h-full w-full object-contain p-1"
        />
      </span>
    );
  }

  return (
    <span
      aria-hidden="true"
      className={`inline-grid shrink-0 place-items-center rounded-xl leading-none transition ${className}`}
      style={{
        width: size,
        height: size,
        background: mark.background,
        color: mark.ink,
        // Un opérateur indisponible garde sa forme mais perd sa couleur : on
        // voit qu'il existe sans croire qu'on peut le choisir.
        filter: muted ? 'grayscale(1)' : undefined,
        opacity: muted ? 0.55 : 1,
      }}
    >
      <span className="text-center">
        <span
          className="block font-display font-semibold tracking-tight"
          style={{ fontSize: size * 0.3 }}
        >
          {mark.label}
        </span>
        {mark.caption && (
          <span
            className="mt-0.5 block font-bold uppercase tracking-[0.1em]"
            style={{ fontSize: size * 0.17, opacity: 0.8 }}
          >
            {mark.caption}
          </span>
        )}
      </span>
    </span>
  );
}

/** Nom complet, pour les libellés qui accompagnent la marque. */
export const operatorLabel = (code) =>
  ({ mtn: 'MTN MoMo', moov: 'Moov Money', celtiis: 'Celtiis Cash' })[code] || code;
