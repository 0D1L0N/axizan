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
};

module.exports = nextConfig;
