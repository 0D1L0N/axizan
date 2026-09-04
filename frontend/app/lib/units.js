/**
 * Unites de vente. Les produits au kg se commandent par demi-kilo,
 * les autres a la piece/au contenant.
 */
const UNITS = {
  kg: { long: 'par kg', short: 'kg', step: 0.5, min: 0.5 },
  bag: { long: 'par sac', short: 'sac', step: 1, min: 1 },
  bottle: { long: 'par bouteille', short: 'bouteille', step: 1, min: 1 },
  jar: { long: 'par pot', short: 'pot', step: 1, min: 1 },
  cup: { long: 'par mesure', short: 'mesure', step: 1, min: 1 },
  piece: { long: 'par pièce', short: 'pièce', step: 1, min: 1 },
};

const unitFor = (priceType) => UNITS[priceType] || UNITS.piece;

export const unitLabel = (priceType) => unitFor(priceType).long;

export const unitShort = (priceType) => unitFor(priceType).short;

export const quantityStep = (priceType) => unitFor(priceType).step;

export const minQuantity = (priceType) => unitFor(priceType).min;

/** Arrondit au pas de l'unite et evite les erreurs de virgule flottante. */
export const normalizeQuantity = (quantity, priceType) => {
  const { step, min } = unitFor(priceType);
  const rounded = Math.round(Number(quantity) / step) * step;
  return Math.max(min, Number(rounded.toFixed(3)));
};

/** "1,5 kg" / "2 pièces" */
export const formatQuantity = (quantity, priceType) => {
  const value = Number(quantity);
  const { short } = unitFor(priceType);
  const formatted = Number.isInteger(value) ? String(value) : value.toLocaleString('fr-FR');
  const plural = value > 1 && (short === 'pièce' || short === 'mesure' || short === 'sac' || short === 'pot');
  return `${formatted} ${short}${plural ? 's' : ''}`;
};

export const formatPrice = (amount) => `${Number(amount || 0).toLocaleString('fr-FR')} XOF`;

/**
 * Arrondi au franc entier.
 *
 * Le XOF n'a pas de subdivision : aucun montant affiche ne doit porter de
 * decimale. Doit rester le miroir exact de App\Support\Money cote serveur,
 * sinon le recapitulatif du client ne correspond pas au total facture.
 */
export const roundXof = (amount) => Math.round(Number(amount) || 0);
