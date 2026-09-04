'use client';

import { useEffect, useState } from 'react';
import { LogoMark } from '@/app/components/Logo';
import { Download, X } from 'lucide-react';

const DISMISSED_KEY = 'axizan_install_dismissed_at';
// Refuser une fois ne doit pas condamner la proposition pour toujours, ni la
// faire revenir le lendemain.
const SNOOZE_DAYS = 30;

/**
 * Invitation a installer Axizan sur l'ecran d'accueil.
 *
 * Au Benin, on utilise un service au quotidien depuis son ecran d'accueil, pas
 * en retapant une adresse — et sans passer par un magasin d'applications.
 *
 * Le mode hors-ligne reste volontairement ecarte : on propose l'installation,
 * pas un fonctionnement deconnecte.
 */
export default function InstallPrompt() {
  const [deferred, setDeferred] = useState(null);
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    // Deja installe : le navigateur ouvre en mode autonome.
    if (window.matchMedia?.('(display-mode: standalone)').matches) return undefined;

    try {
      const dismissedAt = Number(window.localStorage.getItem(DISMISSED_KEY) || 0);
      if (dismissedAt && Date.now() - dismissedAt < SNOOZE_DAYS * 86400000) return undefined;
    } catch {
      // Stockage indisponible (navigation privee) : on propose quand meme.
    }

    const onPrompt = (event) => {
      // Sans preventDefault, Chrome affiche sa propre banniere et l'evenement
      // n'est plus rejouable.
      event.preventDefault();
      setDeferred(event);
      setVisible(true);
    };

    window.addEventListener('beforeinstallprompt', onPrompt);
    return () => window.removeEventListener('beforeinstallprompt', onPrompt);
  }, []);

  const close = () => {
    setVisible(false);
    try {
      window.localStorage.setItem(DISMISSED_KEY, String(Date.now()));
    } catch {
      // Sans stockage, la proposition reviendra a la prochaine visite.
    }
  };

  const install = async () => {
    if (!deferred) return;
    deferred.prompt();
    await deferred.userChoice;
    setDeferred(null);
    setVisible(false);
  };

  if (!visible) return null;

  return (
    <div className="fixed inset-x-0 bottom-0 z-[90] px-4 pb-4 sm:inset-x-auto sm:right-5 sm:max-w-sm">
      <div className="flex items-start gap-3 rounded-panel border border-ink-900/10 bg-white p-4 shadow-lift">
        <LogoMark size={44} />
        <div className="min-w-0 flex-1">
          <p className="font-display text-lg font-semibold text-forest-800">Axizán sur votre écran</p>
          <p className="mt-1 text-sm leading-6 text-ink-500">
            Installez-le pour ouvrir le marché d’un geste, sans retaper l’adresse.
          </p>
          <button
            onClick={install}
            className="mt-3 inline-flex items-center gap-2 rounded-full bg-forest-800 px-5 py-2.5 text-sm font-bold text-cream transition hover:bg-forest-700"
          >
            <Download size={16} /> Installer
          </button>
        </div>
        <button
          onClick={close}
          aria-label="Plus tard"
          className="grid h-8 w-8 shrink-0 place-items-center rounded-full text-ink-300 transition hover:bg-cream-200 hover:text-ink-700"
        >
          <X size={16} />
        </button>
      </div>
    </div>
  );
}
