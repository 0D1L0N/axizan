'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { productService } from '@/app/services';
import ProductCard from '@/app/components/ProductCard';
import Header from '@/app/components/Header';

export default function HomePage() {
  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [selectedCategory, setSelectedCategory] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadData();
  }, [selectedCategory]);

  const loadData = async () => {
    try {
      setLoading(true);

      // Charger catégories
      const catRes = await productService.getCategories();
      setCategories(catRes.data.data);

      // Charger produits
      const prodRes = await productService.getProducts({
        category: selectedCategory,
        available: true,
      });
      setProducts(prodRes.data.data);
    } catch (error) {
      console.error('Failed to load data:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <Header />
      <main className="max-w-7xl mx-auto px-4 py-8">
        {/* Hero Section */}
        <section className="bg-gradient-to-r from-primary to-secondary rounded-lg p-8 text-white mb-12">
          <h1 className="text-4xl font-bold mb-4">🍲 Bienvenue à Axizán</h1>
          <p className="text-lg mb-6">
            Découvrez une sélection de produits alimentaires frais livrés directement à votre porte
          </p>
          <Link href="/products" className="inline-flex items-center justify-center bg-white text-primary font-semibold px-6 py-3 rounded-lg hover:bg-gray-100">
            Commencer →
          </Link>
        </section>

        {/* Catégories */}
        <section className="mb-12">
          <h2 className="text-2xl font-bold mb-6">📂 Catégories</h2>
          <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <button
              onClick={() => setSelectedCategory(null)}
              className={`p-4 rounded-lg text-center font-semibold transition ${
                selectedCategory === null
                  ? 'bg-primary text-white'
                  : 'bg-gray-100 text-gray-800 hover:bg-gray-200'
              }`}
            >
              🏪 Tous
            </button>
            {categories.map((cat) => (
              <button
                key={cat.id}
                onClick={() => setSelectedCategory(cat.id)}
                className={`p-4 rounded-lg text-center font-semibold transition ${
                  selectedCategory === cat.id
                    ? 'bg-primary text-white'
                    : 'bg-gray-100 text-gray-800 hover:bg-gray-200'
                }`}
              >
                {cat.icon_emoji} {cat.name}
              </button>
            ))}
          </div>
        </section>

        {/* Produits */}
        <section>
          <h2 className="text-2xl font-bold mb-6">🛒 Produits {selectedCategory && '- Filtré'}</h2>
          {loading ? (
            <div className="flex items-center justify-center py-12">
              <div className="text-gray-600">Chargement...</div>
            </div>
          ) : products.length === 0 ? (
            <div className="bg-gray-100 rounded-lg p-8 text-center">
              <p className="text-gray-600">Aucun produit disponible dans cette catégorie</p>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
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
