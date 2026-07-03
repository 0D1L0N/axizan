'use client';

import Link from 'next/link';
import { useAuth } from '@/app/contexts/AuthContext';
import { useCart } from '@/app/contexts/CartContext';
import { useRouter } from 'next/navigation';
import { LogOut, Menu, ShoppingBasket, UserCircle } from 'lucide-react';

export default function Header() {
  const { user, logout } = useAuth();
  const { items } = useCart();
  const router = useRouter();

  const handleLogout = async () => {
    await logout();
    router.push('/');
  };

  return (
    <header className="sticky top-0 z-50 border-b border-black/5 bg-white/90 backdrop-blur-xl">
      <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
        <Link href="/" className="flex items-center gap-3">
          <span className="grid h-11 w-11 place-items-center rounded-2xl bg-[#12372A] text-xl text-white shadow-sm">
            A
          </span>
          <span>
            <span className="block text-xl font-black tracking-tight text-[#12372A]">Axizán</span>
            <span className="hidden text-xs font-medium text-gray-500 sm:block">Marché frais livré au Bénin</span>
          </span>
        </Link>

        <nav className="hidden items-center gap-1 rounded-full border border-gray-200 bg-white p-1 text-sm font-semibold text-gray-700 shadow-sm md:flex">
          <Link href="/" className="rounded-full px-4 py-2 hover:bg-[#F4F0E8]">
            Accueil
          </Link>
          <Link href="/products" className="rounded-full px-4 py-2 hover:bg-[#F4F0E8]">
            Catalogue
          </Link>
          <Link href="/menus" className="rounded-full px-4 py-2 hover:bg-[#F4F0E8]">
            Paniers-recettes
          </Link>
          {user && (
            <Link href="/orders" className="rounded-full px-4 py-2 hover:bg-[#F4F0E8]">
              Commandes
            </Link>
          )}
        </nav>

        <div className="flex items-center gap-2">
          <Link
            href="/cart"
            className="relative inline-flex h-11 items-center gap-2 rounded-full bg-[#FFB000] px-4 text-sm font-black text-[#1C140D] shadow-sm transition hover:bg-[#f3a300]"
            title="Voir le panier"
          >
            <ShoppingBasket size={18} />
            <span className="hidden sm:inline">Panier</span>
            {items.length > 0 && (
              <span className="absolute -right-2 -top-2 grid h-6 min-w-6 place-items-center rounded-full bg-[#D62828] px-1 text-xs font-black text-white">
                {items.length}
              </span>
            )}
          </Link>

          {user ? (
            <>
              <div className="hidden items-center gap-3 rounded-full border border-gray-200 bg-white py-1 pl-1 pr-3 md:flex">
                <img
                  src={user.avatar_url || 'https://via.placeholder.com/40'}
                  alt={user.name}
                  className="h-9 w-9 rounded-full object-cover"
                />
                <div>
                  <p className="text-sm font-bold text-gray-900">{user.name}</p>
                  <p className="text-xs text-gray-500 capitalize">{user.role}</p>
                </div>
              </div>
              <button
                onClick={handleLogout}
                className="grid h-11 w-11 place-items-center rounded-full border border-gray-200 bg-white text-gray-700 hover:bg-gray-100"
                title="Déconnexion"
              >
                <LogOut size={18} />
              </button>
            </>
          ) : (
            <>
              <Link
                href="/login"
                className="hidden h-11 items-center gap-2 rounded-full border border-gray-200 bg-white px-4 text-sm font-bold text-gray-800 hover:bg-gray-100 sm:inline-flex"
              >
                <UserCircle size={17} />
                Se connecter
              </Link>
              <Link href="/register" className="hidden rounded-full bg-[#12372A] px-4 py-3 text-sm font-black text-white shadow-sm hover:bg-[#0f2f24] lg:inline-flex">
                S'inscrire
              </Link>
            </>
          )}
          <button className="grid h-11 w-11 place-items-center rounded-full border border-gray-200 bg-white text-gray-800 md:hidden" title="Menu">
            <Menu size={19} />
          </button>
        </div>
      </div>
    </header>
  );
}
