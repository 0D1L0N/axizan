# Axizán — le marché béninois livré chez vous

Axizán permet de commander des produits frais du marché (Dantokpa, Saint-Michel…) et de se faire livrer à Cotonou et environs. Un **préparateur** fait les courses chez des fournisseurs partenaires, un **livreur** apporte la commande, un **administrateur** valide les paiements et pilote la logistique.

## Fonctionnalités

| Rôle | Ce qu'il peut faire |
|---|---|
| **Client** | Catalogue et recherche, panier synchronisé entre appareils, choix de l'adresse, du jour et du créneau, code promo, paiement Mobile Money avec déclaration de la référence, suivi de commande étape par étape, notifications, recommande en un clic |
| **Préparateur** | Commandes payées de son marché, prise en charge, collecte article par article (acheté, remplacé, retiré) avec le fournisseur, commande marquée prête |
| **Livreur** | Vérification d'identité, commandes prêtes, acceptation, départ, confirmation de livraison |
| **Admin** | Validation des paiements (référence vérifiée obligatoire), création des comptes préparateur et livreur, zones et créneaux de livraison avec leurs tarifs, vérification d'identité des livreurs |

### Règles métier
- **Paiement** : aucune API opérateur n'est branchée. Le client envoie l'argent au numéro marchand (MTN, Moov, Celtiis) et déclare sa référence. L'admin la vérifie sur le compte marchand, puis valide le paiement. Un préparateur ne voit une commande qu'une fois son paiement validé.
- **Créneaux** : au moins une heure d'avance, dans les horaires du marché (8 h–19 h), jusqu'à 7 jours à l'avance. La capacité d'un créneau se compte par jour.
- **Tarifs par zone** : frais de base, supplément éventuel du créneau, livraison offerte au-delà d'un seuil, minimum de commande (frais en plus ou refus).
- **Montants** : en francs CFA entiers, avec un arrondi unique pour tout le système (`App\Support\Money`).
- **Livreurs** : seul un livreur dont la pièce d'identité a été validée peut prendre une livraison. Les documents sont stockés sur un disque privé.

## Stack

- **Backend** : Laravel 12 (PHP 8.2+), API REST, authentification Sanctum
- **Frontend** : Next.js 15 (App Router), Tailwind CSS, PWA installable
- **Données** : PostgreSQL 15, Redis (cache et file d'attente)
- **Outils** : Docker Compose, Mailpit (mails de dev), GitHub Actions (CI)

## Démarrage (Docker)

Prérequis : Docker Desktop démarré.

```bash
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env.local
docker compose up -d --build

# Première fois uniquement
docker compose exec laravel php artisan key:generate
docker compose exec laravel php artisan migrate --seed
docker compose exec laravel php artisan storage:link
```

| Service | Adresse |
|---|---|
| Site | http://localhost:3000 |
| API | http://localhost:8000/api/v1 |
| Mails envoyés (Mailpit) | http://localhost:8025 |

### Comptes de démonstration
Mot de passe : `password123`

| Rôle | Email |
|---|---|
| Client | `client@test.com` |
| Préparateur (marché Dantokpa) | `preparateur@test.com` |
| Livreur (identité vérifiée) | `livreur@test.com` |
| Admin | `admin@test.com` |

Codes promo : `BIENVENUE` (−10 %, plafonné à 2 000 FCFA, une fois par client) et `MARCHE500` (−500 FCFA dès 5 000 FCFA d'achats).

### Parcours de démo
1. **Client** : ajouter des produits, passer au checkout, choisir un créneau, appliquer `BIENVENUE`, puis confirmer.
2. **Admin** (`/admin`) : saisir une référence et valider le paiement.
3. **Préparateur** (`/preparateur`) : accepter la commande, collecter chaque article, puis la marquer prête.
4. **Livreur** (`/livreur`) : accepter la livraison, la démarrer, puis la confirmer.
5. **Client** : la commande passe à « Livrée » et une notification arrive.

### Commandes utiles
```bash
docker compose logs -f laravel                          # journaux de l'API
docker compose exec laravel ./vendor/bin/phpunit        # tests backend
docker compose exec laravel php artisan migrate:fresh --seed   # base remise à zéro
docker compose stop                                     # tout arrêter
```

## Structure

```
backend/
  app/Http/Controllers/Api/    endpoints par domaine (commandes, panier, livraison…)
  app/Http/Controllers/Admin/  zones, créneaux, vérification d'identité
  app/Services/                logique métier (OrderService, PaymentService…)
  app/Support/Money.php        arrondi des montants
  database/migrations/         schéma
  database/seeders/            catalogue et données de démo
  tests/Feature/               tests de bout en bout de l'API
frontend/
  app/(client)/                panier, checkout, commandes, compte, notifications
  app/(staff)/                 espaces admin, préparateur, livreur
  app/(auth)/                  connexion, inscription, mot de passe oublié
  app/components/              composants partagés
  app/services/index.js        appels à l'API
```

## API

Toutes les routes sont préfixées par `/api/v1`. Les réponses ont la forme `{ "success": bool, "data": …, "message"?: … }`. Les routes authentifiées attendent l'en-tête `Authorization: Bearer <token>`.

| Domaine | Routes |
|---|---|
| Authentification | `POST auth/register`, `auth/login`, `auth/logout`, `auth/forgot-password`, `auth/reset-password`, `auth/change-password` · `GET auth/me` · `PATCH auth/profile` |
| Catalogue (public) | `GET products`, `products/{id}`, `categories`, `delivery-zones`, `delivery-slots`, `payment-methods` |
| Panier | `GET/POST/DELETE cart` · `POST cart/merge` · `DELETE cart/{productId}` |
| Commandes | `POST/GET orders` · `GET/DELETE orders/{id}` · `POST orders/{id}/declare-payment`, `orders/{id}/reorder` · `POST promotions/check` |
| Adresses, notifications | `addresses` (CRUD) · `GET notifications`, `notifications/unread-count` · `POST notifications/read-all` |
| Préparateur | `GET preparateur/orders`, `preparateur/orders/mine`, `preparateur/suppliers` · `PATCH preparateur/orders/{id}/accept\|start\|ready`, `preparateur/orders/{id}/items/{itemId}` |
| Livreur | `GET deliveries/ready-orders`, `deliveries/mine` · `POST deliveries/{orderId}/accept`, `deliveries/{id}/confirm` · `PATCH deliveries/{id}/start` · `GET/POST livreur/kyc` |
| Admin | `GET admin/payments/pending` · `POST admin/orders/{id}/validate-payment`, `admin/staff` · `admin/delivery-zones` et `admin/delivery-slots` (CRUD) · `admin/kyc/pending`, `admin/kyc/{id}/document\|approve\|reject` |

La liste complète : `docker compose exec laravel php artisan route:list --path=api`.

## Qualité

- **119 tests** de bout en bout de l'API (`tests/Feature`) : parcours complet de commande, paiements, créneaux, arrondis, promotions, vérification d'identité, sécurité.
- Les tests tournent toujours sur une base SQLite en mémoire, jamais sur la base de développement (`tests/TestCase.php`).
- La CI GitHub Actions lance les tests backend, ainsi que le lint et le build du frontend, à chaque push.
- `composer audit` : aucune vulnérabilité connue.
