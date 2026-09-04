'use client';

import { useState } from 'react';
import { useAuth } from '@/app/contexts/AuthContext';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import Header from '@/app/components/Header';
import Footer from '@/app/components/Footer';
import { Loader2 } from 'lucide-react';

/**
 * Ou chaque role atterrit apres connexion. Le client va a la boutique, le
 * personnel a son poste de travail.
 */
const HOME_BY_ROLE = {
  client: '/',
  preparateur: '/preparateur',
  livreur: '/livreur',
  admin: '/admin',
};

const FIELD =
  'mt-2 w-full rounded-card border border-ink-900/12 bg-white px-4 py-3 font-medium text-ink-900 outline-none transition placeholder:text-ink-300 focus:border-forest-600';

export default function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const { login } = useAuth();
  const router = useRouter();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    try {
      const user = await login(email, password);
      // Le rôle vient du compte, pas d'un choix à la connexion : chacun
      // atterrit directement sur son espace de travail.
      router.push(HOME_BY_ROLE[user?.role] ?? '/');
    } catch (err) {
      setError(err.response?.data?.message || 'E-mail ou mot de passe incorrect.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <Header />
      <main className="min-h-screen bg-cream">
        <div className="mx-auto max-w-lg px-4 py-16">
          <div className="rounded-panel border border-ink-900/8 bg-white p-7 shadow-card md:p-9">
            <p className="eyebrow text-clay-500">Votre compte</p>
            <h1 className="mt-3 font-display text-3xl font-semibold text-forest-800">
              Content de vous revoir.
            </h1>
            <p className="mt-3 leading-7 text-ink-500">
              Retrouvez vos paniers, vos adresses et le suivi de vos commandes.
            </p>

            <form onSubmit={handleSubmit} className="mt-7 space-y-5">
              {error && (
                <p className="rounded-card bg-clay-200 px-4 py-3 font-semibold text-clay-600">{error}</p>
              )}

              <label className="block">
                <span className="block text-sm font-bold text-ink-700">Adresse e-mail</span>
                <input
                  type="email"
                  required
                  autoFocus
                  autoComplete="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="vous@exemple.bj"
                  className={FIELD}
                />
              </label>

              <label className="block">
                <span className="flex items-baseline justify-between gap-3">
                  <span className="text-sm font-bold text-ink-700">Mot de passe</span>
                  <Link
                    href="/mot-de-passe-oublie"
                    className="text-sm font-bold text-forest-800 hover:underline"
                  >
                    Oublié&nbsp;?
                  </Link>
                </span>
                <input
                  type="password"
                  required
                  autoComplete="current-password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="••••••••"
                  className={FIELD}
                />
              </label>

              <button
                type="submit"
                disabled={loading}
                className="inline-flex w-full items-center justify-center gap-2 rounded-full bg-forest-800 px-6 py-4 font-bold text-cream transition hover:bg-forest-700 disabled:opacity-60"
              >
                {loading && <Loader2 size={17} className="animate-spin" />}
                {loading ? 'Connexion…' : 'Se connecter'}
              </button>
            </form>

            <p className="mt-7 border-t border-cream-200 pt-6 text-center text-ink-500">
              Pas encore de compte&nbsp;?{' '}
              <Link href="/register" className="font-bold text-forest-800 hover:underline">
                Créer un compte
              </Link>
            </p>
          </div>
        </div>
      </main>
      <Footer />
    </>
  );
}
