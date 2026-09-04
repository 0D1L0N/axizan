'use client';

import { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import Link from 'next/link';
import Header from '@/app/components/Header';
import Footer from '@/app/components/Footer';
import OrderStatusTimeline from '@/app/components/OrderStatusTimeline';
import { orderService, paymentMethodService } from '@/app/services';
import { withAuth } from '@/app/lib/auth-hoc';
import { formatQuantity, formatPrice } from '@/app/lib/units';
import ProductImage from '@/app/components/ProductImage';
import RemoteImage from '@/app/components/RemoteImage';
import PaymentInstructions from '@/app/components/PaymentInstructions';
import LivreurTracker from '@/app/components/LivreurTracker';
import { ArrowLeft, MapPin, CalendarClock, CreditCard, FileText, MessageCircle, RotateCcw } from 'lucide-react';
import { useToast } from '@/app/contexts/ToastContext';
import { whatsappAboutOrder } from '@/app/lib/contact';

// Une commande n'est annulable que tant qu'elle n'est pas partie en livraison.
const CANCELLABLE = ['pending', 'accepted', 'preparing', 'ready'];

function OrderDetailPage() {
  const { id } = useParams();
  const router = useRouter();

  const [order, setOrder] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [cancelling, setCancelling] = useState(false);
  const [reordering, setReordering] = useState(false);
  const [paymentMethods, setPaymentMethods] = useState([]);
  const [paymentDelay, setPaymentDelay] = useState('');
  const [fedapay, setFedapay] = useState(null);
  const toast = useToast();

  const load = () =>
    orderService
      .getOrder(id)
      .then((res) => setOrder(res.data.data))
      .catch(() => setError('Commande introuvable.'))
      .finally(() => setLoading(false));

  useEffect(() => {
    load();
  }, [id]);

  useEffect(() => {
    paymentMethodService
      .list()
      .then((res) => {
        setPaymentMethods(res.data.data);
        setPaymentDelay(res.data.meta?.validation_delay || '');
        setFedapay(res.data.meta?.fedapay ?? null);
      })
      .catch(() => setPaymentMethods([]));
  }, []);

  /**
   * Recommander : les articles repartent au panier aux prix du jour, pas a
   * ceux de la commande d'origine.
   */
  const handleReorder = async () => {
    setReordering(true);
    try {
      const res = await orderService.reorder(order.id);
      const skipped = res.data.meta?.skipped || [];

      if (skipped.length > 0) {
        toast.info(
          `Panier rechargé. ${skipped.length} article(s) ne sont plus au catalogue : ${skipped.join(', ')}.`,
          { duration: 9000 }
        );
      } else {
        toast.success('Panier rechargé avec les articles de cette commande.');
      }
      router.push('/cart');
    } catch (err) {
      toast.fromApi(err, 'Le panier n’a pas pu être rechargé.');
    } finally {
      setReordering(false);
    }
  };

  const handleCancel = async () => {
    const reason = window.prompt('Pourquoi annulez-vous cette commande ? (facultatif)') ?? '';
    if (reason === null) return;

    setCancelling(true);
    setError('');
    try {
      await orderService.cancelOrder(order.id, reason);
      await load();
    } catch (err) {
      setError(err.response?.data?.message || "L'annulation a échoué");
    } finally {
      setCancelling(false);
    }
  };

  if (loading) {
    return (
      <>
        <Header />
        <main className="mx-auto max-w-4xl px-4 py-8">
          <div className="h-64 animate-pulse rounded-2xl bg-white" />
        </main>
      </>
    );
  }

  if (!order) {
    return (
      <>
        <Header />
        <main className="mx-auto max-w-4xl px-4 py-8">
          <div className="rounded-2xl bg-white p-10 text-center shadow">
            <p className="font-bold text-ink-700">{error || 'Commande introuvable'}</p>
            <Link href="/orders" className="mt-4 inline-block font-black text-forest-800 hover:underline">
              ← Mes commandes
            </Link>
          </div>
        </main>
      </>
    );
  }

  return (
    <>
      <Header />
      <main className="mx-auto max-w-4xl px-4 py-8 space-y-6">
        <button
          onClick={() => router.push('/orders')}
          className="inline-flex items-center gap-2 font-black text-forest-800 hover:underline"
        >
          <ArrowLeft size={18} /> Mes commandes
        </button>

        <div className="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h1 className="text-3xl font-black text-forest-800">{order.order_number}</h1>
            <p className="text-sm text-ink-500">
              Commandée le {new Date(order.created_at).toLocaleDateString('fr-FR')}
            </p>
          </div>
          <p className="text-3xl font-black text-forest-800">{formatPrice(order.total)}</p>
        </div>

        {error && <div className="rounded-card border border-clay-200 bg-clay-200/40 px-4 py-3 font-semibold text-clay-600">{error}</div>}

        {/* Le statut d'abord */}
        <OrderStatusTimeline order={order} />

        {/* Suivi du livreur pendant le trajet */}
        <LivreurTracker order={order} onRefresh={load} />

        {/* Photo du panier au dépôt : ce qui a été réuni, avant même que la
            livraison commence. */}
        {order.prep_photo_url && (
          <section className="overflow-hidden rounded-panel border border-ink-900/8 bg-white shadow-card">
            <div className="border-b border-ink-900/8 px-6 py-4">
              <p className="eyebrow text-clay-500">Votre panier</p>
              <h2 className="mt-2 font-display text-2xl font-semibold text-forest-800">
                Réuni pour vous
              </h2>
              {order.ready_at && (
                <p className="mt-1 text-sm text-ink-500">
                  {new Date(order.ready_at).toLocaleString('fr-FR', {
                    dateStyle: 'long',
                    timeStyle: 'short',
                  })}
                </p>
              )}
            </div>
            <div className="relative aspect-[4/3] w-full bg-cream-200 sm:aspect-[16/9]">
              <RemoteImage
                src={order.prep_photo_url}
                alt={`Panier réuni pour la commande ${order.order_number}`}
                fill
                sizes="(max-width: 768px) 100vw, 60vw"
                className="object-contain"
              />
            </div>
          </section>
        )}

        {/*
          Preuve de remise. Elle etait produite par le livreur puis jetee :
          stockee en base, jamais montree. C'est pourtant le principal marqueur
          de confiance d'un service ou le client paie d'avance un achat qu'il ne
          voit pas faire.
        */}
        {order.delivery?.proof_photo_url && (
          <section className="overflow-hidden rounded-panel border border-ink-900/8 bg-white shadow-card">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-ink-900/8 px-6 py-4">
              <div>
                <p className="eyebrow text-clay-500">Preuve de livraison</p>
                <h2 className="mt-2 font-display text-2xl font-semibold text-forest-800">
                  Votre commande, à la remise
                </h2>
              </div>
              <div className="text-sm text-ink-500">
                {order.delivery.delivery_time && (
                  <p className="font-semibold text-ink-900">
                    {new Date(order.delivery.delivery_time).toLocaleString('fr-FR', {
                      dateStyle: 'long',
                      timeStyle: 'short',
                    })}
                  </p>
                )}
                {order.delivery.livreur?.name && <p>Remise par {order.delivery.livreur.name}</p>}
              </div>
            </div>

            <div className="relative aspect-[4/3] w-full bg-cream-200 sm:aspect-[16/9]">
              <RemoteImage
                src={order.delivery.proof_photo_url}
                alt={`Photo prise à la remise de la commande ${order.order_number}`}
                fill
                sizes="(max-width: 768px) 100vw, 60vw"
                className="object-contain"
              />
            </div>

            {order.delivery.delivery_notes && (
              <p className="border-t border-ink-900/8 px-6 py-4 leading-7 text-ink-500">
                « {order.delivery.delivery_notes} »
              </p>
            )}
          </section>
        )}

        {/* Le paiement se fait apres la commande : sans ces instructions, le
            client repartait sans savoir ou envoyer l'argent. */}
        {order.payment_status !== 'completed' && order.status !== 'cancelled' && (
          <PaymentInstructions
            order={order}
            methods={paymentMethods}
            delay={paymentDelay}
            fedapay={fedapay}
            onDeclared={load}
          />
        )}

        {/* Puis le reste */}
        <section className="grid gap-4 sm:grid-cols-3">
          <div className="rounded-2xl bg-white p-4 shadow">
            <MapPin className="text-forest-800" size={18} />
            <p className="mt-2 text-xs font-black uppercase tracking-wide text-ink-300">Livraison</p>
            <p className="mt-1 font-semibold">{order.delivery_address?.label}</p>
            <p className="text-sm text-ink-500">
              {[order.delivery_address?.numero_rue, order.delivery_address?.rue, order.delivery_address?.quartier]
                .filter(Boolean)
                .join(', ')}
            </p>
          </div>
          <div className="rounded-2xl bg-white p-4 shadow">
            <CalendarClock className="text-forest-800" size={18} />
            <p className="mt-2 text-xs font-black uppercase tracking-wide text-ink-300">Créneau</p>
            <p className="mt-1 font-semibold">
              {order.delivery_date ? new Date(order.delivery_date).toLocaleDateString('fr-FR') : '—'}
            </p>
            <p className="text-sm text-ink-500">{order.delivery_slot?.display_label || '—'}</p>
          </div>
          <div className="rounded-2xl bg-white p-4 shadow">
            <CreditCard className="text-forest-800" size={18} />
            <p className="mt-2 text-xs font-black uppercase tracking-wide text-ink-300">Paiement</p>
            <p className="mt-1 font-semibold">
              {order.payment_status === 'completed' ? 'Validé' : 'En attente de validation'}
            </p>
            <p className="text-sm text-ink-500">
              {order.payment_method === 'manual' ? 'Validation manuelle' : order.payment_method}
            </p>
          </div>
        </section>

        <section className="rounded-2xl bg-white p-6 shadow">
          <h2 className="mb-4 text-xl font-bold">Articles ({order.items?.length ?? 0})</h2>
          <div className="divide-y divide-cream-200">
            {(order.items || []).map((item) => (
              <div key={item.id} className="flex items-center gap-4 py-3">
                <ProductImage
                  product={item.product || { name: `Produit #${item.product_id}` }}
                  size="thumb"
                  emojiSize="text-xl"
                  className="h-14 w-14 shrink-0 rounded-xl object-cover"
                />
                <div className="min-w-0 flex-1">
                  <p className="font-semibold">{item.product?.name || `Produit #${item.product_id}`}</p>
                  <p className="text-sm text-ink-500">
                    {formatQuantity(item.quantity, item.product?.price_type)} ·{' '}
                    {item.substitution_preference === 'remove'
                      ? 'à retirer si absent au marché'
                      : 'à remplacer si absent au marché'}
                  </p>
                </div>
                <p className="font-semibold">{formatPrice(item.total_price)}</p>
              </div>
            ))}
          </div>

          <div className="mt-4 space-y-1 border-t border-cream-200 pt-4 text-sm">
            <div className="flex justify-between"><span>Sous-total</span><span>{formatPrice(order.subtotal)}</span></div>
            <div className="flex justify-between"><span>Taxes</span><span>{formatPrice(order.tax)}</span></div>
            <div className="flex justify-between"><span>Livraison</span><span>{formatPrice(order.delivery_fee)}</span></div>
            {Number(order.discount_amount) > 0 && (
              <div className="flex justify-between text-green-600">
                <span>Réduction</span><span>-{formatPrice(order.discount_amount)}</span>
              </div>
            )}
            <div className="flex justify-between border-t pt-2 text-lg font-bold">
              <span>Total</span><span className="text-forest-800">{formatPrice(order.total)}</span>
            </div>
          </div>
        </section>

        {/* La facture n'existe qu'une fois la marchandise remise : avant, le
            contenu du panier peut encore changer au marché. */}
        {['delivered', 'completed'].includes(order.status) && (
          <Link
            href={`/orders/${order.id}/facture`}
            className="inline-flex w-full items-center justify-center gap-2 rounded-full border border-forest-800/25 bg-white px-6 py-3.5 font-bold text-forest-800 transition hover:bg-cream-100"
          >
            <FileText size={17} /> Voir ma facture
          </Link>
        )}

        <section className="flex flex-col gap-3 sm:flex-row">
          <button
            onClick={handleReorder}
            disabled={reordering}
            className="inline-flex flex-1 items-center justify-center gap-2 rounded-full bg-forest-800 px-6 py-3.5 font-bold text-cream transition hover:bg-forest-700 disabled:opacity-60"
          >
            <RotateCcw size={17} />
            {reordering ? 'Ajout au panier…' : 'Recommander la même chose'}
          </button>
          <a
            href={whatsappAboutOrder(order.order_number)}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center justify-center gap-2 rounded-full border border-ink-900/12 px-6 py-3.5 font-bold text-ink-900 transition hover:bg-cream-100"
          >
            <MessageCircle size={17} /> Une question ?
          </a>
        </section>

        {CANCELLABLE.includes(order.status) && (
          <button
            onClick={handleCancel}
            disabled={cancelling}
            className="w-full rounded-full border border-clay-200 py-3 font-bold text-clay-600 transition hover:bg-clay-200/40 disabled:opacity-50"
          >
            {cancelling ? 'Annulation...' : 'Annuler la commande'}
          </button>
        )}
      </main>
      <Footer />
    </>
  );
}

export default withAuth(OrderDetailPage);
