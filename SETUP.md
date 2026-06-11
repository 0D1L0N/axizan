# 🚀 Démarrage Rapide - Axizán

## Prérequis

- **PHP 8.2+** → [php.net/downloads](https://www.php.net/downloads)
- **Node.js 18+** → [nodejs.org](https://nodejs.org)
- **PostgreSQL 15+** → [postgresql.org](https://www.postgresql.org/download)
- **Composer** → [getcomposer.org](https://getcomposer.org)
- **Git** → [git-scm.com](https://git-scm.com)

---

## 🎯 Installation en 5 minutes

### 1️⃣ Cloner / Configurer le projet

```bash
cd axizan
chmod +x install.sh
./install.sh
```

### 2️⃣ Configurer la base de données

```bash
# Créer la DB PostgreSQL
createdb axizan

# Ou via psql
psql -U postgres
CREATE DATABASE axizan;
```

**Éditer** `backend/.env` :
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=axizan
DB_USERNAME=postgres
DB_PASSWORD=postgres  # Changez si nécessaire
```

### 3️⃣ Initialiser le backend

```bash
cd backend

# Migrations
php artisan migrate

# Seeders (données test)
php artisan db:seed
```

### 4️⃣ Lancer le backend

```bash
php artisan serve
# ➜ http://localhost:8000
```

**Laisser tourner dans le terminal!**

### 5️⃣ Lancer le frontend (nouveau terminal)

```bash
cd frontend
npm run dev
# ➜ http://localhost:3000
```

---

## 🧪 Tester

### 1. Cloner et configurer

```bash
cd frontend
npm install
cp .env.example .env.local
```

### 2. Configurer API URL

Éditer `.env.local`:
```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api/v1
```

### 3. Lancer le serveur

```bash
npm run dev
# Frontend disponible à http://localhost:3000
```

---

## 📊 Initialiser les Données

### Via Backend

```bash
# Créer un admin
php artisan tinker
User::create([
  'name' => 'Admin',
  'email' => 'admin@axizan.bj',
  'phone' => '+22960000000',
  'password' => Hash::make('admin123'),
  'role' => 'admin',
  'status' => 'active',
])

# Seed données
php artisan db:seed --class=CategorySeeder
php artisan db:seed --class=ProductSeeder
php artisan db:seed --class=DeliveryZoneSeeder
```

---

## 🔑 Comptes de Test

| Rôle | Email | Mot de passe |
|------|-------|-------------|
| Admin | admin@axizan.bj | admin123 |
| Client | client@axizan.bj | client123 |
| Préparateur | prep@axizan.bj | prep123 |
| Livreur | livreur@axizan.bj | livreur123 |

---

## 🧪 Test API

### Avec cURL

```bash
# Inscription
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name":"Test User",
    "email":"test@example.com",
    "phone":"+22965123456",
    "password":"TestPass123!",
    "password_confirmation":"TestPass123!",
    "role":"client"
  }'

# Connexion
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"TestPass123!"}'

# Lister produits
curl http://localhost:8000/api/v1/products
```

### Avec Postman/Insomnia

Importer la collection API depuis `/docs/postman-collection.json`

---

## 📱 Tester PWA

1. Ouvrir http://localhost:3000 sur mobile
2. Menu → "Ajouter à l'écran d'accueil"
3. App installée et fonctionnelle hors ligne (avec service worker)

---

## 🐳 Avec Docker (Optionnel)

### Docker Compose

```bash
# À la racine du projet
docker-compose up -d

# Initialiser BD dans le conteneur
docker-compose exec laravel php artisan migrate
docker-compose exec laravel php artisan db:seed
```

### Services

- **PostgreSQL**: localhost:5432
- **Redis**: localhost:6379
- **Laravel Backend**: http://localhost:8000
- **Next.js Frontend**: http://localhost:3000
- **Mailpit** (mail test): http://localhost:8025

---

## 🧹 Commandes Utiles

### Backend (Laravel)

```bash
# Migrations
php artisan migrate              # Exécuter migrations
php artisan migrate:rollback     # Annuler dernière migration
php artisan migrate:fresh        # Réinitialiser BD

# Seeding
php artisan db:seed

# Tinker (REPL)
php artisan tinker

# Cache clear
php artisan cache:clear
php artisan config:cache

# Queue
php artisan queue:work

# Tests
php artisan test
```

### Frontend (Next.js)

```bash
# Dev
npm run dev

# Build production
npm run build
npm start

# Lint
npm run lint

# Tests
npm test
npm run test:watch
```

---

## 📚 Documentation

- API Endpoints: [/docs/API.md](docs/API.md)
- Schéma BD: [/docs/DATABASE.md](docs/DATABASE.md)
- Architecture: [README.md](README.md)

---

## 🔍 Troubleshooting

### Backend

#### Erreur: "SQLSTATE[HY000]: General error: 7 ERROR..."
→ PostgreSQL non lancé
```bash
# macOS
brew services start postgresql

# Linux
sudo systemctl start postgresql
```

#### Erreur: "Class not found"
```bash
cd backend
composer dump-autoload
php artisan config:cache
```

### Frontend

#### Erreur: "ECONNREFUSED - Cannot connect to API"
→ Vérifier backend lancé et URL dans `.env.local`
```bash
curl http://localhost:8000/api/v1/products
```

#### Port 3000 déjà utilisé
```bash
npm run dev -- -p 3001
```

---

## 🚀 Déploiement

### Production Checklist

- [ ] Mettre `APP_DEBUG=false` en production
- [ ] Générer clé `APP_KEY`
- [ ] Configurer BD production (PostgreSQL managed)
- [ ] Configurer S3 / stockage fichiers
- [ ] Configurer Mobile Money API réelle
- [ ] Configurer email (SMTP)
- [ ] Configurer Redis cache
- [ ] Configurer SSL/HTTPS
- [ ] Configurer domain DNS
- [ ] Configurer CI/CD (GitHub Actions, etc)
- [ ] Configurer monitoring (Sentry, etc)

---

## 📧 Support

- Issues: [GitHub Issues](https://github.com/axizan/axizan/issues)
- Email: support@axizan.bj
- Documentation: [Docs complets](docs/)

---

**Axizán** © 2026 | 🇧🇯 Bénin | Production-Ready
