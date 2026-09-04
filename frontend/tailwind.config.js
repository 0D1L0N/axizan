/** @type {import('tailwindcss').Config} */

/**
 * Systeme de design Axizan.
 *
 * Regle : plus aucune couleur en dur dans les composants. Tout passe par ces
 * tokens, sinon on retombe sur ce qu'on avait — un theme corail hérité dans la
 * config pendant que les pages peignaient du vert a la main.
 *
 * Palette : le vert profond des feuilles de teck, l'or du marche (et des
 * cauris), la terre cuite des canaris, le creme du pagne ecru.
 */
export default {
  content: [
    './app/**/*.{js,ts,jsx,tsx}',
    './components/**/*.{js,ts,jsx,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        forest: {
          950: '#07231A',
          900: '#0D3226',
          800: '#12372A', // couleur de marque historique, conservee
          700: '#1B5340',
          600: '#256B53',
          500: '#2F8566',
        },
        gold: {
          600: '#D99000',
          500: '#FFB000', // accent principal
          400: '#FFC748',
          200: '#FFE3A3',
        },
        clay: {
          600: '#A32F24',
          500: '#C43C2F', // terre cuite : plus juste que le rouge vif sur du vert
          200: '#F3D3CE',
        },
        cream: {
          DEFAULT: '#FBF7EF',
          100: '#F5EFE2',
          200: '#EDE4D2',
          300: '#DED2BA',
        },
        ink: {
          900: '#1B1712', // noir chaud : jamais de gris bleute sur cette palette
          700: '#3D352C',
          500: '#6B6053',
          300: '#9C9184',
        },
        // Plus d'alias `primary` : toutes les pages sont passees aux tokens.
        // Le reintroduire rouvrirait la porte a deux noms pour une couleur.
      },
      fontFamily: {
        display: ['var(--font-display)', 'Georgia', 'serif'],
        sans: ['var(--font-sans)', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      fontSize: {
        // Echelle d'affichage : interlignage serre, chasse resserree.
        'display-sm': ['clamp(2rem, 4vw, 2.75rem)', { lineHeight: '1.05', letterSpacing: '-0.02em' }],
        'display-md': ['clamp(2.5rem, 5.5vw, 4rem)', { lineHeight: '1', letterSpacing: '-0.025em' }],
        'display-lg': ['clamp(3rem, 7vw, 5.5rem)', { lineHeight: '0.95', letterSpacing: '-0.03em' }],
      },
      borderRadius: {
        // Une seule echelle, au lieu des sept rayons differents d'avant.
        card: '20px',
        panel: '28px',
        hero: '36px',
      },
      boxShadow: {
        card: '0 1px 2px rgba(27,23,18,0.04), 0 8px 24px -12px rgba(27,23,18,0.18)',
        lift: '0 24px 48px -24px rgba(27,23,18,0.35)',
        glow: '0 18px 40px -18px rgba(255,176,0,0.55)',
      },
      keyframes: {
        'rise-in': {
          from: { opacity: '0', transform: 'translateY(12px)' },
          to: { opacity: '1', transform: 'translateY(0)' },
        },
      },
      animation: {
        'rise-in': 'rise-in 0.5s cubic-bezier(0.22, 1, 0.36, 1) both',
      },
    },
  },
  plugins: [],
};
