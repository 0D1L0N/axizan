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

/**
 * Distance a vol d'oiseau, en kilometres (formule de haversine).
 *
 * A vol d'oiseau, donc toujours optimiste face aux rues de Cotonou : on
 * l'annonce comme un ordre de grandeur, jamais comme un temps de trajet.
 */
export function distanceKm(lat1, lon1, lat2, lon2) {
  if ([lat1, lon1, lat2, lon2].some((v) => v == null || Number.isNaN(Number(v)))) return null;

  const R = 6371;
  const toRad = (deg) => (Number(deg) * Math.PI) / 180;
  const dLat = toRad(lat2 - lat1);
  const dLon = toRad(lon2 - lon1);

  const a =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;

  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

export function formatDistance(km) {
  if (km == null) return null;
  return km < 1 ? `${Math.round(km * 1000)} m` : `${km.toFixed(1).replace('.', ',')} km`;
}

/**
 * Fraicheur d'une position. Une position d'il y a vingt minutes n'est plus
 * une position : le dire evite au client d'attendre devant sa porte.
 */
export function positionAge(isoDate) {
  if (!isoDate) return { label: 'Position inconnue', stale: true };

  const minutes = Math.floor((Date.now() - new Date(isoDate).getTime()) / 60000);

  if (minutes < 1) return { label: "à l'instant", stale: false };
  if (minutes < 3) return { label: `il y a ${minutes} min`, stale: false };
  if (minutes < 15) return { label: `il y a ${minutes} min`, stale: false };
  if (minutes < 60) return { label: `il y a ${minutes} min`, stale: true };

  const hours = Math.floor(minutes / 60);
  return { label: `il y a ${hours} h`, stale: true };
}
