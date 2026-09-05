'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import { adminService } from '@/app/services';
import { withRole } from '@/app/lib/auth-hoc';
import { useToast } from '@/app/contexts/ToastContext';
import Header from '@/app/components/Header';
import { formatPrice, roundXof } from '@/app/lib/units';
import {
  AlertTriangle,
  BadgeCheck,
  Bike,
  Check,
  Clock,
  Loader2,
  PackageSearch,
  RefreshCw,
  ShieldCheck,
  Radar,
  Store,
  UserPlus,
  Users,
} from 'lucide-react';

const PROVIDERS = [
  { value: 'mtn', label: 'MTN MoMo' },
  { value: 'moov', label: 'Moov Money' },
  { value: 'celtiis', label: 'Celtiis Cash' },
];

const PROVIDER_LABEL = Object.fromEntries(PROVIDERS.map((p) => [p.value, p.label]));

/** Message d'erreur d'un champ, tel que renvoyé par la validation Laravel. */
function FieldError({ errors, name }) {
  const message = errors[name]?.[0];
  return message ? <p className="mt-1 text-xs font-semibold text-clay-600">{message}</p> : null;
}

/** « il y a 2 h » — l'ancienneté est l'information qui fait agir. */
function waitedSince(iso) {
  if (!iso) return null;
  const minutes = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000));
  if (minutes < 60) return `${minutes} min`;
  const hours = Math.round(minutes / 60);
  if (hours < 24) return `${hours} h`;
  return `${Math.round(hours / 24)} j`;
}

/**
 * Une commande à vérifier.
 *
 * La validation n'est pas un bouton : c'est une décision. Le formulaire
 * s'ouvre sur place, pré-rempli de la référence QUE LE CLIENT A DÉCLARÉE —
 * celle qu'on va chercher sur le compte marchand — mais rien ne part tant que
 * l'administrateur n'a pas confirmé l'avoir retrouvée. Cette référence est ce
 * qui permettra plus tard de trancher un « j'ai payé » / « non ».
 */
function PaymentRow({ order, onValidated }) {
  const toast = useToast();
  const declared = order.payment?.payer_declared_reference || '';

  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({
    reference_number: declared,
    payment_provider: order.payment?.payment_provider || '',
    phone_number: order.payment?.phone_number || order.client?.phone || '',
  });

  const waited = waitedSince(order.created_at);

  const submit = async (event) => {
    event.preventDefault();
    setBusy(true);
    try {
      await adminService.validatePayment(order.id, {
        reference_number: form.reference_number.trim(),
        payment_provider: form.payment_provider || null,
        phone_number: form.phone_number || null,
      });
      toast.success(`Paiement ${order.order_number} validé. La commande part en préparation.`);
      onValidated();
    } catch (err) {
      toast.fromApi(err, 'La validation a échoué.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <article className="rounded-card border border-cream-300 bg-white shadow-card">
      <div className="flex flex-wrap items-start justify-between gap-4 p-5">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="font-display text-lg font-semibold text-ink-900">{order.order_number}</h3>
            {declared ? (
              <span className="inline-flex items-center gap-1 rounded-full bg-gold-200 px-2.5 py-0.5 text-xs font-bold text-forest-900">
                <BadgeCheck size={13} /> Référence déclarée
              </span>
            ) : (
              <span className="inline-flex items-center gap-1 rounded-full bg-cream-200 px-2.5 py-0.5 text-xs font-bold text-ink-500">
                <Clock size={13} /> Aucune déclaration
              </span>
            )}
          </div>
          <p className="mt-1 text-sm text-ink-500">
            {order.client?.name}
            {order.client?.phone ? ` · ${order.client.phone}` : ''}
            {waited ? ` · en attente depuis ${waited}` : ''}
          </p>
        </div>

        <div className="text-right">
          <p className="font-display text-xl font-semibold tabular-nums text-forest-800">
            {formatPrice(roundXof(order.total))}
          </p>
          {order.payment?.payment_provider && (
            <p className="text-xs text-ink-500">
              {PROVIDER_LABEL[order.payment.payment_provider] || order.payment.payment_provider}
            </p>
          )}
        </div>
      </div>

      {declared && (
        <div className="mx-5 mb-4 rounded-2xl bg-cream-100 px-4 py-3">
          <p className="eyebrow text-ink-300">Déclarée par le client</p>
          <p className="mt-1 select-all break-all font-mono text-[15px] font-bold text-ink-900">{declared}</p>
        </div>
      )}

      {!open ? (
        <div className="border-t border-cream-200 px-5 py-4">
          <button
            onClick={() => setOpen(true)}
            className="rounded-full bg-forest-800 px-5 py-2.5 text-sm font-bold text-cream transition hover:bg-forest-700"
          >
            Vérifier et valider
          </button>
        </div>
      ) : (
        <form onSubmit={submit} className="space-y-4 border-t border-cream-200 bg-cream-100/60 px-5 py-5">
          <p className="flex items-start gap-2 text-sm leading-6 text-ink-700">
            <ShieldCheck size={17} className="mt-0.5 shrink-0 text-forest-600" />
            <span>
              Retrouvez cette transaction sur le compte marchand, vérifiez que le montant correspond, puis
              recopiez sa référence ici.
            </span>
          </p>

          <div className="grid gap-3 sm:grid-cols-2">
            <label className="sm:col-span-2 block">
              <span className="eyebrow text-ink-500">Référence de la transaction</span>
              <input
                required
                autoFocus
                value={form.reference_number}
                onChange={(e) => setForm({ ...form, reference_number: e.target.value })}
                placeholder="Ex. MP260904.1432.A54321"
                className="mt-1.5 w-full rounded-xl border border-cream-300 bg-white px-3.5 py-2.5 font-mono text-[15px] text-ink-900 placeholder:font-sans placeholder:text-ink-300"
              />
            </label>

            <label className="block">
              <span className="eyebrow text-ink-500">Opérateur</span>
              <select
                value={form.payment_provider}
                onChange={(e) => setForm({ ...form, payment_provider: e.target.value })}
                className="mt-1.5 w-full rounded-xl border border-cream-300 bg-white px-3.5 py-2.5 text-[15px] text-ink-900"
              >
                <option value="">Non précisé</option>
                {PROVIDERS.map((p) => (
                  <option key={p.value} value={p.value}>
                    {p.label}
                  </option>
                ))}
              </select>
            </label>

            <label className="block">
              <span className="eyebrow text-ink-500">Numéro payeur</span>
              <input
                value={form.phone_number}
                onChange={(e) => setForm({ ...form, phone_number: e.target.value })}
                placeholder="+229…"
                className="mt-1.5 w-full rounded-xl border border-cream-300 bg-white px-3.5 py-2.5 text-[15px] text-ink-900 placeholder:text-ink-300"
              />
            </label>
          </div>

          <div className="flex flex-wrap items-center gap-3">
            <button
              type="submit"
              disabled={busy || !form.reference_number.trim()}
              className="inline-flex items-center gap-2 rounded-full bg-forest-800 px-5 py-2.5 text-sm font-bold text-cream transition hover:bg-forest-700 disabled:cursor-not-allowed disabled:opacity-45"
            >
              {busy ? <Loader2 size={16} className="animate-spin" /> : <Check size={16} />}
              J’ai vérifié — valider
            </button>
            <button
              type="button"
              onClick={() => setOpen(false)}
              className="text-sm font-semibold text-ink-500 underline underline-offset-4"
            >
              Annuler
            </button>
          </div>
        </form>
      )}
    </article>
  );
}

/**
 * Création de compte staff.
 *
 * Le marché n'est demandé que pour un préparateur, et il est obligatoire : un
 * préparateur sans marché ne voit aucune commande. Le compte serait créé
 * mort-né, et personne ne comprendrait pourquoi.
 */
function StaffForm({ markets, onCreated }) {
  const toast = useToast();
  const empty = {
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    role: 'preparateur',
    market_id: '',
  };

  const [form, setForm] = useState(empty);
  const [busy, setBusy] = useState(false);
  const [fieldErrors, setFieldErrors] = useState({});

  const needsMarket = form.role === 'preparateur';
  const set = (patch) => setForm((current) => ({ ...current, ...patch }));

  const submit = async (event) => {
    event.preventDefault();
    setBusy(true);
    setFieldErrors({});
    try {
      const payload = { ...form };
      if (!needsMarket) delete payload.market_id;

      const res = await adminService.createStaff(payload);
      toast.success(`Compte ${form.role} créé pour ${res.data.data.email}.`);
      setForm(empty);
      onCreated();
    } catch (err) {
      // 422 renvoie `errors` champ par champ : les afficher sous le champ
      // concerné plutôt qu'en bloc, sinon on cherche lequel est en cause.
      setFieldErrors(err.response?.data?.errors || {});
      toast.fromApi(err, 'La création du compte a échoué.');
    } finally {
      setBusy(false);
    }
  };

  const fieldClass = (name) =>
    `mt-1.5 w-full rounded-xl border bg-white px-3.5 py-2.5 text-[15px] text-ink-900 placeholder:text-ink-300 ${
      fieldErrors[name] ? 'border-clay-500' : 'border-cream-300'
    }`;


  return (
    <form onSubmit={submit} className="space-y-4">
      <div className="grid grid-cols-2 gap-2">
        {[
          { value: 'preparateur', label: 'Préparateur', Icon: Store },
          { value: 'livreur', label: 'Livreur', Icon: Bike },
        ].map(({ value, label, Icon }) => (
          <button
            key={value}
            type="button"
            onClick={() => set({ role: value })}
            aria-pressed={form.role === value}
            className={`flex items-center justify-center gap-2 rounded-xl border-2 px-3 py-2.5 text-sm font-bold transition ${
              form.role === value
                ? 'border-forest-800 bg-forest-800 text-cream'
                : 'border-cream-300 bg-white text-ink-500 hover:border-forest-600'
            }`}
          >
            <Icon size={16} />
            {label}
          </button>
        ))}
      </div>

      <label className="block">
        <span className="eyebrow text-ink-500">Nom complet</span>
        <input required value={form.name} onChange={(e) => set({ name: e.target.value })} className={fieldClass('name')} />
        <FieldError errors={fieldErrors} name="name" />
      </label>

      <label className="block">
        <span className="eyebrow text-ink-500">Email</span>
        <input
          required
          type="email"
          value={form.email}
          onChange={(e) => set({ email: e.target.value })}
          className={fieldClass('email')}
        />
        <FieldError errors={fieldErrors} name="email" />
      </label>

      <label className="block">
        <span className="eyebrow text-ink-500">Téléphone</span>
        <input
          required
          value={form.phone}
          onChange={(e) => set({ phone: e.target.value })}
          placeholder="+229…"
          className={fieldClass('phone')}
        />
        <FieldError errors={fieldErrors} name="phone" />
      </label>

      {needsMarket && (
        <label className="block">
          <span className="eyebrow text-ink-500">Marché de rattachement</span>
          <select
            required
            value={form.market_id}
            onChange={(e) => set({ market_id: e.target.value })}
            className={fieldClass('market_id')}
          >
            <option value="">Choisir un marché…</option>
            {markets.map((market) => (
              <option key={market.id} value={market.id}>
                {market.name} — {market.city} ({market.preparateurs_count} préparateur
                {market.preparateurs_count > 1 ? 's' : ''})
              </option>
            ))}
          </select>
          <FieldError errors={fieldErrors} name="market_id" />
          <p className="mt-1.5 text-xs leading-5 text-ink-500">
            Il ne verra que les commandes des zones desservies par ce marché.
          </p>
        </label>
      )}

      <div className="grid gap-3 sm:grid-cols-2">
        <label className="block">
          <span className="eyebrow text-ink-500">Mot de passe</span>
          <input
            required
            type="password"
            minLength={8}
            value={form.password}
            onChange={(e) => set({ password: e.target.value })}
            className={fieldClass('password')}
          />
          <FieldError errors={fieldErrors} name="password" />
        </label>
        <label className="block">
          <span className="eyebrow text-ink-500">Confirmer</span>
          <input
            required
            type="password"
            minLength={8}
            value={form.password_confirmation}
            onChange={(e) => set({ password_confirmation: e.target.value })}
            className={fieldClass('password_confirmation')}
          />
        </label>
      </div>

      <button
        type="submit"
        disabled={busy}
        className="inline-flex w-full items-center justify-center gap-2 rounded-full bg-gold-500 px-5 py-3 text-sm font-bold text-forest-900 shadow-glow transition hover:bg-gold-400 disabled:opacity-45"
      >
        {busy ? <Loader2 size={16} className="animate-spin" /> : <UserPlus size={16} />}
        Créer le compte
      </button>
    </form>
  );
}

function AdminPage() {
  const toast = useToast();

  const [pending, setPending] = useState([]);
  const [markets, setMarkets] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    setRefreshing(true);
    try {
      const [paymentsRes, marketsRes] = await Promise.all([
        adminService.getPendingPayments(),
        adminService.getMarkets(),
      ]);
      setPending(paymentsRes.data.data);
      setMarkets(marketsRes.data.data);
    } catch (err) {
      toast.fromApi(err, 'Chargement impossible.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [toast]);

  useEffect(() => {
    load();
  }, [load]);

  // Ce qui est déjà déclaré est ce sur quoi on peut agir tout de suite.
  const declaredCount = useMemo(
    () => pending.filter((order) => order.payment?.payer_declared_reference).length,
    [pending]
  );
  const pendingTotal = useMemo(
    () => pending.reduce((sum, order) => sum + roundXof(order.total), 0),
    [pending]
  );

  return (
    <>
      <Header />

      <div className="relative overflow-hidden bg-forest-900 cowrie-field grain">
        <div className="relative mx-auto max-w-6xl px-4 py-10 sm:py-14">
          <p className="eyebrow text-gold-500">Administration</p>
          <h1 className="mt-2 font-display text-display-sm text-cream">Salle des opérations</h1>
          <p className="mt-3 max-w-xl text-[15px] leading-7 text-cream-300">
            Rien ne part en préparation tant qu’un paiement n’est pas retrouvé sur le compte marchand.
          </p>

          <div className="mt-6 flex flex-wrap gap-3">
            <Link
              href="/admin/commandes"
              className="inline-flex items-center gap-2 rounded-full bg-gold-500 px-5 py-2.5 text-sm font-bold text-forest-950 transition hover:bg-gold-400"
            >
              <Radar size={16} /> Salle de contrôle
            </Link>
            <Link
              href="/admin/clients"
              className="inline-flex items-center gap-2 rounded-full border border-cream/25 px-5 py-2.5 text-sm font-bold text-cream transition hover:bg-cream/10"
            >
              <Users size={16} /> Clients
            </Link>
            <Link
              href="/admin/catalogue"
              className="inline-flex items-center gap-2 rounded-full border border-cream/25 px-5 py-2.5 text-sm font-bold text-cream transition hover:bg-cream/10"
            >
              <PackageSearch size={16} /> Catalogue
            </Link>
            <Link
              href="/admin/fournisseurs"
              className="inline-flex items-center gap-2 rounded-full border border-cream/25 px-5 py-2.5 text-sm font-bold text-cream transition hover:bg-cream/10"
            >
              <Store size={16} /> Le point fournisseur
            </Link>
          </div>

          <dl className="mt-8 flex flex-wrap gap-x-10 gap-y-4">
            {[
              { label: 'En attente', value: pending.length },
              { label: 'Référence déclarée', value: declaredCount },
              { label: 'Montant retenu', value: formatPrice(pendingTotal) },
            ].map((stat) => (
              <div key={stat.label}>
                <dt className="eyebrow text-cream-300/70">{stat.label}</dt>
                <dd className="mt-1 font-display text-2xl font-semibold tabular-nums text-cream">{stat.value}</dd>
              </div>
            ))}
          </dl>
        </div>
      </div>

      <main className="mx-auto max-w-6xl px-4 py-10">
        <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
          <section>
            <div className="mb-4 flex items-center justify-between gap-4">
              <h2 className="font-display text-xl font-semibold text-ink-900">Paiements à vérifier</h2>
              <button
                onClick={load}
                disabled={refreshing}
                className="inline-flex items-center gap-1.5 text-sm font-semibold text-ink-500 transition hover:text-forest-800 disabled:opacity-50"
              >
                <RefreshCw size={15} className={refreshing ? 'animate-spin' : undefined} />
                Actualiser
              </button>
            </div>

            {loading ? (
              <div className="flex items-center gap-2 rounded-card border border-cream-300 bg-white p-8 text-ink-500">
                <Loader2 size={18} className="animate-spin" /> Chargement…
              </div>
            ) : pending.length === 0 ? (
              <div className="rounded-card border border-dashed border-cream-300 bg-white p-10 text-center">
                <Check size={28} className="mx-auto text-forest-600" />
                <p className="mt-3 font-display text-lg font-semibold text-ink-900">File vide</p>
                <p className="mt-1 text-sm text-ink-500">Aucun paiement n’attend de vérification.</p>
              </div>
            ) : (
              <div className="space-y-4">
                {declaredCount > 0 && declaredCount < pending.length && (
                  <p className="flex items-center gap-2 text-sm text-ink-500">
                    <AlertTriangle size={15} className="text-gold-600" />
                    Les {declaredCount} commandes avec référence déclarée sont en tête : quelqu’un attend derrière.
                  </p>
                )}
                {pending.map((order) => (
                  <PaymentRow key={order.id} order={order} onValidated={load} />
                ))}
              </div>
            )}
          </section>

          <aside className="lg:sticky lg:top-6 lg:self-start">
            <div className="rounded-panel border border-cream-300 bg-white p-6 shadow-card">
              <h2 className="font-display text-xl font-semibold text-ink-900">Nouveau compte staff</h2>
              <p className="mt-1 mb-5 text-sm leading-6 text-ink-500">
                Les comptes préparateur et livreur ne se créent que d’ici — l’inscription publique ne donne que
                le rôle client.
              </p>
              <StaffForm markets={markets} onCreated={load} />
            </div>
          </aside>
        </div>
      </main>
    </>
  );
}

export default withRole(AdminPage, ['admin']);
