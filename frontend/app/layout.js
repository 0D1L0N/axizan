import '../styles/globals.css';
import { Fraunces, Plus_Jakarta_Sans } from 'next/font/google';
import { AuthProvider } from '@/app/contexts/AuthContext';
import { CartProvider } from '@/app/contexts/CartContext';
import { ToastProvider } from '@/app/contexts/ToastContext';
import InstallPrompt from '@/app/components/InstallPrompt';

/**
 * Fraunces pour les titres : serif variable, chaleureuse, un peu ecrite a la
 * main — elle raconte le marche et l'artisanat la ou une grasse geometrique
 * raconte la startup. Plus Jakarta Sans pour tout le reste : lisible a petite
 * taille, ronde, sans la banalite d'Inter.
 */
const display = Fraunces({
  subsets: ['latin'],
  display: 'swap',
  variable: '--font-display',
});

const sans = Plus_Jakarta_Sans({
  subsets: ['latin'],
  display: 'swap',
  variable: '--font-sans',
});

export const metadata = {
  title: 'Axizán — On fait votre marché, vous cuisinez.',
  description:
    'Nos acheteurs choisissent vos produits frais au marché à Cotonou, Abomey-Calavi et Porto-Novo, et vous les recevez dans le créneau d’une heure de votre choix.',
  manifest: '/manifest.json',
  icons: {
    icon: [
      { url: '/icon-192.png', sizes: '192x192', type: 'image/png' },
      { url: '/icon-512.png', sizes: '512x512', type: 'image/png' },
    ],
    // iOS ne lit pas le manifeste : sans cette icône, « Sur l'écran d'accueil »
    // produit une capture de la page à la place du logo.
    apple: '/apple-touch-icon.png',
  },
  appleWebApp: {
    capable: true,
    title: 'Axizán',
    statusBarStyle: 'default',
  },
};

export const viewport = {
  width: 'device-width',
  initialScale: 1,
  themeColor: '#12372A',
};

export default function RootLayout({ children }) {
  return (
    <html lang="fr" className={`${display.variable} ${sans.variable}`}>
      <body>
        <AuthProvider>
          <ToastProvider>
            <CartProvider>
              {children}
              <InstallPrompt />
            </CartProvider>
          </ToastProvider>
        </AuthProvider>
      </body>
    </html>
  );
}
