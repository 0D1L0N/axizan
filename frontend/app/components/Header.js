'use client';

import Link from 'next/link';
import { useEffect, useRef, useState } from 'react';
import { usePathname, useRouter } from 'next/navigation';
import { useAuth } from '@/app/contexts/AuthContext';
import { useCart } from '@/app/contexts/CartContext';
import Logo from '@/app/components/Logo';
import ProfileReminder from '@/app/components/ProfileReminder';
import NotificationBell from '@/app/components/NotificationBell';
import { LogOut, Menu, ShoppingBasket, User, UserCircle, X } from 'lucide-react';

const LINKS = [
  ['Accueil', '/'],
  ['Catalogue', '/products'],
  ['Paniers-recettes', '/menus'],
];

export default function Header() {
  const { user, logout } = useAuth();
  const { items } = useCart();
  const router = useRouter();
  const pathname = usePathname();

  const [menuOpen, setMenuOpen] = useState(false);
  const panelRef = useRef(null);
  const toggleRef = useRef(null);

  const handleLogout = async () => {
    setMenuOpen(false);
    await logout();
    router.push('/');
  };

  const navLinks = user ? [...LINKS, ['Commandes', '/orders']] : LINKS;

  // Changer de page ferme le panneau : sans cela, on navigue derriere un menu
  // reste ouvert.
  useEffect(() => {
    setMenuOpen(false);
  }, [pathname]);

  useEffect(() => {
    if (!menuOpen) return undefined;

    const onPointerDown = (event) => {
      if (
        panelRef.current &&
        !panelRef.current.contains(event.target) &&
        !toggleRef.current?.contains(event.target)
      ) {
        setMenuOpen(false);
      }
    };
    const onKeyDown = (event) => {
      if (event.key === 'Escape') {
        setMenuOpen(false);
        toggleRef.current?.focus();
      }
    };

    document.addEventListener('mousedown', onPointerDown);
    document.addEventListener('keydown', onKeyDown);
    // Le fond ne defile pas pendant que le panneau est ouvert.
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    return () => {
      document.removeEventListener('mousedown', onPointerDown);
      document.removeEventListener('keydown', onKeyDown);
      document.body.style.overflow = previousOverflow;
    };
  }, [menuOpen]);

  return (
    <header className="sticky top-0 z-50 border-b border-ink-900/8 bg-cream/85 backdrop-blur-xl">
      <ProfileReminder />
      <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3">
        <Link href="/" className="flex items-center" aria-label="Axizán, accueil">
          <Logo size={46} display />
        </Link>

        <nav className="hidden items-center gap-1 rounded-full border border-ink-900/8 bg-white/70 p-1 text-sm font-semibold text-ink-700 shadow-card md:flex">
          {navLinks.map(([label, href]) => {
            const active = pathname === href;
            return (
              <Link
                key={href}
                href={href}
                aria-current={active ? 'page' : undefined}
                className={`rounded-full px-4 py-2 transition ${
                  active ? 'bg-forest-800 text-cream' : 'hover:bg-cream-200'
                }`}
              >
                {label}
              </Link>
            );
          })}
          {user?.role && user.role !== 'client' && (
            <Link href={`/${user.role}`} className="rounded-full px-4 py-2 capitalize transition hover:bg-cream-200">
              Espace {user.role}
            </Link>
          )}
        </nav>

        <div className="flex items-center gap-2">
          <NotificationBell />
          <Link
            href="/cart"
            className="relative inline-flex h-11 items-center gap-2 rounded-full bg-gold-500 px-4 text-sm font-bold text-forest-950 shadow-glow transition hover:bg-gold-400"
            title="Voir le panier"
          >
            <ShoppingBasket size={18} />
            <span className="hidden sm:inline">Panier</span>
            {items.length > 0 && (
              <span className="absolute -right-2 -top-2 grid h-6 min-w-6 place-items-center rounded-full bg-clay-500 px-1 text-xs font-bold text-white ring-2 ring-cream">
                {items.length}
              </span>
            )}
          </Link>

          {user ? (
            <>
              <Link
                href="/account"
                title="Mon compte"
                className="hidden items-center gap-3 rounded-full border border-ink-900/8 bg-white/70 py-1 pl-1 pr-4 transition hover:bg-cream-200 md:flex"
              >
                <span className="grid h-9 w-9 place-items-center rounded-full bg-forest-800 text-sm font-bold text-cream">
                  {user.name?.trim().charAt(0).toUpperCase() || '?'}
                </span>
                <div className="leading-tight">
                  <p className="text-sm font-bold text-ink-900">{user.name}</p>
                  <p className="text-xs capitalize text-ink-500">{user.role}</p>
                </div>
              </Link>
              <button
                onClick={handleLogout}
                className="grid h-11 w-11 place-items-center rounded-full border border-ink-900/8 bg-white/70 text-ink-700 transition hover:bg-cream-200"
                title="Déconnexion"
              >
                <LogOut size={18} />
              </button>
            </>
          ) : (
            <>
              <Link
                href="/login"
                className="hidden h-11 items-center gap-2 rounded-full border border-ink-900/8 bg-white/70 px-4 text-sm font-bold text-ink-900 transition hover:bg-cream-200 sm:inline-flex"
              >
                <UserCircle size={17} />
                Se connecter
              </Link>
              <Link
                href="/register"
                className="hidden h-11 items-center rounded-full bg-forest-800 px-5 text-sm font-bold text-cream transition hover:bg-forest-700 lg:inline-flex"
              >
                S’inscrire
              </Link>
            </>
          )}
          <button
            ref={toggleRef}
            onClick={() => setMenuOpen((open) => !open)}
            aria-expanded={menuOpen}
            aria-controls="menu-mobile"
            aria-label={menuOpen ? 'Fermer le menu' : 'Ouvrir le menu'}
            className="grid h-11 w-11 place-items-center rounded-full border border-ink-900/8 bg-white/70 text-ink-900 transition hover:bg-cream-200 md:hidden"
          >
            {menuOpen ? <X size={19} /> : <Menu size={19} />}
          </button>
        </div>
      </div>

      {/* Panneau mobile. Sous 768 px, c'est la seule navigation disponible :
          le bouton n'ouvrait rien auparavant et le site etait injoignable au
          telephone. */}
      {menuOpen && (
        <>
          <div
            className="fixed inset-0 top-[var(--header-h,68px)] z-40 bg-forest-950/40 md:hidden"
            aria-hidden="true"
          />
          <div
            id="menu-mobile"
            ref={panelRef}
            className="absolute inset-x-0 z-50 border-b border-ink-900/10 bg-cream shadow-lift md:hidden"
          >
            <nav className="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-4">
              {navLinks.map(([label, href]) => {
                const active = pathname === href;
                return (
                  <Link
                    key={href}
                    href={href}
                    aria-current={active ? 'page' : undefined}
                    className={`rounded-card px-4 py-3.5 text-lg font-bold transition ${
                      active ? 'bg-forest-800 text-cream' : 'text-ink-900 hover:bg-cream-200'
                    }`}
                  >
                    {label}
                  </Link>
                );
              })}

              {user?.role && user.role !== 'client' && (
                <Link
                  href={`/${user.role}`}
                  className="rounded-card px-4 py-3.5 text-lg font-bold capitalize text-ink-900 hover:bg-cream-200"
                >
                  Espace {user.role}
                </Link>
              )}

              <div className="mt-3 border-t border-ink-900/10 pt-3">
                {user ? (
                  <>
                    <Link
                      href="/account"
                      className="flex items-center gap-3 rounded-card px-4 py-3.5 text-lg font-bold text-ink-900 hover:bg-cream-200"
                    >
                      <User size={19} /> Mon compte
                    </Link>
                    <Link
                      href="/notifications"
                      className="flex items-center gap-3 rounded-card px-4 py-3.5 text-lg font-bold text-ink-900 hover:bg-cream-200"
                    >
                      <UserCircle size={19} /> Notifications
                    </Link>
                    <button
                      onClick={handleLogout}
                      className="flex w-full items-center gap-3 rounded-card px-4 py-3.5 text-left text-lg font-bold text-clay-600 hover:bg-clay-200"
                    >
                      <LogOut size={19} /> Déconnexion
                    </button>
                  </>
                ) : (
                  <div className="flex flex-col gap-2 px-1">
                    <Link
                      href="/login"
                      className="rounded-full border border-ink-900/12 px-5 py-3.5 text-center text-lg font-bold text-ink-900"
                    >
                      Se connecter
                    </Link>
                    <Link
                      href="/register"
                      className="rounded-full bg-forest-800 px-5 py-3.5 text-center text-lg font-bold text-cream"
                    >
                      S’inscrire
                    </Link>
                  </div>
                )}
              </div>
            </nav>
          </div>
        </>
      )}
    </header>
  );
}
