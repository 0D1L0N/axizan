'use client';

import { Suspense, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import Link from 'next/link';
import Header from '@/app/components/Header';
import Footer from '@/app/components/Footer';
import { authService } from '@/app/services';
import { CheckCircle2, Loader2 } from 'lucide-react';

const inputClass =
  'mt-2 w-full rounded-card border border-ink-900/12 bg-white px-4 py-3 font-medium text-ink-900 outline-none transition placeholder:text-ink-300 focus:border-forest-600';

function ResetPasswordForm() {
  const params = useSearchParams();
  const router = useRouter();

  const token = params.get('token') || '';
  const email = params.get('email') || '';

  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [loading, setLoading] = useState(false);
  const [done, setDone] = useState(false);
  const [errors, setErrors] = useState({});

  const handleSubmit = async (event) => {
    event.preventDefault();
    setLoading(true);
    setErrors({});
    try {
      await authService.resetPassword({
        token,
        email,
        password,
        password_confirmation: confirmation,
      });
      setDone(true);
      setTimeout(() => router.push('/login'), 2500);
    } catch (err) {
      const data = err.response?.data;
      setErrors(data?.errors || { global: data?.message || 'La réinitialisation a échoué.' });
    } finally {
      setLoading(false);
    }
  };

  if (!token || !email) {
    return (
      <Shell>
        <h1 className="font-display text-3xl font-semibold text-forest-800">Lien incomplet</h1>
        <p className="mt-3 leading-7 text-ink-500">
          Ce lien ne contient pas les informations nécessaires. Il a peut-être été tronqué par votre messagerie.
        </p>
        <Link
          href="/mot-de-passe-oublie"
          className="mt-6 inline-flex rounded-full bg-forest-800 px-6 py-3.5 font-bold text-cream"
        >
          Demander un nouveau lien
        </Link>
      </Shell>
    );
  }

  if (done) {
    return (
      <Shell>
        <div className="grid h-14 w-14 place-items-center rounded-full bg-gold-500 text-forest-950">
          <CheckCircle2 size={26} />
        </div>
        <h1 className="mt-5 font-display text-3xl font-semibold text-forest-800">Mot de passe modifié</h1>
        <p className="mt-3 leading-7 text-ink-500">
          Par sécurité, vos autres appareils ont été déconnectés. Redirection vers la connexion…
        </p>
      </Shell>
    );
  }

  return (
    <Shell>
      <p className="eyebrow text-clay-500">Nouveau mot de passe</p>
      <h1 className="mt-3 font-display text-3xl font-semibold text-forest-800">Choisissez votre clé.</h1>
      <p className="mt-3 leading-7 text-ink-500">
        Pour le compte <strong className="text-ink-900">{email}</strong>.
      </p>

      <form onSubmit={handleSubmit} className="mt-7 space-y-5">
        {errors.global && (
          <p className="rounded-card bg-clay-200 px-4 py-3 font-semibold text-clay-600">{errors.global}</p>
        )}

        <label className="block">
          <span className="block text-sm font-bold text-ink-700">Nouveau mot de passe</span>
          <input
            type="password"
            required
            autoFocus
            minLength={8}
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            className={inputClass}
          />
          {errors.password && (
            <span className="mt-1.5 block text-sm font-semibold text-clay-600">{errors.password[0]}</span>
          )}
        </label>

        <label className="block">
          <span className="block text-sm font-bold text-ink-700">Confirmer le mot de passe</span>
          <input
            type="password"
            required
            minLength={8}
            value={confirmation}
            onChange={(e) => setConfirmation(e.target.value)}
            className={inputClass}
          />
        </label>

        <button
          type="submit"
          disabled={loading}
          className="inline-flex w-full items-center justify-center gap-2 rounded-full bg-forest-800 px-6 py-4 font-bold text-cream transition hover:bg-forest-700 disabled:opacity-60"
        >
          {loading && <Loader2 size={17} className="animate-spin" />}
          Enregistrer le nouveau mot de passe
        </button>
      </form>
    </Shell>
  );
}

function Shell({ children }) {
  return (
    <div className="mx-auto max-w-lg px-4 py-16">
      <div className="rounded-panel border border-ink-900/8 bg-white p-7 shadow-card md:p-9">{children}</div>
    </div>
  );
}

export default function ResetPasswordPage() {
  return (
    <>
      <Header />
      <main className="min-h-screen bg-cream">
        <Suspense
          fallback={
            <div className="mx-auto max-w-lg px-4 py-16">
              <div className="h-72 animate-pulse rounded-panel bg-white" />
            </div>
          }
        >
          <ResetPasswordForm />
        </Suspense>
      </main>
      <Footer />
    </>
  );
}
