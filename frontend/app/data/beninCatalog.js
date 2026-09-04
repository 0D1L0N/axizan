/**
 * Paniers-recettes : ce sont des raccourcis qui ajoutent plusieurs produits au panier.
 * Les ingredients referencent les produits par leur nom exact ; ils sont resolus a
 * l'execution contre le catalogue de l'API (source de verite : la base de donnees).
 * Categories et produits ne vivent plus ici.
 */
export const RECIPE_MENUS = [
  {
    id: 'recipe-salade-fruits',
    title: 'Salade de fruits maison',
    subtitle: 'Tout pour une salade fraîche et colorée',
    image: 'https://images.unsplash.com/photo-1490474418585-ba9bad8fd0ea?auto=format&fit=crop&w=1400&q=90',
    serves: '4 personnes',
    time: '15 min',
    tags: ['Frais', 'Dessert', 'Sans cuisson'],
    ingredients: [
      { product: 'Ananas pain de sucre', quantity: 1 },
      { product: 'Mangues mûres', quantity: 1 },
      { product: 'Papaye solo', quantity: 1 },
      { product: 'Oranges fraîches', quantity: 1 },
      { product: 'Citron vert', quantity: 1 },
      { product: 'Menthe fraîche', quantity: 1 },
    ],
  },
  {
    id: 'recipe-atassi',
    title: 'Atassi complet',
    subtitle: 'Riz-haricot béninois avec poisson fumé et huile rouge',
    image: 'https://images.unsplash.com/photo-1512058564366-18510be2db19?auto=format&fit=crop&w=1400&q=90',
    serves: '5 personnes',
    time: '1 h',
    tags: ['Béninois', 'Consistant', 'Marché'],
    ingredients: [
      { product: 'Riz parfumé', quantity: 1 },
      { product: 'Haricot blanc', quantity: 1 },
      { product: 'Poisson fumé', quantity: 2 },
      { product: 'Huile rouge', quantity: 1 },
      { product: 'Oignons violets', quantity: 1 },
      { product: 'Piment rouge frais', quantity: 1 },
    ],
  },
  {
    id: 'recipe-amiwo',
    title: 'Amiwo au poulet',
    subtitle: 'Pâte rouge, poulet, tomate et condiments',
    image: 'https://images.unsplash.com/photo-1604908177453-7462950a6a3b?auto=format&fit=crop&w=1400&q=90',
    serves: '4 personnes',
    time: '55 min',
    tags: ['Fête', 'Poulet', 'Tomate'],
    ingredients: [
      { product: 'Farine de maïs', quantity: 2 },
      { product: 'Poulet fermier', quantity: 1 },
      { product: 'Tomates locales', quantity: 1 },
      { product: 'Tomate concentrée', quantity: 1 },
      { product: 'Oignons violets', quantity: 1 },
      { product: 'Piment rouge frais', quantity: 1 },
    ],
  },
  {
    id: 'recipe-gari-foto',
    title: 'Gari foto',
    subtitle: 'Gari sauté avec oeufs, légumes et sardines',
    image: 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?auto=format&fit=crop&w=1400&q=90',
    serves: '3 personnes',
    time: '25 min',
    tags: ['Rapide', 'Étudiant', 'Économique'],
    ingredients: [
      { product: 'Gari fin', quantity: 1 },
      { product: 'Oeufs fermiers', quantity: 1 },
      { product: 'Sardines', quantity: 2 },
      { product: 'Tomates locales', quantity: 1 },
      { product: 'Oignons violets', quantity: 0.5 },
      { product: 'Piment rouge frais', quantity: 1 },
    ],
  },
];
