'use client';

import Header from '@/app/components/Header';
import { useCart } from '@/app/contexts/CartContext';
import { RECIPE_MENUS } from '@/app/data/beninCatalog';
import { Check, Clock, Plus, ShoppingBasket, Users } from 'lucide-react';

export default function MenusPage() {
  const { addItem } = useCart();

  const handleAddRecipe = (recipe) => {
    recipe.ingredients.forEach((ingredient, index) => {
      addItem({
        product_id: `${recipe.id}-${index}`,
        type: 'recipe_ingredient',
        name: `${ingredient.name} (${ingredient.quantity})`,
        price: ingredient.price,
        image: ingredient.image,
        quantity: 1,
        price_type: 'piece',
      });
    });
  };

  const handleAddIngredient = (recipe, ingredient, index) => {
    addItem({
      product_id: `${recipe.id}-${index}`,
      type: 'recipe_ingredient',
      name: `${ingredient.name} (${ingredient.quantity})`,
      price: ingredient.price,
      image: ingredient.image,
      quantity: 1,
      price_type: 'piece',
    });
  };

  return (
    <>
      <Header />
      <main className="min-h-screen bg-[#FBF7EF]">
        <section className="relative overflow-hidden bg-[#12372A] text-white">
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(255,176,0,0.26),_transparent_30%)]" />
          <div className="relative mx-auto grid max-w-7xl gap-10 px-4 py-14 lg:grid-cols-[0.95fr_1.05fr] lg:items-center">
            <div>
              <p className="text-sm font-black uppercase tracking-[0.3em] text-[#FFB000]">Paniers-recettes</p>
              <h1 className="mt-4 text-5xl font-black leading-tight tracking-tight md:text-6xl">
                Tu choisis le plat, Axizán prépare les courses.
              </h1>
              <p className="mt-5 max-w-2xl text-lg font-medium leading-8 text-white/75">
                Ici, “menu” ne veut pas dire formule vague. C’est un panier complet pour cuisiner : salade de fruits, Atassi, Amiwo, Gari foto et autres plats du quotidien.
              </p>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              {RECIPE_MENUS.slice(0, 4).map((recipe) => (
                <div key={recipe.id} className="overflow-hidden rounded-[30px] bg-white/10 shadow-sm backdrop-blur">
                  <img src={recipe.image} alt={recipe.title} className="h-40 w-full object-cover" />
                  <div className="p-4">
                    <p className="text-xs font-black uppercase tracking-[0.22em] text-[#FFB000]">{recipe.time}</p>
                    <h2 className="mt-1 text-xl font-black">{recipe.title}</h2>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>

        <section className="mx-auto max-w-7xl px-4 py-12">
          <div className="grid gap-8">
            {RECIPE_MENUS.map((recipe, recipeIndex) => (
              <article key={recipe.id} className="overflow-hidden rounded-[36px] border border-black/5 bg-white shadow-sm">
                <div className="grid lg:grid-cols-[0.9fr_1.1fr]">
                  <div className="relative min-h-[360px] overflow-hidden">
                    <img src={recipe.image} alt={recipe.title} className="absolute inset-0 h-full w-full object-cover" />
                    <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent" />
                    <div className="absolute left-6 right-6 top-6 flex flex-wrap gap-2">
                      {recipe.tags.map((tag) => (
                        <span key={tag} className="rounded-full bg-white/92 px-3 py-1 text-xs font-black text-[#12372A] shadow-sm">
                          {tag}
                        </span>
                      ))}
                    </div>
                    <div className="absolute bottom-6 left-6 right-6 text-white">
                      <p className="text-sm font-black uppercase tracking-[0.28em] text-[#FFB000]">Recette #{recipeIndex + 1}</p>
                      <h2 className="mt-2 text-4xl font-black tracking-tight">{recipe.title}</h2>
                      <p className="mt-2 max-w-md text-white/82">{recipe.subtitle}</p>
                    </div>
                  </div>

                  <div className="p-6 lg:p-8">
                    <div className="flex flex-col gap-4 border-b border-gray-100 pb-6 md:flex-row md:items-center md:justify-between">
                      <div className="flex flex-wrap gap-3">
                        <span className="inline-flex items-center gap-2 rounded-full bg-[#F4F0E8] px-4 py-2 text-sm font-black text-[#12372A]">
                          <Users size={16} /> {recipe.serves}
                        </span>
                        <span className="inline-flex items-center gap-2 rounded-full bg-[#F4F0E8] px-4 py-2 text-sm font-black text-[#12372A]">
                          <Clock size={16} /> {recipe.time}
                        </span>
                      </div>
                      <div className="text-left md:text-right">
                        <p className="text-xs font-black uppercase tracking-[0.2em] text-gray-400">Panier estimé</p>
                        <p className="text-3xl font-black text-[#12372A]">{recipe.total.toLocaleString('fr-FR')} XOF</p>
                      </div>
                    </div>

                    <div className="mt-6">
                      <div className="mb-4 flex items-center justify-between gap-4">
                        <h3 className="text-2xl font-black text-[#12372A]">Articles nécessaires</h3>
                        <span className="rounded-full bg-[#12372A]/8 px-3 py-1 text-xs font-black text-[#12372A]">
                          {recipe.ingredients.length} produits
                        </span>
                      </div>

                      <div className="grid gap-3 md:grid-cols-2">
                        {recipe.ingredients.map((ingredient, index) => (
                          <div key={`${recipe.id}-${ingredient.name}`} className="flex items-center gap-3 rounded-2xl border border-gray-100 bg-[#FBF7EF] p-3">
                            <img src={ingredient.image} alt={ingredient.name} className="h-14 w-14 rounded-xl object-cover" />
                            <div className="min-w-0 flex-1">
                              <p className="truncate font-black text-gray-900">{ingredient.name}</p>
                              <p className="text-sm font-semibold text-gray-500">{ingredient.quantity} · {ingredient.price.toLocaleString('fr-FR')} XOF</p>
                            </div>
                            <button
                              onClick={() => handleAddIngredient(recipe, ingredient, index)}
                              className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-white text-[#12372A] shadow-sm hover:bg-[#FFB000]"
                              title={`Ajouter ${ingredient.name}`}
                            >
                              <Plus size={17} />
                            </button>
                          </div>
                        ))}
                      </div>

                      <button
                        onClick={() => handleAddRecipe(recipe)}
                        className="mt-6 inline-flex w-full items-center justify-center gap-3 rounded-full bg-[#FFB000] px-6 py-4 font-black text-[#1C140D] shadow-sm transition hover:bg-[#f3a300] md:w-auto"
                      >
                        <ShoppingBasket size={19} /> Ajouter tout le panier-recette
                      </button>
                    </div>
                  </div>
                </div>
              </article>
            ))}
          </div>
        </section>

        <section className="mx-auto max-w-7xl px-4 pb-14">
          <div className="rounded-[34px] bg-[#12372A] p-8 text-white md:p-10">
            <div className="grid gap-6 md:grid-cols-3">
              {[
                ['Simple à présenter', 'Le client comprend immédiatement : plat choisi, ingrédients livrés.'],
                ['Adapté au Bénin', 'Les produits et recettes correspondent aux habitudes locales.'],
                ['Prêt pour la suite', 'On pourra brancher stock réel, variantes et paiement Mobile Money.'],
              ].map(([title, text]) => (
                <div key={title} className="rounded-[26px] bg-white/10 p-5">
                  <Check className="text-[#FFB000]" size={22} />
                  <h3 className="mt-4 text-xl font-black">{title}</h3>
                  <p className="mt-2 text-sm leading-6 text-white/72">{text}</p>
                </div>
              ))}
            </div>
          </div>
        </section>
      </main>
    </>
  );
}
