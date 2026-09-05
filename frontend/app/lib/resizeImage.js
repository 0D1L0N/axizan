'use client';

/**
 * Redimensionne une image DANS LE NAVIGATEUR, avant l'envoi.
 *
 * Ni GD ni Imagick ne sont installés côté serveur, et c'est un choix plutôt
 * qu'un manque : une photo de téléphone pèse 4 Mo, et la téléverser telle
 * quelle depuis une connexion béninoise prendrait une minute pour un fichier
 * dont on n'utilisera qu'un quart. Réduire d'abord, envoyer ensuite.
 *
 * Le serveur vérifie ce qu'il reçoit ; il ne le fabrique pas.
 */

/** Largeurs retenues : la fiche produit, et la vignette des listes. */
export const FULL_WIDTH = 1200;
export const THUMB_WIDTH = 400;

/**
 * @returns {Promise<Blob>} l'image réduite, en JPEG.
 */
export function resizeImage(file, maxWidth, quality = 0.82) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const image = new Image();

    image.onload = () => {
      // L'objet URL est libéré dans tous les cas : sans cela, téléverser
      // vingt photos d'affilée garde vingt images en mémoire.
      URL.revokeObjectURL(url);

      // Une image déjà plus petite n'est jamais agrandie : on ne fabrique pas
      // du détail qui n'existe pas.
      const scale = Math.min(1, maxWidth / image.width);
      const canvas = document.createElement('canvas');
      canvas.width = Math.round(image.width * scale);
      canvas.height = Math.round(image.height * scale);

      const context = canvas.getContext('2d');
      if (!context) {
        reject(new Error('canvas indisponible'));
        return;
      }

      // Fond blanc : un PNG transparent deviendrait noir en JPEG.
      context.fillStyle = '#ffffff';
      context.fillRect(0, 0, canvas.width, canvas.height);
      context.drawImage(image, 0, 0, canvas.width, canvas.height);

      canvas.toBlob(
        (blob) => (blob ? resolve(blob) : reject(new Error('conversion impossible'))),
        'image/jpeg',
        quality
      );
    };

    image.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error('image illisible'));
    };

    image.src = url;
  });
}

/**
 * Prépare le couple image de fiche + vignette pour l'envoi.
 *
 * @returns {Promise<FormData>}
 */
export async function buildPhotoUpload(file) {
  const [full, thumb] = await Promise.all([
    resizeImage(file, FULL_WIDTH),
    resizeImage(file, THUMB_WIDTH, 0.7),
  ]);

  const form = new FormData();
  form.append('photo', full, 'photo.jpg');
  form.append('thumbnail', thumb, 'thumbnail.jpg');

  return form;
}

/** « 3,2 Mo » — pour montrer ce qu'on a économisé. */
export const humanSize = (bytes) =>
  bytes > 1024 * 1024
    ? `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} Mo`
    : `${Math.round(bytes / 1024)} Ko`;
