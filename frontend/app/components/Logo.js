'use client';

/**
 * Logo Axizán — le cauri.
 *
 * Pourquoi pas un panier : tout le monde en met un. Le cauri, lui, est
 * litteralement la monnaie du Danxome, l'objet du commerce et du marche, et il
 * sert encore au Fa. C'est un signe local, memorable, qui n'appartient a aucun
 * concurrent — et sa silhouette pointue en haut fait un A.
 *
 * Il tient a 16 px (favicon) comme a 400 px : une seule forme pleine, une
 * fente, huit dents. Aucun detail ne disparait avant l'autre.
 *
 * variant="mark" -> coquille seule | variant="full" -> coquille + nom
 * tone="dark" (fond clair) | tone="light" (fond sombre)
 * tile=true -> pastille verte (favicon, avatar) | false -> coquille detouree
 */

const FOREST = '#12372A';
const GOLD = '#FFB000';
const CREAM = '#FBF7EF';

export function LogoMark({ size = 44, className = '', tile = true, tone = 'dark' }) {
  // Detouree, la fente doit rester lisible : creme sur fond sombre, vert sinon.
  const apertureColor = tile ? FOREST : tone === 'light' ? FOREST : FOREST;
  const shellColor = tile ? GOLD : tone === 'light' ? CREAM : GOLD;

  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 64 64"
      role="img"
      aria-label="Axizán"
      className={className}
    >
      {tile && <rect width="64" height="64" rx="17" fill={FOREST} />}

      {/* Coquille : pointe en haut (le A), ventre rond en bas */}
      <path
        d="M32 6c5 0 13.5 12 18 24.5 3.5 10 3 18-1.6 22.1C44 56.5 20 56.5 15.6 52.6 11 48.5 10.5 40.5 14 30.5 18.5 18 27 6 32 6z"
        fill={shellColor}
      />

      {/* Fente centrale */}
      <path
        d="M32 17.5c2.6 5.5 3.8 16 3 26-.3 4.3-1.4 7-3 7s-2.7-2.7-3-7c-.8-10 .4-20.5 3-26z"
        fill={apertureColor}
      />

      {/* Dents du cauri */}
      <g stroke={apertureColor} strokeWidth="1.9" strokeLinecap="round">
        <path d="M28.8 25.2 23.2 23.6M28.4 30.8 22.4 29.8M28.6 36.4 22.6 36.2M29.2 42 23.6 42.6" />
        <path d="M35.2 25.2 40.8 23.6M35.6 30.8 41.6 29.8M35.4 36.4 41.4 36.2M34.8 42 40.4 42.6" />
      </g>
    </svg>
  );
}

/**
 * Le nom, en Fraunces. L'accent de "á" passe en or : un detail minuscule qui
 * signe la marque et rappelle l'accent aigu que tout le monde oublie d'ecrire.
 */
export function Wordmark({ tone = 'dark', className = '' }) {
  const base = tone === 'light' ? 'text-cream' : 'text-forest-800';

  return (
    <span className={`font-display font-semibold tracking-[-0.03em] ${base} ${className}`}>
      Axiz<span className="text-gold-500">á</span>n
    </span>
  );
}

export default function Logo({
  size = 44,
  variant = 'full',
  tone = 'dark',
  className = '',
  tagline = true,
  display = false,
}) {
  if (variant === 'mark') {
    return <LogoMark size={size} className={className} tone={tone} />;
  }

  // display : le nom en capitales, grand, colle au cauri — la signature
  // d'enseigne, pas la mention discrete.
  if (display) {
    return (
      <span className={`inline-flex items-center gap-3 ${className}`}>
        <LogoMark size={size} tone={tone} />
        <span className="leading-none">
          <span
            className={`block font-display text-3xl font-semibold uppercase tracking-[-0.02em] sm:text-4xl ${
              tone === 'light' ? 'text-cream' : 'text-forest-800'
            }`}
          >
            Axiz<span className="text-gold-500">á</span>n
          </span>
          {tagline && (
            <span
              className={`mt-1 hidden text-[0.62rem] font-bold uppercase tracking-[0.22em] sm:block ${
                tone === 'light' ? 'text-cream/55' : 'text-ink-300'
              }`}
            >
              Le marché, choisi pour vous
            </span>
          )}
        </span>
      </span>
    );
  }

  return (
    <span className={`inline-flex items-center gap-3 ${className}`}>
      <LogoMark size={size} tone={tone} />
      <span className="leading-none">
        <Wordmark tone={tone} className="block text-2xl" />
        {tagline && (
          <span
            className={`eyebrow mt-1.5 hidden sm:block ${tone === 'light' ? 'text-cream/55' : 'text-ink-300'}`}
          >
            Le marché, choisi pour vous
          </span>
        )}
      </span>
    </span>
  );
}
