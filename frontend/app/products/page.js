'use client';

import { useEffect, useState } from 'react';
import Header from '@/app/components/Header';
import ProductCard from '@/app/components/ProductCard';
import { productService } from '@/app/services';

export default function ProductsPage() {
  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [selectedCategory, setSelectedCategory] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadContent();
  }, [selectedCategory]);

  const loadContent = async () => {
    setLoading(true);
    try {
      const [productRes, categoryRes] = await Promise.all([
        productService.getProducts({ category: selectedCategory, available: true }),
        productService.getCategories(),
      ]);

      setProducts(productRes.data.data || []);
      setCategories(categoryRes.data.data || []);
    } catch (error) {
      console.error('Impossible de charger les produits', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <Header />
      <main className="max-w-7xl mx-auto px-4 py-8">
        <div className="mb-8 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-sm uppercase tracking-[0.3em] text-primary font-semibold">Catalogue</p>
            <h1 className="text-4xl font-bold mt-3">Découvrez nos produits frais</h1>
            <p className="mt-3 max-w-2xl text-gray-600">
              Parcourez les dernières offres, filtrez par catégorie et ajoutez vos produits préférés au panier.
            </p>
          </div>
          <div className="flex flex-wrap gap-3">
            <button
              onClick={() => setSelectedCategory(null)}
              className={`rounded-full border px-4 py-2 transition ${selectedCategory === null ? 'bg-primary text-white' : 'bg-white text-gray-700 hover:bg-gray-100'}`}
            >
              Tout
            </button>
            {categories.map((category) => (
              <button
                key={category.id}
                onClick={() => setSelectedCategory(category.id)}
                className={`rounded-full border px-4 py-2 transition ${selectedCategory === category.id ? 'bg-primary text-white' : 'bg-white text-gray-700 hover:bg-gray-100'}`}
              >
                {category.icon_emoji || '•'} {category.name}
              </button>
            ))}
          </div>
        </div>

        {loading ? (
          <div className="rounded-3xl bg-white p-10 text-center shadow-sm">Chargement des produits...</div>
        ) : products.length === 0 ? (
          <div className="rounded-3xl bg-white p-10 text-center shadow-sm">
            Aucun produit disponible pour le moment.
          </div>
        ) : (
          <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            {products.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        )}
      </main>
    </>
  );
}
