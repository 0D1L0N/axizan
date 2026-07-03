'use client';

import Link from 'next/link';
import { useMemo, useState } from 'react';
import ProductCard from '@/app/components/ProductCard';
import Header from '@/app/components/Header';
import { BENIN_CATEGORIES, DEMO_PRODUCTS, RECIPE_MENUS, getProductsByCategory } from '@/app/data/beninCatalog';
import { ArrowRight, Clock, MapPin, ShieldCheck, Sparkles } from 'lucide-react';

export default function HomePage() {
  const [selectedCategory, setSelectedCategory] = useState('fruits');

  const visibleProducts = useMemo(() => getProductsByCategory(selectedCategory).slice(0, 6), [selectedCategory]);
  const activeCategory = BENIN_CATEGORIES.find((category) => category.id === selectedCategory);

  return (
    <>
      <Header />
      <main className="overflow-hidden bg-[#FBF7EF]">
        <section className="relative">
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(255,176,0,0.28),_transparent_34%),linear-gradient(135deg,_#12372A_0%,_#205B43_52%,_#F4A261_100%)]" />
          <div className="relative mx-auto grid min-h-[calc(100vh-76px)] max-w-7xl gap-10 px-4 py-10 lg:grid-cols-[1.03fr_0.97fr] lg:items-center">
            <div className="max-w-2xl text-white">
              <div className="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/12 px-4 py-2 text-sm font-bold backdrop-blur">
                <MapPin size={16} /> Cotonou, Abomey-Calavi, Porto-Novo
              </div>
              <h1 className="mt-6 text-5xl font-black leading-[0.96] tracking-tight md:text-7xl">
                Ton marché du Bénin, livré sans détour.
              </h1>
              <p className="mt-6 max-w-xl text-lg font-medium leading-8 text-white/82">
                Fruits, tubercules, condiments, poissons, céréales et paniers-recettes pour cuisiner les plats que les clients connaissent vraiment.
              </p>
              <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                <Link href="/products" className="inline-flex items-center justify-center gap-2 rounded-full bg-[#FFB000] px-6 py-4 font-black text-[#1C140D] shadow-xl shadow-black/15 hover:bg-[#f3a300]">
                  Voir le marché <ArrowRight size={18} />
                </Link>
                <Link href="/menus" className="inline-flex items-center justify-center gap-2 rounded-full border border-white/30 bg-white/12 px-6 py-4 font-black text-white backdrop-blur hover:bg-white/20">
                  Composer par plat
                </Link>
              </div>
              <div className="mt-10 grid gap-3 sm:grid-cols-3">
                {[
                  ['35 min', 'livraison moyenne', Clock],
                  ['Produits vérifiés', 'marchands locaux', ShieldCheck],
                  ['Paniers recettes', 'ingrédients complets', Sparkles],
                ].map(([title, text, Icon]) => (
                  <div key={title} className="rounded-3xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                    <Icon size={20} className="text-[#FFB000]" />
                    <p className="mt-3 text-lg font-black">{title}</p>
                    <p className="text-sm text-white/70">{text}</p>
                  </div>
                ))}
              </div>
            </div>

            <div className="relative min-h-[560px]">
              <img
                src="https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=1600&q=90"
                alt="Étal de marché avec fruits et légumes frais"
                className="absolute inset-0 h-full w-full rounded-[42px] object-cover shadow-2xl shadow-black/25"
              />
              <div className="absolute inset-0 rounded-[42px] bg-gradient-to-t from-black/50 via-transparent to-transparent" />
              <div className="absolute bottom-5 left-5 right-5 rounded-[30px] bg-white/94 p-5 shadow-xl backdrop-blur">
                <p className="text-xs font-black uppercase tracking-[0.24em] text-[#D62828]">Panier populaire</p>
                <div className="mt-3 flex items-center justify-between gap-4">
                  <div>
                    <h2 className="text-2xl font-black text-[#12372A]">Atassi complet</h2>
                    <p className="mt-1 text-sm text-gray-600">Riz, haricot, poisson fumé, huile rouge, piment.</p>
                  </div>
                  <Link href="/menus" className="shrink-0 rounded-full bg-[#12372A] px-4 py-3 text-sm font-black text-white">
                    Voir
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="mx-auto max-w-7xl px-4 py-14">
          <div className="mb-8 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
              <p className="text-sm font-black uppercase tracking-[0.3em] text-[#D62828]">Catégories</p>
              <h2 className="mt-3 text-4xl font-black tracking-tight text-[#12372A]">Ce qu’on consomme vraiment</h2>
            </div>
            <Link href="/products" className="inline-flex items-center gap-2 font-black text-[#12372A]">
              Tout le catalogue <ArrowRight size={18} />
            </Link>
          </div>

          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {BENIN_CATEGORIES.map((category) => (
              <button
                key={category.id}
                onClick={() => setSelectedCategory(category.id)}
                className={`group relative h-52 overflow-hidden rounded-[30px] text-left shadow-sm transition hover:-translate-y-1 hover:shadow-xl ${
                  selectedCategory === category.id ? 'ring-4 ring-[#FFB000]' : ''
                }`}
              >
                <img src={category.image} alt={category.name} className="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-110" />
                <div className="absolute inset-0 bg-gradient-to-t from-black/78 via-black/18 to-transparent" />
                <div className="absolute inset-x-0 bottom-0 p-5 text-white">
                  <span className="text-3xl">{category.emoji}</span>
                  <h3 className="mt-2 text-2xl font-black">{category.name}</h3>
                  <p className="mt-1 line-clamp-2 text-sm font-medium text-white/82">{category.description}</p>
                </div>
              </button>
            ))}
          </div>
        </section>

        <section className="mx-auto max-w-7xl px-4 pb-14">
          <div className="mb-7 flex items-end justify-between gap-4">
            <div>
              <p className="text-sm font-black uppercase tracking-[0.3em] text-[#D62828]">{activeCategory?.name}</p>
              <h2 className="mt-2 text-4xl font-black tracking-tight text-[#12372A]">Sélection du marché</h2>
            </div>
          </div>
          <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            {visibleProducts.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        </section>

        <section className="bg-[#12372A] py-14 text-white">
          <div className="mx-auto max-w-7xl px-4">
            <div className="mb-8 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
              <div>
                <p className="text-sm font-black uppercase tracking-[0.3em] text-[#FFB000]">Paniers-recettes</p>
                <h2 className="mt-3 text-4xl font-black tracking-tight">Choisis un plat, on prépare les ingrédients.</h2>
              </div>
              <Link href="/menus" className="inline-flex items-center gap-2 rounded-full bg-white px-5 py-3 font-black text-[#12372A]">
                Voir les plats <ArrowRight size={18} />
              </Link>
            </div>
            <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
              {RECIPE_MENUS.map((menu) => (
                <Link key={menu.id} href="/menus" className="group overflow-hidden rounded-[30px] bg-white/10 shadow-sm transition hover:-translate-y-1 hover:bg-white/15">
                  <img src={menu.image} alt={menu.title} className="h-44 w-full object-cover transition duration-700 group-hover:scale-105" />
                  <div className="p-5">
                    <p className="text-xs font-black uppercase tracking-[0.22em] text-[#FFB000]">{menu.time} · {menu.serves}</p>
                    <h3 className="mt-2 text-2xl font-black">{menu.title}</h3>
                    <p className="mt-2 line-clamp-2 text-sm text-white/70">{menu.subtitle}</p>
                  </div>
                </Link>
              ))}
            </div>
          </div>
        </section>
      </main>
    </>
  );
}
