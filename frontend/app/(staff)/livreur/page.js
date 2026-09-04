'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { deliveryService } from '@/app/services';
import { withRole } from '@/app/lib/auth-hoc';
import { useToast } from '@/app/contexts/ToastContext';
import Header from '@/app/components/Header';
import StaffSteps, { DELIVERY_STEPS } from '@/app/components/StaffSteps';
import { formatQuantity, formatPrice } from '@/app/lib/units';
import { directionsLink, mapsLink } from '@/app/lib/geo';
import {
  Camera,
  CheckCircle2,
  ChevronLeft,
  Loader2,
  MapPin,
  Navigation,
  PackageCheck,
  Phone,
  RefreshCw,
  Radio,
} from 'lucide-react';

/** Fréquence d'envoi de la position pendant un trajet. */
const POSITION_INTERVAL_MS = 45000;

/**
 * Fréquence de relecture du dépôt.
 *
 * Le préparateur dépose et repart : sans ce rappel, un panier attend qu'un
 * livreur pense à rafraîchir. 45 s est un compromis — assez court pour qu'une
 * course ne dorme pas, assez long pour ne pas manger le forfait data d'un
 * téléphone qui reste ouvert toute la journée.
 */
const POOL_POLL_MS = 45000;

/**
 * Espace livreur — application de terrain, comme celle du préparateur.
 *
 * Le parcours : il voit les paniers déposés, en prend un, va au dépôt, part
 * chez le client, remet et photographie. Pendant le trajet, son téléphone
 * envoie sa position pour que le client puisse la consulter.
 */
function LivreurPage() {
  const toast = useToast();

  const [ready, setReady] = useState([]);
  const [mine, setMine] = useState([]);
  const [openId, setOpenId] = useState(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);

  const [photo, setPhoto] = useState(null);
  const [notes, setNotes] = useState('');
  const [sharing, setSharing] = useState(false);
  const watchRef = useRef(null);
  const lastSentRef = useRef(0);
  /** Paniers deja vus : sert a ne signaler que ce qui vient d'arriver. */
  const seenRef = useRef(new Set());

  /**
   * `silent` : relecture de fond, déclenchée par la minuterie ou le retour sur
   * l'onglet. Elle ne montre pas le squelette et n'affiche pas d'erreur — une
   * coupure réseau au marché est banale, la tentative suivante repartira.
   */
  const load = useCallback(
    async ({ silent = false } = {}) => {
      try {
        const [readyRes, mineRes] = await Promise.all([
          deliveryService.getReadyOrders(),
          deliveryService.getMine(),
        ]);

        const pool = readyRes.data.data;

        // Signaler ce qui vient d'arriver : la liste seule ne se remarque pas
        // quand on ne la regarde pas.
        if (silent) {
          const fresh = pool.filter((order) => !seenRef.current.has(order.id));
          if (fresh.length > 0) {
            toast.success(
              fresh.length === 1
                ? `Nouveau panier au dépôt : ${fresh[0].order_number}.`
                : `${fresh.length} nouveaux paniers au dépôt.`
            );
          }
        }

        seenRef.current = new Set(pool.map((order) => order.id));
        setReady(pool);
        setMine(mineRes.data.data);
      } catch (err) {
        if (!silent) toast.fromApi(err, 'Chargement impossible.');
      } finally {
        setLoading(false);
      }
    },
    [toast]
  );

  useEffect(() => {
    load();
  }, [load]);

  /**
   * Relecture automatique du dépôt, seulement sur l'écran de liste et onglet
   * au premier plan : en pleine livraison le pool n'est pas affiché, et
   * sonder en arrière-plan ne ferait que consommer batterie et data.
   */
  useEffect(() => {
    if (openId !== null) return undefined;

    const tick = () => {
      if (document.visibilityState === 'visible') load({ silent: true });
    };

    const timer = setInterval(tick, POOL_POLL_MS);
    // Revenir sur l'onglet vaut une relecture : le livreur veut voir l'état
    // du dépôt maintenant, pas au prochain battement.
    document.addEventListener('visibilitychange', tick);

    return () => {
      clearInterval(timer);
      document.removeEventListener('visibilitychange', tick);
    };
  }, [openId, load]);

  const openDelivery = mine.find((d) => d.id === openId);

  /**
   * Partage de position pendant le trajet.
   *
   * `watchPosition` suit en continu, mais on n'envoie qu'une fois toutes les
   * 45 secondes : le GPS bavarde, la data se paie, et le client n'a pas
   * besoin d'un point par seconde.
   */
  const startSharing = useCallback(
    (deliveryId) => {
      if (!navigator.geolocation) {
        toast.error('Ce téléphone ne partage pas sa position.');
        return;
      }

      watchRef.current = navigator.geolocation.watchPosition(
        (position) => {
          const now = Date.now();
          if (now - lastSentRef.current < POSITION_INTERVAL_MS) return;
          lastSentRef.current = now;

          deliveryService
            .updateLocation(deliveryId, position.coords.latitude, position.coords.longitude)
            // Un envoi raté ne doit rien interrompre : le suivant repartira.
            .catch(() => {});
        },
        () => toast.error('Position refusée. Le client ne pourra pas vous suivre.'),
        { enableHighAccuracy: true, maximumAge: 30000, timeout: 20000 }
      );
      setSharing(true);
    },
    [toast]
  );

  const stopSharing = useCallback(() => {
    if (watchRef.current !== null) {
      navigator.geolocation.clearWatch(watchRef.current);
      watchRef.current = null;
    }
    setSharing(false);
  }, []);

  // Le suivi s'arrête avec l'écran : laisser le GPS tourner viderait la
  // batterie du livreur pour rien.
  useEffect(() => stopSharing, [stopSharing]);

  useEffect(() => {
    if (openDelivery?.status === 'in_transit' && !sharing) {
      startSharing(openDelivery.id);
    }
    if (openDelivery?.status !== 'in_transit' && sharing) {
      stopSharing();
    }
  }, [openDelivery?.status, openDelivery?.id, sharing, startSharing, stopSharing]);

  const run = async (fn, message) => {
    setBusy(true);
    try {
      await fn();
      await load();
      if (message) toast.success(message);
      return true;
    } catch (err) {
      toast.fromApi(err, 'L’action a échoué.');
      return false;
    } finally {
      setBusy(false);
    }
  };

  const handleConfirm = async () => {
    const form = new FormData();
    // Les noms des champs sont ceux de DeliveryController::confirm. Ils
    // portaient auparavant « photo » et « notes » : le serveur les ignorait
    // en silence, et aucune preuve de remise n'était jamais enregistrée.
    form.append('proof_photo', photo);
    if (notes.trim()) form.append('delivery_notes', notes.trim());

    const ok = await run(
      () => deliveryService.confirmDelivery(openDelivery.id, form),
      'Livraison confirmée. Merci.'
    );

    if (ok) {
      stopSharing();
      setPhoto(null);
      setNotes('');
      setOpenId(null);
    }
  };

  // ---------------------------------------------------------------- écrans

  if (loading) {
    return (
      <>
        <Header />
        <main className="min-h-screen bg-cream p-4">
          <div className="mx-auto max-w-2xl space-y-3">
            {[1, 2, 3].map((i) => (
              <div key={i} className="h-32 animate-pulse rounded-panel bg-white" />
            ))}
          </div>
        </main>
      </>
    );
  }

  // ---- Une livraison en cours ----
  if (openDelivery) {
    const order = openDelivery.order || {};
    const address = order.delivery_address || {};
    const depot = order.preparateur?.market?.depot;
    const inTransit = openDelivery.status === 'in_transit';

    return (
      <>
        <Header />
        <main className="min-h-screen bg-cream pb-32">
          <div className="mx-auto max-w-2xl px-4 py-4">
            <button
              onClick={() => setOpenId(null)}
              className="inline-flex h-12 items-center gap-2 font-bold text-forest-800"
            >
              <ChevronLeft size={22} /> Mes livraisons
            </button>

            <div className="mt-3">
              <StaffSteps
                steps={DELIVERY_STEPS}
                currentKey={openDelivery.status}
                source={openDelivery}
              />
            </div>

            {/* Étape 1 : où prendre le panier */}
            {!inTransit && depot && (
              <section className="mt-4 rounded-panel border-2 border-gold-500/50 bg-white p-5">
                <p className="eyebrow text-clay-500">À récupérer</p>
                <h2 className="mt-2 flex items-center gap-2 font-display text-xl font-semibold text-forest-800">
                  <PackageCheck size={20} /> {depot.name}
                </h2>
                <p className="mt-2 leading-7 text-ink-700">
                  {depot.address}
                  {depot.landmark && <> — <strong>{depot.landmark}</strong></>}
                </p>
                {depot.phone && (
                  <a href={`tel:${depot.phone}`} className="mt-1 inline-block font-bold text-forest-800">
                    {depot.phone}
                  </a>
                )}
              </section>
            )}

            {/* Étape 2 : où livrer */}
            <section className="mt-4 rounded-panel border-2 border-forest-800/15 bg-white p-5">
              <p className="font-mono text-sm font-bold text-ink-500">{order.order_number}</p>
              <h2 className="mt-1 font-display text-2xl font-semibold text-forest-800">
                {order.client?.name}
              </h2>

              <p className="mt-3 flex items-start gap-2.5 text-ink-700">
                <MapPin size={19} className="mt-0.5 shrink-0 text-forest-600" />
                <span>
                  <strong>{address.quartier}</strong>
                  {[address.numero_rue, address.rue].filter(Boolean).length > 0 && (
                    <> — {[address.numero_rue, address.rue].filter(Boolean).join(' ')}</>
                  )}
                  {address.landmark && <><br />{address.landmark}</>}
                </span>
              </p>

              {address.detailed_instructions && (
                <p className="mt-2 rounded-card bg-gold-500/15 p-3 leading-6 text-ink-900">
                  {address.detailed_instructions}
                </p>
              )}

              <div className="mt-4 flex flex-col gap-2 sm:flex-row">
                {order.client?.phone && (
                  <a
                    href={`tel:${order.client.phone}`}
                    className="inline-flex h-14 flex-1 items-center justify-center gap-2 rounded-full border-2 border-ink-900/15 font-bold text-forest-800"
                  >
                    <Phone size={19} /> Appeler
                  </a>
                )}
                {directionsLink(address.latitude, address.longitude) ? (
                  <a
                    href={directionsLink(address.latitude, address.longitude)}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex h-14 flex-1 items-center justify-center gap-2 rounded-full bg-forest-800 font-bold text-cream"
                  >
                    <Navigation size={19} /> Itinéraire
                  </a>
                ) : address.maps_url ? (
                  <a
                    href={address.maps_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex h-14 flex-1 items-center justify-center gap-2 rounded-full bg-forest-800 font-bold text-cream"
                  >
                    <Navigation size={19} /> Ouvrir le lien du client
                  </a>
                ) : (
                  <span className="inline-flex h-14 flex-1 items-center justify-center rounded-full bg-cream-200 font-semibold text-ink-500">
                    Pas de position — appelez
                  </span>
                )}
              </div>
            </section>

            {/* Le panier, pour vérifier avant de partir */}
            <section className="mt-4 rounded-panel border-2 border-forest-800/15 bg-white p-5">
              <div className="flex items-baseline justify-between">
                <h3 className="font-bold text-ink-900">Contenu</h3>
                <span className="font-black text-forest-800">{formatPrice(order.total)}</span>
              </div>
              <ul className="mt-3 space-y-1.5 text-ink-700">
                {(order.items || []).map((item) => (
                  <li key={item.id} className="flex justify-between gap-3">
                    <span className="truncate">{item.product?.name}</span>
                    <span className="shrink-0 font-bold tabular-nums">
                      {formatQuantity(item.quantity, item.product?.price_type)}
                    </span>
                  </li>
                ))}
              </ul>
            </section>

            {/* Confirmation de remise */}
            {inTransit && (
              <section className="mt-4 rounded-panel border-2 border-forest-800/15 bg-white p-5">
                <h3 className="font-bold text-ink-900">À la remise</h3>

                {/* Photo obligatoire : « je n'ai rien reçu » ne se tranche pas
                    sur parole. `capture` ouvre l'appareil photo directement. */}
                <label className="mt-3 block">
                  <span className="text-xs font-bold uppercase tracking-[0.18em] text-ink-500">
                    Photo de remise · obligatoire
                  </span>
                  <input
                    type="file"
                    accept="image/*"
                    capture="environment"
                    required
                    onChange={(e) => setPhoto(e.target.files?.[0] || null)}
                    className={`mt-2 block w-full text-base file:mr-3 file:h-14 file:rounded-card file:border-2 file:bg-white file:px-5 file:font-bold file:text-forest-800 ${
                      photo ? 'file:border-forest-800/20' : 'file:border-clay-500'
                    }`}
                  />
                  {photo ? (
                    <span className="mt-2 flex items-center gap-2 font-bold text-forest-700">
                      <Camera size={17} /> {photo.name}
                    </span>
                  ) : (
                    <span className="mt-2 block text-sm font-semibold text-clay-600">
                      Photographiez le panier remis avant de confirmer.
                    </span>
                  )}
                </label>

                <label className="mt-4 block">
                  <span className="text-xs font-bold uppercase tracking-[0.18em] text-ink-500">
                    Remarque (facultatif)
                  </span>
                  <textarea
                    rows={2}
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    placeholder="Remis à la voisine, portail bleu…"
                    className="mt-2 w-full rounded-card border-2 border-ink-900/15 p-3 text-base outline-none"
                  />
                </label>
              </section>
            )}

            {/* Position partagée : le livreur doit savoir qu'il est suivi. */}
            {inTransit && (
              <p
                className={`mt-4 flex items-center gap-2.5 rounded-card px-4 py-3 font-semibold ${
                  sharing ? 'bg-forest-500/10 text-forest-700' : 'bg-cream-200 text-ink-500'
                }`}
              >
                <Radio size={18} className={sharing ? 'animate-pulse' : ''} />
                {sharing
                  ? 'Le client voit votre position pendant le trajet.'
                  : 'Position non partagée — le client ne peut pas vous suivre.'}
              </p>
            )}
          </div>

          {/* Action principale, fixée sous le pouce */}
          <div className="fixed inset-x-0 bottom-0 border-t-2 border-forest-800/15 bg-white p-4">
            <div className="mx-auto max-w-2xl">
              {!inTransit ? (
                <button
                  disabled={busy}
                  onClick={() =>
                    run(
                      () => deliveryService.startDelivery(openDelivery.id),
                      'Bonne route. Le client suit votre position.'
                    )
                  }
                  className="h-16 w-full rounded-full bg-forest-800 text-lg font-bold text-cream disabled:opacity-60"
                >
                  {busy ? <Loader2 className="mx-auto animate-spin" /> : 'J’ai le panier, je pars'}
                </button>
              ) : (
                <button
                  disabled={busy || !photo}
                  onClick={handleConfirm}
                  className="h-16 w-full rounded-full bg-gold-500 text-lg font-bold text-forest-950 disabled:bg-ink-300 disabled:text-white"
                >
                  {busy ? (
                    <Loader2 className="mx-auto animate-spin" />
                  ) : photo ? (
                    'Confirmer la remise'
                  ) : (
                    'Photo de remise requise'
                  )}
                </button>
              )}
            </div>
          </div>
        </main>
      </>
    );
  }

  // ---- Liste ----
  return (
    <>
      <Header />
      <main className="min-h-screen bg-cream">
        <div className="mx-auto max-w-2xl px-4 py-5">
          <div className="flex items-start justify-between gap-4">
            <div>
              <p className="eyebrow text-clay-500">Livreur</p>
              <h1 className="mt-2 font-display text-3xl font-semibold text-forest-800">
                Mes livraisons
              </h1>
            </div>
            <button
              onClick={() => load()}
              aria-label="Rafraîchir"
              className="grid h-14 w-14 shrink-0 place-items-center rounded-full border-2 border-ink-900/15 bg-white text-forest-800"
            >
              <RefreshCw size={20} />
            </button>
          </div>

          {mine.length > 0 && (
            <section className="mt-7">
              <h2 className="font-bold uppercase tracking-wide text-ink-500">En cours</h2>
              <ul className="mt-3 space-y-3">
                {mine.map((delivery) => (
                  <li key={delivery.id}>
                    <button
                      onClick={() => setOpenId(delivery.id)}
                      className="w-full rounded-panel border-2 border-forest-800/20 bg-white p-5 text-left"
                    >
                      <div className="flex items-baseline justify-between gap-3">
                        <span className="font-mono text-sm font-bold text-ink-500">
                          {delivery.order?.order_number}
                        </span>
                        <span className="rounded-full bg-forest-800 px-3 py-1 text-xs font-bold text-cream">
                          {delivery.status === 'in_transit' ? 'En route' : 'À récupérer'}
                        </span>
                      </div>
                      <p className="mt-1.5 text-xl font-bold text-ink-900">
                        {delivery.order?.client?.name}
                      </p>
                      <p className="mt-1 font-semibold text-ink-500">
                        {delivery.order?.delivery_address?.quartier} ·{' '}
                        {delivery.order?.delivery_slot?.display_label || '—'}
                      </p>
                    </button>
                  </li>
                ))}
              </ul>
            </section>
          )}

          <section className="mt-8">
            <h2 className="font-bold uppercase tracking-wide text-ink-500">
              Paniers déposés {ready.length > 0 && `(${ready.length})`}
            </h2>

            {ready.length === 0 ? (
              <div className="mt-3 rounded-panel border-2 border-dashed border-ink-900/15 p-8 text-center">
                <CheckCircle2 className="mx-auto text-ink-300" size={28} />
                <p className="mt-3 font-bold text-ink-700">Aucun panier en attente</p>
                <p className="mt-1 text-ink-500">
                  Les paniers apparaissent ici une fois déposés par le préparateur.
                </p>
              </div>
            ) : (
              <ul className="mt-3 space-y-3">
                {ready.map((order) => {
                  const depot = order.preparateur?.market?.depot;

                  return (
                    <li key={order.id} className="rounded-panel border-2 border-ink-900/12 bg-white p-5">
                      <div className="flex items-baseline justify-between gap-3">
                        <span className="font-mono text-sm font-bold text-ink-500">
                          {order.order_number}
                        </span>
                        <span className="font-bold text-forest-800">{formatPrice(order.total)}</span>
                      </div>
                      <p className="mt-1.5 text-xl font-bold text-ink-900">{order.client?.name}</p>

                      <div className="mt-3 space-y-1.5 text-ink-700">
                        {depot && (
                          <p className="flex items-start gap-2">
                            <PackageCheck size={17} className="mt-0.5 shrink-0 text-gold-600" />
                            <span>À prendre au <strong>{depot.name}</strong></span>
                          </p>
                        )}
                        <p className="flex items-start gap-2">
                          <MapPin size={17} className="mt-0.5 shrink-0 text-forest-600" />
                          <span>
                            Vers <strong>{order.delivery_address?.quartier}</strong> ·{' '}
                            {order.delivery_slot?.display_label || '—'}
                          </span>
                        </p>
                      </div>

                      <button
                        disabled={busy}
                        onClick={() =>
                          run(() => deliveryService.acceptDelivery(order.id), 'Livraison acceptée.')
                        }
                        className="mt-4 h-16 w-full rounded-full bg-forest-800 text-lg font-bold text-cream disabled:opacity-60"
                      >
                        Prendre cette livraison
                      </button>
                    </li>
                  );
                })}
              </ul>
            )}
          </section>
        </div>
      </main>
    </>
  );
}

export default withRole(LivreurPage, ['livreur']);
