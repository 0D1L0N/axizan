'use client';

import Link from 'next/link';
import { LogoMark, Wordmark } from '@/app/components/Logo';
import { AppliqueField, PagneBand } from '@/app/components/Motifs';
import { Clock, Mail, MapPin, MessageCircle, Phone } from 'lucide-react';
import { SUPPORT_EMAIL, SUPPORT_PHONE, whatsappLink } from '@/app/lib/contact';

const NAV = [
  {
    title: 'Le marché',
    links: [
      ['Tout le catalogue', '/products'],
      ['Paniers-recettes', '/menus'],
      ['Mon panier', '/cart'],
      ['Mes commandes', '/orders'],
    ],
  },
  {
    title: 'Mon compte',
    links: [
      ['Se connecter', '/login'],
      ['Créer un compte', '/register'],
    ],
  },
];

const ZONES = ['Cotonou', 'Abomey-Calavi', 'Porto-Novo'];

export default function Footer() {
  return (
    <footer className="relative grain overflow-hidden bg-forest-950 text-cream">
      <PagneBand />
      <AppliqueField opacity={0.55} />

      <div className="relative mx-auto max-w-7xl px-4 pt-16">
        <div className="grid gap-12 border-b border-cream/10 pb-14 lg:grid-cols-[1.4fr_1fr_1fr_1.1fr]">
          <div>
            <div className="flex items-center gap-3">
              <LogoMark size={52} />
              <Wordmark tone="light" className="text-4xl uppercase" />
            </div>
            <p className="mt-6 max-w-sm leading-8 text-cream/65">
              Nos acheteurs choisissent vos produits au marché le jour de la livraison, et vous les
              recevez dans le créneau d’une heure que vous choisissez.
            </p>
            <div className="mt-7 flex flex-wrap gap-2">
              {ZONES.map((zone) => (
                <span
                  key={zone}
                  className="inline-flex items-center gap-1.5 rounded-full border border-cream/15 bg-cream/8 px-3.5 py-1.5 text-sm font-semibold"
                >
                  <MapPin size={14} className="text-gold-500" />
                  {zone}
                </span>
              ))}
            </div>
          </div>

          {NAV.map((column) => (
            <div key={column.title}>
              <h2 className="eyebrow text-gold-500">{column.title}</h2>
              <ul className="mt-6 space-y-3.5">
                {column.links.map(([label, href]) => (
                  <li key={href}>
                    <Link href={href} className="font-semibold text-cream/70 transition hover:text-gold-500">
                      {label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ))}

          <div>
            <h2 className="eyebrow text-gold-500">Nous joindre</h2>
            <ul className="mt-6 space-y-3.5 text-cream/70">
              <li className="flex items-center gap-2.5">
                <MessageCircle size={16} className="shrink-0 text-gold-500" />
                <a
                  href={whatsappLink('Bonjour Axizán, j’ai une question.')}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="font-semibold transition hover:text-gold-500"
                >
                  WhatsApp
                </a>
              </li>
              <li className="flex items-center gap-2.5">
                <Phone size={16} className="shrink-0 text-gold-500" />
                <a href={`tel:${SUPPORT_PHONE}`} className="font-semibold transition hover:text-gold-500">
                  {SUPPORT_PHONE}
                </a>
              </li>
              <li className="flex items-center gap-2.5">
                <Mail size={16} className="shrink-0 text-gold-500" />
                <a href={`mailto:${SUPPORT_EMAIL}`} className="font-semibold transition hover:text-gold-500">
                  {SUPPORT_EMAIL}
                </a>
              </li>
              <li className="flex items-center gap-2.5">
                <Clock size={16} className="shrink-0 text-gold-500" />
                <span className="font-semibold">Créneaux d’1 h, 7 j/7</span>
              </li>
            </ul>
          </div>
        </div>

        <div className="flex flex-col gap-3 py-7 text-sm font-medium text-cream/45 md:flex-row md:items-center md:justify-between">
          <p>© {new Date().getFullYear()} Axizán · Cotonou, Bénin</p>
          <p>Paiement validé manuellement · Mobile Money bientôt disponible</p>
        </div>
      </div>

      {/*
        Signature de bas de page : le nom en tres grand, rogne par le bas.
        Le cauri remplace l'accent aigu du "a" — la marque signe avec son signe.
      */}
      <div className="relative select-none overflow-hidden" aria-hidden="true">
        <p className="-mb-[0.17em] whitespace-nowrap text-center font-display text-[19vw] font-semibold leading-[0.8] tracking-[-0.05em] text-cream/10">
          AXIZ<span className="relative inline-block">Á</span>N
        </p>
      </div>
    </footer>
  );
}
