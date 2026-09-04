'use client';

import { useEffect, useMemo, useState } from 'react';
import Header from '@/app/components/Header';
import Footer from '@/app/components/Footer';
import { useCart } from '@/app/contexts/CartContext';
import { RECIPE_MENUS } from '@/app/data/beninCatalog';
import { productService } from '@/app/services';
import { formatQuantity, formatPrice, normalizeQuantity } from '@/app/lib/units';
import { productImage } from '@/app/lib/images';
import ProductImage from '@/app/components/ProductImage';
import RemoteImage from '@/app/components/RemoteImage';
import { Check, Clock, Plus, ShoppingBasket, Users } from 'lucide-react';

export default function MenusPage() {
  const { addItem } = useCart();
  const [productsByName, setProductsByName] = useState({});
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    productService
      .getProducts({ limit: 100 })
      .then((res) => {
        const map = {};
        res.data.data.forEach((product) => {
          map[product.name] = product;
        });
        setProductsByName(map);
      })
      .catch(() => setProductsByName({}))
      .finally(() => setLoading(false));
  }, []);

  /**
   * Un panier-recette n'est pas une entite metier : c'est un raccourci qui resout
   * ses ingredients vers de vrais produits du catalogue, puis les ajoute au panier.
   */
  const recipes = useMemo(
    () =>
      RECIPE_MENUS.map((recipe) => {
        const lines = recipe.ingredients.map((ingredient) => {
          const product = productsByName[ingredient.product];
          const quantity = product ? normalizeQuantity(ingredient.quantity, product.price_type) : ingredient.quantity;
          return {
            product,
            name: ingredient.product,
            quantity,
            lineTotal: product ? Number(product.base_price) * quantity : 0,
          };
        });

        return {
          ...recipe,
          lines,
          total: lines.reduce((sum, line) => sum + line.lineTotal, 0),
          missing: lines.filter((line) => !line.product).length,
        };
      }),
    [productsByName]
  );

  const addLine = (line) => {
    if (!line.product) return;
    addItem({
      product_id: line.product.id,
      type: 'product',
      name: line.product.name,
      price: Number(line.product.base_price),
      image: productImage(line.product, 'thumb'),
      quantity: line.quantity,
      price_type: line.product.price_type,
    });
  };

  const handleAddRecipe = (recipe) => recipe.lines.forEach(addLine);

  return (
    <>
      <Header />
      <main className="min-h-screen bg-cream">
        <section className="relative overflow-hidden bg-forest-800 text-white">
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(255,176,0,0.26),_transparent_30%)]" />
          <div className="relative mx-auto grid max-w-7xl gap-10 px-4 py-14 lg:grid-cols-[0.95fr_1.05fr] lg:items-center">
            <div>
              <p className="text-sm font-black uppercase tracking-[0.3em] text-gold-500">Paniers-recettes</p>
              <h1 className="mt-4 text-5xl font-black leading-tight tracking-tight md:text-6xl">
                Vous choisissez le plat, Axizán fait les courses.
              </h1>
              <p className="mt-5 max-w-2xl text-lg font-medium leading-8 text-white/75">
                Ici, “menu” ne veut pas dire formule vague. C’est un panier complet pour cuisiner : salade de fruits, Atassi, Amiwo, Gari foto et autres plats du quotidien.
              </p>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              {recipes.slice(0, 4).map((recipe) => (
                <div key={recipe.id} className="overflow-hidden rounded-[30px] bg-white/10 shadow-sm backdrop-blur">
                  <div className="relative h-40 w-full overflow-hidden">
                    <RemoteImage src={recipe.image} alt={recipe.title} fill sizes="(max-width: 768px) 100vw, 25vw" className="object-cover" />
                  </div>
                  <div className="p-4">
                    <p className="text-xs font-black uppercase tracking-[0.22em] text-gold-500">{recipe.time}</p>
                    <h2 className="mt-1 text-xl font-black">{recipe.title}</h2>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>

        <section className="mx-auto max-w-7xl px-4 py-12">
          <div className="grid gap-8">
            {recipes.map((recipe, recipeIndex) => (
              <article key={recipe.id} className="overflow-hidden rounded-[36px] border border-black/5 bg-white shadow-sm">
                <div className="grid lg:grid-cols-[0.9fr_1.1fr]">
                  <div className="relative min-h-[360px] overflow-hidden">
                    <RemoteImage src={recipe.image} alt={recipe.title} fill sizes="(max-width: 1024px) 100vw, 45vw" className="object-cover" />
                    <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent" />
                    <div className="absolute left-6 right-6 top-6 flex flex-wrap gap-2">
                      {recipe.tags.map((tag) => (
                        <span key={tag} className="rounded-full bg-white/92 px-3 py-1 text-xs font-black text-forest-800 shadow-sm">
                          {tag}
                        </span>
                      ))}
                    </div>
                    <div className="absolute bottom-6 left-6 right-6 text-white">
                      <p className="text-sm font-black uppercase tracking-[0.28em] text-gold-500">Recette #{recipeIndex + 1}</p>
                      <h2 className="mt-2 text-4xl font-black tracking-tight">{recipe.title}</h2>
                      <p className="mt-2 max-w-md text-white/82">{recipe.subtitle}</p>
                    </div>
                  </div>

                  <div className="p-6 lg:p-8">
                    <div className="flex flex-col gap-4 border-b border-cream-200 pb-6 md:flex-row md:items-center md:justify-between">
                      <div className="flex flex-wrap gap-3">
                        <span className="inline-flex items-center gap-2 rounded-full bg-cream-100 px-4 py-2 text-sm font-black text-forest-800">
                          <Users size={16} /> {recipe.serves}
                        </span>
                        <span className="inline-flex items-center gap-2 rounded-full bg-cream-100 px-4 py-2 text-sm font-black text-forest-800">
                          <Clock size={16} /> {recipe.time}
                        </span>
                      </div>
                      <div className="text-left md:text-right">
                        <p className="text-xs font-black uppercase tracking-[0.2em] text-ink-300">Panier estimé</p>
                        <p className="text-3xl font-black text-forest-800">
                          {loading ? '—' : formatPrice(recipe.total)}
                        </p>
                      </div>
                    </div>

                    <div className="mt-6">
                      <div className="mb-4 flex items-center justify-between gap-4">
                        <h3 className="text-2xl font-black text-forest-800">Articles nécessaires</h3>
                        <span className="rounded-full bg-forest-800/8 px-3 py-1 text-xs font-black text-forest-800">
                          {recipe.lines.length} produits
                        </span>
                      </div>

                      <div className="grid gap-3 md:grid-cols-2">
                        {recipe.lines.map((line) => (
                          <div
                            key={`${recipe.id}-${line.name}`}
                            className={`flex items-center gap-3 rounded-2xl border border-cream-200 bg-cream p-3 ${
                              !line.product && !loading ? 'opacity-50' : ''
                            }`}
                          >
                            <ProductImage
                              product={line.product || { name: line.name }}
                              size="thumb"
                              emojiSize="text-xl"
                              className="h-14 w-14 shrink-0 rounded-xl object-cover"
                            />
                            <div className="min-w-0 flex-1">
                              <p className="truncate font-black text-ink-900">{line.name}</p>
                              <p className="text-sm font-semibold text-ink-500">
                                {line.product
                                  ? `${formatQuantity(line.quantity, line.product.price_type)} · ${formatPrice(line.lineTotal)}`
                                  : loading
                                    ? 'Chargement...'
                                    : 'Indisponible au catalogue'}
                              </p>
                            </div>
                            <button
                              onClick={() => addLine(line)}
                              disabled={!line.product}
                              className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-white text-forest-800 shadow-sm transition hover:bg-gold-500 disabled:cursor-not-allowed disabled:opacity-40"
                              title={`Ajouter ${line.name}`}
                            >
                              <Plus size={17} />
                            </button>
                          </div>
                        ))}
                      </div>

                      <button
                        onClick={() => handleAddRecipe(recipe)}
                        disabled={loading || recipe.lines.every((line) => !line.product)}
                        className="mt-6 inline-flex w-full items-center justify-center gap-3 rounded-full bg-gold-500 px-6 py-4 font-black text-forest-900 shadow-sm transition hover:bg-gold-600 disabled:cursor-not-allowed disabled:opacity-40 md:w-auto"
                      >
                        <ShoppingBasket size={19} /> Ajouter tout le panier-recette
                      </button>
                      {recipe.missing > 0 && !loading && (
                        <p className="mt-3 text-sm font-semibold text-ink-500">
                          {recipe.missing} article(s) de cette recette ne sont pas encore au catalogue.
                        </p>
                      )}
                    </div>
                  </div>
                </div>
              </article>
            ))}
          </div>
        </section>

        <section className="mx-auto max-w-7xl px-4 pb-14">
          <div className="rounded-[34px] bg-forest-800 p-8 text-white md:p-10">
            <div className="grid gap-6 md:grid-cols-3">
              {[
                ['Simple à présenter', 'Le client comprend immédiatement : plat choisi, ingrédients livrés.'],
                ['Adapté au Bénin', 'Les produits et recettes correspondent aux habitudes locales.'],
                ['Prêt pour la suite', 'On pourra brancher variantes, substitutions et paiement Mobile Money.'],
              ].map(([title, text]) => (
                <div key={title} className="rounded-[26px] bg-white/10 p-5">
                  <Check className="text-gold-500" size={22} />
                  <h3 className="mt-4 text-xl font-black">{title}</h3>
                  <p className="mt-2 text-sm leading-6 text-white/72">{text}</p>
                </div>
              ))}
            </div>
          </div>
        </section>
      </main>
      <Footer />
    </>
  );
}
