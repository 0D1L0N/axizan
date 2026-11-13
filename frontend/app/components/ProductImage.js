'use client';

import { productImage } from '@/app/lib/images';
import RemoteImage from '@/app/components/RemoteImage';

/**
 * Image d'un produit, avec un repli assume quand la photo n'existe pas encore.
 *
 * Tant que nous n'avons pas nos propres photos du marche, beaucoup d'articles
 * n'en ont pas. Plutot qu'une image trompeuse ou un cadre vide, on affiche une
 * tuile aux couleurs de la marque portant l'emoji de la categorie : la grille
 * reste lisible et l'absence de photo parait voulue, pas cassee.
 */
export default function ProductImage({
  product,
  size = 'thumb',
  className = '',
  emojiSize = 'text-5xl',
  sizes,
}) {
  const hasPhoto = Boolean(product?.thumbnail_url || product?.image_url);

  if (!hasPhoto) {
    return (
      <div
        className={`flex flex-col items-center justify-center gap-2 bg-gradient-to-br from-[#F4F0E8] to-[#E7E0D2] ${className}`}
        role="img"
        aria-label={`${product?.name || 'Produit'} — photo à venir`}
      >
        <span className={emojiSize}>{product?.category?.icon_emoji || '🧺'}</span>
        <span className="px-2 text-center text-[11px] font-black uppercase tracking-[0.16em] text-[#12372A]/45">
          Photo à venir
        </span>
      </div>
    );
  }

  // `fill` a besoin d'un parent positionne : on le fournit ici plutot que de
  // l'exiger de chaque appelant. Les classes de dimension passees en
  // `className` habillent donc le conteneur, comme pour le repli ci-dessus.
  return (
    <div className={`relative overflow-hidden ${className}`}>
      <RemoteImage
        src={productImage(product, size)}
        alt={product.name}
        fill
        sizes={sizes || '(max-width: 768px) 100vw, (max-width: 1280px) 50vw, 33vw'}
        className="object-cover"
      />
    </div>
  );
}
