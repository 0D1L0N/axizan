'use client';

import { useMemo, useState } from 'react';
import Header from '@/app/components/Header';
import ProductCard from '@/app/components/ProductCard';
import { BENIN_CATEGORIES, DEMO_PRODUCTS, getProductsByCategory } from '@/app/data/beninCatalog';
import { Search, SlidersHorizontal } from 'lucide-react';

export default function ProductsPage() {
  const [selectedCategory, setSelectedCategory] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [sortBy, setSortBy] = useState('popularity');

  const products = useMemo(() => {
    const normalizedSearch = searchTerm.trim().toLowerCase();
    const filtered = getProductsByCategory(selectedCategory).filter((product) => {
      if (!normalizedSearch) return true;
      return `${product.name} ${product.description}`.toLowerCase().includes(normalizedSearch);
    });

    return [...filtered].sort((a, b) => {
      if (sortBy === 'price_asc') return Number(a.base_price) - Number(b.base_price);
      if (sortBy === 'price_desc') return Number(b.base_price) - Number(a.base_price);
      if (sortBy === 'name') return a.name.localeCompare(b.name);
      return (b.popularity_score || 0) - (a.popularity_score || 0);
    });
  }, [selectedCategory, searchTerm, sortBy]);

  const selectedCategoryLabel = selectedCategory
    ? BENIN_CATEGORIES.find((category) => category.id === selectedCategory)?.name
    : 'Tout le marché';

  return (
    <>
      <Header />
      <main className="min-h-screen bg-[#FBF7EF]">
        <section className="border-b border-black/5 bg-[#12372A] text-white">
          <div className="mx-auto max-w-7xl px-4 py-12">
            <p className="text-sm font-black uppercase tracking-[0.3em] text-[#FFB000]">Catalogue</p>
            <div className="mt-4 grid gap-6 lg:grid-cols-[1fr_420px] lg:items-end">
              <div>
                <h1 className="text-5xl font-black tracking-tight md:text-6xl">Le marché complet, rangé pour commander vite.</h1>
                <p className="mt-4 max-w-2xl text-lg font-medium leading-8 text-white/75">
                  Fruits, légumes, tubercules, céréales, poissons, condiments et boissons locales. Clique une catégorie, ajoute au panier, c’est clair.
                </p>
              </div>
              <div className="rounded-[30px] bg-white/10 p-4 backdrop-blur">
                <div className="relative">
                  <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" size={19} />
                  <input
                    type="search"
                    placeholder="Rechercher : igname, atassi, piment..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="h-14 w-full rounded-2xl border border-white/15 bg-white pl-12 pr-4 font-semibold text-gray-900 outline-none ring-[#FFB000] focus:ring-4"
                  />
                </div>
                <div className="mt-3 flex items-center gap-2">
                  <SlidersHorizontal size={18} className="text-[#FFB000]" />
                  <select
                    value={sortBy}
                    onChange={(e) => setSortBy(e.target.value)}
                    className="h-12 flex-1 rounded-2xl border border-white/15 bg-white px-4 font-semibold text-gray-900 outline-none"
                  >
                    <option value="popularity">Meilleures ventes</option>
                    <option value="name">Nom A-Z</option>
                    <option value="price_asc">Prix croissant</option>
                    <option value="price_desc">Prix décroissant</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="mx-auto max-w-7xl px-4 py-8">
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <button
              onClick={() => setSelectedCategory(null)}
              className={`rounded-[26px] border p-5 text-left shadow-sm transition hover:-translate-y-1 ${
                selectedCategory === null ? 'border-[#FFB000] bg-[#FFB000] text-[#1C140D]' : 'border-black/5 bg-white text-gray-900'
              }`}
            >
              <span className="text-3xl">🧺</span>
              <span className="mt-3 block text-xl font-black">Tout</span>
              <span className="mt-1 block text-sm font-medium opacity-70">{DEMO_PRODUCTS.length} produits disponibles</span>
            </button>
            {BENIN_CATEGORIES.map((category) => (
              <button
                key={category.id}
                onClick={() => setSelectedCategory(category.id)}
                className={`group relative h-44 overflow-hidden rounded-[26px] text-left shadow-sm transition hover:-translate-y-1 ${
                  selectedCategory === category.id ? 'ring-4 ring-[#FFB000]' : ''
                }`}
              >
                <img src={category.image} alt={category.name} className="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-110" />
                <div className="absolute inset-0 bg-gradient-to-t from-black/75 via-black/10 to-transparent" />
                <div className="absolute inset-x-0 bottom-0 p-5 text-white">
                  <span className="text-2xl">{category.emoji}</span>
                  <h2 className="mt-1 text-xl font-black">{category.name}</h2>
                  <p className="mt-1 line-clamp-1 text-xs font-semibold text-white/75">{category.description}</p>
                </div>
              </button>
            ))}
          </div>
        </section>

        <section className="mx-auto max-w-7xl px-4 pb-14">
          <div className="mb-6 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
            <div>
              <p className="text-sm font-black uppercase tracking-[0.3em] text-[#D62828]">{selectedCategoryLabel}</p>
              <h2 className="mt-2 text-4xl font-black tracking-tight text-[#12372A]">{products.length} articles prêts à commander</h2>
            </div>
          </div>

          {products.length === 0 ? (
            <div className="rounded-[30px] bg-white p-10 text-center font-bold text-gray-600 shadow-sm">
              Aucun produit ne correspond à cette recherche.
            </div>
          ) : (
            <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
              {products.map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
          )}
        </section>
      </main>
    </>
  );
}
