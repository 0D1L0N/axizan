'use client';

import { useEffect, useState } from 'react';
import Header from '@/app/components/Header';
import { productService } from '@/app/services';

const sampleMenus = [
  {
    id: 'menu-delice',
    title: 'Menu Délice du Jour',
    price: 4200,
    description: 'Repas complet avec poulet, riz, légumes et sauce locale.',
    details: ['Poulet grillé', 'Riz parfumé', 'Salade fraîche', 'Sauce pimentée'],
  },
  {
    id: 'menu-famille',
    title: 'Menu Famille',
    price: 7800,
    description: 'Plat généreux pour deux personnes avec boissons incluses.',
    details: ['Brochettes', 'Frites maison', 'Portion de fonio', 'Jus de bissap'],
  },
  {
    id: 'menu-vegetarien',
    title: 'Menu Végétarien',
    price: 3600,
    description: 'Sélection de légumes frais et protéines végétales.',
    details: ['Salade composée', 'Légumes grillés', 'Houmous maison', 'Eau citronnée'],
  },
];

export default function MenusPage() {
  const [categories, setCategories] = useState([]);

  useEffect(() => {
    productService
      .getCategories()
      .then((response) => setCategories(response.data.data || []))
      .catch((error) => {
        console.error('Impossible de charger les catégories', error);
      });
  }, []);

  return (
    <>
      <Header />
      <main className="max-w-7xl mx-auto px-4 py-8">
        <div className="mb-8">
          <p className="text-sm uppercase tracking-[0.3em] text-primary font-semibold">Menus</p>
          <h1 className="text-4xl font-bold mt-3">Menus rapides et prêts à commander</h1>
          <p className="mt-3 max-w-2xl text-gray-600">
            Choisissez une formule pratique et adaptez-la à vos envies du jour.
          </p>
        </div>

        <div className="grid gap-6 lg:grid-cols-3">
          {sampleMenus.map((menu) => (
            <div key={menu.id} className="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
              <div className="flex items-center justify-between gap-4 mb-4">
                <div>
                  <p className="text-sm uppercase tracking-[0.3em] text-primary font-semibold">Menu</p>
                  <h2 className="text-2xl font-bold mt-2">{menu.title}</h2>
                </div>
                <div className="text-right">
                  <p className="text-lg font-bold text-primary">{menu.price.toFixed(0)} XOF</p>
                </div>
              </div>
              <p className="text-gray-600 mb-4">{menu.description}</p>
              <ul className="space-y-2 mb-6 text-sm text-gray-700">
                {menu.details.map((item) => (
                  <li key={item} className="flex items-center gap-2">
                    <span>•</span> {item}
                  </li>
                ))}
              </ul>
              <button className="w-full rounded-full bg-primary px-5 py-3 text-white font-semibold hover:bg-red-700 transition">
                Ajouter au panier
              </button>
            </div>
          ))}
        </div>

        <section className="mt-12 rounded-3xl bg-primary/10 p-8">
          <h2 className="text-3xl font-bold">Explorer par catégorie</h2>
          <p className="mt-3 text-gray-600 max-w-2xl">
            Les menus suivants sont inspirés de nos meilleures catégories de produits.
          </p>
          <div className="mt-8 grid gap-6 md:grid-cols-3">
            {categories.length > 0 ? (
              categories.map((category) => (
                <div key={category.id} className="rounded-3xl bg-white p-6 shadow-sm">
                  <p className="text-sm text-gray-500">{category.icon_emoji || '•'} Catégorie</p>
                  <h3 className="text-xl font-semibold mt-2">{category.name}</h3>
                  <p className="mt-3 text-gray-600 line-clamp-3">{category.description}</p>
                </div>
              ))
            ) : (
              <div className="rounded-3xl bg-gray-100 p-6 text-center text-gray-600">Chargement des catégories...</div>
            )}
          </div>
        </section>
      </main>
    </>
  );
}
