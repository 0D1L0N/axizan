'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { adminService } from '@/app/services';
import { withRole } from '@/app/lib/auth-hoc';
import { useToast } from '@/app/contexts/ToastContext';
import Header from '@/app/components/Header';
import {
  AlertTriangle,
  ArrowLeft,
  Ban,
  Bike,
  Check,
  Loader2,
  Moon,
  Phone,
  Search,
  ShieldCheck,
  Store,
} from 'lucide-react';

const FILTERS = [
  { key: 'all', label: 'Tous' },
  { key: 'preparateur', label: 'Préparateurs' },
  { key: 'livreur', label: 'Livreurs' },
  { key: 'dormant', label: 'Sans nouvelle' },
  { key: 'suspended', label: 'Suspendus' },
];

const dateShort = (iso) =>
  iso ? new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: '2-digit' }) : '—';

/** « il y a 12 j » — pour le personnel, c'est le chiffre qui compte le plus. */
function sinceDays(iso) {
  if (!iso) return 'jamais';
  const days = Math.floor((Date.now() - new Date(iso).getTime()) / 86400000);
  if (days < 1) return 'aujourd’hui';
  if (days === 1) return 'hier';
  return `il y a ${days} j`;
}

const RoleIcon = ({ role, ...props }) => (role === 'livreur' ? <Bike {...props} /> : <Store {...props} />);

/**
 * La fiche.
 *
 * Elle est organisée autour d'une seule question : que se passe-t-il si je
 * suspends ce compte maintenant ? La réponse est en haut, pas en bas.
 */
function StaffFile({ id, onBack, onChanged }) {
  const toast = useToast();

  const [data, setData] = useState(null);
  const [busy, setBusy] = useState(false);
  const [confirming, setConfirming] = useState(false);
  const [reason, setReason] = useState('');
  const [blocked, setBlocked] = useState(null);

  const load = useCallback(async () => {
    try {
      const res = await adminService.getStaffMember(id);
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
    setBlocked(null);
    try {
      const res = await adminService.setStaffSuspended(id, suspended, suspended ? reason.trim() : null);
      toast.success(res.data.message);
      setConfirming(false);
      setReason('');
      await load();
      onChanged();
    } catch (err) {
      // Le refus « panier encore dans la sacoche » n'est pas une panne : c'est
      // une réponse, et elle nomme la commande à traiter. Elle reste affichée
      // à l'écran, là où l'action a été tentée — un toast disparaîtrait avant
      // qu'on ait noté le numéro.
      if (err.response?.data?.error === 'delivery_in_progress') {
        setBlocked(err.response.data);
      } else {
        toast.fromApi(err, 'L’action a échoué.');
      }
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

  const { person, stats, history, in_hand: inHand } = data;
  const suspended = person.status !== 'active';
  const holdsGoods = inHand.some((line) => line.blocks_suspension);
  const isLivreur = person.role === 'livreur';

  return (
    <div className="space-y-6">
      <button onClick={onBack} className="inline-flex items-center gap-2 font-bold text-forest-800 hover:underline">
        <ArrowLeft size={17} /> Tout le personnel
      </button>

      <section className="rounded-panel border border-cream-300 bg-white p-6 shadow-card">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <div className="flex flex-wrap items-center gap-2">
              <RoleIcon role={person.role} size={20} className="text-forest-600" />
              <h2 className="font-display text-2xl font-semibold text-ink-900">{person.name}</h2>
              {suspended && (
                <span className="rounded-full bg-clay-200 px-2.5 py-0.5 text-xs font-bold text-clay-600">
                  Suspendu
                </span>
              )}
            </div>
            <p className="mt-1.5 text-sm text-ink-500">
              {person.role_label}
              {person.market && ` · ${person.market.name}, ${person.market.city}`}
              {' · depuis le '}
              {dateShort(person.created_at)}
            </p>
            <div className="mt-3 flex flex-wrap gap-3">
              {person.phone && (
                <a
                  href={`tel:${person.phone}`}
                  className="inline-flex items-center gap-1.5 rounded-full bg-forest-800 px-3.5 py-1.5 text-sm font-bold text-cream transition hover:bg-forest-700"
                >
                  <Phone size={13} /> {person.phone}
                </a>
              )}
              {person.email && <span className="py-1.5 text-sm text-ink-500">{person.email}</span>}
            </div>
          </div>

          <dl className="grid grid-cols-2 gap-x-8 gap-y-3 text-right">
            {[
              [isLivreur ? 'Courses' : 'Commandes', stats.handled],
              ['Terminées', stats.completed],
              ['Aujourd’hui', stats.today],
              ['Dernier passage', sinceDays(stats.last_active_at)],
            ].map(([label, value]) => (
              <div key={label}>
                <dt className="eyebrow text-ink-300">{label}</dt>
                <dd className="mt-0.5 font-semibold tabular-nums text-ink-900">{value}</dd>
              </div>
            ))}
          </dl>
        </div>
      </section>

      <section className="rounded-panel border border-cream-300 bg-white p-6 shadow-card">
        <h3 className="font-display text-lg font-semibold text-ink-900">En main maintenant</h3>

        {inHand.length === 0 ? (
          <p className="mt-1.5 text-sm leading-6 text-ink-500">
            Rien en cours. Suspendre ce compte ne laisse aucune commande en plan.
          </p>
        ) : (
          <>
            <p className="mt-1.5 text-sm leading-6 text-ink-500">
              {holdsGoods
                ? 'La marchandise est physiquement chez cette personne.'
                : 'Ces commandes repartiront au marché si le compte est suspendu.'}
            </p>
            <ul className="mt-3 divide-y divide-cream-200">
              {inHand.map((line) => (
                <li key={line.order_number} className="flex flex-wrap items-center justify-between gap-3 py-2.5">
                  <span className="font-mono text-sm font-bold text-ink-700">{line.order_number}</span>
                  <span className="text-sm text-ink-500">
                    {line.status_label}
                    {line.delivery_date && ` · ${dateShort(line.delivery_date)}`}
                  </span>
                </li>
              ))}
            </ul>
          </>
        )}
      </section>

      <section className="rounded-panel border border-cream-300 bg-white p-6 shadow-card">
        <h3 className="font-display text-lg font-semibold text-ink-900">Derniers passages</h3>
        {history.length === 0 ? (
          <p className="mt-3 text-sm text-ink-500">Cette personne n’a encore rien traité.</p>
        ) : (
          <ul className="mt-3 divide-y divide-cream-200">
            {history.map((line, index) => (
              <li
                key={`${line.order_number}-${index}`}
                className="flex flex-wrap items-center justify-between gap-3 py-2.5"
              >
                <span className="font-mono text-sm font-bold text-ink-700">{line.order_number || '—'}</span>
                <span className="text-sm text-ink-500">
                  {line.status_label}
                  {line.delivery_date && ` · ${dateShort(line.delivery_date)}`}
                </span>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="rounded-panel border border-cream-300 bg-white p-6 shadow-card">
        <h3 className="font-display text-lg font-semibold text-ink-900">Accès au compte</h3>

        {blocked && (
          <p className="mt-3 flex items-start gap-2.5 rounded-card border border-clay-200 bg-clay-200/30 px-4 py-3 text-sm leading-6 text-clay-600">
            <AlertTriangle size={17} className="mt-0.5 shrink-0" />
            <span>
              {blocked.message}{' '}
              <Link href="/admin/commandes" className="font-bold underline underline-offset-4">
                Ouvrir la salle de contrôle
              </Link>
            </span>
          </p>
        )}

        {suspended ? (
          <>
            <p className="mt-1.5 text-sm leading-6 text-ink-500">
              Ce compte ne peut plus se connecter. Il ne reçoit aucune commande.
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
              {holdsGoods
                ? ' Impossible tant qu’un panier est dans sa sacoche : la marchandise ne reviendrait pas.'
                : inHand.length > 0
                  ? ` Ses ${inHand.length} commande${inHand.length > 1 ? 's' : ''} en cours repartiront au marché.`
                  : ''}
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
                placeholder="A rendu son tablier le 12…"
                className="mt-1.5 w-full rounded-xl border border-cream-300 bg-white px-3.5 py-2.5 text-[15px] text-ink-900 placeholder:text-ink-300"
              />
              <p className="mt-1.5 text-xs leading-5 text-ink-500">
                Le motif suit les commandes libérées dans le journal : c’est ce qu’on relit six mois
                plus tard pour comprendre pourquoi une commande est repartie au marché.
              </p>
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
 * Le personnel.
 *
 * Le trou que cet écran ferme : un livreur qui rendait son tablier gardait son
 * accès à vie. Le verrou existait — `users.status` coupe la connexion et
 * détruit les jetons — mais rien ne permettait de l'actionner sur quelqu'un du
 * personnel. La serrure était posée, sans poignée.
 *
 * Le filtre « Sans nouvelle » est celui qui sert : personne ne vient déclarer
 * qu'il arrête, il cesse simplement de venir.
 */
function StaffPage() {
  const toast = useToast();

  const [staff, setStaff] = useState([]);
  const [meta, setMeta] = useState({});
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');
  const [openId, setOpenId] = useState(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminService.getStaff({
        filter: filter === 'all' ? undefined : filter,
        search: search.trim() || undefined,
      });
      setStaff(res.data.data);
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
          <p className="eyebrow mt-5 text-gold-500">Personnel</p>
          <h1 className="mt-2 font-display text-display-sm text-cream">Qui travaille pour nous</h1>

          <dl className="mt-8 flex flex-wrap gap-x-10 gap-y-4">
            {[
              ['Préparateurs', meta.preparateurs || 0],
              ['Livreurs', meta.livreurs || 0],
              ['Sans nouvelle', meta.dormant || 0],
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
          <StaffFile id={openId} onBack={() => setOpenId(null)} onChanged={load} />
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

            {filter === 'dormant' && meta.dormant_after_days && (
              <p className="mb-5 text-sm leading-6 text-ink-500">
                Comptes actifs sans un seul passage depuis {meta.dormant_after_days} jours. Personne ne
                vient dire qu’il arrête — il cesse simplement de venir, et son accès reste ouvert.
              </p>
            )}

            {loading ? (
              <div className="flex items-center gap-2 rounded-card border border-cream-300 bg-white p-8 text-ink-500">
                <Loader2 size={18} className="animate-spin" /> Chargement…
              </div>
            ) : staff.length === 0 ? (
              <div className="rounded-card border border-dashed border-cream-300 bg-white p-10 text-center">
                <p className="font-display text-lg font-semibold text-ink-900">Personne ici</p>
                <p className="mt-1 text-sm text-ink-500">Aucun compte ne correspond à ce filtre.</p>
              </div>
            ) : (
              <ul className="space-y-3">
                {staff.map((person) => (
                  <li key={person.id}>
                    <button
                      onClick={() => setOpenId(person.id)}
                      className={`flex w-full flex-wrap items-center justify-between gap-4 rounded-card border bg-white p-5 text-left shadow-card transition hover:border-forest-600 ${
                        person.dormant ? 'border-gold-500/50' : 'border-cream-300'
                      }`}
                    >
                      <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                          <RoleIcon role={person.role} size={17} className="shrink-0 text-forest-600" />
                          <span className="font-display text-lg font-semibold text-ink-900">
                            {person.name}
                          </span>
                          {person.status !== 'active' && (
                            <span className="rounded-full bg-clay-200 px-2 py-0.5 text-xs font-bold text-clay-600">
                              Suspendu
                            </span>
                          )}
                          {person.dormant && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-gold-500 px-2.5 py-0.5 text-xs font-bold text-forest-950">
                              <Moon size={12} /> sans nouvelle
                            </span>
                          )}
                          {person.in_hand > 0 && (
                            <span className="rounded-full bg-forest-800 px-2.5 py-0.5 text-xs font-bold text-cream">
                              {person.in_hand} en main
                            </span>
                          )}
                        </div>
                        <p className="mt-1 text-sm text-ink-500">
                          {person.role_label}
                          {person.market && ` · ${person.market}`}
                          {person.phone && ` · ${person.phone}`}
                        </p>
                      </div>

                      <div className="text-right">
                        <p className="font-display text-lg font-semibold tabular-nums text-forest-800">
                          {sinceDays(person.last_active_at)}
                        </p>
                        <p className="text-xs text-ink-500">dernier passage</p>
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

export default withRole(StaffPage, ['admin']);
