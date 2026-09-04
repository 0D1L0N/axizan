'use client';

import { useState } from 'react';
import Link from 'next/link';
import Header from '@/app/components/Header';
import Footer from '@/app/components/Footer';
import { authService } from '@/app/services';
import { ArrowLeft, Loader2, MailCheck } from 'lucide-react';

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [sent, setSent] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (event) => {
    event.preventDefault();
    setLoading(true);
    setError('');
    try {
      await authService.forgotPassword(email);
      setSent(true);
    } catch (err) {
      setError(
        err.response?.status === 429
          ? 'Un lien vient déjà d’être envoyé. Patientez quelques minutes.'
          : 'L’envoi a échoué. Réessayez dans un instant.'
      );
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <Header />
      <main className="min-h-screen bg-cream">
        <div className="mx-auto max-w-lg px-4 py-16">
          <Link href="/login" className="inline-flex items-center gap-2 font-bold text-forest-800 hover:underline">
            <ArrowLeft size={17} /> Retour à la connexion
          </Link>

          <div className="mt-6 rounded-panel border border-ink-900/8 bg-white p-7 shadow-card md:p-9">
            {sent ? (
              <>
                <div className="grid h-14 w-14 place-items-center rounded-full bg-gold-500 text-forest-950">
                  <MailCheck size={26} />
                </div>
                <h1 className="mt-5 font-display text-3xl font-semibold text-forest-800">Vérifiez vos e-mails</h1>
                <p className="mt-3 leading-7 text-ink-500">
                  Si un compte existe avec <strong className="text-ink-900">{email}</strong>, un lien de
                  réinitialisation vient d’y être envoyé. Il est valable une heure.
                </p>
                <p className="mt-4 text-sm leading-6 text-ink-500">
                  Rien reçu&nbsp;? Regardez dans les indésirables, puis{' '}
                  <button onClick={() => setSent(false)} className="font-bold text-forest-800 underline">
                    réessayez
                  </button>
                  .
                </p>
              </>
            ) : (
              <>
                <p className="eyebrow text-clay-500">Mot de passe oublié</p>
                <h1 className="mt-3 font-display text-3xl font-semibold text-forest-800">
                  On vous renvoie une clé.
                </h1>
                <p className="mt-3 leading-7 text-ink-500">
                  Saisissez l’adresse e-mail de votre compte. Nous vous enverrons un lien pour choisir un
                  nouveau mot de passe.
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
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      placeholder="vous@exemple.bj"
                      className="mt-2 w-full rounded-card border border-ink-900/12 bg-white px-4 py-3 font-medium text-ink-900 outline-none transition placeholder:text-ink-300 focus:border-forest-600"
                    />
                  </label>
                  <button
                    type="submit"
                    disabled={loading}
                    className="inline-flex w-full items-center justify-center gap-2 rounded-full bg-forest-800 px-6 py-4 font-bold text-cream transition hover:bg-forest-700 disabled:opacity-60"
                  >
                    {loading && <Loader2 size={17} className="animate-spin" />}
                    Envoyer le lien
                  </button>
                </form>
              </>
            )}
          </div>
        </div>
      </main>
      <Footer />
    </>
  );
}
