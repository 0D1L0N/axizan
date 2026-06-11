# 🏗️ Architecture Technique - Axizán

## 📐 Vue d'Ensemble

```
┌─────────────────────────────────────────────────────────────────┐
│                      UTILISATEURS (4 RÔLES)                      │
│         Client │ Préparateur │ Livreur │ Admin                  │
└──────────────────────────────┬──────────────────────────────────┘
                               │
        ┌──────────────────────┴──────────────────────┐
        │                                             │
┌───────▼─────────────────────────────┐   ┌─────────▼──────────┐
│    FRONTEND (Next.js + PWA)         │   │  Admin Dashboard   │
│  - Responsive Design                │   │  - Analytics       │
│  - Offline Support (SW)             │   │  - Management      │
│  - Real-time Tracking              │   └────────────────────┘
│  - Mobile Optimized                │
└───────┬─────────────────────────────┘
        │
        │ REST API + WebSocket
        │ JWT Authentication
        │
┌───────▼──────────────────────────────────────────────────┐
│           BACKEND API (Laravel 11 + Sanctum)              │
│                                                           │
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────┐ │
│  │  Auth Module │  │  Order Flow  │  │ Payment Module │ │
│  └──────────────┘  └──────────────┘  └────────────────┘ │
│                                                           │
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────┐ │
│  │  Delivery    │  │  Products    │  │  Promotions    │ │
│  │  Management  │  │  & Menus     │  │  & Invoicing   │ │
│  └──────────────┘  └──────────────┘  └────────────────┘ │
│                                                           │
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────┐ │
│  │ Notifications│  │  Analytics   │  │  WebSocket     │ │
│  │   Service    │  │   Engine     │  │  Real-time     │ │
│  └──────────────┘  └──────────────┘  └────────────────┘ │
└───────┬──────────────────────────────────────────────────┘
        │
        │ SQL Queries (Eloquent ORM)
        │
┌───────▼──────────────────────────────────────────────────┐
│         DATABASE LAYER (PostgreSQL 15+)                   │
│                                                           │
│  Tables:                                                 │
│  - users (clients, préparateurs, livreurs, admins)       │
│  - orders (workflow complet)                             │
│  - order_items (détails commandes)                       │
│  - products (catalogue)                                  │
│  - menus (templates)                                     │
│  - deliveries (suivi)                                    │
│  - payments (transactions)                               │
│  - notifications (audit)                                 │
│  - promotions (codes promo)                              │
│  - reviews (avis)                                        │
│                                                           │
│  Indexes: Full-text search, Geo-spatial, Composite       │
└───────┬──────────────────────────────────────────────────┘
        │
        ├─── REDIS (Cache + Sessions)
        │
        ├─── S3 Storage (Images, Preuves)
        │
        └─── External Services
              ├─ Mobile Money API (MTN, Moov, Celtiis)
              ├─ SMS API (Twilio / Local)
              ├─ Email (SMTP)
              └─ Pusher/Soketi (Real-time)
```

---

## 🗂️ Structure Répertoires

```
axizan/
│
├── backend/                     # Laravel API
│   ├── app/
│   │   ├── Enums/              # UserRole, OrderStatus, etc
│   │   ├── Http/
│   │   │   └── Controllers/    # API Controllers
│   │   ├── Models/             # Eloquent Models
│   │   ├── Services/           # Business Logic
│   │   ├── Listeners/          # Event Listeners
│   │   └── Jobs/               # Queued Jobs
│   ├── database/
│   │   ├── migrations/         # Schema migrations
│   │   └── seeders/            # Data seeders
│   ├── routes/
│   │   └── api.php             # API Routes
│   ├── config/                 # Configuration files
│   ├── tests/                  # Unit & Feature tests
│   ├── composer.json
│   ├── .env.example
│   └── artisan
│
├── frontend/                    # Next.js App
│   ├── app/
│   │   ├── (auth)/             # Login/Register
│   │   ├── (client)/           # Client routes
│   │   ├── (admin)/            # Admin routes (si nécessaire)
│   │   ├── components/         # Reusable components
│   │   ├── contexts/           # React Context (Auth, Cart)
│   │   ├── services/           # API services
│   │   ├── lib/                # Utilities
│   │   ├── styles/             # Global styles
│   │   └── layout.js           # Root layout
│   ├── public/
│   │   ├── manifest.json       # PWA manifest
│   │   └── icons/              # App icons
│   ├── next.config.js
│   ├── tailwind.config.js
│   ├── package.json
│   ├── .env.example
│   └── .env.local
│
├── docs/
│   ├── API.md                  # API Documentation
│   ├── DATABASE.md             # Schema & SQL
│   ├── ARCHITECTURE.md         # This file
│   └── DEPLOYMENT.md           # Deploy guide
│
├── SETUP.md                    # Quick start
├── docker-compose.yml          # Docker setup
└── README.md                   # Project overview
```

---

## 🔄 Flux de Données

### Création Commande (Client)

```
1. CLIENT FRONTEND
   └─ Browse Products
   └─ Add to Cart (localStorage)
   └─ Checkout

2. FRONTEND SERVICE
   └─ Valider cart
   └─ POST /orders avec items
   └─ Header: Authorization: Bearer {token}

3. BACKEND - OrderController
   └─ Auth middleware (token validation)
   └─ Valider items & adresse
   └─ Appeler OrderService::createOrder()

4. ORDERSERVICE (Transaction)
   └─ Vérifier stock
   └─ Calculer subtotal, taxes, frais
   └─ Appliquer promo si validée
   └─ Créer Order record
   └─ Ajouter OrderItems
   └─ Réserver créneau livraison
   └─ Créer notification

5. DATABASE
   └─ INSERT orders
   └─ INSERT order_items
   └─ UPDATE delivery_slots
   └─ INSERT notifications

6. RESPONSE
   └─ Renvoyer order avec status=PENDING

7. FRONTEND
   └─ Clear cart
   └─ Redirect vers /orders/{id} ou /checkout
```

### Workflow Commande Complète

```
PENDING (attente paiement)
  └─ Client effectue paiement
  └─ Webhook Mobile Money confirm
  └─ UPDATE order status=ACCEPTED
  └─ Notif préparateur disponible

ACCEPTED (acceptée par préparateur)
  └─ Préparateur clique "Accept"
  └─ UPDATE order: preparateur_id, status=ACCEPTED
  └─ Notif client "acceptée"

PREPARING (en préparation)
  └─ Préparateur débute prep
  └─ UPDATE status=PREPARING
  └─ Notif client "en préparation"

READY (prête à livrer)
  └─ Préparateur marque prête
  └─ UPDATE status=READY
  └─ Notif client "prête"

IN_DELIVERY (en livraison)
  └─ Livreur accepte livraison
  └─ UPDATE status=IN_DELIVERY
  └─ Livreur peut tracker GPS
  └─ Notif client "en route" + livreur

DELIVERED (livrée)
  └─ Livreur confirme + photo
  └─ UPDATE status=DELIVERED
  └─ Notif client "livrée"
  └─ Demande avis client

COMPLETED (complétée)
  └─ Payment confirmé totalement
  └─ UPDATE status=COMPLETED
  └─ Fermeture commande
```

---

## 🔐 Sécurité

### Authentication
- **Laravel Sanctum**: Token-based API auth
- **JWT-like**: Tokens stockés dans localStorage frontend
- **Expiration**: Tokens valides 24h, refresh possible
- **HTTPS**: Requis en production

### Authorization
- **Middleware**: Vérifier rôle utilisateur
- **Gate/Policy**: Permissions granulaires
- **Scopes**: Limiter accès selon role
- **Rate Limiting**: Throttle API par IP/user

### Data Protection
- **Password Hashing**: bcrypt (Laravel)
- **Input Validation**: Sanitization côté backend
- **SQL Injection**: Eloquent ORM paramétrisé
- **CORS**: Whitelist origins
- **Encryption**: Données sensibles chiffrées

---

## ⚡ Performance

### Frontend
- **Code Splitting**: Lazy loading routes (Next.js)
- **Image Optimization**: Next/Image component
- **Caching**: Service Worker (PWA)
- **Bundle**: Minified, Tree-shaken
- **CDN**: S3 pour assets statiques

### Backend
- **Database Indexes**: Sur colonnes queryées (status, user_id, etc)
- **Query Optimization**: Eager loading (with), select colonnes
- **Caching**: Redis pour sessions, queries fréquentes
- **Pagination**: Limiter resultsets (20-50 items)
- **Queue**: Jobs asynchrones (notifications, emails)

### Infrastructure
- **Horizontal Scaling**: Stateless Laravel instances
- **Load Balancing**: Nginx / AWS ALB
- **Auto-scaling**: Basé sur CPU/Memory
- **CDN**: CloudFlare / AWS CloudFront

---

## 🧪 Testing

### Backend (Laravel)
```bash
# Unit Tests
php artisan test --testsuite=Unit

# Feature Tests
php artisan test --testsuite=Feature

# Test Coverage
php artisan test --coverage
```

### Frontend (Next.js)
```bash
# Jest + React Testing Library
npm test

# E2E Tests (Cypress/Playwright)
npm run e2e
```

---

## 📡 API Communication

### RESTful Endpoints Pattern

```
GET    /api/v1/products          # List
GET    /api/v1/products/{id}     # Show
POST   /api/v1/products          # Create (admin)
PATCH  /api/v1/products/{id}     # Update (admin)
DELETE /api/v1/products/{id}     # Delete (admin)

GET    /api/v1/orders            # List (own orders)
POST   /api/v1/orders            # Create
GET    /api/v1/orders/{id}       # Show
DELETE /api/v1/orders/{id}       # Cancel
```

### WebSocket Events (Real-time)

```
Socket.io / Pusher

Events:
- order:status-updated
- delivery:location-updated
- notification:new
- order:payment-confirmed
- review:requested
```

---

## 🔄 Integrations

### External Services

1. **Mobile Money**
   - MTN Mobile Money API
   - Moov Money
   - Celtiis Cash
   - Status: Webhooks pour confirmation

2. **SMS Notifications**
   - Twilio API
   - ou local provider
   - Events: Order status, delivery alert

3. **Email**
   - SMTP (Mail host)
   - Queue: async via jobs
   - Templates: Order confirmation, invoice

4. **File Storage**
   - AWS S3 / Cloudflare R2
   - Photos livraison, invoices

---

## 🚀 Deployment

### Cloud Providers

- **Backend**: AWS EC2 / DigitalOcean / Heroku
- **Database**: AWS RDS PostgreSQL / DigitalOcean Managed
- **Frontend**: Vercel / Netlify / AWS S3 + CloudFront
- **Cache**: Redis (AWS ElastiCache / DigitalOcean)

### CI/CD Pipeline

```
Git Push → GitHub Actions
  └─ Run tests
  └─ Build Docker images
  └─ Push to registry
  └─ Deploy to production
  └─ Run migrations
  └─ Health checks
```

---

## 📊 Monitoring

- **Errors**: Sentry / Rollbar
- **Logs**: CloudWatch / ELK Stack
- **APM**: New Relic / DataDog
- **Uptime**: Pingdom / UptimeRobot
- **Analytics**: Google Analytics / Mixpanel

---

## Version: 1.0 | Juin 2026 | Bénin 🇧🇯

**Designed for Scalability, Performance, and West African Markets**
