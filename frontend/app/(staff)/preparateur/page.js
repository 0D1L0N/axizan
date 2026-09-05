'use client';

import { useCallback, useEffect, useState } from 'react';
import { preparatorService, productService } from '@/app/services';
import { withRole } from '@/app/lib/auth-hoc';
import { useToast } from '@/app/contexts/ToastContext';
import Header from '@/app/components/Header';
import PickListItem from '@/app/components/PickListItem';
import StaffSteps, { PREP_STEPS } from '@/app/components/StaffSteps';
import { usePickQueue } from '@/app/lib/pickQueue';
import { formatQuantity } from '@/app/lib/units';
import { AlertTriangle, Camera, CheckCircle2, ChevronLeft, Loader2, MapPin, Navigation, PackageCheck, Phone, RefreshCw, Store, WifiOff } from "lucide-react";

/**
 * Reproduit localement ce que le serveur ferait de ce pointage.
 *
 * Sans cela l'affichage optimiste montrerait bien « pris », mais avec un nom
 * de fournisseur vide ou l'ancien remplacement : les noms viennent de
 * relations que seule la réponse serveur porte, alors qu'on les a déjà en
 * mémoire ici.
 */
function applyLocally(item, payload, { suppliers, products }) {
  const next = { ...item, ...payload };

  if ('supplier_id' in payload) {
    const id = payload.supplier_id ? Number(payload.supplier_id) : null;
    next.supplier_id = id;
    next.supplier = id ? suppliers.find((s) => s.id === id) ?? null : null;
  }

  if ('replacement_product_id' in payload) {
    const id = payload.replacement_product_id ? Number(payload.replacement_product_id) : null;
    next.replacement_product_id = id;
    next.replacement_product = id ? products.find((p) => p.id === id) ?? null : null;
  }

  return next;
}

/**
 * Espace preparateur — pensee comme une application de terrain.
 *
 * On l'utilise debout chez le fournisseur, une main prise, en plein soleil.
 * D'ou les partis pris : une seule commande a l'ecran a la fois, des cibles
 * tactiles larges, un fort contraste, et aucune saisie au clavier hors du
 * choix d'un remplacement.
 *
 * Le preparateur ne saisit aucun prix : il retire en compte chez des
 * partenaires. Ce qu'il enregistre, c'est ce qu'il a pris et CHEZ QUI.
 */
function PreparatorPage() {
  const toast = useToast();

  const [available, setAvailable] = useState([]);
  const [mine, setMine] = useState([]);
  const [suppliers, setSuppliers] = useState([]);
  const [products, setProducts] = useState([]);
  const [market, setMarket] = useState(null);
  const [depot, setDepot] = useState(null);
  const [photo, setPhoto] = useState(null);

  const [openOrderId, setOpenOrderId] = useState(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [blocked, setBlocked] = useState('');

  const load = useCallback(async () => {
    try {
      const [availableRes, mineRes] = await Promise.all([
        preparatorService.getAvailable(),
        preparatorService.getMine(),
      ]);
      setAvailable(availableRes.data.data);
      setMine(mineRes.data.data);
      setMarket(availableRes.data.meta?.market ?? null);
      setDepot(mineRes.data.meta?.depot ?? null);
      setBlocked('');
    } catch (err) {
      if (err.response?.data?.error === 'no_market') {
        setBlocked(err.response.data.message);
      } else {
        toast.fromApi(err, 'Chargement impossible.');
      }
    } finally {
      setLoading(false);
    }
  }, [toast]);

  /**
   * File des pointages. Le rechargement à la vidange remet l'écran d'accord
   * avec le serveur : d'ici là on affiche ce que le préparateur a saisi.
   */
  const { enqueue, pending, pendingForOrder, online } = usePickQueue({
    onDrained: load,
    onPermanentFailure: (entry, error) => {
      // Un refus du serveur (fournisseur hors marché, remplacement interdit)
      // ne se rejoue pas : il faut que le préparateur le voie et corrige.
      toast.fromApi(error, 'Un pointage a été refusé. Vérifiez cet article.');
      load();
    },
  });

  useEffect(() => {
    load();
    preparatorService.getSuppliers().then((r) => setSuppliers(r.data.data)).catch(() => {});
    // Catalogue complet : sert a choisir un remplacement sans reseau
    // supplementaire au moment du choix.
    productService.getProducts({ limit: 200 }).then((r) => setProducts(r.data.data)).catch(() => {});
  }, [load]);

  const openOrder = mine.find((o) => o.id === openOrderId);

  const run = async (fn, successMessage) => {
    setBusy(true);
    try {
      await fn();
      await load();
      if (successMessage) toast.success(successMessage);
      return true;
    } catch (err) {
      toast.fromApi(err, 'L’action a échoué.');
      return false;
    } finally {
      setBusy(false);
    }
  };

  /**
   * Pointer un article.
   *
   * L'écran acte le geste tout de suite et confie l'envoi à la file : chez le
   * fournisseur, attendre un aller-retour réseau à chaque tap rend
   * l'application inutilisable, et une requête perdue ferait croire au
   * préparateur qu'il a pointé un article qui reste « en attente » côté
   * serveur — il ne l'apprendrait qu'au moment de déposer, à l'autre bout du
   * marché.
   */
  const handleItemUpdate = async (itemId, payload) => {
    setMine((orders) =>
      orders.map((order) =>
        order.id !== openOrderId
          ? order
          : {
              ...order,
              items: order.items.map((item) =>
                item.id !== itemId ? item : applyLocally(item, payload, { suppliers, products })
              ),
            }
      )
    );

    enqueue(openOrderId, itemId, payload);
  };

  /**
   * Rendre la commande. Le motif est exigé, et l'avertissement est explicite :
   * ce qui a déjà été récupéré est physiquement chez lui, la liste repart
   * vierge pour le préparateur suivant.
   */
  const handleRelease = async (order) => {
    const picked = (order.items || []).filter(
      (i) => (i.collection_status || 'pending') !== 'pending'
    ).length;

    const warning = picked > 0
      ? `\n\nAttention : ${picked} article(s) déjà pointé(s) seront remis à zéro. Rapportez ce que vous avez déjà récupéré.`
      : '';

    const reason = window.prompt(
      `Pourquoi rendez-vous cette commande ?${warning}`
    );
    if (!reason || !reason.trim()) return;

    const ok = await run(
      () => preparatorService.release(order.id, reason.trim()),
      'Commande rendue. Elle est de nouveau disponible.'
    );
    if (ok) setOpenOrderId(null);
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

  if (blocked) {
    return (
      <>
        <Header />
        <main className="grid min-h-screen place-items-center bg-cream p-4">
          <div className="max-w-md rounded-panel border-2 border-clay-500/40 bg-white p-8 text-center">
            <AlertTriangle className="mx-auto text-clay-500" size={32} />
            <h1 className="mt-4 font-display text-2xl font-semibold text-forest-800">
              Aucun marché rattaché
            </h1>
            <p className="mt-3 leading-7 text-ink-500">{blocked}</p>
          </div>
        </main>
      </>
    );
  }

  // ---- Détail d'une commande : la liste de retrait ----
  if (openOrder) {
    const items = openOrder.items || [];
    const done = items.filter((i) => (i.collection_status || 'pending') !== 'pending').length;
    const missingSupplier = items.filter(
      (i) => ['collected', 'replaced'].includes(i.collection_status) && !i.supplier_id
    ).length;
    const allDone = done === items.length && items.length > 0;
    const removed = items.filter((i) => i.collection_status === 'removed').length;
    const unsent = pendingForOrder(openOrder.id);

    return (
      <>
        <Header />
        <main className="min-h-screen bg-cream pb-32">
          <div className="mx-auto max-w-2xl px-4 py-4">
            <button
              onClick={() => setOpenOrderId(null)}
              className="inline-flex h-12 items-center gap-2 font-bold text-forest-800"
            >
              <ChevronLeft size={22} /> Mes commandes
            </button>

            {/* En-tête : ce qu'il faut savoir avant de partir */}
            <section className="mt-3 rounded-panel border-2 border-forest-800/15 bg-white p-5">
              <p className="font-mono text-sm font-bold text-ink-500">{openOrder.order_number}</p>
              <h1 className="mt-1 font-display text-2xl font-semibold text-forest-800">
                {openOrder.client?.name}
              </h1>

              <div className="mt-4 space-y-2.5 text-base">
                <p className="flex items-start gap-2.5 text-ink-700">
                  <MapPin size={19} className="mt-0.5 shrink-0 text-forest-600" />
                  <span>
                    <strong>{openOrder.delivery_address?.quartier}</strong>
                    {openOrder.delivery_address?.landmark && ` — ${openOrder.delivery_address.landmark}`}
                  </span>
                </p>
                {openOrder.client?.phone && (
                  <a
                    href={`tel:${openOrder.client.phone}`}
                    className="flex items-center gap-2.5 font-bold text-forest-800"
                  >
                    <Phone size={19} className="shrink-0" /> {openOrder.client.phone}
                  </a>
                )}
                <p className="flex items-center gap-2.5 text-ink-700">
                  <span className="text-sm font-bold uppercase tracking-wide text-ink-500">Créneau</span>
                  <strong>{openOrder.delivery_slot?.display_label || '—'}</strong>
                </p>
              </div>

              {openOrder.client_notes && (
                <p className="mt-4 rounded-card bg-gold-500/15 p-3.5 leading-6 text-ink-900">
                  « {openOrder.client_notes} »
                </p>
              )}
            </section>

            {/* Où en est cette commande, et ce qu'on attend de lui maintenant */}
            <div className="mt-4">
              <StaffSteps steps={PREP_STEPS} currentKey={openOrder.status} source={openOrder} />
            </div>

            {/* Progression : lisible d'un coup d'œil, pouce sur l'écran */}
            <div className="sticky top-[72px] z-10 mt-4 rounded-panel border-2 border-forest-800/15 bg-cream/95 p-4 backdrop-blur">
              <div className="flex items-baseline justify-between">
                <span className="font-bold text-ink-900">Liste de retrait</span>
                <span className="text-2xl font-black tabular-nums text-forest-800">
                  {done}<span className="text-ink-300">/{items.length}</span>
                </span>
              </div>
              <div className="mt-2 h-3 overflow-hidden rounded-full bg-ink-900/10">
                <div
                  className="h-full rounded-full bg-forest-600 transition-all"
                  style={{ width: items.length ? `${(done / items.length) * 100}%` : '0%' }}
                />
              </div>

              {/* État du réseau : ses pointages sont gardés, il doit le savoir
                  plutôt que de recommencer ou de douter. */}
              {(!online || pending > 0) && (
                <p className="mt-2.5 flex items-center gap-2 text-sm font-bold text-ink-700">
                  {online ? (
                    <>
                      <Loader2 size={16} className="animate-spin text-forest-600" />
                      {pending} pointage{pending > 1 ? 's' : ''} en cours d’envoi
                    </>
                  ) : (
                    <>
                      <WifiOff size={16} className="text-clay-500" />
                      Hors réseau — vos pointages sont gardés et partiront tout seuls
                    </>
                  )}
                </p>
              )}
            </div>

            {/* Trié par étal : il traverse le marché une fois, sans revenir
                sur ses pas. */}
            <ul className="mt-4 space-y-3">
              {[...items]
                .sort((a, b) => (a.pick_order ?? 999) - (b.pick_order ?? 999))
                .map((item) => (
                  <PickListItem
                    key={item.id}
                    item={item}
                    suppliers={suppliers}
                    products={products}
                    onUpdate={handleItemUpdate}
                    disabled={busy}
                  />
                ))}
            </ul>

            {/* Où déposer : il doit le savoir avant de quitter le marché. */}
            {depot && (
              <section className="mt-6 rounded-panel border-2 border-forest-800/20 bg-white p-5">
                <p className="eyebrow text-clay-500">Après le retrait</p>
                <h2 className="mt-2 flex items-center gap-2 font-display text-xl font-semibold text-forest-800">
                  <PackageCheck size={20} /> Déposer au {depot.name}
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
                <p className="mt-3 text-sm leading-6 text-ink-500">
                  Le livreur vient chercher le panier ici. Ne marquez « déposé » qu’une fois sur place.
                </p>

              </section>
            )}

            {/*
              Photo du panier — obligatoire, et volontairement HORS du bloc
              dépôt : tous les marchés n'en ont pas encore un, et l'y laisser
              rendrait le dépôt impossible à ces préparateurs-là.

              Le client paie d'avance un retrait qu'il ne voit pas faire :
              cette image est la promesse de qualité rendue vérifiable, et la
              seule preuve d'Axizán si elle est contestée.
              `capture` ouvre l'appareil photo directement.
            */}
            <section
              className={`mt-4 rounded-panel border-2 bg-white p-5 ${
                photo ? 'border-forest-800/20' : 'border-clay-500/50'
              }`}
            >
              <p className="eyebrow text-clay-500">Obligatoire</p>
              <h2 className="mt-2 flex items-center gap-2 font-display text-xl font-semibold text-forest-800">
                <Camera size={20} /> Photo du panier
              </h2>
              <p className="mt-2 text-sm leading-6 text-ink-500">
                C’est ce que le client verra du travail que vous venez de faire.
              </p>

              <input
                type="file"
                accept="image/*"
                capture="environment"
                required
                onChange={(e) => setPhoto(e.target.files?.[0] || null)}
                className="mt-3 block w-full text-base file:mr-3 file:h-14 file:rounded-card file:border-2 file:border-forest-800/20 file:bg-white file:px-5 file:font-bold file:text-forest-800"
              />

              {photo && (
                <span className="mt-2 flex items-center gap-2 font-bold text-forest-700">
                  <Camera size={17} /> {photo.name}
                  <button
                    type="button"
                    onClick={() => setPhoto(null)}
                    className="ml-auto font-bold text-clay-600 underline"
                  >
                    Reprendre
                  </button>
                </span>
              )}
            </section>

            {/* Porte de sortie : sans elle, une moto en panne bloque la
                commande pour toujours. */}
            <button
              onClick={() => handleRelease(openOrder)}
              disabled={busy}
              className="mt-4 h-14 w-full rounded-full border-2 border-clay-500/40 font-bold text-clay-600 transition hover:bg-clay-200 disabled:opacity-50"
            >
              Je ne peux pas honorer cette commande
            </button>
          </div>

          {/* Barre d'action fixe : le pouce y arrive sans remonter la page */}
          <div className="fixed inset-x-0 bottom-0 border-t-2 border-forest-800/15 bg-white p-4">
            <div className="mx-auto max-w-2xl">
              {missingSupplier > 0 && (
                <p className="mb-2.5 flex items-center gap-2 font-bold text-clay-600">
                  <AlertTriangle size={18} />
                  {missingSupplier} article(s) sans fournisseur
                </p>
              )}
              {removed > 0 && allDone && missingSupplier === 0 && (
                <p className="mb-2.5 text-sm font-semibold text-ink-500">
                  {removed} article(s) retiré(s) — l’équipe traitera le remboursement.
                </p>
              )}

              {openOrder.status === 'accepted' ? (
                <button
                  disabled={busy}
                  onClick={() =>
                    run(() => preparatorService.start(openOrder.id), 'Bonne tournée.')
                  }
                  className="h-16 w-full rounded-full bg-forest-800 text-lg font-bold text-cream disabled:opacity-60"
                >
                  {busy ? <Loader2 className="mx-auto animate-spin" /> : 'Démarrer le retrait'}
                </button>
              ) : (
                /* « Prête » ne veut plus dire « articles réunis » mais
                   « déposé au dépôt » : le livreur s’y déplace, il ne doit
                   pas trouver un panier encore au marché. */
                <button
                  disabled={busy || !allDone || missingSupplier > 0 || unsent > 0 || !photo}
                  onClick={async () => {
                    const ok = await run(
                      () => preparatorService.ready(openOrder.id, { photo }),
                      'Déposé. Le livreur peut venir le chercher.'
                    );
                    if (ok) {
                      setPhoto(null);
                      setOpenOrderId(null);
                    }
                  }}
                  className="h-16 w-full rounded-full bg-gold-500 text-lg font-bold text-forest-950 disabled:bg-ink-300 disabled:text-white"
                >
                  {busy ? (
                    <Loader2 className="mx-auto animate-spin" />
                  ) : !allDone ? (
                    `Encore ${items.length - done} article(s)`
                  ) : missingSupplier > 0 ? (
                    'Fournisseur manquant'
                  ) : !photo ? (
                    'Photo du panier requise'
                  ) : unsent > 0 ? (
                    /* Déposer avant que la file soit vide ferait refuser le
                       serveur pour « articles en attente » — alors que tout
                       est bien pointé sur le téléphone. */
                    'Envoi des pointages…'
                  ) : depot ? (
                    `J’ai déposé au ${depot.name}`
                  ) : (
                    'Marquer prête'
                  )}
                </button>
              )}
            </div>
          </div>
        </main>
      </>
    );
  }

  // ---- Liste : mes commandes, puis celles à prendre ----
  return (
    <>
      <Header />
      <main className="min-h-screen bg-cream">
        <div className="mx-auto max-w-2xl px-4 py-5">
          <div className="flex items-start justify-between gap-4">
            <div>
              <p className="eyebrow text-clay-500">Préparateur</p>
              <h1 className="mt-2 font-display text-3xl font-semibold text-forest-800">
                {market?.name || 'Mon marché'}
              </h1>
              {market?.city && (
                <p className="mt-1 flex items-center gap-1.5 font-semibold text-ink-500">
                  <Store size={16} /> {market.city}
                  {market.opening_hours && <> · {market.opening_hours}</>}
                </p>
              )}
            </div>
            <button
              onClick={load}
              aria-label="Rafraîchir"
              className="grid h-14 w-14 shrink-0 place-items-center rounded-full border-2 border-ink-900/15 bg-white text-forest-800"
            >
              <RefreshCw size={20} />
            </button>
          </div>

          {/*
            La fiche du marché.

            Un préparateur ne connaît pas forcément le marché où on l'envoie —
            les pôles du Grand Nokoué ont tous ouvert en 2026, après la
            fermeture de Dantokpa. Sans adresse ni repère, il perd sa matinée.
          */}
          {market?.address && (
            <section className="mt-5 rounded-panel border-2 border-forest-800/15 bg-white p-5">
              <div className="flex items-start gap-2.5">
                <MapPin size={19} className="mt-0.5 shrink-0 text-forest-600" />
                <div className="min-w-0 flex-1">
                  <p className="font-bold leading-6 text-ink-900">{market.address}</p>
                  {market.landmark && (
                    <p className="mt-0.5 text-sm leading-6 text-ink-500">{market.landmark}</p>
                  )}
                </div>
              </div>

              {market.notes && (
                <p className="mt-3 rounded-card bg-cream-100 p-3.5 text-sm leading-6 text-ink-700">
                  {market.notes}
                </p>
              )}

              {market.maps_query && (
                // Une recherche plutôt qu'un point figé : ces pôles sont neufs
                // et leurs coordonnées ne sont pas encore stables dans les cartes.
                <a
                  href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(market.maps_query)}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="mt-3 inline-flex h-12 items-center gap-2 rounded-full bg-forest-800 px-5 font-bold text-cream"
                >
                  <Navigation size={17} /> Y aller
                </a>
              )}
            </section>
          )}

          {/* En cours d'abord : c'est ce qu'il doit finir */}
          {mine.length > 0 && (
            <section className="mt-7">
              <h2 className="font-bold uppercase tracking-wide text-ink-500">En cours</h2>
              <ul className="mt-3 space-y-3">
                {mine.map((order) => {
                  const items = order.items || [];
                  const done = items.filter((i) => (i.collection_status || 'pending') !== 'pending').length;

                  return (
                    <li key={order.id}>
                      <button
                        onClick={() => setOpenOrderId(order.id)}
                        className="w-full rounded-panel border-2 border-forest-800/20 bg-white p-5 text-left"
                      >
                        <div className="flex items-baseline justify-between gap-3">
                          <span className="font-mono text-sm font-bold text-ink-500">{order.order_number}</span>
                          <span className="text-xl font-black tabular-nums text-forest-800">
                            {done}<span className="text-ink-300">/{items.length}</span>
                          </span>
                        </div>
                        <p className="mt-1.5 text-xl font-bold text-ink-900">{order.client?.name}</p>
                        <p className="mt-1 font-semibold text-ink-500">
                          {order.delivery_address?.quartier} · {order.delivery_slot?.display_label || '—'}
                        </p>
                        <div className="mt-3 h-2.5 overflow-hidden rounded-full bg-ink-900/10">
                          <div
                            className="h-full rounded-full bg-forest-600"
                            style={{ width: items.length ? `${(done / items.length) * 100}%` : '0%' }}
                          />
                        </div>
                      </button>
                    </li>
                  );
                })}
              </ul>
            </section>
          )}

          <section className="mt-8">
            <h2 className="font-bold uppercase tracking-wide text-ink-500">
              À prendre {available.length > 0 && `(${available.length})`}
            </h2>

            {available.length === 0 ? (
              <div className="mt-3 rounded-panel border-2 border-dashed border-ink-900/15 p-8 text-center">
                <CheckCircle2 className="mx-auto text-ink-300" size={28} />
                <p className="mt-3 font-bold text-ink-700">Aucune commande en attente</p>
                <p className="mt-1 text-ink-500">Les commandes payées de votre marché arrivent ici.</p>
              </div>
            ) : (
              <ul className="mt-3 space-y-3">
                {available.map((order) => (
                  <li key={order.id} className="rounded-panel border-2 border-ink-900/12 bg-white p-5">
                    <div className="flex items-baseline justify-between gap-3">
                      <span className="font-mono text-sm font-bold text-ink-500">{order.order_number}</span>
                      <span className="font-bold text-forest-800">
                        {(order.items || []).length} article(s)
                      </span>
                    </div>
                    <p className="mt-1.5 text-xl font-bold text-ink-900">{order.client?.name}</p>
                    <p className="mt-1 font-semibold text-ink-500">
                      {order.delivery_address?.quartier} · {order.delivery_slot?.display_label || '—'}
                    </p>

                    <ul className="mt-3 space-y-1 text-ink-700">
                      {(order.items || []).slice(0, 4).map((item) => (
                        <li key={item.id} className="flex justify-between gap-3">
                          <span className="truncate">{item.product?.name}</span>
                          <span className="shrink-0 font-bold tabular-nums">
                            {formatQuantity(item.quantity, item.product?.price_type)}
                          </span>
                        </li>
                      ))}
                      {(order.items || []).length > 4 && (
                        <li className="text-ink-500">+ {(order.items || []).length - 4} autre(s)</li>
                      )}
                    </ul>

                    <button
                      disabled={busy}
                      onClick={() =>
                        run(() => preparatorService.accept(order.id), 'Commande acceptée.')
                      }
                      className="mt-4 h-16 w-full rounded-full bg-forest-800 text-lg font-bold text-cream disabled:opacity-60"
                    >
                      Prendre cette commande
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </section>
        </div>
      </main>
    </>
  );
}

export default withRole(PreparatorPage, ['preparateur']);
