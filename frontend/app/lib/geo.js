/**
 * Position et liens de carte.
 *
 * Deux besoins distincts, souvent confondus :
 *  - *poser* un point : le client capte sa position ou colle un lien de carte ;
 *  - *ouvrir* un point : le livreur lance l'itineraire depuis son telephone.
 */

/** iOS et macOS ouvrent Plans nativement ; partout ailleurs, Google Maps. */
export function prefersAppleMaps() {
  if (typeof navigator === 'undefined') return false;
  return /iPad|iPhone|iPod|Macintosh/.test(navigator.userAgent);
}

export function mapsLink(latitude, longitude, label = '') {
  if (latitude == null || longitude == null) return null;
  const point = `${Number(latitude).toFixed(6)},${Number(longitude).toFixed(6)}`;

  if (prefersAppleMaps()) {
    return `https://maps.apple.com/?q=${encodeURIComponent(label || point)}&ll=${point}`;
  }
  return `https://www.google.com/maps/search/?api=1&query=${point}`;
}

/** Itineraire depuis la position courante — l'usage du livreur. */
export function directionsLink(latitude, longitude) {
  if (latitude == null || longitude == null) return null;
  const point = `${Number(latitude).toFixed(6)},${Number(longitude).toFixed(6)}`;

  if (prefersAppleMaps()) {
    return `https://maps.apple.com/?daddr=${point}&dirflg=d`;
  }
  return `https://www.google.com/maps/dir/?api=1&destination=${point}`;
}

/**
 * Capture la position courante.
 *
 * Resout { latitude, longitude, accuracy }. Rejette avec un message deja
 * traduit : les codes d'erreur bruts de l'API ne veulent rien dire pour un
 * utilisateur.
 */
export function captureLocation({ timeout = 15000 } = {}) {
  return new Promise((resolve, reject) => {
    if (typeof navigator === 'undefined' || !navigator.geolocation) {
      reject(new Error("Votre navigateur ne permet pas de partager la position."));
      return;
    }

    // La géolocalisation exige une origine sécurisée. En développement,
    // localhost est considéré comme sûr ; en production il faut du HTTPS.
    if (typeof window !== 'undefined' && !window.isSecureContext) {
      reject(new Error('Le partage de position nécessite une connexion sécurisée (HTTPS).'));
      return;
    }

    navigator.geolocation.getCurrentPosition(
      (position) =>
        resolve({
          latitude: Number(position.coords.latitude.toFixed(7)),
          longitude: Number(position.coords.longitude.toFixed(7)),
          accuracy: Math.round(position.coords.accuracy),
        }),
      (error) => {
        const messages = {
          1: 'Vous avez refusé le partage de position. Autorisez-le dans votre navigateur, ou saisissez le quartier à la main.',
          2: 'Position introuvable. Essayez dehors ou avec les données mobiles activées.',
          3: 'La localisation a pris trop de temps. Réessayez.',
        };
        reject(new Error(messages[error.code] || 'Impossible de récupérer votre position.'));
      },
      { enableHighAccuracy: true, timeout, maximumAge: 0 }
    );
  });
}

/**
 * Extrait des coordonnees d'un lien de carte colle.
 *
 * Couvre les formes courantes de Google Maps (@lat,lng / q=lat,lng / !3dlat!4dlng)
 * et de Plans. Les liens courts (maps.app.goo.gl) ne contiennent aucune
 * coordonnee : impossible de les resoudre sans appel reseau, on garde alors le
 * lien tel quel sans pretendre connaitre le point.
 */
export function parseCoordinates(url) {
  if (!url) return null;

  const patterns = [
    /@(-?\d+\.\d+),(-?\d+\.\d+)/,
    /[?&](?:q|ll|daddr|destination)=(-?\d+\.\d+),\s*(-?\d+\.\d+)/,
    /!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/,
  ];

  for (const pattern of patterns) {
    const match = url.match(pattern);
    if (match) {
      const latitude = Number(match[1]);
      const longitude = Number(match[2]);
      if (Math.abs(latitude) <= 90 && Math.abs(longitude) <= 180) {
        return { latitude, longitude };
      }
    }
  }
  return null;
}
