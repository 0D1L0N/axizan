'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import { useAuth } from '@/app/contexts/AuthContext';
import { notificationService } from '@/app/services';
import { Bell, Check } from 'lucide-react';

/** Rafraichissement periodique : pas de websocket dans cette version, et une
 *  minute suffit largement pour un suivi de commande. */
const POLL_MS = 60000;

function timeAgo(iso) {
  const seconds = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
  if (seconds < 60) return "à l'instant";
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `il y a ${minutes} min`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `il y a ${hours} h`;
  const days = Math.floor(hours / 24);
  return days === 1 ? 'hier' : `il y a ${days} jours`;
}

export default function NotificationBell() {
  const { user } = useAuth();
  const [open, setOpen] = useState(false);
  const [items, setItems] = useState([]);
  const [unread, setUnread] = useState(0);
  const [loading, setLoading] = useState(false);
  const panelRef = useRef(null);

  const refreshCount = useCallback(() => {
    if (!user) return;
    notificationService
      .unreadCount()
      .then((res) => setUnread(res.data.data.unread_count))
      .catch(() => {});
  }, [user]);

  useEffect(() => {
    refreshCount();
    if (!user) return undefined;
    const timer = setInterval(refreshCount, POLL_MS);
    return () => clearInterval(timer);
  }, [user, refreshCount]);

  // Fermeture au clic exterieur et a la touche Echap.
  useEffect(() => {
    if (!open) return undefined;
    const onClick = (event) => {
      if (panelRef.current && !panelRef.current.contains(event.target)) setOpen(false);
    };
    const onKey = (event) => event.key === 'Escape' && setOpen(false);
    document.addEventListener('mousedown', onClick);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onClick);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  const openPanel = () => {
    setOpen((current) => !current);
    if (open) return;
    setLoading(true);
    notificationService
      .list({ limit: 8 })
      .then((res) => {
        setItems(res.data.data);
        setUnread(res.data.meta.unread_count);
      })
      .catch(() => setItems([]))
      .finally(() => setLoading(false));
  };

  const handleMarkAll = async () => {
    await notificationService.markAllRead().catch(() => {});
    setItems((current) => current.map((item) => ({ ...item, is_read: true })));
    setUnread(0);
  };

  const handleOpenOne = async (notification) => {
    if (!notification.is_read) {
      await notificationService.markRead(notification.id).catch(() => {});
      setUnread((current) => Math.max(0, current - 1));
      setItems((current) =>
        current.map((item) => (item.id === notification.id ? { ...item, is_read: true } : item))
      );
    }
  };

  if (!user) return null;

  return (
    <div className="relative" ref={panelRef}>
      <button
        onClick={openPanel}
        aria-label={unread > 0 ? `${unread} notification(s) non lue(s)` : 'Notifications'}
        aria-expanded={open}
        className="relative grid h-11 w-11 place-items-center rounded-full border border-ink-900/8 bg-white/70 text-ink-700 transition hover:bg-cream-200"
      >
        <Bell size={18} />
        {unread > 0 && (
          <span className="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-clay-500 px-1 text-[11px] font-bold text-white ring-2 ring-cream">
            {unread > 9 ? '9+' : unread}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 z-50 mt-2 w-[22rem] overflow-hidden rounded-panel border border-ink-900/10 bg-white shadow-lift">
          <div className="flex items-center justify-between border-b border-ink-900/8 px-5 py-3.5">
            <p className="font-display text-lg font-semibold text-forest-800">Notifications</p>
            {unread > 0 && (
              <button
                onClick={handleMarkAll}
                className="inline-flex items-center gap-1.5 text-sm font-bold text-forest-600 hover:underline"
              >
                <Check size={14} /> Tout marquer lu
              </button>
            )}
          </div>

          <div className="max-h-96 overflow-y-auto">
            {loading ? (
              <p className="px-5 py-8 text-center font-semibold text-ink-300">Chargement…</p>
            ) : items.length === 0 ? (
              <p className="px-5 py-10 text-center leading-7 text-ink-500">
                Aucune notification.
                <br />
                <span className="text-sm text-ink-300">Le suivi de vos commandes apparaîtra ici.</span>
              </p>
            ) : (
              items.map((notification) => {
                const orderId = notification.data?.order_id;
                const Wrapper = orderId ? Link : 'div';
                const props = orderId ? { href: `/orders/${orderId}` } : {};

                return (
                  <Wrapper
                    key={notification.id}
                    {...props}
                    onClick={() => handleOpenOne(notification)}
                    className={`block border-b border-ink-900/5 px-5 py-4 transition last:border-0 hover:bg-cream-100 ${
                      notification.is_read ? '' : 'bg-gold-500/8'
                    }`}
                  >
                    <div className="flex items-start gap-3">
                      {!notification.is_read && (
                        <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-gold-500" aria-hidden="true" />
                      )}
                      <div className={notification.is_read ? 'pl-5' : ''}>
                        <p className="font-bold text-ink-900">{notification.title}</p>
                        <p className="mt-0.5 text-sm leading-6 text-ink-500">{notification.message}</p>
                        <p className="mt-1 text-xs font-semibold text-ink-300">
                          {timeAgo(notification.created_at)}
                        </p>
                      </div>
                    </div>
                  </Wrapper>
                );
              })
            )}
          </div>

          <Link
            href="/notifications"
            onClick={() => setOpen(false)}
            className="block border-t border-ink-900/8 px-5 py-3.5 text-center font-bold text-forest-800 transition hover:bg-cream-100"
          >
            Tout voir
          </Link>
        </div>
      )}
    </div>
  );
}
