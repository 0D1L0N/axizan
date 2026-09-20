'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import { adminService } from '@/app/services';
import { withRole } from '@/app/lib/auth-hoc';
import { useToast } from '@/app/contexts/ToastContext';
import Header from '@/app/components/Header';
import { formatPrice } from '@/app/lib/units';
import { mapsLink } from '@/app/lib/geo';
import {
  AlertTriangle,
  ArrowLeft,
  Ban,
  Check,
  Loader2,
  MapPin,
  Navigation,
  PackageX,
  Phone,
  RefreshCw,
  Undo2,
  UserCog,
} from 'lucide-react';

/** Relecture automatique : une salle de contrôle qu'il faut rafraîchir à la main n'en est pas une. */
const POLL_MS = 30000;

const TONE = {
  urgent: {
    chip: 'bg-clay-500 text-white',
    card: 'border-clay-500 bg-clay-200/25',
  },
  warning: {
    chip: 'bg-gold-500 text-forest-950',
    card: 'border-gold-500/60 bg-gold-200/25',
  },
};

/** « dans 40 min », « il y a 1 h 20 » — le temps qui reste avant le créneau. */
function slotCountdown(minutes) {
  if (minutes === null || minutes === undefined) return null;
  const abs = Math.abs(minutes);
  const text = abs < 60 ? `${abs} min` : `${Math.floor(abs / 60)} h ${String(abs % 60).padStart(2, '0')}`;
  return minutes >= 0 ? `créneau dans ${text}` : `créneau dépassé de ${text}`;
}

function Contact({ role, person, live }) {
  if (!person) return null;

  return (
    <span className="inline-flex items-center gap-1.5 text-sm">
      <span className="text-ink-300">{role}</span>
      <span className="font-semibold text-ink-900">{person.name}</span>
      {person.phone && (
        <a
          href={`tel:${person.phone}`}
          onClick={(e) => e.stopPropagation()}
          className="inline-flex items-center gap-1 rounded-full bg-forest-800 px-2 py-0.5 text-xs font-bold text-cream transition hover:bg-forest-700"
        >
          <Phone size={11} /> Appeler
        </a>
      )}
      {live && (
        <a
          href={mapsLink(live.lat, live.lng)}
          target="_blank"
          rel="noopener noreferrer"
          onClick={(e) => e.stopPropagation()}
          className="inline-flex items-center gap-1 rounded-full border border-forest-800/25 px-2 py-0.5 text-xs font-bold text-forest-800 transition hover:bg-cream-100"
        >
          <Navigation size={11} /> Où il est
        </a>
      )}
    </span>
  );
}

/** Une commande dans la vue du jour. */
function OrderRow({ row, onOpen }) {
  const tone = row.attention ? TONE[row.attention.level] : null;
  const countdown = slotCountdown(row.minutes_to_slot);

  return (
    <button
      onClick={() => onOpen(row.id)}
      className={`w-full rounded-card border bg-white p-5 text-left shadow-card transition hover:border-forest-600 ${
        tone ? tone.card : 'border-cream-300'
      }`}
    >
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <span className="font-mono text-sm font-bold text-ink-500">{row.order_number}</span>
            <span className="rounded-full bg-cream-200 px-2.5 py-0.5 text-xs font-bold text-ink-700">
              {row.status_label}
            </span>
            {row.attention && (
              <span
                className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-bold ${tone.chip}`}
              >
                <AlertTriangle size={12} /> {row.attention.level === 'urgent' ? 'Urgent' : 'À surveiller'}
              </span>
            )}
          </div>

          <p className="mt-1.5 font-display text-lg font-semibold text-ink-900">
            {row.client?.name}
            <span className="ml-2 text-sm font-normal text-ink-500">{row.quartier}</span>
          </p>

          {row.attention && (
            <p className="mt-1 text-sm font-semibold text-clay-600">{row.attention.reason}</p>
          )}

          <div className="mt-2.5 flex flex-wrap items-center gap-x-5 gap-y-1.5">
            <Contact role="Préparateur" person={row.preparateur} />
            <Contact role="Livreur" person={row.livreur} live={row.position} />
            {!row.preparateur && (
              <span className="text-sm font-semibold text-ink-300">Personne ne l’a prise</span>
            )}
          </div>
        </div>

        <div className="text-right">
          <p className="font-display text-lg font-semibold tabular-nums text-forest-800">
            {formatPrice(row.total)}
          </p>
          <p className="text-xs text-ink-500">{row.slot}</p>
          {countdown && (
            <p className={`text-xs font-semibold ${row.minutes_to_slot < 0 ? 'text-clay-600' : 'text-ink-500'}`}>
              {countdown}
            </p>
          )}
          {row.progress.total > 0 && row.status === 'preparing' && (
            <p className="mt-1 text-xs font-bold tabular-nums text-forest-700">
              {row.progress.picked}/{row.progress.total} articles
            </p>
          )}
        </div>
      </div>
    </button>
  );
}

/** La fiche : le journal, les preuves, et les trois gestes de déblocage. */
function OrderDetail({ id, onBack, onChanged }) {
  const toast = useToast();

  const [data, setData] = useState(null);
  const [candidates, setCandidates] = useState([]);
  const [busy, setBusy] = useState(false);
  const [action, setAction] = useState(null);
  const [reason, setReason] = useState('');
  const [target, setTarget] = useState('');

  const load = useCallback(async () => {
    try {
      const res = await adminService.getOrder(id);
      setData(res.data.data);
    } catch (err) {
      toast.fromApi(err, 'Fiche illisible.');
    }
  }, [id, toast]);

  useEffect(() => {
    load();
  }, [load]);

  const openAction = async (kind) => {
    setAction(kind);
    setReason('');
    if (kind === 'reassign' && candidates.length === 0) {
      try {
        const res = await adminService.getReassignCandidates(id);
        setCandidates(res.data.data);
      } catch {
        /* La liste vide se voit à l'écran, inutile d'alarmer. */
      }
    }
  };

  const run = async () => {
    setBusy(true);
    try {
      if (action === 'release') await adminService.releaseOrder(id, reason.trim());
      if (action === 'cancel') await adminService.cancelOrder(id, reason.trim());
      if (action === 'reassign') await adminService.reassignOrder(id, Number(target), reason.trim() || null);
      if (action === 'abandon') await adminService.abandonDelivery(id, reason.trim());

      toast.success(
        {
          release: 'Commande libérée.',
          cancel: 'Commande annulée.',
          reassign: 'Commande confiée.',
          abandon: 'Course close. Le client est remboursé et prévenu.',
        }[action]
      );
      setAction(null);
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

  const { order, journal, attention } = data;
  const address = order.delivery_address || {};
  const position = order.delivery?.current_latitude
    ? { lat: Number(order.delivery.current_latitude), lng: Number(order.delivery.current_longitude) }
    : null;

  return (
    <div className="space-y-6">
      <button onClick={onBack} className="inline-flex items-center gap-2 font-bold text-forest-800 hover:underline">
        <ArrowLeft size={17} /> Toutes les commandes
      </button>

      {attention && (
        <p
          className={`flex items-start gap-2.5 rounded-card border-2 px-4 py-3 font-semibold ${
            TONE[attention.level].card
          } text-clay-600`}
        >
          <AlertTriangle size={18} className="mt-0.5 shrink-0" />
          {attention.reason}
        </p>
      )}

      <section className="rounded-panel border border-cream-300 bg-white p-6 shadow-card">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p className="font-mono text-sm font-bold text-ink-500">{order.order_number}</p>
            <h2 className="mt-1 font-display text-2xl font-semibold text-ink-900">{order.client?.name}</h2>
            <p className="mt-1.5 flex items-center gap-2 text-sm text-ink-500">
              <MapPin size={14} />
              {[address.quartier, address.landmark, address.city].filter(Boolean).join(' — ')}
            </p>
          </div>
          <div className="text-right">
            <p className="font-display text-2xl font-semibold tabular-nums text-forest-800">
              {formatPrice(order.total)}
            </p>
            <p className="text-sm text-ink-500">{order.delivery_slot?.display_label}</p>
          </div>
        </div>

        <div className="mt-5 flex flex-wrap gap-x-6 gap-y-2 border-t border-cream-200 pt-4">
          <Contact role="Client" person={order.client} />
          <Contact role="Préparateur" person={order.preparateur} />
          <Contact role="Livreur" person={order.livreur} live={position} />
        </div>

        {(order.prep_photo_url || order.delivery?.proof_photo_url) && (
          <div className="mt-5 flex flex-wrap gap-3 border-t border-cream-200 pt-4">
            {[
              ['Panier au dépôt', order.prep_photo_url],
              ['Remise au client', order.delivery?.proof_photo_url],
            ]
              .filter(([, url]) => url)
              .map(([label, url]) => (
                <a
                  key={label}
                  href={url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-sm font-semibold text-forest-800 underline underline-offset-4"
                >
                  {label}
                </a>
              ))}
          </div>
        )}
      </section>

      {/* Le journal : il existait depuis le premier jour, sans écran pour le lire. */}
      <section className="rounded-panel border border-cream-300 bg-white p-6 shadow-card">
        <h3 className="font-display text-xl font-semibold text-ink-900">Journal</h3>
        <ol className="mt-4 space-y-0">
          {journal.map((entry, index) => (
            <li key={entry.id} className="flex gap-4">
              <div className="flex flex-col items-center">
                <span className="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-forest-600" />
                {index < journal.length - 1 && <span className="w-px flex-1 bg-cream-300" />}
              </div>
              <div className="pb-5">
                <p className="font-semibold text-ink-900">
                  {entry.to_status ? `→ ${entry.to_status}` : entry.event}
                  {entry.actor && (
                    <span className="ml-2 text-sm font-normal text-ink-500">
                      par {entry.actor.name}
                      {entry.actor_role && ` (${entry.actor_role})`}
                    </span>
                  )}
                </p>
                {entry.reason && <p className="mt-0.5 text-sm text-ink-700">« {entry.reason} »</p>}
                <p className="mt-0.5 text-xs text-ink-300">
                  {new Date(entry.at).toLocaleString('fr-FR', {
                    day: '2-digit',
                    month: 'short',
                    hour: '2-digit',
                    minute: '2-digit',
                  })}
                </p>
              </div>
            </li>
          ))}
        </ol>
      </section>

      <section className="rounded-panel border border-cream-300 bg-white p-6 shadow-card">
        <h3 className="font-display text-xl font-semibold text-ink-900">Débloquer</h3>

        {!action ? (
          <div className="mt-4 flex flex-wrap gap-3">
            <button
              onClick={() => openAction('release')}
              className="inline-flex items-center gap-2 rounded-full border border-ink-900/15 px-5 py-2.5 text-sm font-bold text-ink-900 transition hover:bg-cream-100"
            >
              <Undo2 size={16} /> Libérer vers le pool
            </button>
            <button
              onClick={() => openAction('reassign')}
              className="inline-flex items-center gap-2 rounded-full border border-ink-900/15 px-5 py-2.5 text-sm font-bold text-ink-900 transition hover:bg-cream-100"
            >
              <UserCog size={16} /> Réassigner
            </button>
            <button
              onClick={() => openAction('cancel')}
              className="inline-flex items-center gap-2 rounded-full border border-clay-200 px-5 py-2.5 text-sm font-bold text-clay-600 transition hover:bg-clay-200/40"
            >
              <Ban size={16} /> Annuler
            </button>
            {/*
              La seule issue quand une course ne revient pas. Proposée
              uniquement dans ce cas : partout ailleurs, « Libérer » ou
              « Annuler » font l'affaire sans rembourser personne.
            */}
            {order.livreur && !['completed', 'cancelled', 'returned'].includes(order.status) && (
              <button
                onClick={() => openAction('abandon')}
                className="inline-flex items-center gap-2 rounded-full border border-clay-500 px-5 py-2.5 text-sm font-bold text-clay-600 transition hover:bg-clay-200/40"
              >
                <PackageX size={16} /> La course ne revient pas
              </button>
            )}
          </div>
        ) : (
          <div className="mt-4 space-y-3">
            {action === 'reassign' && (
              <label className="block">
                <span className="eyebrow text-ink-500">Confier à</span>
                <select
                  value={target}
                  onChange={(e) => setTarget(e.target.value)}
                  className="mt-1.5 w-full rounded-xl border border-cream-300 bg-white px-3.5 py-2.5 text-[15px] text-ink-900"
                >
                  <option value="">Choisir un préparateur…</option>
                  {candidates.map((person) => (
                    <option key={person.id} value={person.id}>
                      {person.name} — {person.active_orders} en cours
                    </option>
                  ))}
                </select>
                {candidates.length === 0 && (
                  <span className="mt-1.5 block text-sm text-clay-600">
                    Aucun autre préparateur ne couvre cette zone.
                  </span>
                )}
              </label>
            )}

            <label className="block">
              <span className="eyebrow text-ink-500">
                Motif {action === 'reassign' ? '(facultatif)' : '· obligatoire'}
              </span>
              <textarea
                rows={2}
                autoFocus
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder={
                  action === 'cancel'
                    ? 'Le client sera prévenu de ce motif.'
                    : 'Injoignable, moto en panne…'
                }
                className="mt-1.5 w-full rounded-xl border border-cream-300 bg-white px-3.5 py-2.5 text-[15px] text-ink-900 placeholder:text-ink-300"
              />
            </label>

            <div className="flex flex-wrap items-center gap-3">
              <button
                onClick={run}
                disabled={
                  busy ||
                  (action !== 'reassign' && !reason.trim()) ||
                  (action === 'reassign' && !target)
                }
                className="inline-flex items-center gap-2 rounded-full bg-forest-800 px-5 py-2.5 text-sm font-bold text-cream transition hover:bg-forest-700 disabled:opacity-45"
              >
                {busy ? <Loader2 size={16} className="animate-spin" /> : <Check size={16} />}
                Confirmer
              </button>
              <button
                onClick={() => setAction(null)}
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
 * La salle de contrôle.
 *
 * Une liste dit ce qui existe ; elle ne dit pas ce qui coince. Les commandes
 * qui ne bougent pas remontent donc en tête, avec le motif — c'est la seule
 * information qu'un administrateur doit voir sans la chercher.
 */
function ControlRoomPage() {
  const toast = useToast();

  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState({});
  const [openId, setOpenId] = useState(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    try {
      const res = await adminService.getOrders();
      setRows(res.data.data);
      setMeta(res.data.meta);
    } catch (err) {
      toast.fromApi(err, 'Chargement impossible.');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    load();
  }, [load]);

  // Relecture de fond, seulement sur la liste et onglet au premier plan.
  useEffect(() => {
    if (openId !== null) return undefined;

    const tick = () => {
      if (document.visibilityState === 'visible') load();
    };
    const timer = setInterval(tick, POLL_MS);
    document.addEventListener('visibilitychange', tick);

    return () => {
      clearInterval(timer);
      document.removeEventListener('visibilitychange', tick);
    };
  }, [openId, load]);

  // Ce qui coince d'abord, le reste ensuite : c'est l'ordre dans lequel on agit.
  const { blocked, running } = useMemo(
    () => ({
      blocked: rows.filter((row) => row.attention),
      running: rows.filter((row) => !row.attention),
    }),
    [rows]
  );

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
          <p className="eyebrow mt-5 text-gold-500">Salle de contrôle</p>
          <h1 className="mt-2 font-display text-display-sm text-cream">Ce qui tourne aujourd’hui</h1>

          <dl className="mt-8 flex flex-wrap gap-x-10 gap-y-4">
            {[
              ['En cours', meta.total || 0],
              ['Urgent', meta.urgent || 0],
              ['À surveiller', meta.warning || 0],
              ['Sans préparateur', meta.unassigned || 0],
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
          <OrderDetail id={openId} onBack={() => setOpenId(null)} onChanged={load} />
        ) : loading ? (
          <div className="flex items-center gap-2 rounded-card border border-cream-300 bg-white p-8 text-ink-500">
            <Loader2 size={18} className="animate-spin" /> Chargement…
          </div>
        ) : rows.length === 0 ? (
          <div className="rounded-card border border-dashed border-cream-300 bg-white p-10 text-center">
            <Check size={28} className="mx-auto text-forest-600" />
            <p className="mt-3 font-display text-lg font-semibold text-ink-900">Rien en cours</p>
            <p className="mt-1 text-sm text-ink-500">Aucune commande n’attend d’être traitée.</p>
          </div>
        ) : (
          <div className="space-y-8">
            {blocked.length > 0 && (
              <section>
                <div className="mb-3 flex items-center justify-between gap-4">
                  <h2 className="flex items-center gap-2 font-display text-xl font-semibold text-clay-600">
                    <AlertTriangle size={19} /> Ça ne bouge pas ({blocked.length})
                  </h2>
                  <button
                    onClick={load}
                    className="inline-flex items-center gap-1.5 text-sm font-semibold text-ink-500 transition hover:text-forest-800"
                  >
                    <RefreshCw size={15} /> Actualiser
                  </button>
                </div>
                <div className="space-y-3">
                  {blocked.map((row) => (
                    <OrderRow key={row.id} row={row} onOpen={setOpenId} />
                  ))}
                </div>
              </section>
            )}

            {running.length > 0 && (
              <section>
                <h2 className="mb-3 font-display text-xl font-semibold text-ink-900">
                  En cours ({running.length})
                </h2>
                <div className="space-y-3">
                  {running.map((row) => (
                    <OrderRow key={row.id} row={row} onOpen={setOpenId} />
                  ))}
                </div>
              </section>
            )}
          </div>
        )}
      </main>
    </>
  );
}

export default withRole(ControlRoomPage, ['admin']);
