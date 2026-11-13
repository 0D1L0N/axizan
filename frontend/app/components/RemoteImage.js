'use client';

import Image from 'next/image';
import { useState } from 'react';
import { PLACEHOLDER_IMAGE } from '@/app/lib/images';

/**
 * Image distante optimisee.
 *
 * Next redimensionne, sert du WebP/AVIF et differe le chargement hors ecran.
 * Ce n'est pas un detail de confort ici : au Benin la donnee mobile se paie au
 * megaoctet, et servir une photo pleine resolution la fait payer au client.
 *
 * `fill` couvre le parent (qui doit etre positionne) ; sinon passer width et
 * height. Une URL cassee bascule sur le visuel de repli plutot que d'afficher
 * une icone d'image morte.
 */
export default function RemoteImage({
  src,
  alt,
  fill = false,
  width,
  height,
  className = '',
  sizes,
  priority = false,
  quality = 72,
}) {
  const [failed, setFailed] = useState(false);
  const source = failed || !src ? PLACEHOLDER_IMAGE : src;

  // sizes indique au navigateur la largeur reelle d'affichage : sans lui,
  // `fill` telecharge systematiquement la plus grande variante.
  const resolvedSizes = sizes || (fill ? '(max-width: 768px) 100vw, 33vw' : undefined);

  return (
    <Image
      src={source}
      alt={alt}
      {...(fill ? { fill: true } : { width: width || 800, height: height || 600 })}
      sizes={resolvedSizes}
      quality={quality}
      priority={priority}
      loading={priority ? undefined : 'lazy'}
      onError={() => setFailed(true)}
      className={className}
      // Le fichier de repli est un SVG local : l'optimiseur n'a rien a en faire.
      unoptimized={source === PLACEHOLDER_IMAGE}
    />
  );
}
