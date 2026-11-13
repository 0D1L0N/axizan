'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { usePathname } from 'next/navigation';
import { useAuth } from '@/app/contexts/AuthContext';
import { authService } from '@/app/services';
import { ArrowRight, X } from 'lucide-react';

const LABELS = {
  name: 'votre nom',
  email: 'votre e-mail',
  phone: 'votre téléphone',
  address: 'une adresse de livraison',
};

/**
 * Rappel affiche tant que le compte ne permet pas de commander.
 *
 * Une banniere, pas une fenetre modale : on ne bloque pas quelqu'un qui veut
 * d'abord regarder le catalogue. Le blocage arrive au checkout, la ou il est
 * justifie. Le renvoi est masquable pour la session en cours seulement — a la
 * session suivante, si le compte est toujours incomplet, il revient.
 */
export default function ProfileReminder() {
  const { user, loading } = useAuth();
  const pathname = usePathname();
  const [missing, setMissing] = useState([]);
  const [dismissed, setDismissed] = useState(false);

  useEffect(() => {
    try {
      setDismissed(sessionStorage.getItem('profile-reminder-dismissed') === '1');
    } catch {
      // Navigation privee : on affiche, c'est le comportement le plus sûr.
    }
  }, []);

  useEffect(() => {
    if (!user || user.role !== 'client') {
      setMissing([]);
      return;
    }
    authService
      .getProfile()
      .then((res) => setMissing(res.data.profile?.missing ?? []))
      .catch(() => setMissing([]));
  }, [user, pathname]);

  const hide =
    loading ||
    !user ||
    user.role !== 'client' ||
    missing.length === 0 ||
    dismissed ||
    pathname?.startsWith('/account');

  if (hide) return null;

  const handleDismiss = () => {
    setDismissed(true);
    try {
      sessionStorage.setItem('profile-reminder-dismissed', '1');
    } catch {
      // Sans stockage, le rappel reviendra au prochain rendu : acceptable.
    }
  };

  const list = missing.map((key) => LABELS[key] ?? key);
  const formatted =
    list.length === 1 ? list[0] : `${list.slice(0, -1).join(', ')} et ${list[list.length - 1]}`;

  return (
    <div className="border-b border-forest-900/10 bg-gold-500">
      <div className="mx-auto flex max-w-7xl items-center gap-4 px-4 py-3">
        <p className="flex-1 font-semibold text-forest-950">
          Il manque {formatted} pour pouvoir commander.{' '}
          <Link href="/account" className="inline-flex items-center gap-1 font-bold underline">
            Compléter mon compte <ArrowRight size={15} />
          </Link>
        </p>
        <button
          onClick={handleDismiss}
          aria-label="Masquer ce rappel"
          className="grid h-8 w-8 shrink-0 place-items-center rounded-full text-forest-950/60 transition hover:bg-forest-950/10 hover:text-forest-950"
        >
          <X size={17} />
        </button>
      </div>
    </div>
  );
}
