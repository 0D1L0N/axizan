'use client';

import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { AlertTriangle, Check, Info, X } from 'lucide-react';

/**
 * Notifications ephemeres.
 *
 * Avant : chaque page inventait sa facon d'afficher une erreur — une chaine
 * posee a la main, stylee differemment a chaque endroit — et le succes etait
 * le plus souvent muet. Regle desormais : toute action reussie se confirme,
 * toute action echouee dit comment la reprendre.
 */

const ToastContext = createContext(null);

const DEFAULT_DURATION = 5000;

const VARIANTS = {
  success: {
    Icon: Check,
    surface: 'bg-white border-forest-600/30',
    badge: 'bg-forest-600 text-cream',
  },
  error: {
    Icon: AlertTriangle,
    surface: 'bg-white border-clay-500/35',
    badge: 'bg-clay-500 text-white',
  },
  info: {
    Icon: Info,
    surface: 'bg-white border-ink-900/12',
    badge: 'bg-forest-800 text-cream',
  },
};

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);
  const timers = useRef(new Map());

  const dismiss = useCallback((id) => {
    setToasts((current) => current.filter((toast) => toast.id !== id));
    const timer = timers.current.get(id);
    if (timer) {
      clearTimeout(timer);
      timers.current.delete(id);
    }
  }, []);

  const push = useCallback(
    (toast) => {
      const id = `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
      const duration = toast.duration ?? DEFAULT_DURATION;

      setToasts((current) => [...current, { ...toast, id }]);

      // Une erreur reste jusqu'a ce qu'on la ferme : elle porte souvent une
      // consigne, la faire disparaitre seule serait la perdre.
      if (toast.variant !== 'error' && duration > 0) {
        timers.current.set(id, setTimeout(() => dismiss(id), duration));
      }

      return id;
    },
    [dismiss]
  );

  // Purge des minuteurs au demontage : sinon on met a jour un composant parti.
  useEffect(() => {
    const pending = timers.current;
    return () => {
      pending.forEach((timer) => clearTimeout(timer));
      pending.clear();
    };
  }, []);

  const api = useMemo(
    () => ({
      toast: push,
      success: (message, options = {}) => push({ ...options, message, variant: 'success' }),
      info: (message, options = {}) => push({ ...options, message, variant: 'info' }),
      /**
       * `action` : ce que la personne peut faire pour reprendre. Un message
       * d'erreur sans issue ne vaut guere mieux que le silence.
       */
      error: (message, options = {}) => push({ ...options, message, variant: 'error' }),
      /** Extrait un message lisible d'une erreur axios. */
      fromApi: (err, fallback = 'L’opération a échoué.') => {
        const data = err?.response?.data;
        const firstFieldError = data?.errors && Object.values(data.errors)[0]?.[0];
        return push({
          variant: 'error',
          message: firstFieldError || data?.message || fallback,
        });
      },
      dismiss,
    }),
    [push, dismiss]
  );

  return (
    <ToastContext.Provider value={api}>
      {children}

      {/* aria-live polite : annonce sans interrompre la tache en cours. */}
      <div
        role="status"
        aria-live="polite"
        className="pointer-events-none fixed inset-x-0 bottom-0 z-[100] flex flex-col items-center gap-2.5 px-4 pb-5 sm:inset-x-auto sm:right-5 sm:items-end"
      >
        {toasts.map((toast) => {
          const { Icon, surface, badge } = VARIANTS[toast.variant] || VARIANTS.info;

          return (
            <div
              key={toast.id}
              className={`pointer-events-auto flex w-full max-w-md animate-rise-in items-start gap-3 rounded-panel border p-4 shadow-lift ${surface}`}
            >
              <span className={`grid h-9 w-9 shrink-0 place-items-center rounded-full ${badge}`}>
                <Icon size={17} strokeWidth={2.5} />
              </span>

              <div className="min-w-0 flex-1 pt-1">
                {toast.title && <p className="font-bold text-ink-900">{toast.title}</p>}
                <p className="text-[15px] leading-6 text-ink-700">{toast.message}</p>
                {toast.action && (
                  <button
                    onClick={() => {
                      toast.action.onClick();
                      dismiss(toast.id);
                    }}
                    className="mt-2 text-sm font-bold text-forest-800 underline underline-offset-2"
                  >
                    {toast.action.label}
                  </button>
                )}
              </div>

              <button
                onClick={() => dismiss(toast.id)}
                aria-label="Fermer"
                className="grid h-8 w-8 shrink-0 place-items-center rounded-full text-ink-300 transition hover:bg-cream-200 hover:text-ink-700"
              >
                <X size={16} />
              </button>
            </div>
          );
        })}
      </div>
    </ToastContext.Provider>
  );
}

export function useToast() {
  const context = useContext(ToastContext);
  if (!context) {
    throw new Error('useToast doit être utilisé dans un ToastProvider');
  }
  return context;
}
