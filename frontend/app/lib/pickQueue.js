'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { preparatorService } from '@/app/services';

const STORAGE_KEY = 'axizan_pick_queue_v1';

/** Attentes entre deux tentatives, en millisecondes. Au-delà, on garde 30 s. */
const BACKOFF_MS = [1000, 3000, 8000, 20000, 30000];

const backoffFor = (attempts) => BACKOFF_MS[Math.min(attempts, BACKOFF_MS.length - 1)];

/** localStorage peut lever (navigation privée, stockage bloqué) : jamais bloquant. */
const readStored = () => {
  if (typeof window === 'undefined') return [];
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    return raw ? JSON.parse(raw) : [];
  } catch {
    return [];
  }
};

const writeStored = (queue) => {
  if (typeof window === 'undefined') return;
  try {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(queue));
  } catch {
    /* Le stockage est un confort, pas une dépendance. */
  }
};

/**
 * Une erreur 4xx est définitive : rejouer ne changera rien.
 *
 * C'est la distinction qui compte sur le terrain. Un 422 « fournisseur hors
 * marché » doit remonter au préparateur pour qu'il corrige ; une coupure
 * réseau doit être réessayée en silence. Confondre les deux, c'est soit
 * boucler à l'infini sur une erreur de saisie, soit jeter un pointage à cause
 * d'un trou de couverture.
 */
const isPermanent = (error) => {
  const status = error?.response?.status;
  return typeof status === 'number' && status >= 400 && status < 500 && status !== 408 && status !== 429;
};

/**
 * File d'envoi des pointages du préparateur.
 *
 * Au marché, le réseau va et vient. Sans file, chaque tap est un pari : il
 * part, ou il se perd sans que personne le sache — et le préparateur croit
 * avoir pointé un article qui reste « en attente » côté serveur, jusqu'à ce
 * que « Marquer prête » le lui apprenne, trop tard, à l'autre bout du marché.
 *
 * Ici, le tap est accepté immédiatement, écrit sur le téléphone, puis rejoué
 * jusqu'à ce qu'il passe. Fermer l'écran ou perdre la page ne le perd pas.
 *
 * Les envois sont séquentiels et dans l'ordre : deux modifications du même
 * article doivent arriver dans l'ordre où on les a faites, sinon le serveur
 * garde la mauvaise.
 */
export function usePickQueue({ onDrained, onPermanentFailure } = {}) {
  const [queue, setQueue] = useState([]);
  const [online, setOnline] = useState(true);
  const [hydrated, setHydrated] = useState(false);

  // Le worker lit la file par référence : le state seul le ferait travailler
  // sur une photo périmée entre deux rendus.
  const queueRef = useRef([]);
  const sendingRef = useRef(false);
  const timerRef = useRef(null);

  const commit = useCallback((next) => {
    queueRef.current = next;
    setQueue(next);
    writeStored(next);
  }, []);

  // Les callbacks changent à chaque rendu du parent : les garder dans une réf
  // évite de relancer le worker à chaque frappe.
  const handlers = useRef({ onDrained, onPermanentFailure });
  handlers.current = { onDrained, onPermanentFailure };

  useEffect(() => {
    commit(readStored());
    setHydrated(true);

    if (typeof navigator !== 'undefined') setOnline(navigator.onLine !== false);
  }, [commit]);

  const flush = useCallback(async () => {
    if (sendingRef.current) return;
    if (queueRef.current.length === 0) return;

    sendingRef.current = true;

    try {
      while (queueRef.current.length > 0) {
        const [entry] = queueRef.current;

        try {
          await preparatorService.updateItem(entry.orderId, entry.itemId, entry.payload);
          commit(queueRef.current.slice(1));
        } catch (error) {
          if (isPermanent(error)) {
            // Rejouer ne servirait à rien : on retire l'entrée et on le dit.
            commit(queueRef.current.slice(1));
            handlers.current.onPermanentFailure?.(entry, error);
            continue;
          }

          // Coupure réseau : on garde l'entrée et on repassera.
          const attempts = (entry.attempts || 0) + 1;
          commit([{ ...entry, attempts }, ...queueRef.current.slice(1)]);

          timerRef.current = setTimeout(() => {
            timerRef.current = null;
            flush();
          }, backoffFor(attempts));

          return;
        }
      }

      handlers.current.onDrained?.();
    } finally {
      sendingRef.current = false;
    }
  }, [commit]);

  /** Le tap est accepté ici et maintenant ; l'envoi suivra. */
  const enqueue = useCallback(
    (orderId, itemId, payload) => {
      // Un pointage plus récent remplace le précédent sur le même article,
      // tant qu'il n'est pas parti : inutile d'envoyer « pris » puis
      // « absent » quand seule la seconde décision compte.
      const [inFlight, ...rest] = queueRef.current;
      const keepFirst = sendingRef.current && inFlight ? [inFlight] : [];
      const tail = (sendingRef.current && inFlight ? rest : queueRef.current).filter(
        (item) => !(item.orderId === orderId && item.itemId === itemId)
      );

      commit([
        ...keepFirst,
        ...tail,
        { id: `${itemId}-${Date.now()}`, orderId, itemId, payload, attempts: 0 },
      ]);

      flush();
    },
    [commit, flush]
  );

  // Retour du réseau : on repart tout de suite plutôt que d'attendre le
  // prochain palier de backoff.
  useEffect(() => {
    if (typeof window === 'undefined') return undefined;

    const goOnline = () => {
      setOnline(true);
      if (timerRef.current) {
        clearTimeout(timerRef.current);
        timerRef.current = null;
      }
      flush();
    };
    const goOffline = () => setOnline(false);

    window.addEventListener('online', goOnline);
    window.addEventListener('offline', goOffline);

    return () => {
      window.removeEventListener('online', goOnline);
      window.removeEventListener('offline', goOffline);
    };
  }, [flush]);

  // Une file rechargée depuis le téléphone repart d'elle-même.
  useEffect(() => {
    if (hydrated) flush();
  }, [hydrated, flush]);

  useEffect(
    () => () => {
      if (timerRef.current) clearTimeout(timerRef.current);
    },
    []
  );

  const pendingForOrder = useCallback(
    (orderId) => queue.filter((entry) => entry.orderId === orderId).length,
    [queue]
  );

  return { enqueue, flush, pending: queue.length, pendingForOrder, online };
}
