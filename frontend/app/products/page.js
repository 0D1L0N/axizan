'use client';

import { useEffect, useState } from 'react';
import Header from '@/app/components/Header';
import Footer from '@/app/components/Footer';
import ProductCard from '@/app/components/ProductCard';
import RemoteImage from '@/app/components/RemoteImage';
import { productService } from '@/app/services';
import { Search, SlidersHorizontal } from 'lucide-react';

export default function ProductsPage() {
  const [categories, setCategories] = useState([]);
  const [products, setProducts] = useState([]);
  const [totalProducts, setTotalProducts] = useState(0);

  const [selectedCategory, setSelectedCategory] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [sortBy, setSortBy] = useState('popularity');

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    productService
      .getCategories()
      .then((res) => setCategories(res.data.data))
      .catch(() => setError("Impossible de charger les catégories"));
  }, []);

  // La recherche est debouncee : une frappe ne declenche pas une requete par caractere.
  useEffect(() => {
    const timer = setTimeout(() => {
      setLoading(true);
      const params = { limit: 60, sort: sortBy };
      if (selectedCategory) params.category = selectedCategory;
      if (searchTerm.trim()) params.search = searchTerm.trim();

      productService
        .getProducts(params)
        .then((res) => {
          setProducts(res.data.data);
          setTotalProducts(res.data.pagination?.total ?? res.data.data.length);
          setError('');
        })
        .catch(() => setError("Le catalogue n'a pas pu être chargé. L'API est-elle démarrée ?"))
        .finally(() => setLoading(false));
    }, searchTerm ? 300 : 0);

    return () => clearTimeout(timer);
  }, [selectedCategory, searchTerm, sortBy]);

  const selectedCategoryLabel = selectedCategory
    ? categories.find((category) => category.id === selectedCategory)?.name
    : 'Tout le marché';

  return (
    <>
      <Header />
      <main className="min-h-screen bg-cream">
        <section className="relative grain overflow-hidden bg-forest-900 text-cream">
          <div className="cowrie-field absolute inset-0 opacity-60" />
          <div className="relative mx-auto max-w-7xl px-4 py-14">
            <p className="eyebrow text-gold-500">Catalogue</p>
            <div className="mt-5 grid gap-8 lg:grid-cols-[1fr_420px] lg:items-end">
              <div>
                <h1 className="font-display text-display-md font-semibold">Le marché complet, rangé pour commander vite.</h1>
                <p className="mt-5 max-w-2xl text-lg leading-8 text-cream/70">
                  Fruits, légumes, tubercules, céréales, poissons, condiments et boissons locales. Choisissez une catégorie, ajoutez au panier, c’est tout.
                </p>
              </div>
              <div className="rounded-panel border border-cream/12 bg-cream/8 p-4 backdrop-blur">
                <div className="relative">
                  <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-ink-300" size={19} />
                  <input
                    type="search"
                    placeholder="Rechercher : igname, gombo, piment..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="h-14 w-full rounded-card border-none bg-cream pl-12 pr-4 font-semibold text-ink-900 outline-none placeholder:text-ink-300"
                  />
                </div>
                <div className="mt-3 flex items-center gap-2">
                  <SlidersHorizontal size={18} className="text-gold-500" />
                  <select
                    value={sortBy}
                    onChange={(e) => setSortBy(e.target.value)}
                    className="h-12 flex-1 rounded-card border-none bg-cream px-4 font-semibold text-ink-900 outline-none"
                  >
                    <option value="popularity">Meilleures ventes</option>
                    <option value="newest">Nouveautés</option>
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
              className={`rounded-panel border p-5 text-left shadow-card card-lift ${
                selectedCategory === null ? 'border-gold-500 bg-gold-500 text-forest-950' : 'border-ink-900/8 bg-white text-ink-900'
              }`}
            >
              <span className="text-3xl">🧺</span>
              <span className="mt-3 block font-display text-2xl font-semibold">Tout</span>
              <span className="mt-1 block text-sm font-medium opacity-70">{totalProducts} produits disponibles</span>
            </button>
            {categories.map((category) => (
              <button
                key={category.id}
                onClick={() => setSelectedCategory(category.id)}
                className={`group relative h-44 overflow-hidden rounded-panel text-left shadow-card card-lift ${
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
                  <h2 className="mt-1 font-display text-xl font-semibold">{category.name}</h2>
                  <p className="mt-1 line-clamp-1 text-xs font-medium text-cream/70">{category.description}</p>
                </div>
              </button>
            ))}
          </div>
        </section>

        <section className="mx-auto max-w-7xl px-4 pb-14">
          <div className="mb-6 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
            <div>
              <p className="eyebrow text-clay-500">{selectedCategoryLabel}</p>
              <h2 className="mt-3 font-display text-display-sm font-semibold text-forest-800">
                {loading ? 'Chargement…' : `${products.length} articles prêts à commander`}
              </h2>
            </div>
          </div>

          {error ? (
            <div className="rounded-panel bg-white p-10 text-center font-semibold text-clay-600 shadow-card">{error}</div>
          ) : loading ? (
            <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
              {[1, 2, 3].map((i) => (
                <div key={i} className="h-96 animate-pulse rounded-panel bg-white/70" />
              ))}
            </div>
          ) : products.length === 0 ? (
            <div className="rounded-panel bg-white p-10 text-center font-semibold text-ink-500 shadow-card">
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
      <Footer />
    </>
  );
}
