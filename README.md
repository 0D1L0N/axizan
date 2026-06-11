# 🍲 Axizán - Plateforme de Livraison Alimentaire Bénin

Application complète de livraison de produits alimentaires avec support multi-rôles (client, préparateur, livreur, admin).

## 📋 Architecture Globale

```
Axizán (3 couches)
├── Frontend (Next.js + Tailwind + PWA)
├── Backend API (Laravel REST)
└── Database (PostgreSQL)

4 Rôles:
├── Client (mobile app, commandes, tracking)
├── Préparateur (commandes, achat marché)
├── Livreur (livraisons, photos preuve)
└── Admin (dashboard, gestion système)
```

## 🗂️ Structure du Projet

```
axizan/
├── backend/                 # Laravel API
│   ├── app/
│   │   ├── Http/
│   │   │   └── Controllers/
│   │   ├── Models/
│   │   ├── Services/
│   │   └── Enums/
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── routes/
│   ├── config/
│   └── .env.example
├── frontend/                # Next.js App
│   ├── app/
│   │   ├── (auth)/
│   │   ├── (client)/
│   │   ├── (admin)/
│   │   ├── components/
│   │   └── services/
│   ├── public/
│   ├── tailwind.config.js
│   └── .env.example
└── docs/
    ├── API.md
    ├── DATABASE.md
    └── SETUP.md
```

## 🚀 Stack Technique

- **Backend**: Laravel 11, PHP 8.2+
- **Frontend**: Next.js 15, React 19, Tailwind CSS
- **Database**: PostgreSQL 15+
- **Cache**: Redis (optionnel)
- **File Storage**: S3 compatible (AWS S3 / Cloudflare R2)
- **Payments**: Mobile Money API (MTN, Celtiis)
- **SMS**: Twilio / Local SMS provider
- **Real-time**: Laravel Echo + Pusher / Soketi

## 📦 Modules Backend

1. **Auth** - Authentification multi-rôle, JWT
2. **Users** - Gestion utilisateurs par rôle
3. **Products** - Catalogue, catégories, prix
4. **Menus** - Templates menus modifiables
5. **Orders** - Workflow complet commande
6. **Delivery** - Zones, créneaux, assignation
7. **Payments** - Mobile Money, factures
8. **Promotions** - Codes promo, règles
9. **Notifications** - SMS, in-app, email
10. **Analytics** - Dashboard admin

## 📱 Modules Frontend

1. **Client App**
   - Catalogue produits
   - Menus rapides
   - Panier modifiable
   - Adresses et livraison
   - Paiement Mobile Money
   - Suivi commande temps réel

2. **Préparateur**
   - Liste commandes disponibles
   - Prise en charge
   - Marché (achat)
   - Validation prêt

3. **Livreur**
   - Commandes prêtes
   - Navigation
   - Upload photo preuve
   - Confirmation livraison

4. **Admin**
   - Dashboard KPI
   - Gestion produits
   - Gestion menus
   - Gestion promotions
   - Gestion utilisateurs
   - Statistiques

## 🔄 Workflow Commande

```
CREATION → ACCEPTED → PREPARING → READY → IN_DELIVERY → DELIVERED → COMPLETED
             ↓          ↓          ↓        ↓            ↓
        (Client)  (Préparateur) (Admin)  (Livreur)   (Système)
```

## 📊 Modèle de Données

Voir [DATABASE.md](docs/DATABASE.md) pour le schéma complet.

### Tables principales:
- `users` - Utilisateurs avec rôles
- `products` - Catalogue produits
- `menus` - Templates menus
- `orders` - Commandes
- `order_items` - Items commande
- `addresses` - Adresses livraison
- `delivery_slots` - Créneaux horaires
- `deliveries` - Assignations livreur
- `payments` - Historique paiements
- `promotions` - Codes promo
- `notifications` - Logs notifications

## 🔐 Sécurité

- JWT Authentication
- Role-Based Access Control (RBAC)
- Input validation & sanitization
- SQL injection prevention
- CORS configuration
- Rate limiting API
- HTTPS only (production)

## 📡 API Endpoints

Voir [API.md](docs/API.md) pour tous les endpoints.

### Exemples:
```
POST   /api/auth/register          - Inscription
POST   /api/auth/login             - Connexion
GET    /api/products               - Liste produits
POST   /api/orders                 - Créer commande
GET    /api/orders/{id}            - Détail commande
PUT    /api/orders/{id}/status     - MAJ statut
GET    /api/admin/dashboard        - Dashboard admin
```

## ⚡ Démarrage Rapide

### Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

### Frontend

```bash
cd frontend
npm install
npm run dev
```

Frontend accessible: http://localhost:3000
Backend API: http://localhost:8000/api

## 📱 PWA Installation

L'app est installable sur mobile:
1. Ouvrir sur mobile
2. Menu → "Installer l'app"
3. Icône sur écran d'accueil

## 🧪 Testing

```bash
cd backend
php artisan test

cd ../frontend
npm run test
```

## 📈 Performance

- Pagination des listes (20 items/page)
- Cache Redis (sessions, queries)
- Image optimization (WebP, srcset)
- Code splitting (Next.js)
- Lazy loading composants
- Database indexing (queries optimisées)

## 🤝 Contributing

1. Fork le repo
2. Feature branch (`git checkout -b feature/amazing`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push (`git push origin feature/amazing`)
5. Pull request

## 📄 License

MIT License - voir LICENSE.md

## 🆘 Support

- Issues: GitHub Issues
- Documentation: /docs
- Email: support@axizan.bj

---

**Axizán** - Livraison alimentaire moderne au Bénin 🇧🇯
