'use client';

import { useEffect, useState } from 'react';
import { distanceKm, formatDistance, mapsLink, positionAge } from '@/app/lib/geo';
import { MapPin, Navigation, Phone, Radio } from 'lucide-react';

/**
 * Suivi du livreur, côté client.
 *
 * Volontairement pas de carte embarquée : les tuiles se paient au mégaoctet
 * sur un forfait béninois, et le téléphone a déjà une application de cartes
 * qui fait mieux — itinéraire, zoom, position propre. On donne donc le point,
 * la distance et un lien qui ouvre Maps.
 *
 * La fraîcheur de la position est affichée sans détour : un point d'il y a
 * vingt minutes n'est plus une position, et le dire évite d'attendre devant
 * sa porte pour rien.
 */
export default function LivreurTracker({ order, onRefresh }) {
  const delivery = order.delivery;
  const [, setTick] = useState(0);

  // Rafraîchit l'affichage de l'ancienneté sans rappeler le serveur.
  useEffect(() => {
    const timer = setInterval(() => setTick((t) => t + 1), 30000);
    return () => clearInterval(timer);
  }, []);

  // Le suivi n'a de sens que pendant le trajet.
  if (!delivery || order.status !== 'in_delivery') return null;

  const lat = delivery.current_latitude;
  const lon = delivery.current_longitude;
  const hasPosition = lat != null && lon != null;

  const age = positionAge(delivery.last_location_update);
  const address = order.delivery_address;
  const km = hasPosition ? distanceKm(lat, lon, address?.latitude, address?.longitude) : null;

  return (
    <section className="overflow-hidden rounded-panel border-2 border-forest-600/40 bg-white shadow-card">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-ink-900/8 bg-forest-500/8 px-6 py-4">
        <div>
          <p className="eyebrow text-clay-500">En route</p>
          <h2 className="mt-2 font-display text-2xl font-semibold text-forest-800">
            {delivery.livreur?.name || 'Votre livreur'} arrive
          </h2>
        </div>
        <span
          className={`inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-bold ${
            age.stale ? 'bg-cream-200 text-ink-500' : 'bg-forest-600 text-cream'
          }`}
        >
          <Radio size={15} className={age.stale ? '' : 'animate-pulse'} />
          {age.label}
        </span>
      </div>

      <div className="space-y-4 p-6">
        {hasPosition ? (
          <>
            {km != null && (
              <p className="text-lg leading-8 text-ink-700">
                Il est à environ <strong className="text-forest-800">{formatDistance(km)}</strong> de
                chez vous, à vol d’oiseau.
              </p>
            )}

            {age.stale && (
              <p className="rounded-card bg-gold-500/15 px-4 py-3 text-sm leading-6 text-ink-700">
                Cette position date un peu — le réseau a pu couper. Appelez-le si vous l’attendez.
              </p>
            )}

            <div className="flex flex-col gap-2.5 sm:flex-row">
              <a
                href={mapsLink(lat, lon, delivery.livreur?.name || 'Livreur Axizán')}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex h-14 flex-1 items-center justify-center gap-2 rounded-full bg-forest-800 font-bold text-cream transition hover:bg-forest-700"
              >
                <Navigation size={18} /> Voir sur la carte
              </a>
              {delivery.livreur?.phone && (
                <a
                  href={`tel:${delivery.livreur.phone}`}
                  className="inline-flex h-14 items-center justify-center gap-2 rounded-full border-2 border-ink-900/12 px-6 font-bold text-ink-900 transition hover:bg-cream-100"
                >
                  <Phone size={18} /> Appeler
                </a>
              )}
            </div>
          </>
        ) : (
          <p className="flex items-start gap-2.5 leading-7 text-ink-500">
            <MapPin size={19} className="mt-0.5 shrink-0 text-ink-300" />
            Votre livreur est parti, mais sa position n’est pas encore remontée. Elle apparaîtra ici
            dès qu’il l’aura partagée.
          </p>
        )}

        {onRefresh && (
          <button onClick={onRefresh} className="text-sm font-bold text-forest-800 underline">
            Actualiser
          </button>
        )}
      </div>
    </section>
  );
}
