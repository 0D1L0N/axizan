'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { adminService } from '@/app/services';
import { withRole } from '@/app/lib/auth-hoc';
import { useToast } from '@/app/contexts/ToastContext';
import Header from '@/app/components/Header';
import { formatPrice } from '@/app/lib/units';
import {
  AlertTriangle,
  ArrowLeft,
  Ban,
  Check,
  Loader2,
  MapPin,
  Phone,
  Search,
  ShieldCheck,
} from 'lucide-react';

const FILTERS = [
  { key: 'all', label: 'Tous' },
  { key: 'unpaid', label: 'Impayés' },
  { key: 'suspended', label: 'Suspendus' },
];

const dateShort = (iso) =>
  iso ? new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: '2-digit' }) : '—';

/** « il y a 12 j » — savoir qui a décroché compte autant que le total dépensé. */
function sinceDays(iso) {
  if (!iso) return 'jamais commandé';
  const days = Math.floor((Date.now() - new Date(iso).getTime()) / 86400000);
  if (days < 1) return 'aujourd’hui';
  if (days === 1) return 'hier';
  return `il y a ${days} j`;
}

/** La fiche : ce qu'on ouvre quand quelqu'un appelle pour réclamer. */
function ClientFile({ id, onBack, onChanged }) {
  const toast = useToast();

  const [data, setData] = useState(null);
  const [busy, setBusy] = useState(false);
  const [confirming, setConfirming] = useState(false);
  const [reason, setReason] = useState('');

  const load = useCallback(async () => {
    try {
      const res = await adminService.getClient(id);
      setData(res.data.data);
    } catch (err) {
      toast.fromApi(err, 'Fiche illisible.');
    }
  }, [id, toast]);

  useEffect(() => {
    load();
  }, [load]);

  const setSuspended = async (suspended) => {
    setBusy(true);
    try {
      const res = await adminService.setClientSuspended(id, suspended, suspended ? reason.trim() : null);
      toast.success(res.data.message);
      setConfirming(false);
      setReason('');
      await load();
      onChanged();
    } catch (err) {
      toast.fromApi(err, 'L’action a échoué.');
    } finally {
      setBusy(false);
    }
  };

  if (!data) {
    return (
      <div className="flex items-center gap-2 rounded-card border border-cream-300 bg-white p-8 text-ink-500">
        <Loader2 size={18} className="animate-spin" /> Chargement…
      </div>
    );
  }

  const { client, stats, orders, addresses } = data;
  const suspended = client.status !== 'active';

  return (
    <div className="space-y-6">
      <button onClick={onBack} className="inline-flex items-center gap-2 font-bold text-forest-800 hover:underline">
        <ArrowLeft size={17} /> Tous les clients
      </button>

      <section className="rounded-panel border border-cream-300 bg-white p-6 shadow-card">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <div className="flex flex-wrap items-center gap-2">
              <h2 className="font-display text-2xl font-semibold text-ink-900">{client.name}</h2>
              {suspended && (
                <span className="rounded-full bg-clay-200 px-2.5 py-0.5 text-xs font-bold text-clay-600">
                  Suspendu
                </span>
              )}
            </div>
            <p className="mt-1.5 text-sm text-ink-500">
              Client depuis le {dateShort(client.member_since || client.created_at)}
            </p>
            <div className="mt-3 flex flex-wrap gap-3">
              {client.phone && (
                <a
                  href={`tel:${client.phone}`}
                  className="inline-flex items-center gap-1.5 rounded-full bg-forest-800 px-3.5 py-1.5 text-sm font-bold text-cream transition hover:bg-forest-700"
                >
                  <Phone size={13} /> {client.phone}
                </a>
              )}
              {client.email && <span className="py-1.5 text-sm text-ink-500">{client.email}</span>}
            </div>
          </div>

          <dl className="grid grid-cols-2 gap-x-8 gap-y-3 text-right sm:grid-cols-3">
            {[
              ['Commandes', stats.orders],
              ['Livrées', stats.completed],
              ['Total payé', formatPrice(stats.spent)],
              ['Panier moyen', formatPrice(stats.average_basket)],
              ['Annulées', stats.cancelled],
              ['Dernière', sinceDays(stats.last_order_at)],
            ].map(([label, value]) => (
              <div key={label}>
                <dt className="eyebrow text-ink-300">{label}</dt>
                <dd className="mt-0.5 font-semibold tabular-nums text-ink-900">{value}</dd>
              </div>
            ))}
          </dl>
        </div>

        {stats.unpaid > 0 && (
          <p className="mt-5 flex items-start gap-2.5 rounded-card border border-clay-200 bg-clay-200/30 px-4 py-3 text-sm leading-6 text-clay-600">
            <AlertTriangle size={17} className="mt-0.5 shrink-0" />
            <span>
              <strong>
                {stats.unpaid} commande{stats.unpaid > 1 ? 's' : ''} jamais payée
                {stats.unpaid > 1 ? 's' : ''}
              </strong>{' '}
              — {formatPrice(stats.unpaid_amount)}. Chacune a occupé un créneau que personne d’autre n’a
              pu prendre.
            </span>
          </p>
        )}
      </section>

      {addresses.length > 0 && (
        <section className="rounded-panel border border-cream-300 bg-white p-6 shadow-card">
          <h3 className="font-display text-lg font-semibold text-ink-900">Adresses</h3>
          <ul className="mt-3 space-y-2">
            {addresses.map((address) => (
              <li key={address.id} className="flex items-start gap-2.5 text-sm">
                <MapPin size={15} className="mt-0.5 shrink-0 text-forest-600" />
                <span className="text-ink-700">
                  <strong className="text-ink-900">{address.label}</strong> —{' '}
                  {[address.quartier, address.landmark, address.city].filter(Boolean).join(', ')}
                  {address.zone && <span className="text-ink-500"> · zone {address.zone.name}</span>}
                  {address.is_default && <span className="text-ink-300"> · par défaut</span>}
                </span>
              </li>
            ))}
          </ul>
        </section>
      )}

      <section className="rounded-panel border border-cream-300 bg-white p-6 shadow-card">
        <h3 className="font-display text-lg font-semibold text-ink-900">Historique</h3>
        {orders.length === 0 ? (
          <p className="mt-3 text-sm text-ink-500">Aucune commande.</p>
        ) : (
          <ul className="mt-3 divide-y divide-cream-200">
            {orders.map((order) => (
              <li key={order.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                <div className="min-w-0">
                  <Link
                    href="/admin/commandes"
                    className="font-mono text-sm font-bold text-ink-700 hover:text-forest-800"
                  >
                    {order.order_number}
                  </Link>
                  <p className="mt-0.5 text-sm text-ink-500">
                    {order.status_label}
                    {order.quartier && ` · ${order.quartier}`}
                    {order.delivery_date && ` · ${dateShort(order.delivery_date)}`}
                    {order.slot && ` · ${order.slot}`}
                  </p>
                </div>
                <div className="flex items-center gap-3">
                  {order.unpaid && (
                    <span className="rounded-full bg-clay-200 px-2.5 py-0.5 text-xs font-bold text-clay-600">
                      Jamais payée
                    </span>
                  )}
                  <span className="font-semibold tabular-nums text-ink-900">{formatPrice(order.total)}</span>
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="rounded-panel border border-cream-300 bg-white p-6 shadow-card">
        <h3 className="font-display text-lg font-semibold text-ink-900">Accès au compte</h3>

        {suspended ? (
          <>
            <p className="mt-1.5 text-sm leading-6 text-ink-500">
              Ce compte ne peut ni se connecter ni commander.
            </p>
            <button
              onClick={() => setSuspended(false)}
              disabled={busy}
              className="mt-4 inline-flex items-center gap-2 rounded-full bg-forest-800 px-5 py-2.5 text-sm font-bold text-cream transition hover:bg-forest-700 disabled:opacity-50"
            >
              {busy ? <Loader2 size={15} className="animate-spin" /> : <ShieldCheck size={15} />}
              Rétablir l’accès
            </button>
          </>
        ) : !confirming ? (
          <>
            <p className="mt-1.5 text-sm leading-6 text-ink-500">
              Suspendre coupe la connexion immédiatement et déconnecte les appareils déjà ouverts.
            </p>
            <button
              onClick={() => setConfirming(true)}
              className="mt-4 inline-flex items-center gap-2 rounded-full border border-clay-200 px-5 py-2.5 text-sm font-bold text-clay-600 transition hover:bg-clay-200/40"
            >
              <Ban size={15} /> Suspendre le compte
            </button>
          </>
        ) : (
          <div className="mt-4 space-y-3">
            <label className="block">
              <span className="eyebrow text-ink-500">Motif · obligatoire</span>
              <textarea
                rows={2}
                autoFocus
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder="Trois commandes jamais payées…"
                className="mt-1.5 w-full rounded-xl border border-cream-300 bg-white px-3.5 py-2.5 text-[15px] text-ink-900 placeholder:text-ink-300"
              />
            </label>
            <div className="flex flex-wrap items-center gap-3">
              <button
                onClick={() => setSuspended(true)}
                disabled={busy || !reason.trim()}
                className="inline-flex items-center gap-2 rounded-full bg-clay-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-clay-600 disabled:opacity-45"
              >
                {busy ? <Loader2 size={15} className="animate-spin" /> : <Check size={15} />} Suspendre
              </button>
              <button
                onClick={() => setConfirming(false)}
                className="text-sm font-semibold text-ink-500 underline underline-offset-4"
              >
                Annuler
              </button>
            </div>
          </div>
        )}
      </section>
    </div>
  );
}

/**
 * Les clients.
 *
 * Deux choses seulement, mais celles dont on a besoin dès la première semaine :
 * la fiche qu'on ouvre quand quelqu'un appelle, et les commandes restées
 * impayées après leur créneau.
 */
function ClientsPage() {
  const toast = useToast();

  const [clients, setClients] = useState([]);
  const [meta, setMeta] = useState({});
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');
  const [openId, setOpenId] = useState(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminService.getClients({
        filter: filter === 'all' ? undefined : filter,
        search: search.trim() || undefined,
      });
      setClients(res.data.data);
      setMeta(res.data.meta);
    } catch (err) {
      toast.fromApi(err, 'Chargement impossible.');
    } finally {
      setLoading(false);
    }
  }, [filter, search, toast]);

  useEffect(() => {
    load();
  }, [load]);

  return (
    <>
      <Header />

      <div className="relative overflow-hidden bg-forest-900 cowrie-field grain">
        <div className="relative mx-auto max-w-5xl px-4 py-10 sm:py-12">
          <Link
            href="/admin"
            className="inline-flex items-center gap-2 text-sm font-bold text-cream/70 hover:text-cream"
          >
            <ArrowLeft size={15} /> Administration
          </Link>
          <p className="eyebrow mt-5 text-gold-500">Clients</p>
          <h1 className="mt-2 font-display text-display-sm text-cream">Qui commande chez nous</h1>

          <dl className="mt-8 flex flex-wrap gap-x-10 gap-y-4">
            {[
              ['Clients', meta.total || 0],
              ['Avec impayés', meta.with_unpaid || 0],
              ['Suspendus', meta.suspended || 0],
            ].map(([label, value]) => (
              <div key={label}>
                <dt className="eyebrow text-cream-300/70">{label}</dt>
                <dd className="mt-1 font-display text-2xl font-semibold tabular-nums text-cream">{value}</dd>
              </div>
            ))}
          </dl>
        </div>
      </div>

      <main className="mx-auto max-w-5xl px-4 py-10">
        {openId ? (
          <ClientFile id={openId} onBack={() => setOpenId(null)} onChanged={load} />
        ) : (
          <>
            <div className="mb-5 flex flex-wrap items-center gap-3">
              <div className="flex flex-wrap gap-2">
                {FILTERS.map((option) => (
                  <button
                    key={option.key}
                    onClick={() => setFilter(option.key)}
                    aria-pressed={filter === option.key}
                    className={`rounded-full px-4 py-2 text-sm font-bold transition ${
                      filter === option.key
                        ? 'bg-forest-800 text-cream'
                        : 'border border-cream-300 bg-white text-ink-500 hover:border-forest-600'
                    }`}
                  >
                    {option.label}
                  </button>
                ))}
              </div>

              <label className="relative ml-auto">
                <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-ink-300" />
                <input
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Nom, téléphone, e-mail…"
                  className="w-60 rounded-full border border-cream-300 bg-white py-2 pl-9 pr-3.5 text-sm text-ink-900 placeholder:text-ink-300"
                />
              </label>
            </div>

            {loading ? (
              <div className="flex items-center gap-2 rounded-card border border-cream-300 bg-white p-8 text-ink-500">
                <Loader2 size={18} className="animate-spin" /> Chargement…
              </div>
            ) : clients.length === 0 ? (
              <div className="rounded-card border border-dashed border-cream-300 bg-white p-10 text-center">
                <p className="font-display text-lg font-semibold text-ink-900">Personne ici</p>
                <p className="mt-1 text-sm text-ink-500">Aucun client ne correspond à ce filtre.</p>
              </div>
            ) : (
              <ul className="space-y-3">
                {clients.map((client) => (
                  <li key={client.id}>
                    <button
                      onClick={() => setOpenId(client.id)}
                      className={`flex w-full flex-wrap items-center justify-between gap-4 rounded-card border bg-white p-5 text-left shadow-card transition hover:border-forest-600 ${
                        client.unpaid > 0 ? 'border-clay-500/50' : 'border-cream-300'
                      }`}
                    >
                      <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                          <span className="font-display text-lg font-semibold text-ink-900">
                            {client.name}
                          </span>
                          {client.status !== 'active' && (
                            <span className="rounded-full bg-clay-200 px-2 py-0.5 text-xs font-bold text-clay-600">
                              Suspendu
                            </span>
                          )}
                          {client.unpaid > 0 && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-clay-500 px-2.5 py-0.5 text-xs font-bold text-white">
                              <AlertTriangle size={12} /> {client.unpaid} impayée
                              {client.unpaid > 1 ? 's' : ''}
                            </span>
                          )}
                        </div>
                        <p className="mt-1 text-sm text-ink-500">
                          {client.phone}
                          {' · '}
                          {client.orders} commande{client.orders > 1 ? 's' : ''}
                          {' · '}
                          {sinceDays(client.last_order_at)}
                        </p>
                      </div>

                      <div className="text-right">
                        <p className="font-display text-lg font-semibold tabular-nums text-forest-800">
                          {formatPrice(client.spent)}
                        </p>
                        <p className="text-xs text-ink-500">total payé</p>
                      </div>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </>
        )}
      </main>
    </>
  );
}

export default withRole(ClientsPage, ['admin']);
