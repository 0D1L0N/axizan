'use client';

import { useState } from 'react';
import { useAuth } from '@/app/contexts/AuthContext';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import Header from '@/app/components/Header';
import Footer from '@/app/components/Footer';
import { Loader2 } from 'lucide-react';

const FIELD =
  'mt-2 w-full rounded-card border border-ink-900/12 bg-white px-4 py-3 font-medium text-ink-900 outline-none transition placeholder:text-ink-300 focus:border-forest-600';

/**
 * Champs du formulaire, dans l'ordre où on les remplit.
 *
 * Le téléphone est requis parce qu'il porte les SMS du parcours — commande
 * reçue, paiement confirmé, livreur en route. Un compte sans numéro reçoit
 * les messages qui comptent le moins.
 */
const FIELDS = [
  { name: 'name', label: 'Nom complet', type: 'text', placeholder: 'Awa Hounkpatin', autoComplete: 'name' },
  { name: 'email', label: 'Adresse e-mail', type: 'email', placeholder: 'vous@exemple.bj', autoComplete: 'email' },
  {
    name: 'phone',
    label: 'Téléphone',
    type: 'tel',
    placeholder: '+229 01 XX XX XX XX',
    autoComplete: 'tel',
    hint: 'C’est là qu’arrivent le suivi de commande et l’appel du livreur.',
  },
  {
    name: 'password',
    label: 'Mot de passe',
    type: 'password',
    placeholder: '••••••••',
    autoComplete: 'new-password',
    hint: '8 caractères minimum.',
  },
  {
    name: 'password_confirmation',
    label: 'Confirmer le mot de passe',
    type: 'password',
    placeholder: '••••••••',
    autoComplete: 'new-password',
  },
];

export default function RegisterPage() {
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const { register } = useAuth();
  const router = useRouter();

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    if (formData.password !== formData.password_confirmation) {
      setError('Les deux mots de passe ne sont pas identiques.');
      setLoading(false);
      return;
    }

    try {
      await register(formData);
      // Premiere connexion : on emmene directement completer le compte plutot
      // que de laisser decouvrir au checkout qu'il manque une adresse.
      router.push('/account?bienvenue=1');
    } catch (err) {
      setError(err.response?.data?.message || 'La création du compte a échoué.');
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
            <p className="eyebrow text-clay-500">Bienvenue</p>
            <h1 className="mt-3 font-display text-3xl font-semibold text-forest-800">
              Le marché, choisi pour vous.
            </h1>
            <p className="mt-3 leading-7 text-ink-500">
              Quelques minutes suffisent. Ensuite, vous choisissez un créneau et nous nous occupons du reste.
            </p>

            <form onSubmit={handleSubmit} className="mt-7 space-y-5">
              {error && (
                <p className="rounded-card bg-clay-200 px-4 py-3 font-semibold text-clay-600">{error}</p>
              )}

              {FIELDS.map((field, index) => (
                <label key={field.name} className="block">
                  <span className="block text-sm font-bold text-ink-700">{field.label}</span>
                  <input
                    type={field.type}
                    name={field.name}
                    value={formData[field.name]}
                    onChange={handleChange}
                    required
                    autoFocus={index === 0}
                    autoComplete={field.autoComplete}
                    minLength={field.type === 'password' ? 8 : undefined}
                    placeholder={field.placeholder}
                    className={FIELD}
                  />
                  {field.hint && <span className="mt-1.5 block text-xs leading-5 text-ink-500">{field.hint}</span>}
                </label>
              ))}

              <button
                type="submit"
                disabled={loading}
                className="inline-flex w-full items-center justify-center gap-2 rounded-full bg-forest-800 px-6 py-4 font-bold text-cream transition hover:bg-forest-700 disabled:opacity-60"
              >
                {loading && <Loader2 size={17} className="animate-spin" />}
                {loading ? 'Création…' : 'Créer mon compte'}
              </button>
            </form>

            <p className="mt-7 border-t border-cream-200 pt-6 text-center text-ink-500">
              Déjà un compte&nbsp;?{' '}
              <Link href="/login" className="font-bold text-forest-800 hover:underline">
                Se connecter
              </Link>
            </p>
          </div>
        </div>
      </main>
      <Footer />
    </>
  );
}
