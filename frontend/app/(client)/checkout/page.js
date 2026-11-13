'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { useCart } from '@/app/contexts/CartContext';
import { useToast } from '@/app/contexts/ToastContext';
import { addressService, logisticsService, orderService, paymentMethodService, promotionService } from '@/app/services';
import { withAuth } from '@/app/lib/auth-hoc';
import Header from '@/app/components/Header';
import Footer from '@/app/components/Footer';
import AddressForm from '@/app/components/AddressForm';
import PaymentMethodPicker from '@/app/components/PaymentMethodPicker';
import { unitShort, formatQuantity, formatPrice, roundXof } from '@/app/lib/units';
import { AlertCircle, Loader2, MapPin, Plus } from 'lucide-react';

const DAY_LABELS = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];

/**
 * Frais de livraison estimes avec les memes regles que le serveur
 * (OrderService::calculateDeliveryFee) : base de la zone, supplement du
 * creneau, livraison offerte au-dela du seuil, minimum de commande.
 */
function estimateDeliveryFee(zone, slot, subtotal) {
  let fee = Number(zone?.base_delivery_fee ?? 500) + Number(slot?.additional_fee ?? 0);
  if (!zone) return { fee, blockedBelow: null };

  if (zone.free_delivery_threshold !== null && zone.free_delivery_threshold !== undefined
    && subtotal >= Number(zone.free_delivery_threshold)) {
    fee = 0;
  }

  const minimum = Number(zone.min_order_amount ?? 0);
  if (subtotal < minimum) {
    if (zone.small_order_action === 'block') return { fee, blockedBelow: minimum };
    fee += Number(zone.small_order_fee ?? 0);
  }

  return { fee, blockedBelow: null };
}

/** Date locale au format YYYY-MM-DD — toISOString() convertirait en UTC et
 *  ferait sauter un jour en soirée. */
function toLocalDate(date) {
  const offset = date.getTimezoneOffset() * 60000;
  return new Date(date.getTime() - offset).toISOString().slice(0, 10);
}

function buildDayOptions(count = 5) {
  return Array.from({ length: count }, (_, index) => {
    const date = new Date();
    date.setDate(date.getDate() + index);
    return {
      value: toLocalDate(date),
      day: index === 0 ? "aujourd'hui" : index === 1 ? 'demain' : DAY_LABELS[date.getDay()],
      number: date.getDate(),
    };
  });
}

function CheckoutPage() {
  const { items, subtotal, clearCart } = useCart();
  const router = useRouter();

  const [addresses, setAddresses] = useState([]);
  const [zones, setZones] = useState([]);
  const [slots, setSlots] = useState([]);
  const [slotMeta, setSlotMeta] = useState(null);
  const [paymentMethods, setPaymentMethods] = useState([]);
  const [paymentProvider, setPaymentProvider] = useState('');
  const [paymentDelay, setPaymentDelay] = useState('');

  const [days] = useState(() => buildDayOptions());
  const [selectedAddressId, setSelectedAddressId] = useState('');
  const [selectedSlotId, setSelectedSlotId] = useState('');
  const [deliveryDate, setDeliveryDate] = useState(() => toLocalDate(new Date()));

  const [showNewAddress, setShowNewAddress] = useState(false);
  const [substitutions, setSubstitutions] = useState({});
  const [loadingSlots, setLoadingSlots] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [promoInput, setPromoInput] = useState('');
  const [appliedPromo, setAppliedPromo] = useState(null);
  const [promoError, setPromoError] = useState('');
  const [applyingPromo, setApplyingPromo] = useState(false);
  const toast = useToast();

  // Une cle par tentative de commande, generee une seule fois : un renvoi du
  // meme panier apres coupure reseau reutilise la meme cle et ne cree pas de
  // doublon cote serveur.
  const idempotencyKey = useRef(
    typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : String(Date.now())
  );

  const selectedAddress = addresses.find((a) => String(a.id) === selectedAddressId);

  const loadAddresses = useCallback(async () => {
    const res = await addressService.list();
    const list = res.data.data;
    setAddresses(list);
    const preferred = list.find((a) => a.is_default) || list[0];
    if (preferred) setSelectedAddressId(String(preferred.id));
  }, []);

  useEffect(() => {
    loadAddresses().catch(() => setError('Impossible de charger vos adresses.'));
    logisticsService
      .getZones()
      .then((res) => setZones(res.data.data))
      .catch(() => setZones([]));

    // Les moyens de paiement viennent du serveur : un opérateur fermé ne doit
    // pas rester sélectionnable parce que le front l'aurait codé en dur.
    paymentMethodService
      .list()
      .then((res) => {
        setPaymentMethods(res.data.data);
        setPaymentDelay(res.data.meta?.validation_delay || '');
        const first = res.data.data.find((method) => method.enabled);
        if (first) setPaymentProvider(first.code);
      })
      .catch(() => setPaymentMethods([]));
  }, [loadAddresses]);

  // Les creneaux dependent de la date ET de la zone : un creneau du matin
  // disparait au fil de la journee.
  useEffect(() => {
    if (!deliveryDate) return;
    setLoadingSlots(true);
    logisticsService
      .getSlots(selectedAddress?.delivery_zone_id, deliveryDate)
      .then((res) => {
        setSlots(res.data.data);
        setSlotMeta(res.data.meta);
        setSelectedSlotId((current) =>
          res.data.data.some((slot) => String(slot.id) === current && slot.is_bookable) ? current : ''
        );
      })
      .catch(() => {
        setSlots([]);
        setSlotMeta(null);
      })
      .finally(() => setLoadingSlots(false));
  }, [deliveryDate, selectedAddress?.delivery_zone_id]);

  const handleAddressSaved = async (created) => {
    setShowNewAddress(false);
    await loadAddresses();
    setSelectedAddressId(String(created.id));
  };

  const selectedZone = zones.find((z) => z.id === selectedAddress?.delivery_zone_id);
  const selectedSlot = slots.find((s) => String(s.id) === String(selectedSlotId));
  const { fee: deliveryFee, blockedBelow } = estimateDeliveryFee(selectedZone, selectedSlot, subtotal);
  const promoDiscount = appliedPromo ? roundXof(appliedPromo.discount) : 0;
  // Arrondi identique au serveur, sinon le recapitulatif ment sur le total.
  const tax = roundXof(subtotal * 0.1);
  const total = roundXof(subtotal) + tax + roundXof(deliveryFee) - promoDiscount;

  const applyPromo = async () => {
    const code = promoInput.trim();
    if (!code) return;
    setPromoError('');
    setApplyingPromo(true);
    try {
      const res = await promotionService.check(code, roundXof(subtotal));
      setAppliedPromo(res.data.data);
    } catch (err) {
      setAppliedPromo(null);
      setPromoError(err.response?.data?.message || 'Code promo invalide.');
    } finally {
      setApplyingPromo(false);
    }
  };

  const removePromo = () => {
    setAppliedPromo(null);
    setPromoInput('');
    setPromoError('');
  };

  const bookableCount = slots.filter((slot) => slot.is_bookable).length;

  const handleSubmit = async () => {
    setError('');
    if (!selectedAddressId || !selectedSlotId || !deliveryDate) {
      setError('Sélectionnez une adresse, une date et un créneau de livraison.');
      return;
    }

    setLoading(true);
    try {
      const response = await orderService.createOrder(
        {
          delivery_address_id: Number(selectedAddressId),
          delivery_slot_id: Number(selectedSlotId),
          delivery_date: deliveryDate,
          payment_method: 'manual',
          payment_provider: paymentProvider || undefined,
          promo_code: appliedPromo?.code || undefined,
          items: items.map((item) => ({
            type: 'product',
            product_id: item.product_id,
            quantity: item.quantity,
            quantity_unit: unitShort(item.price_type),
            substitution_preference: substitutions[item.id] || 'replace',
          })),
        },
        idempotencyKey.current
      );

      clearCart();
      toast.success('Commande enregistrée. Nous validons votre paiement, puis nous partons au marché.');
      router.push(`/orders/${response.data.data.id}`);
    } catch (err) {
      const message = err.response?.data?.message || 'La commande a échoué.';
      setError(message);
      toast.error(message);
    } finally {
      setLoading(false);
    }
  };

  if (items.length === 0) {
    return (
      <>
        <Header />
        <main className="mx-auto max-w-4xl px-4 py-16">
          <div className="rounded-panel border border-ink-900/8 bg-white p-10 text-center shadow-card">
            <p className="font-semibold text-ink-500">Votre panier est vide.</p>
            <Link href="/products" className="mt-4 inline-block font-bold text-forest-800 hover:underline">
              ← Voir le marché
            </Link>
          </div>
        </main>
        <Footer />
      </>
    );
  }

  return (
    <>
      <Header />
      <main className="min-h-screen bg-cream">
        <div className="mx-auto max-w-4xl space-y-6 px-4 py-10">
          <div>
            <p className="eyebrow text-clay-500">Dernière étape</p>
            <h1 className="mt-3 font-display text-display-sm font-semibold text-forest-800">
              Livraison &amp; commande
            </h1>
          </div>

          {error && (
            <p className="flex items-start gap-2.5 rounded-card bg-clay-200 px-4 py-3 font-semibold text-clay-600">
              <AlertCircle size={18} className="mt-0.5 shrink-0" />
              {error}
            </p>
          )}

          {/* ---- Adresse ---- */}
          <section className="rounded-panel border border-ink-900/8 bg-white p-6 shadow-card">
            <h2 className="font-display text-2xl font-semibold text-forest-800">Adresse de livraison</h2>

            {addresses.length > 0 && (
              <div className="mt-5 space-y-3">
                {addresses.map((address) => (
                  <label
                    key={address.id}
                    className={`flex cursor-pointer items-start gap-3 rounded-card border p-4 transition ${
                      String(address.id) === selectedAddressId
                        ? 'border-forest-800 bg-cream-100'
                        : 'border-ink-900/10 hover:bg-cream-100'
                    }`}
                  >
                    <input
                      type="radio"
                      name="address"
                      className="mt-1 h-4 w-4 accent-[#12372A]"
                      checked={String(address.id) === selectedAddressId}
                      onChange={() => setSelectedAddressId(String(address.id))}
                    />
                    <span className="min-w-0">
                      <span className="block font-bold text-ink-900">
                        {address.label} — {address.quartier}
                      </span>
                      <span className="block text-sm text-ink-500">
                        {[address.numero_rue, address.rue].filter(Boolean).join(' ')}
                        {address.landmark ? ` · ${address.landmark}` : ''}
                      </span>
                      {!address.delivery_zone_id && (
                        <span className="mt-1 block text-sm font-semibold text-clay-600">
                          Zone non renseignée — frais estimés à {formatPrice(500)}
                        </span>
                      )}
                    </span>
                  </label>
                ))}
              </div>
            )}

            {!showNewAddress ? (
              <button
                onClick={() => setShowNewAddress(true)}
                className="mt-5 inline-flex items-center gap-2 rounded-full border border-ink-900/12 px-5 py-3 font-bold text-forest-800 transition hover:bg-cream-200"
              >
                <Plus size={17} /> Ajouter une adresse
              </button>
            ) : (
              <div className="mt-6 rounded-panel border border-ink-900/8 bg-cream p-5">
                <AddressForm onSaved={handleAddressSaved} onCancel={() => setShowNewAddress(false)} />
              </div>
            )}

            {addresses.length === 0 && !showNewAddress && (
              <p className="mt-4 flex items-center gap-2 font-semibold text-clay-600">
                <MapPin size={17} /> Il faut une adresse pour continuer.
              </p>
            )}
          </section>

          {/* ---- Date + creneau ---- */}
          <section className="rounded-panel border border-ink-900/8 bg-white p-6 shadow-card">
            <h2 className="font-display text-2xl font-semibold text-forest-800">Quand livrer&nbsp;?</h2>
            <p className="mt-1.5 text-ink-500">
              Marché ouvert de 8&nbsp;h à 19&nbsp;h. Comptez au moins une heure entre votre commande et le début
              du créneau : c’est le temps d’aller acheter.
            </p>

            <div className="mt-5 flex flex-wrap gap-2">
              {days.map((day) => (
                <button
                  key={day.value}
                  onClick={() => setDeliveryDate(day.value)}
                  className={`min-w-[92px] rounded-card border px-4 py-3 text-center transition ${
                    deliveryDate === day.value
                      ? 'border-forest-800 bg-forest-800 text-cream'
                      : 'border-ink-900/10 text-ink-700 hover:bg-cream-100'
                  }`}
                >
                  <span className="block text-sm font-semibold capitalize">{day.day}</span>
                  <span className="block font-display text-2xl font-semibold">{day.number}</span>
                </button>
              ))}
            </div>

            <div className="mt-6">
              {loadingSlots ? (
                <p className="inline-flex items-center gap-2 font-semibold text-ink-500">
                  <Loader2 size={16} className="animate-spin" /> Chargement des créneaux…
                </p>
              ) : slots.length === 0 ? (
                <p className="font-semibold text-ink-500">Aucun créneau pour cette date.</p>
              ) : (
                <>
                  {bookableCount === 0 && (
                    <p className="mb-4 flex items-start gap-2.5 rounded-card bg-gold-200 px-4 py-3 font-semibold text-forest-900">
                      <AlertCircle size={18} className="mt-0.5 shrink-0" />
                      Plus de créneau disponible aujourd’hui. Choisissez demain.
                    </p>
                  )}
                  <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 md:grid-cols-4">
                    {slots.map((slot) => {
                      const active = String(slot.id) === selectedSlotId;
                      return (
                        <button
                          key={slot.id}
                          type="button"
                          disabled={!slot.is_bookable}
                          onClick={() => setSelectedSlotId(String(slot.id))}
                          title={
                            slot.unavailable_reason === 'too_soon'
                              ? 'Trop tôt : il faut au moins une heure avant le début du créneau'
                              : slot.unavailable_reason === 'full'
                                ? 'Créneau complet'
                                : undefined
                          }
                          className={`rounded-card border px-3 py-3 text-center font-bold transition ${
                            active
                              ? 'border-forest-800 bg-forest-800 text-cream'
                              : slot.is_bookable
                                ? 'border-ink-900/10 text-ink-700 hover:bg-cream-100'
                                : 'cursor-not-allowed border-ink-900/5 bg-cream-100 text-ink-300 line-through'
                          }`}
                        >
                          {slot.display_label}
                        </button>
                      );
                    })}
                  </div>
                  {slotMeta && (
                    <p className="mt-3 text-sm text-ink-500">
                      Les créneaux barrés sont passés ou complets pour le{' '}
                      {new Date(`${slotMeta.date}T00:00:00`).toLocaleDateString('fr-FR')}.
                    </p>
                  )}
                </>
              )}
            </div>
          </section>

          {/* ---- Substitutions ---- */}
          <section className="rounded-panel border border-ink-900/8 bg-white p-6 shadow-card">
            <h2 className="font-display text-2xl font-semibold text-forest-800">
              Si un article est introuvable au marché
            </h2>
            <p className="mt-1.5 text-ink-500">
              Nous achetons vos produits le jour de la livraison. Dites-nous quoi faire, article par article.
            </p>
            <div className="mt-5 space-y-4">
              {items.map((item) => (
                <div
                  key={item.id}
                  className="flex flex-col gap-3 border-b border-ink-900/8 pb-4 last:border-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                >
                  <div>
                    <p className="font-bold text-ink-900">{item.name}</p>
                    <p className="text-sm text-ink-500">{formatQuantity(item.quantity, item.price_type)}</p>
                  </div>
                  <div className="flex gap-2">
                    {[
                      ['replace', 'Remplacer'],
                      ['remove', 'Retirer'],
                    ].map(([value, label]) => (
                      <button
                        key={value}
                        type="button"
                        onClick={() => setSubstitutions({ ...substitutions, [item.id]: value })}
                        className={`rounded-full border px-4 py-2 text-sm font-bold transition ${
                          (substitutions[item.id] || 'replace') === value
                            ? 'border-forest-800 bg-forest-800 text-cream'
                            : 'border-ink-900/12 text-ink-700 hover:bg-cream-100'
                        }`}
                      >
                        {label}
                      </button>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          </section>

          {/* ---- Recapitulatif ---- */}
          <section className="rounded-panel border border-ink-900/8 bg-white p-6 shadow-card">
            <h2 className="font-display text-2xl font-semibold text-forest-800">Récapitulatif</h2>
            <div className="mt-5 space-y-2.5 text-ink-700">
              <Row label="Sous-total" value={formatPrice(subtotal)} />
              <Row label="Taxes (10 %)" value={formatPrice(tax)} />
              <Row label="Livraison" value={deliveryFee === 0 ? 'Offerte' : formatPrice(deliveryFee)} />
              {appliedPromo && (
                <div className="flex items-center justify-between text-forest-700">
                  <span>
                    Code {appliedPromo.code}{' '}
                    <button onClick={removePromo} className="text-sm underline text-ink-500 hover:text-ink-700">retirer</button>
                  </span>
                  <span className="font-semibold">−{formatPrice(promoDiscount)}</span>
                </div>
              )}
              <div className="flex items-center justify-between border-t border-ink-900/8 pt-3">
                <span className="font-bold text-ink-900">Total</span>
                <span className="font-display text-3xl font-semibold text-forest-800">{formatPrice(total)}</span>
              </div>
            </div>

            {!appliedPromo && (
              <div className="mt-5">
                <label htmlFor="promo" className="text-sm font-semibold text-ink-700">Code promo</label>
                <div className="mt-1.5 flex gap-2">
                  <input
                    id="promo"
                    value={promoInput}
                    onChange={(e) => setPromoInput(e.target.value.toUpperCase())}
                    onKeyDown={(e) => e.key === 'Enter' && applyPromo()}
                    placeholder="BIENVENUE"
                    className="min-w-0 flex-1 rounded-full border border-ink-900/12 px-4 py-2.5 uppercase"
                  />
                  <button
                    onClick={applyPromo}
                    disabled={applyingPromo || !promoInput.trim()}
                    className="rounded-full border border-forest-800 px-5 py-2.5 font-semibold text-forest-800 hover:bg-cream-100 disabled:opacity-50"
                  >
                    {applyingPromo ? '…' : 'Appliquer'}
                  </button>
                </div>
                {promoError && <p className="mt-1.5 text-sm text-red-600">{promoError}</p>}
              </div>
            )}

            {blockedBelow && (
              <p className="mt-5 flex items-start gap-2 rounded-2xl bg-red-50 p-3 text-sm text-red-700">
                <AlertCircle size={17} className="mt-0.5 shrink-0" />
                Le minimum de commande pour {selectedZone?.name} est de {formatPrice(blockedBelow)}.
              </p>
            )}

            <div className="mt-6 border-t border-ink-900/8 pt-6">
              <h3 className="font-display text-xl font-semibold text-forest-800">Comment payez-vous&nbsp;?</h3>
              <p className="mt-1 text-sm text-ink-500">
                Vous recevrez les instructions juste après la commande.
              </p>
              <div className="mt-4">
                <PaymentMethodPicker
                  methods={paymentMethods}
                  value={paymentProvider}
                  onChange={setPaymentProvider}
                  delay={paymentDelay}
                />
              </div>
            </div>

            <button
              onClick={handleSubmit}
              disabled={loading || !selectedAddressId || !selectedSlotId || Boolean(blockedBelow)}
              className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-full bg-gold-500 px-6 py-4 font-bold text-forest-950 shadow-glow transition hover:bg-gold-400 disabled:cursor-not-allowed disabled:bg-ink-300 disabled:text-white disabled:shadow-none"
            >
              {loading && <Loader2 size={17} className="animate-spin" />}
              {loading ? 'Envoi…' : 'Confirmer la commande'}
            </button>
          </section>
        </div>
      </main>
      <Footer />
    </>
  );
}

function Row({ label, value }) {
  return (
    <div className="flex items-center justify-between">
      <span>{label}</span>
      <span className="font-semibold">{value}</span>
    </div>
  );
}

export default withAuth(CheckoutPage);
