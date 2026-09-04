/**
 * Acces aux images produits.
 *
 * Regle : les listes (cartes, panier, recap) utilisent la vignette, la fiche produit
 * utilise l'image pleine taille. Si une photo manque, on renvoie un placeholder local
 * plutot qu'une URL externe cassee.
 */
export const PLACEHOLDER_IMAGE = '/placeholder-product.svg';

/** size: 'thumb' pour les listes, 'full' pour la fiche produit. */
export const productImage = (product, size = 'thumb') => {
  if (!product) return PLACEHOLDER_IMAGE;
  if (size === 'thumb') {
    return product.thumbnail_url || product.image_url || PLACEHOLDER_IMAGE;
  }
  return product.image_url || product.thumbnail_url || PLACEHOLDER_IMAGE;
};

/** A brancher sur onError d'une <img> pour eviter les images cassees. */
export const onImageError = (event) => {
  if (event.currentTarget.src.endsWith(PLACEHOLDER_IMAGE)) return;
  event.currentTarget.src = PLACEHOLDER_IMAGE;
};
