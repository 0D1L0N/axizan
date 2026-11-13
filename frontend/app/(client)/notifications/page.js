'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import Header from '@/app/components/Header';
import Footer from '@/app/components/Footer';
import { notificationService } from '@/app/services';
import { withAuth } from '@/app/lib/auth-hoc';
import { Bell, Check, Inbox } from 'lucide-react';

const FILTERS = [
  ['all', 'Toutes'],
  ['unread', 'Non lues'],
];

function NotificationsPage() {
  const [items, setItems] = useState([]);
  const [unread, setUnread] = useState(0);
  const [filter, setFilter] = useState('all');
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    setLoading(true);
    notificationService
      .list({ limit: 50, ...(filter === 'unread' ? { unread: 1 } : {}) })
      .then((res) => {
        setItems(res.data.data);
        setUnread(res.data.meta.unread_count);
      })
      .catch(() => setItems([]))
      .finally(() => setLoading(false));
  }, [filter]);

  useEffect(() => {
    load();
  }, [load]);

  const handleMarkAll = async () => {
    await notificationService.markAllRead().catch(() => {});
    load();
  };

  const handleRead = async (notification) => {
    if (notification.is_read) return;
    await notificationService.markRead(notification.id).catch(() => {});
    setUnread((current) => Math.max(0, current - 1));
    setItems((current) =>
      current.map((item) => (item.id === notification.id ? { ...item, is_read: true } : item))
    );
  };

  return (
    <>
      <Header />
      <main className="min-h-screen bg-cream">
        <section className="relative grain overflow-hidden bg-forest-900 text-cream">
          <div className="cowrie-field absolute inset-0 opacity-60" />
          <div className="relative mx-auto max-w-4xl px-4 py-14">
            <p className="eyebrow text-gold-500">Boîte de réception</p>
            <h1 className="mt-4 font-display text-display-sm font-semibold">Notifications</h1>
            <p className="mt-4 text-lg text-cream/70">
              {unread > 0 ? `${unread} non lue${unread > 1 ? 's' : ''}.` : 'Tout est à jour.'}
            </p>
          </div>
        </section>

        <div className="mx-auto max-w-4xl px-4 py-10">
          <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div className="flex gap-2">
              {FILTERS.map(([value, label]) => (
                <button
                  key={value}
                  onClick={() => setFilter(value)}
                  className={`rounded-full border px-5 py-2.5 font-bold transition ${
                    filter === value
                      ? 'border-forest-800 bg-forest-800 text-cream'
                      : 'border-ink-900/12 text-ink-700 hover:bg-cream-100'
                  }`}
                >
                  {label}
                </button>
              ))}
            </div>
            {unread > 0 && (
              <button
                onClick={handleMarkAll}
                className="inline-flex items-center gap-2 rounded-full border border-ink-900/12 px-5 py-2.5 font-bold text-forest-800 transition hover:bg-cream-100"
              >
                <Check size={16} /> Tout marquer comme lu
              </button>
            )}
          </div>

          {loading ? (
            <div className="space-y-3">
              {[1, 2, 3].map((i) => (
                <div key={i} className="h-24 animate-pulse rounded-panel bg-white" />
              ))}
            </div>
          ) : items.length === 0 ? (
            <div className="rounded-panel border border-dashed border-ink-900/15 bg-white p-12 text-center">
              <Inbox className="mx-auto text-ink-300" size={30} />
              <p className="mt-4 font-display text-2xl font-semibold text-forest-800">
                {filter === 'unread' ? 'Aucune notification non lue' : 'Rien pour le moment'}
              </p>
              <p className="mt-2 leading-7 text-ink-500">
                Vous serez prévenu ici à chaque étape de vos commandes.
              </p>
              <Link
                href="/products"
                className="mt-6 inline-flex rounded-full bg-forest-800 px-6 py-3.5 font-bold text-cream"
              >
                Faire le marché
              </Link>
            </div>
          ) : (
            <ul className="space-y-3">
              {items.map((notification) => {
                const orderId = notification.data?.order_id;
                const body = (
                  <div className="flex items-start gap-4">
                    <span
                      className={`mt-0.5 grid h-10 w-10 shrink-0 place-items-center rounded-full ${
                        notification.is_read ? 'bg-cream-200 text-ink-300' : 'bg-gold-500 text-forest-950'
                      }`}
                    >
                      <Bell size={17} />
                    </span>
                    <div className="min-w-0 flex-1">
                      <p className="font-display text-xl font-semibold text-forest-800">
                        {notification.title}
                      </p>
                      <p className="mt-1 leading-7 text-ink-500">{notification.message}</p>
                      <p className="mt-1.5 text-sm font-semibold text-ink-300">
                        {new Date(notification.created_at).toLocaleString('fr-FR', {
                          dateStyle: 'long',
                          timeStyle: 'short',
                        })}
                      </p>
                    </div>
                    {!notification.is_read && (
                      <span className="mt-2 h-2.5 w-2.5 shrink-0 rounded-full bg-gold-500" aria-label="Non lue" />
                    )}
                  </div>
                );

                return (
                  <li key={notification.id}>
                    {orderId ? (
                      <Link
                        href={`/orders/${orderId}`}
                        onClick={() => handleRead(notification)}
                        className={`block rounded-panel border p-5 shadow-card transition hover:-translate-y-0.5 hover:shadow-lift ${
                          notification.is_read ? 'border-ink-900/8 bg-white' : 'border-gold-500/40 bg-white'
                        }`}
                      >
                        {body}
                      </Link>
                    ) : (
                      <button
                        onClick={() => handleRead(notification)}
                        className={`block w-full rounded-panel border p-5 text-left shadow-card transition ${
                          notification.is_read ? 'border-ink-900/8 bg-white' : 'border-gold-500/40 bg-white'
                        }`}
                      >
                        {body}
                      </button>
                    )}
                  </li>
                );
              })}
            </ul>
          )}
        </div>
      </main>
      <Footer />
    </>
  );
}

export default withAuth(NotificationsPage);
