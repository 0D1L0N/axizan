/**
 * Canaux de contact.
 *
 * WhatsApp d'abord : c'est la ou les clients ecrivent, que nous l'ayons prevu
 * ou non. On commence par le lien — il coute une heure et il apprend ce que les
 * gens demandent vraiment, avant toute integration de l'API Business.
 *
 * Le numero est un remplacant : mettre le vrai avant la mise en ligne.
 */
export const SUPPORT_PHONE = '+22900000000';
export const SUPPORT_EMAIL = 'contact@axizan.bj';

/** wa.me exige le numero sans + ni espaces. */
export const whatsappLink = (message = '') => {
  const number = SUPPORT_PHONE.replace(/[^0-9]/g, '');
  const text = message ? `?text=${encodeURIComponent(message)}` : '';
  return `https://wa.me/${number}${text}`;
};

/** Lien pre-rempli au sujet d'une commande precise : evite l'aller-retour « quel numéro ? ». */
export const whatsappAboutOrder = (orderNumber) =>
  whatsappLink(`Bonjour Axizán, j'ai une question sur ma commande ${orderNumber}.`);
