'use client';

import { Suspense, useCallback, useEffect, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import Link from 'next/link';
import Header from '@/app/components/Header';
import Footer from '@/app/components/Footer';
import AddressForm from '@/app/components/AddressForm';
import { useAuth } from '@/app/contexts/AuthContext';
import { useToast } from '@/app/contexts/ToastContext';
import { addressService, authService } from '@/app/services';
import { withAuth } from '@/app/lib/auth-hoc';
import { mapsLink } from '@/app/lib/geo';
import {
  ArrowRight,
  Check,
  ExternalLink,
  Info,
  Loader2,
  MapPin,
  Pencil,
  Plus,
  Star,
  Trash2,
} from 'lucide-react';

const STEP_LABELS = {
  name: 'Votre nom',
  email: 'Votre e-mail',
  phone: 'Votre téléphone',
  address: 'Une adresse de livraison',
};

function AccountPage() {
  const { user, updateProfile } = useAuth();
  const searchParams = useSearchParams();
  const isWelcome = searchParams.get('bienvenue') === '1';

  const [profile, setProfile] = useState({ name: '', email: '', phone: '' });
  const [status, setStatus] = useState(null);
  const [savingProfile, setSavingProfile] = useState(false);
  const [profileErrors, setProfileErrors] = useState({});
  const [profileSaved, setProfileSaved] = useState(false);

  const [addresses, setAddresses] = useState([]);
  const [loadingAddresses, setLoadingAddresses] = useState(true);
  const [editing, setEditing] = useState(null); // null | 'new' | address
  const [addressError, setAddressError] = useState('');

  useEffect(() => {
    if (user) {
      setProfile({ name: user.name ?? '', email: user.email ?? '', phone: user.phone ?? '' });
    }
  }, [user]);

  const refreshStatus = useCallback(() => {
    authService
      .getProfile()
      .then((res) => setStatus(res.data.profile))
      .catch(() => setStatus(null));
  }, []);

  const loadAddresses = useCallback(() => {
    setLoadingAddresses(true);
    addressService
      .list()
      .then((res) => setAddresses(res.data.data))
      .catch(() => setAddressError('Impossible de charger vos adresses.'))
      .finally(() => setLoadingAddresses(false));
  }, []);

  useEffect(() => {
    refreshStatus();
    loadAddresses();
  }, [refreshStatus, loadAddresses]);

  const handleProfileSubmit = async (event) => {
    event.preventDefault();
    setSavingProfile(true);
    setProfileErrors({});
    setProfileSaved(false);
    try {
      await updateProfile(profile);
      setProfileSaved(true);
      refreshStatus();
    } catch (error) {
      setProfileErrors(
        error.response?.data?.errors || { global: 'Les informations n’ont pas pu être enregistrées.' }
      );
    } finally {
      setSavingProfile(false);
    }
  };

  const handleAddressSaved = () => {
    setEditing(null);
    loadAddresses();
    refreshStatus();
  };

  const handleDelete = async (address) => {
    if (!window.confirm(`Supprimer l’adresse « ${address.label} » ?`)) return;
    try {
      await addressService.remove(address.id);
      loadAddresses();
      refreshStatus();
    } catch {
      setAddressError('La suppression a échoué.');
    }
  };

  const handleSetDefault = async (address) => {
    try {
      await addressService.update(address.id, { ...address, is_default: true });
      loadAddresses();
    } catch {
      setAddressError('Impossible de définir cette adresse par défaut.');
    }
  };

  const missing = status?.missing ?? [];
  const isComplete = status?.is_complete;

  return (
    <>
      <Header />
      <main className="min-h-screen bg-cream">
        {/* ---- En-tete ---- */}
        <section className="relative grain overflow-hidden bg-forest-900 text-cream">
          <div className="cowrie-field absolute inset-0 opacity-60" />
          <div className="relative mx-auto max-w-4xl px-4 py-14">
            <p className="eyebrow text-gold-500">{isWelcome ? 'Bienvenue' : 'Mon compte'}</p>
            <h1 className="mt-4 font-display text-display-sm font-semibold">
              {isWelcome ? `Bonjour ${user?.name?.split(' ')[0] || ''}, finissons votre compte.` : 'Mon compte'}
            </h1>
            <p className="mt-4 max-w-2xl text-lg leading-8 text-cream/70">
              {isWelcome
                ? 'Trois minutes une seule fois, et vos prochaines commandes se passent en deux clics.'
                : 'Vos informations et vos adresses de livraison.'}
            </p>

            {status && !isComplete && (
              <ul className="mt-8 grid gap-3 sm:grid-cols-2">
                {Object.entries(STEP_LABELS).map(([key, label]) => {
                  const done = !missing.includes(key);
                  return (
                    <li
                      key={key}
                      className={`flex items-center gap-3 rounded-card border px-4 py-3 font-semibold ${
                        done ? 'border-gold-500/40 bg-gold-500/10 text-cream' : 'border-cream/15 bg-cream/5 text-cream/60'
                      }`}
                    >
                      <span
                        className={`grid h-6 w-6 shrink-0 place-items-center rounded-full ${
                          done ? 'bg-gold-500 text-forest-950' : 'border border-cream/30'
                        }`}
                      >
                        {done && <Check size={14} strokeWidth={3} />}
                      </span>
                      {label}
                    </li>
                  );
                })}
              </ul>
            )}

            {status && isComplete && (
              <div className="mt-8 inline-flex items-center gap-3 rounded-full bg-gold-500 px-5 py-3 font-bold text-forest-950">
                <Check size={18} strokeWidth={3} /> Compte complet
                <Link href="/products" className="inline-flex items-center gap-1.5 underline">
                  Faire le marché <ArrowRight size={15} />
                </Link>
              </div>
            )}
          </div>
        </section>

        <div className="mx-auto max-w-4xl space-y-8 px-4 py-12">
          {/* ---- Informations ---- */}
          <section className="rounded-panel border border-ink-900/8 bg-white p-6 shadow-card md:p-8">
            <h2 className="font-display text-2xl font-semibold text-forest-800">Vos informations</h2>
            <p className="mt-1.5 text-ink-500">
              Le téléphone sert au livreur le jour de la livraison. C’est le seul moyen de vous joindre.
            </p>

            <form onSubmit={handleProfileSubmit} className="mt-6 space-y-5">
              {profileErrors.global && (
                <p className="rounded-card bg-clay-200 px-4 py-3 font-semibold text-clay-600">
                  {profileErrors.global}
                </p>
              )}

              <div className="grid gap-5 sm:grid-cols-2">
                <ProfileField label="Nom complet" error={profileErrors.name?.[0]}>
                  <input
                    required
                    value={profile.name}
                    onChange={(e) => setProfile((p) => ({ ...p, name: e.target.value }))}
                    className={inputClass}
                  />
                </ProfileField>
                <ProfileField label="Téléphone" error={profileErrors.phone?.[0]}>
                  <input
                    type="tel"
                    value={profile.phone}
                    onChange={(e) => setProfile((p) => ({ ...p, phone: e.target.value }))}
                    placeholder="+229 97 00 00 00"
                    className={inputClass}
                  />
                </ProfileField>
              </div>

              <ProfileField label="Adresse e-mail" error={profileErrors.email?.[0]}>
                <input
                  type="email"
                  required
                  value={profile.email}
                  onChange={(e) => setProfile((p) => ({ ...p, email: e.target.value }))}
                  className={inputClass}
                />
              </ProfileField>

              <div className="flex flex-wrap items-center gap-4">
                <button
                  type="submit"
                  disabled={savingProfile}
                  className="inline-flex items-center gap-2 rounded-full bg-forest-800 px-6 py-3.5 font-bold text-cream transition hover:bg-forest-700 disabled:opacity-60"
                >
                  {savingProfile && <Loader2 size={16} className="animate-spin" />}
                  Enregistrer
                </button>
                {profileSaved && (
                  <span className="inline-flex items-center gap-2 font-semibold text-forest-600">
                    <Check size={17} strokeWidth={3} /> Enregistré
                  </span>
                )}
              </div>
            </form>
          </section>

          {/* ---- Notifications ---- */}
          <NotificationPreferences />

          {/* ---- Mot de passe ---- */}
          <PasswordSection />

          {/* ---- Adresses ---- */}
          <section className="rounded-panel border border-ink-900/8 bg-white p-6 shadow-card md:p-8">
            <div className="flex flex-wrap items-start justify-between gap-4">
              <div>
                <h2 className="font-display text-2xl font-semibold text-forest-800">Adresses de livraison</h2>
                <p className="mt-1.5 text-ink-500">Vous pouvez en enregistrer plusieurs : maison, bureau…</p>
              </div>
              {editing === null && (
                <button
                  onClick={() => setEditing('new')}
                  className="inline-flex items-center gap-2 rounded-full bg-gold-500 px-5 py-3 font-bold text-forest-950 shadow-glow transition hover:bg-gold-400"
                >
                  <Plus size={17} /> Ajouter
                </button>
              )}
            </div>

            {addressError && (
              <p className="mt-5 rounded-card bg-clay-200 px-4 py-3 font-semibold text-clay-600">{addressError}</p>
            )}

            {editing !== null && (
              <div className="mt-6 rounded-panel border border-ink-900/8 bg-cream p-5 md:p-6">
                <h3 className="mb-5 font-display text-xl font-semibold text-forest-800">
                  {editing === 'new' ? 'Nouvelle adresse' : `Modifier « ${editing.label} »`}
                </h3>
                <AddressForm
                  address={editing === 'new' ? null : editing}
                  onSaved={handleAddressSaved}
                  onCancel={() => setEditing(null)}
                />
              </div>
            )}

            <div className="mt-6 space-y-4">
              {loadingAddresses ? (
                <div className="h-28 animate-pulse rounded-panel bg-cream-100" />
              ) : addresses.length === 0 && editing === null ? (
                <div className="rounded-panel border border-dashed border-ink-900/15 p-8 text-center">
                  <MapPin className="mx-auto text-ink-300" size={26} />
                  <p className="mt-3 font-semibold text-ink-700">Aucune adresse enregistrée</p>
                  <p className="mt-1 text-ink-500">Il en faut une pour passer commande.</p>
                  <button
                    onClick={() => setEditing('new')}
                    className="mt-5 inline-flex items-center gap-2 rounded-full bg-forest-800 px-5 py-3 font-bold text-cream"
                  >
                    <Plus size={17} /> Ajouter ma première adresse
                  </button>
                </div>
              ) : (
                addresses.map((address) => {
                  const link = address.maps_url || mapsLink(address.latitude, address.longitude, address.label);
                  return (
                    <article
                      key={address.id}
                      className="flex flex-col gap-4 rounded-panel border border-ink-900/8 bg-cream-100 p-5 sm:flex-row sm:items-start sm:justify-between"
                    >
                      <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                          <h3 className="font-display text-xl font-semibold text-forest-800">{address.label}</h3>
                          {address.is_default && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-gold-500 px-2.5 py-1 text-xs font-bold text-forest-950">
                              <Star size={11} fill="currentColor" /> Par défaut
                            </span>
                          )}
                        </div>
                        <p className="mt-1.5 font-medium text-ink-700">
                          {[address.numero_rue, address.rue, address.quartier].filter(Boolean).join(', ')}
                        </p>
                        {address.landmark && <p className="mt-1 text-ink-500">Repère : {address.landmark}</p>}
                        {link && (
                          <a
                            href={link}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="mt-2.5 inline-flex items-center gap-1.5 text-sm font-bold text-forest-600 hover:underline"
                          >
                            <ExternalLink size={14} /> Ouvrir dans Maps
                          </a>
                        )}
                      </div>

                      <div className="flex shrink-0 gap-2">
                        {!address.is_default && (
                          <button
                            onClick={() => handleSetDefault(address)}
                            title="Définir par défaut"
                            className="grid h-10 w-10 place-items-center rounded-full border border-ink-900/10 bg-white text-ink-700 transition hover:bg-gold-500 hover:text-forest-950"
                          >
                            <Star size={16} />
                          </button>
                        )}
                        <button
                          onClick={() => setEditing(address)}
                          title="Modifier"
                          className="grid h-10 w-10 place-items-center rounded-full border border-ink-900/10 bg-white text-ink-700 transition hover:bg-cream-200"
                        >
                          <Pencil size={16} />
                        </button>
                        <button
                          onClick={() => handleDelete(address)}
                          title="Supprimer"
                          className="grid h-10 w-10 place-items-center rounded-full border border-ink-900/10 bg-white text-clay-500 transition hover:bg-clay-200"
                        >
                          <Trash2 size={16} />
                        </button>
                      </div>
                    </article>
                  );
                })
              )}
            </div>
          </section>
        </div>
      </main>
      <Footer />
    </>
  );
}

const inputClass =
  'w-full rounded-card border border-ink-900/12 bg-white px-4 py-3 font-medium text-ink-900 outline-none transition placeholder:text-ink-300 focus:border-forest-600';

/**
 * Preferences de notification.
 *
 * Un service qui envoie des SMS sans permettre de les couper se fait bloquer
 * par ses propres clients. Ce qui touche a l'argent ou a une livraison
 * imminente reste hors de ces reglages, et on le dit.
 */
function NotificationPreferences() {
  const { user, updateProfile } = useAuth();
  const toast = useToast();

  const preferences = user?.notification_preferences || {};
  const [saving, setSaving] = useState(null);

  const toggle = async (channel, next) => {
    setSaving(channel);
    try {
      await updateProfile({
        notification_preferences: { ...preferences, [channel]: next },
      });
      toast.success(next ? 'Canal réactivé.' : 'Canal désactivé.');
    } catch (error) {
      toast.fromApi(error, 'La préférence n’a pas pu être enregistrée.');
    } finally {
      setSaving(null);
    }
  };

  const CHANNELS = [
    ['sms', 'SMS', 'Suivi de livraison sur votre téléphone.'],
    ['email', 'E-mail', 'Récapitulatifs et preuve de livraison.'],
  ];

  return (
    <section className="rounded-panel border border-ink-900/8 bg-white p-6 shadow-card md:p-8">
      <h2 className="font-display text-2xl font-semibold text-forest-800">Notifications</h2>
      <p className="mt-1.5 text-ink-500">Choisissez comment nous vous tenons au courant.</p>

      <div className="mt-6 divide-y divide-ink-900/8">
        {CHANNELS.map(([channel, label, hint]) => {
          const active = preferences[channel] !== false;

          return (
            <div key={channel} className="flex items-center justify-between gap-4 py-4">
              <div className="min-w-0">
                <p className="font-bold text-ink-900">{label}</p>
                <p className="mt-0.5 text-sm text-ink-500">{hint}</p>
              </div>
              <button
                role="switch"
                aria-checked={active}
                aria-label={`${label} : ${active ? 'activé' : 'désactivé'}`}
                disabled={saving === channel}
                onClick={() => toggle(channel, !active)}
                className={`relative h-8 w-14 shrink-0 rounded-full transition disabled:opacity-60 ${
                  active ? 'bg-forest-800' : 'bg-ink-300'
                }`}
              >
                <span
                  className={`absolute top-1 h-6 w-6 rounded-full bg-white transition-all ${
                    active ? 'left-7' : 'left-1'
                  }`}
                />
              </button>
            </div>
          );
        })}
      </div>

      <p className="mt-5 flex items-start gap-2.5 rounded-card bg-cream-100 px-4 py-3 text-sm leading-6 text-ink-500">
        <Info size={17} className="mt-0.5 shrink-0 text-forest-600" />
        <span>
          Les messages qui concernent votre paiement ou une livraison imminente vous parviennent
          toujours, même canal désactivé — sinon vous risqueriez de manquer une commande déjà payée.
        </span>
      </p>
    </section>
  );
}

function PasswordSection() {
  const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' });
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const [done, setDone] = useState('');

  const handleSubmit = async (event) => {
    event.preventDefault();
    setSaving(true);
    setErrors({});
    setDone('');
    try {
      const res = await authService.changePassword(form);
      setDone(res.data.message);
      setForm({ current_password: '', password: '', password_confirmation: '' });
    } catch (error) {
      setErrors(
        error.response?.data?.errors || { global: 'Le mot de passe n’a pas pu être modifié.' }
      );
    } finally {
      setSaving(false);
    }
  };

  return (
    <section className="rounded-panel border border-ink-900/8 bg-white p-6 shadow-card md:p-8">
      <h2 className="font-display text-2xl font-semibold text-forest-800">Mot de passe</h2>
      <p className="mt-1.5 text-ink-500">
        Le changer déconnecte vos autres appareils, mais pas celui-ci.
      </p>

      <form onSubmit={handleSubmit} className="mt-6 space-y-5">
        {errors.global && (
          <p className="rounded-card bg-clay-200 px-4 py-3 font-semibold text-clay-600">{errors.global}</p>
        )}

        <ProfileField label="Mot de passe actuel" error={errors.current_password?.[0]}>
          <input
            type="password"
            required
            autoComplete="current-password"
            value={form.current_password}
            onChange={(e) => setForm((f) => ({ ...f, current_password: e.target.value }))}
            className={inputClass}
          />
        </ProfileField>

        <div className="grid gap-5 sm:grid-cols-2">
          <ProfileField label="Nouveau mot de passe" error={errors.password?.[0]}>
            <input
              type="password"
              required
              minLength={8}
              autoComplete="new-password"
              value={form.password}
              onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))}
              className={inputClass}
            />
          </ProfileField>
          <ProfileField label="Confirmer">
            <input
              type="password"
              required
              minLength={8}
              autoComplete="new-password"
              value={form.password_confirmation}
              onChange={(e) => setForm((f) => ({ ...f, password_confirmation: e.target.value }))}
              className={inputClass}
            />
          </ProfileField>
        </div>

        <div className="flex flex-wrap items-center gap-4">
          <button
            type="submit"
            disabled={saving}
            className="inline-flex items-center gap-2 rounded-full border border-ink-900/12 px-6 py-3.5 font-bold text-forest-800 transition hover:bg-cream-100 disabled:opacity-60"
          >
            {saving && <Loader2 size={16} className="animate-spin" />}
            Changer le mot de passe
          </button>
          {done && (
            <span className="inline-flex items-center gap-2 font-semibold text-forest-600">
              <Check size={17} strokeWidth={3} /> {done}
            </span>
          )}
        </div>
      </form>
    </section>
  );
}

function ProfileField({ label, error, children }) {
  return (
    <label className="block">
      <span className="block text-sm font-bold text-ink-700">{label}</span>
      <span className="mt-2 block">{children}</span>
      {error && <span className="mt-1.5 block text-sm font-semibold text-clay-600">{error}</span>}
    </label>
  );
}

const GuardedAccountPage = withAuth(AccountPage);

// useSearchParams impose une frontiere Suspense : sans elle, la page bascule en
// rendu dynamique au build et Next refuse de la prerendre.
export default function AccountRoute() {
  return (
    <Suspense
      fallback={
        <>
          <Header />
          <main className="mx-auto max-w-4xl px-4 py-12">
            <div className="h-72 animate-pulse rounded-panel bg-white" />
          </main>
        </>
      }
    >
      <GuardedAccountPage />
    </Suspense>
  );
}
