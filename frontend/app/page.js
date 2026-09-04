'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import ProductCard from '@/app/components/ProductCard';
import Header from '@/app/components/Header';
import Footer from '@/app/components/Footer';
import { LogoMark } from '@/app/components/Logo';
import RemoteImage from '@/app/components/RemoteImage';
import { AppliqueField, PagneBand } from '@/app/components/Motifs';
import { RECIPE_MENUS } from '@/app/data/beninCatalog';
import { productService } from '@/app/services';
import { ArrowRight, MapPin } from 'lucide-react';

/**
 * Les trois engagements, sous le titre.
 *
 * Un ton pose et factuel : chacun avance une information verifiable — le delai
 * et l'amplitude horaire, qui choisit, ce qu'il advient du stock. Les
 * formulations parlees et les guillemets ironiques ont ete retires : ils
 * faisaient dire a la marque autre chose que ce qu'elle vaut.
 */
const PROOF = [
  ['Livraison dès 1 heure', 'Vous fixez le créneau, à l’heure près, de 8 h à 19 h.'],
  ['Sélection à la source', 'Chaque article choisi étal par étal, par un acheteur formé.'],
  ['Achat du jour', 'Aucun entrepôt, aucun stock. Tout est acheté le jour de votre livraison.'],
];

/**
 * Ce qu'un acheteur verifie, range PAR SENS.
 *
 * L'ordre n'est pas decoratif : bien acheter au marche est un geste sensoriel,
 * et c'est exactement ce qui ne se delegue pas a une photo de catalogue. Le
 * classement par sens dit la competence mieux que ne le ferait le mot
 * « qualite » repete quatre fois.
 */
const CHECKS = [
  ['I', 'À l’œil', 'Le poisson', 'La pupille claire et bombée, les ouïes d’un rouge franc. Un œil terne a déjà trop attendu.'],
  ['II', 'Au nez', 'L’huile rouge', 'Non coupée. L’odeur de palme, la couleur dense, le dépôt qui repose au fond du bidon.'],
  ['III', 'À la main', 'Les tomates', 'Fermes sous le pouce, sans meurtrissure. Choisies pour tenir jusqu’à la cuisson.'],
  ['IV', 'À l’oreille', 'L’igname', 'Le son creux à la percussion. Ni piqûre, ni collet abîmé.'],
];

const STEPS = [
  ['01', 'Vous composez', 'Article par article, ou un panier-recette entier pour un plat précis.'],
  ['02', 'On va au marché', 'Le jour de votre livraison. On choisit chez nos fournisseurs partenaires, étal par étal.'],
  ['03', 'Vous recevez', 'Dans le créneau que vous avez choisi. Photo du panier, photo à la remise.'],
];

export default function HomePage() {
  const [categories, setCategories] = useState([]);
  const [selectedCategory, setSelectedCategory] = useState(null);
  const [visibleProducts, setVisibleProducts] = useState([]);
  const [tickerItems, setTickerItems] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    productService
      .getCategories()
      .then((res) => {
        const list = res.data.data;
        setCategories(list);
        if (list.length > 0) setSelectedCategory(list[0].id);
      })
      .catch(() => setLoading(false));

    // Le bandeau defilant se nourrit du vrai catalogue : il change quand le
    // catalogue change, et il prouve au passage que le stock affiche est reel.
    productService
      .getProducts({ limit: 18, sort: 'popularity' })
      .then((res) => setTickerItems(res.data.data.map((product) => product.name)))
      .catch(() => setTickerItems([]));
  }, []);

  useEffect(() => {
    if (!selectedCategory) return;
    setLoading(true);
    productService
      .getProducts({ category: selectedCategory, limit: 6 })
      .then((res) => setVisibleProducts(res.data.data))
      .catch(() => setVisibleProducts([]))
      .finally(() => setLoading(false));
  }, [selectedCategory]);

  const activeCategory = categories.find((category) => category.id === selectedCategory);
  const ticker = tickerItems.length > 0 ? tickerItems : ['Igname de Dassa', 'Gombo frais', 'Huile rouge', 'Tilapia frais'];

  return (
    <>
      <Header />
      <main className="overflow-hidden bg-cream">
        {/* ---------- Hero ---------- */}
        <section className="relative grain overflow-hidden bg-forest-900 text-cream">
          <div className="cowrie-field absolute inset-0 opacity-70" />
          <div className="absolute -left-40 -top-40 h-[520px] w-[520px] rounded-full bg-gold-500/12 blur-3xl" />

          <div className="relative mx-auto grid max-w-7xl gap-12 px-4 py-16 lg:grid-cols-[1.05fr_0.95fr] lg:items-center lg:py-24">
            <div className="max-w-2xl animate-rise-in">
              <div className="inline-flex items-center gap-2 rounded-full border border-cream/20 bg-cream/8 px-4 py-2 text-sm font-semibold backdrop-blur">
                <MapPin size={15} className="text-gold-500" />
                Cotonou · Abomey-Calavi · Porto-Novo
              </div>

              <h1 className="mt-7 font-display text-display-lg font-semibold">
                On fait votre marché,
                <span className="block text-gold-500">vous cuisinez.</span>
              </h1>

              <p className="mt-7 max-w-xl text-lg leading-8 text-cream/75">
                Nos acheteurs choisissent vos produits au marché le jour de la livraison, avec l’œil de
                quelqu’un qui sait reconnaître le bon. Faites-vous livrer dès&nbsp;1&nbsp;heure, au
                créneau que vous choisissez.
              </p>

              <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                <Link
                  href="/products"
                  className="inline-flex items-center justify-center gap-2 rounded-full bg-gold-500 px-7 py-4 font-bold text-forest-950 shadow-glow transition hover:bg-gold-400"
                >
                  Voir le marché <ArrowRight size={18} />
                </Link>
                <Link
                  href="/menus"
                  className="inline-flex items-center justify-center gap-2 rounded-full border border-cream/25 px-7 py-4 font-bold text-cream backdrop-blur transition hover:bg-cream/10"
                >
                  Composer par plat
                </Link>
              </div>

              {/*
                Un bandeau typographique, plus des cartes.

                Trois boîtes identiques avec une icône chacune, c'est la mise
                en page que produit n'importe quel gabarit : elle range
                l'information sans la hiérarchiser, et l'icône ne dit rien que
                le titre ne dise déjà. Ici ce sont des filets — un trait d'or
                court en tête, une règle fine entre les colonnes — et c'est la
                typographie qui porte. Moins d'éléments, plus de tenue.
              */}
              <dl className="mt-12 grid gap-y-8 border-t border-cream/15 pt-9 sm:grid-cols-3 sm:gap-x-10">
                {PROOF.map(([title, text], index) => (
                  <div
                    key={title}
                    className={index > 0 ? 'sm:border-l sm:border-cream/15 sm:pl-10' : undefined}
                  >
                    <span aria-hidden="true" className="block h-px w-8 bg-gold-500" />
                    <dt className="mt-5 font-display text-lg font-semibold leading-snug">{title}</dt>
                    <dd className="mt-2 text-sm leading-6 text-cream/60">{text}</dd>
                  </div>
                ))}
              </dl>
            </div>

            {/* Image calee dans un cadre or decale : le marche encadre, pas un stock photo pose la */}
            <div className="relative mx-auto w-full max-w-xl lg:mx-0">
              <div className="absolute -inset-3 rotate-2 rounded-hero border-2 border-gold-500/45" aria-hidden="true" />
              {/* priority : c'est la plus grande image de la premiere vue, la
                  differer retarderait l'affichage utile. */}
              <div className="relative h-[420px] w-full overflow-hidden rounded-hero shadow-lift lg:h-[540px]">
                <RemoteImage
                  src="https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=1600&q=90"
                  alt="Étal de marché couvert de fruits et légumes frais"
                  fill
                  priority
                  sizes="(max-width: 1024px) 100vw, 45vw"
                  className="object-cover"
                />
              </div>
              <div className="relative -mt-14 ml-5 mr-5 rounded-panel bg-cream p-5 shadow-lift">
                <p className="eyebrow text-clay-500">Panier populaire</p>
                <div className="mt-2.5 flex items-center justify-between gap-4">
                  <div>
                    <h2 className="font-display text-2xl font-semibold text-forest-800">Atassi complet</h2>
                    <p className="mt-1 text-sm text-ink-500">Riz, haricot, poisson fumé, huile rouge, piment.</p>
                  </div>
                  <Link
                    href="/menus"
                    className="shrink-0 rounded-full bg-forest-800 px-5 py-3 text-sm font-bold text-cream transition hover:bg-forest-700"
                  >
                    Voir
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* ---------- Bandeau defilant ---------- */}
        <div className="overflow-hidden border-y border-forest-900/12 bg-gold-500 py-3.5">
          <div className="marquee-track whitespace-nowrap">
            {[0, 1].map((copy) => (
              <span key={copy} className="flex shrink-0 items-center" aria-hidden={copy === 1}>
                {ticker.map((name, index) => (
                  <span key={`${copy}-${name}-${index}`} className="flex items-center">
                    <span className="px-5 font-display text-lg font-semibold text-forest-950">{name}</span>
                    <LogoMark size={14} tile={false} />
                  </span>
                ))}
              </span>
            ))}
          </div>
        </div>

        {/* ---------- Le savoir-faire d'achat ---------- */}
        <section className="relative grain overflow-hidden bg-forest-950 py-20 text-cream">
          <AppliqueField opacity={0.6} />
          <div className="relative mx-auto max-w-7xl px-4">
            {/*
              Deux colonnes : la thèse tient à gauche et reste lisible seule,
              l'index des sens se déroule à droite. Sur téléphone elles
              s'empilent — la thèse d'abord, ce qui est le bon ordre de lecture.
            */}
            <div className="grid gap-12 lg:grid-cols-[minmax(0,26rem)_minmax(0,1fr)] lg:gap-20">
              {/* Pas de `sticky` ici : la colonne dépasse maintenant la hauteur
                  d'un écran, et une colonne collante plus haute que la fenêtre
                  rend son propre bas inatteignable. */}
              <div>
                <p className="eyebrow text-gold-500">Nos préparateurs</p>
                <h2 className="mt-5 font-display text-display-sm font-semibold">
                  Bien acheter,
                  <span className="block text-gold-500">c’est un métier.</span>
                </h2>
                <p className="mt-7 text-lg leading-8 text-cream/70">
                  N’importe qui peut rapporter un poisson. Rapporter le bon demande un œil exercé.
                  Nos préparateurs sont des acheteurs confirmés&nbsp;: ils savent quoi regarder, et
                  surtout quoi écarter.
                </p>

                <p className="mt-7 border-l-2 border-gold-500 pl-5 leading-8 text-cream/70">
                  Si un article n’est pas au niveau, il n’entre pas dans votre panier. Vous avez dit
                  à la commande s’il fallait le remplacer ou le retirer&nbsp;— c’est votre consigne
                  qui tranche, pas la nôtre.
                </p>

                {/*
                  Photographie d'ambiance, PAS un portrait de l'équipe.

                  Le crédit sous l'image n'est pas de la coquetterie éditoriale :
                  il dit ce que l'image est. Une photo achetée présentée comme
                  « notre préparatrice » serait une affirmation fausse sur une
                  personne réelle, et le premier client qui la reconnaîtrait
                  ailleurs emporterait la crédibilité de toute la page.

                  À remplacer par une vraie photo de vos préparatrices dès que
                  vous en avez : c'est la seule image que personne d'autre ne
                  peut avoir.
                */}
                <figure className="mt-10">
                  <div className="relative aspect-[4/3] overflow-hidden rounded-panel">
                    <RemoteImage
                      src="https://images.unsplash.com/photo-1575303093127-18b3c4ef8c41?auto=format&fit=crop&w=1200&q=90"
                      alt="Vendeuse de piment derrière son étal, sur un marché ouest-africain"
                      fill
                      sizes="(max-width: 1024px) 100vw, 26rem"
                      className="object-cover"
                    />
                    {/* Voile chaud : la photo entre dans le vert profond de la
                        section au lieu de flotter dessus comme une vignette. */}
                    <div
                      aria-hidden="true"
                      className="absolute inset-0 bg-gradient-to-t from-forest-950/70 via-forest-950/10 to-transparent"
                    />
                  </div>
                  <figcaption className="mt-3 text-xs leading-5 text-cream/40">
                    Marché ouest-africain — photographie Omotayo Tajudeen
                  </figcaption>
                </figure>
              </div>

              {/*
                Un index, pas des cartes. Le chiffre romain et le sens portent
                l'information — on n'achète pas avec les yeux seulement — et le
                filet horizontal suffit à séparer : quatre boîtes identiques
                auraient aplati la hiérarchie.
              */}
              <dl className="divide-y divide-cream/12 border-y border-cream/12">
                {CHECKS.map(([numeral, sense, item, check]) => (
                  <div
                    key={item}
                    className="group grid gap-x-8 gap-y-3 py-8 sm:grid-cols-[4.5rem_minmax(0,1fr)]"
                  >
                    <div className="flex items-baseline gap-3 sm:flex-col sm:gap-1.5">
                      <span className="font-display text-2xl font-semibold text-gold-500/45 transition group-hover:text-gold-500">
                        {numeral}
                      </span>
                      <span className="eyebrow text-cream/40">{sense}</span>
                    </div>

                    <div>
                      <dt className="font-display text-3xl font-semibold leading-tight">{item}</dt>
                      <dd className="mt-2.5 max-w-xl text-lg leading-8 text-cream/65">{check}</dd>
                    </div>
                  </div>
                ))}
              </dl>
            </div>
          </div>
        </section>

        <PagneBand />

        {/* ---------- Comment ca marche ---------- */}
        <section className="mx-auto max-w-7xl px-4 py-20">
          <div className="max-w-2xl">
            <p className="eyebrow text-clay-500">Comment ça marche</p>
            <h2 className="mt-4 font-display text-display-sm font-semibold text-forest-800">
              Nous n’avons pas de stock. C’est tout l’intérêt.
            </h2>
            <p className="mt-4 text-lg leading-8 text-ink-500">
              Rien n’attend dans un entrepôt : tout est acheté le jour même, sur le marché, à votre nom.
            </p>
          </div>

          <ol className="mt-12 grid gap-6 md:grid-cols-3">
            {STEPS.map(([number, title, text]) => (
              <li key={number} className="relative rounded-panel border border-ink-900/8 bg-white p-7 shadow-card">
                <span className="font-display text-5xl font-semibold text-cream-300">{number}</span>
                <h3 className="mt-4 font-display text-2xl font-semibold text-forest-800">{title}</h3>
                <p className="mt-2.5 leading-7 text-ink-500">{text}</p>
              </li>
            ))}
          </ol>
        </section>

        {/* ---------- Categories ---------- */}
        <section className="mx-auto max-w-7xl px-4 pb-20">
          <div className="mb-9 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
              <p className="eyebrow text-clay-500">Catégories</p>
              <h2 className="mt-4 font-display text-display-sm font-semibold text-forest-800">
                Ce qu’on consomme vraiment
              </h2>
            </div>
            <Link href="/products" className="inline-flex items-center gap-2 font-bold text-forest-800 hover:underline">
              Tout le catalogue <ArrowRight size={18} />
            </Link>
          </div>

          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {categories.map((category) => (
              <button
                key={category.id}
                onClick={() => setSelectedCategory(category.id)}
                className={`group relative h-56 overflow-hidden rounded-panel text-left shadow-card card-lift ${
                  selectedCategory === category.id ? 'ring-2 ring-gold-500 ring-offset-4 ring-offset-cream' : ''
                }`}
              >
                <RemoteImage
                  src={category.image_url}
                  alt={category.name}
                  fill
                  sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 25vw"
                  className="object-cover transition duration-700 group-hover:scale-105"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-forest-950 via-forest-950/25 to-transparent" />
                <div className="absolute inset-x-0 bottom-0 p-5 text-cream">
                  <span className="text-2xl">{category.icon_emoji}</span>
                  <h3 className="mt-2 font-display text-2xl font-semibold">{category.name}</h3>
                  <p className="mt-1 line-clamp-2 text-sm text-cream/70">{category.description}</p>
                </div>
              </button>
            ))}
          </div>
        </section>

        {/* ---------- Selection ---------- */}
        <section className="mx-auto max-w-7xl px-4 pb-20">
          <div className="mb-8">
            <p className="eyebrow text-clay-500">{activeCategory?.name}</p>
            <h2 className="mt-4 font-display text-display-sm font-semibold text-forest-800">Sélection du marché</h2>
          </div>
          {loading ? (
            <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
              {[1, 2, 3].map((i) => (
                <div key={i} className="h-96 animate-pulse rounded-panel bg-white/70" />
              ))}
            </div>
          ) : visibleProducts.length === 0 ? (
            <div className="rounded-panel bg-white p-10 text-center font-semibold text-ink-500 shadow-card">
              Catalogue indisponible pour le moment.
            </div>
          ) : (
            <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
              {visibleProducts.map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
          )}
        </section>

        <PagneBand />

        {/* ---------- Paniers-recettes ---------- */}
        <section className="relative grain bg-forest-800 py-20 text-cream">
          <AppliqueField opacity={0.55} />
          <div className="relative mx-auto max-w-7xl px-4">
            <div className="mb-10 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
              <div className="max-w-xl">
                <p className="eyebrow text-gold-500">Paniers-recettes</p>
                <h2 className="mt-4 font-display text-display-sm font-semibold">
                  Choisissez un plat, on réunit les ingrédients.
                </h2>
              </div>
              <Link
                href="/menus"
                className="inline-flex items-center gap-2 rounded-full bg-cream px-6 py-3.5 font-bold text-forest-800 transition hover:bg-gold-500"
              >
                Voir les plats <ArrowRight size={18} />
              </Link>
            </div>

            <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
              {RECIPE_MENUS.map((menu) => (
                <Link
                  key={menu.id}
                  href="/menus"
                  className="group overflow-hidden rounded-panel bg-cream/8 ring-1 ring-cream/12 transition duration-300 hover:-translate-y-1 hover:bg-cream/14"
                >
                  <div className="relative h-44 w-full overflow-hidden">
                    <RemoteImage
                      src={menu.image}
                      alt={menu.title}
                      fill
                      sizes="(max-width: 768px) 100vw, 25vw"
                      className="object-cover transition duration-700 group-hover:scale-105"
                    />
                  </div>
                  <div className="p-5">
                    <p className="eyebrow text-gold-500">{menu.time} · {menu.serves}</p>
                    <h3 className="mt-2.5 font-display text-2xl font-semibold">{menu.title}</h3>
                    <p className="mt-2 line-clamp-2 text-sm leading-6 text-cream/65">{menu.subtitle}</p>
                  </div>
                </Link>
              ))}
            </div>
          </div>
        </section>
      </main>
      <Footer />
    </>
  );
}
