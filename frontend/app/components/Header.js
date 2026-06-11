'use client';

import Link from 'next/link';
import { useAuth } from '@/app/contexts/AuthContext';
import { useRouter } from 'next/navigation';

export default function Header() {
  const { user, logout } = useAuth();
  const router = useRouter();

  const handleLogout = async () => {
    await logout();
    router.push('/');
  };

  return (
    <header className="bg-primary text-white sticky top-0 z-50 shadow-md">
      <div className="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
        {/* Logo */}
        <Link href="/" className="flex items-center gap-2 text-2xl font-bold">
          🍲 <span>Axizán</span>
        </Link>

        {/* Navigation */}
        <nav className="hidden md:flex items-center gap-6">
          <Link href="/" className="hover:text-gray-100">
            Accueil
          </Link>
          <Link href="/products" className="hover:text-gray-100">
            Catalogue
          </Link>
          <Link href="/menus" className="hover:text-gray-100">
            Menus
          </Link>
          {user && (
            <>
              <Link href="/orders" className="hover:text-gray-100">
                Mes Commandes
              </Link>
            </>
          )}
        </nav>

        {/* User Actions */}
        <div className="flex items-center gap-4">
          {user ? (
            <>
              <Link href="/cart" className="relative">
                🛒<span className="ml-1">Panier</span>
              </Link>
              <div className="flex items-center gap-3">
                <img
                  src={user.avatar_url || 'https://via.placeholder.com/40'}
                  alt={user.name}
                  className="w-10 h-10 rounded-full"
                />
                <div className="hidden sm:block">
                  <p className="text-sm font-semibold">{user.name}</p>
                  <p className="text-xs text-gray-200">{user.role}</p>
                </div>
              </div>
              <button
                onClick={handleLogout}
                className="bg-red-600 hover:bg-red-700 px-4 py-2 rounded text-sm"
              >
                Déconnexion
              </button>
            </>
          ) : (
            <>
              <Link href="/login" className="bg-white text-primary px-4 py-2 rounded font-semibold">
                Connexion
              </Link>
              <Link href="/register" className="border-2 border-white px-4 py-2 rounded font-semibold">
                S'inscrire
              </Link>
            </>
          )}
        </div>
      </div>
    </header>
  );
}
