/** @type {import('next').NextConfig} */
const nextConfig = {
  images: {
    remotePatterns: [
      {
        protocol: 'https',
        hostname: 'images.unsplash.com',
      },
      {
        protocol: 'https',
        hostname: 's3.amazonaws.com',
      },
      {
        protocol: 'https',
        hostname: '*.r2.cloudflarestorage.com',
      },
      // Preuves de livraison servies par l'API en développement.
      {
        protocol: 'http',
        hostname: 'localhost',
        port: '8000',
      },
    ],
    // AVIF puis WebP : sur un forfait data béninois, chaque kilo-octet
    // épargné est payé par le client.
    formats: ['image/avif', 'image/webp'],
  },
  env: {
    NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL || '',
  },

  /**
   * L'API et les fichiers stockés sont servis sous le même domaine que le
   * front, par réécriture côté serveur Next.
   *
   * Deux bénéfices : un seul tunnel suffit pour exposer l'application en
   * ligne, et le navigateur ne fait plus de requête inter-domaine — donc plus
   * aucune question de CORS, ni d'adresse d'API à recaler quand l'IP change.
   *
   * La destination utilise le nom de service Docker : la réécriture s'exécute
   * dans le conteneur Next, pas dans le navigateur.
   */
  async rewrites() {
    const backend = process.env.BACKEND_ORIGIN || 'http://laravel:8000';

    return [
      { source: '/api/:path*', destination: `${backend}/api/:path*` },
      { source: '/storage/:path*', destination: `${backend}/storage/:path*` },
    ];
  },
};

module.exports = nextConfig;
