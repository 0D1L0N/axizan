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
  CalendarOff,
  Check,
  Loader2,
  Minus,
  Plus,
  RotateCcw,
} from 'lucide-react';

const dateLong = (iso) =>
  iso
    ? new Date(`${iso}T00:00:00`).toLocaleDateString('fr-FR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
      })
    : '—';

const dayShort = (iso) =>
  new Date(`${iso}T00:00:00`).toLocaleDateString('fr-FR', { weekday: 'narrow', day: 'numeric' });

const todayIso = () => new Date().toISOString().slice(0, 10);

/**
 * La charge d'un créneau sur la semaine ouverte.
 *
 * Une capacité se règle en regardant ce qui se remplit, pas dans l'abstrait :
 * un créneau plein tous les jours demande plus de places, un créneau vide en
 * demande moins. Le chiffre seul ne le dirait pas.
 */
function Load({ load, capacity }) {
  return (
    <div className="flex gap-1">
      {load.map((day) => {
        const ratio = capacity > 0 ? day.booked / capacity : 0;
        const tone =
          day.booked === 0
            ? 'bg-cream-200 text-ink-300'
            : ratio >= 1
              ? 'bg-clay-500 text-white'
              : ratio >= 0.7
                ? 'bg-gold-500 text-forest-950'
                : 'bg-forest-800 text-cream';

        return (
          <span
            key={day.date}
            title={`${dateLong(day.date)} — ${day.booked} sur ${capacity}`}
            className={`flex h-7 w-7 items-center justify-center rounded-md text-xs font-bold tabular-nums ${tone}`}
          >
            {day.booked || ''}
          </span>
        );
      })}
    </div>
  );
}

/** Un créneau : sa capacité, et l'interrupteur qui le retire. */
function SlotRow({ slot, onChanged }) {
  const toast = useToast();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  const save = async (patch) => {
    setBusy(true);
    setError(null);
    try {
      const res = await adminService.updateSlot(slot.id, patch);
      toast.success(res.data.message);
      onChanged();
    } catch (err) {
      // Le refus « en dessous de ce qui est réservé » n'est pas une panne :
      // c'est la réponse, et elle porte le chiffre. Elle reste sous la ligne
      // concernée — un toast disparaîtrait avant qu'on l'ait lue.
      if (err.response?.data?.error === 'below_booked') {
        setError(err.response.data.message);
      } else {
        toast.fromApi(err, 'Modification refusée.');
      }
    } finally {
      setBusy(false);
    }
  };

  return (
    <li className="py-3">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex min-w-0 items-center gap-3">
          <span
            className={`font-mono text-sm font-bold ${slot.is_active ? 'text-ink-900' : 'text-ink-300 line-through'}`}
          >
            {slot.display_label}
          </span>
          {!slot.is_active && (
            <span className="rounded-full bg-cream-200 px-2 py-0.5 text-xs font-bold text-ink-500">
              retiré
            </span>
          )}
        </div>

        <div className="flex flex-wrap items-center gap-4">
          <Load load={slot.load} capacity={slot.max_orders_per_slot} />

          <div className="flex items-center gap-1.5">
            <button
              onClick={() => save({ max_orders_per_slot: Math.max(0, slot.max_orders_per_slot - 1) })}
              disabled={busy || slot.max_orders_per_slot === 0}
              aria-label="Une place de moins"
              className="flex h-7 w-7 items-center justify-center rounded-full border border-cream-300 text-ink-500 transition hover:border-forest-600 disabled:opacity-40"
            >
              <Minus size={13} />
            </button>
            <span className="w-9 text-center font-semibold tabular-nums text-ink-900">
              {slot.max_orders_per_slot}
            </span>
            <button
              onClick={() => save({ max_orders_per_slot: slot.max_orders_per_slot + 1 })}
              disabled={busy}
              aria-label="Une place de plus"
              className="flex h-7 w-7 items-center justify-center rounded-full border border-cream-300 text-ink-500 transition hover:border-forest-600 disabled:opacity-40"
            >
              <Plus size={13} />
            </button>
          </div>

          <button
            onClick={() => save({ is_active: !slot.is_active })}
            disabled={busy}
            className="text-sm font-semibold text-ink-500 underline underline-offset-4 hover:text-forest-800 disabled:opacity-40"
          >
            {slot.is_active ? 'Retirer' : 'Remettre'}
          </button>
        </div>
      </div>

      {error && (
        <p className="mt-2 flex items-start gap-2 text-sm leading-6 text-clay-600">
          <AlertTriangle size={15} className="mt-1 shrink-0" />
          {error}
        </p>
      )}
    </li>
  );
}

/** Fermer une journée : le formulaire, et ce que ça laisse derrière. */
function CloseDayForm({ zones, onClosed }) {
  const toast = useToast();

  const [date, setDate] = useState('');
  const [zoneId, setZoneId] = useState('');
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);
  const [affected, setAffected] = useState(null);

  const submit = async (event) => {
    event.preventDefault();
    setBusy(true);
    setAffected(null);
    try {
      const res = await adminService.closeDay(date, zoneId ? Number(zoneId) : null, reason.trim());
      toast.success(res.data.message);
      setAffected(res.data.affected_orders || []);
      setDate('');
      setReason('');
      onClosed();
    } catch (err) {
      toast.fromApi(err, 'La fermeture a échoué.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      <form onSubmit={submit} className="mt-4 space-y-4">
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="block">
            <span className="eyebrow text-ink-500">Date</span>
            <input
              required
              type="date"
              min={todayIso()}
              value={date}
              onChange={(e) => setDate(e.target.value)}
              className="mt-1.5 w-full rounded-xl border border-cream-300 bg-white px-3.5 py-2.5 text-[15px] text-ink-900"
            />
          </label>
          <label className="block">
            <span className="eyebrow text-ink-500">Zone</span>
            <select
              value={zoneId}
              onChange={(e) => setZoneId(e.target.value)}
              className="mt-1.5 w-full rounded-xl border border-cream-300 bg-white px-3.5 py-2.5 text-[15px] text-ink-900"
            >
              <option value="">Toute la couverture</option>
              {zones.map((zone) => (
                <option key={zone.id} value={zone.id}>
                  {zone.name}
                </option>
              ))}
            </select>
            <p className="mt-1.5 text-xs leading-5 text-ink-500">
              Un jour férié ferme tout. Une route coupée ne ferme qu’une zone.
            </p>
          </label>
        </div>

        <label className="block">
          <span className="eyebrow text-ink-500">Motif · obligatoire</span>
          <input
            required
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            placeholder="Fête nationale — les marchés sont fermés"
            className="mt-1.5 w-full rounded-xl border border-cream-300 bg-white px-3.5 py-2.5 text-[15px] text-ink-900 placeholder:text-ink-300"
          />
          <p className="mt-1.5 text-xs leading-5 text-ink-500">
            Le client le lit à la place des créneaux. « Aucun créneau disponible » ressemble à une
            panne ; une raison fait choisir un autre jour.
          </p>
        </label>

        <button
          type="submit"
          disabled={busy || !date || !reason.trim()}
          className="inline-flex items-center gap-2 rounded-full bg-clay-500 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-clay-600 disabled:opacity-45"
        >
          {busy ? <Loader2 size={15} className="animate-spin" /> : <CalendarOff size={15} />}
          Fermer cette journée
        </button>
      </form>

      {affected?.length > 0 && (
        <div className="mt-5 rounded-card border border-clay-200 bg-clay-200/30 px-4 py-3">
          <p className="flex items-start gap-2.5 text-sm leading-6 text-clay-600">
            <AlertTriangle size={17} className="mt-0.5 shrink-0" />
            <span>
              <strong>
                {affected.length} commande{affected.length > 1 ? 's' : ''} déjà posée
                {affected.length > 1 ? 's' : ''} ce jour-là.
              </strong>{' '}
              Fermer n’annule rien : ces clients attendent toujours leur livraison. Traitez-les dans
              la{' '}
              <Link href="/admin/commandes" className="font-bold underline underline-offset-4">
                salle de contrôle
              </Link>{' '}
              — ils seront remboursés.
            </span>
          </p>
          <ul className="mt-2.5 flex flex-wrap gap-2">
            {affected.map((order) => (
              <li
                key={order.order_number}
                className="rounded-full bg-white px-2.5 py-0.5 font-mono text-xs font-bold text-ink-700"
              >
                {order.order_number}
              </li>
            ))}
          </ul>
        </div>
      )}
    </>
  );
}

/**
 * Les créneaux.
 *
 * Depuis que la dernière place est défendue par un verrou, la capacité d'un
 * créneau est fiable — mais elle n'était réglable que dans la base. Un marché
 * qui déborde un samedi, une zone qu'on suspend le temps de trouver un
 * préparateur, un jour férié : tout cela demandait une requête SQL à la main.
 *
 * Règle qui tient tout l'écran : rien ici ne touche aux commandes déjà posées.
 * Ces actions arrêtent les NOUVELLES réservations. Annuler des commandes payées
 * est une décision d'argent, qui se prend une par une dans la salle de contrôle.
 */
function SlotsPage() {
  const toast = useToast();

  const [data, setData] = useState(null);
  const [meta, setMeta] = useState({});
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminService.getSlots();
      setData(res.data.data);
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

  const toggleZone = async (zone) => {
    try {
      const res = await adminService.setZoneActive(zone.id, !zone.is_active);
      toast.success(res.data.message);
      load();
    } catch (err) {
      toast.fromApi(err, 'L’action a échoué.');
    }
  };

  const reopen = async (closure) => {
    try {
      const res = await adminService.reopenDay(closure.id);
      toast.success(res.data.message);
      load();
    } catch (err) {
      toast.fromApi(err, 'La réouverture a échoué.');
    }
  };

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
          <p className="eyebrow mt-5 text-gold-500">Créneaux</p>
          <h1 className="mt-2 font-display text-display-sm text-cream">Ce qu’on peut tenir</h1>
          <p className="mt-3 max-w-xl text-[15px] leading-7 text-cream-300">
            La capacité des créneaux est le seul stock réel d’Axizán. Rien sur cette page ne touche
            aux commandes déjà posées.
          </p>
        </div>
      </div>

      <main className="mx-auto max-w-5xl space-y-6 px-4 py-10">
        {loading || !data ? (
          <div className="flex items-center gap-2 rounded-card border border-cream-300 bg-white p-8 text-ink-500">
            <Loader2 size={18} className="animate-spin" /> Chargement…
          </div>
        ) : (
          <>
            <section className="rounded-panel border border-cream-300 bg-white p-6 shadow-card">
              <h2 className="font-display text-xl font-semibold text-ink-900">Fermer une journée</h2>
              <p className="mt-1.5 text-sm leading-6 text-ink-500">
                Jour férié, marché qui ne tient pas, route coupée. Arrête les réservations sur les{' '}
                {meta.horizon_days} jours ouverts.
              </p>

              <CloseDayForm zones={data.zones} onClosed={load} />

              {data.closures.length > 0 && (
                <ul className="mt-6 divide-y divide-cream-200 border-t border-cream-200 pt-2">
                  {data.closures.map((closure) => (
                    <li
                      key={closure.id}
                      className="flex flex-wrap items-center justify-between gap-3 py-3"
                    >
                      <div className="min-w-0">
                        <p className="font-semibold text-ink-900">
                          {dateLong(closure.closed_on)}
                          <span className="ml-2 text-sm font-normal text-ink-500">
                            {closure.zone || 'toute la couverture'}
                          </span>
                        </p>
                        <p className="mt-0.5 text-sm text-ink-500">
                          {closure.reason}
                          {closure.by && ` · ${closure.by}`}
                        </p>
                      </div>
                      <div className="flex items-center gap-3">
                        {closure.orders > 0 && (
                          <span className="inline-flex items-center gap-1 rounded-full bg-clay-500 px-2.5 py-0.5 text-xs font-bold text-white">
                            <AlertTriangle size={12} /> {closure.orders} en attente
                          </span>
                        )}
                        <button
                          onClick={() => reopen(closure)}
                          className="inline-flex items-center gap-1.5 text-sm font-semibold text-ink-500 underline underline-offset-4 hover:text-forest-800"
                        >
                          <RotateCcw size={13} /> Rouvrir
                        </button>
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </section>

            {data.zones.map((zone) => (
              <section
                key={zone.id}
                className={`rounded-panel border bg-white p-6 shadow-card ${
                  zone.is_active ? 'border-cream-300' : 'border-cream-300 bg-cream-100/60'
                }`}
              >
                <div className="flex flex-wrap items-start justify-between gap-4">
                  <div>
                    <div className="flex flex-wrap items-center gap-2">
                      <h2 className="font-display text-xl font-semibold text-ink-900">{zone.name}</h2>
                      {!zone.is_active && (
                        <span className="rounded-full bg-clay-200 px-2.5 py-0.5 text-xs font-bold text-clay-600">
                          Suspendue
                        </span>
                      )}
                    </div>
                    <p className="mt-1 text-sm text-ink-500">
                      {zone.slots.length} créneau{zone.slots.length > 1 ? 'x' : ''}
                      {zone.booked_ahead > 0 &&
                        ` · ${zone.booked_ahead} commande${zone.booked_ahead > 1 ? 's' : ''} à venir`}
                    </p>
                  </div>

                  <button
                    onClick={() => toggleZone(zone)}
                    className={`inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-bold transition ${
                      zone.is_active
                        ? 'border border-clay-200 text-clay-600 hover:bg-clay-200/40'
                        : 'bg-forest-800 text-cream hover:bg-forest-700'
                    }`}
                  >
                    {zone.is_active ? <CalendarOff size={15} /> : <Check size={15} />}
                    {zone.is_active ? 'Suspendre la zone' : 'Rétablir'}
                  </button>
                </div>

                {zone.slots.length === 0 ? (
                  <p className="mt-4 text-sm text-ink-500">
                    Aucun créneau : cette zone est choisissable mais jamais livrable.
                  </p>
                ) : (
                  <>
                    <div className="mt-5 flex justify-end gap-1 pr-[8.5rem]">
                      {zone.slots[0].load.map((day) => (
                        <span
                          key={day.date}
                          className="w-7 text-center text-[11px] font-bold uppercase text-ink-300"
                        >
                          {dayShort(day.date)}
                        </span>
                      ))}
                    </div>
                    <ul className="divide-y divide-cream-200">
                      {zone.slots.map((slot) => (
                        <SlotRow key={slot.id} slot={slot} onChanged={load} />
                      ))}
                    </ul>
                  </>
                )}
              </section>
            ))}
          </>
        )}
      </main>
    </>
  );
}

export default withRole(SlotsPage, ['admin']);
