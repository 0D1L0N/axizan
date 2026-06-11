# Axizán Project Structure

```
axizan/                              # Root project directory
│
├── 📄 README.md                    # Project overview
├── 📄 SETUP.md                     # Quick start guide
├── 📄 docker-compose.yml           # Docker configuration
├── 📄 install.sh                   # Installation script
├── 📄 .gitignore                   # Git ignore rules
│
├── backend/                         # Laravel Backend (Port 8000)
│   ├── app/
│   │   ├── Enums/                 # Enumerations (UserRole, OrderStatus, etc)
│   │   │   ├── UserRole.php
│   │   │   ├── OrderStatus.php
│   │   │   ├── PaymentStatus.php
│   │   │   ├── DeliveryStatus.php
│   │   │   └── NotificationType.php
│   │   │
│   │   ├── Models/                # Eloquent ORM Models
│   │   │   ├── User.php
│   │   │   ├── Address.php
│   │   │   ├── Product.php
│   │   │   ├── Category.php
│   │   │   ├── Menu.php
│   │   │   ├── MenuItem.php
│   │   │   ├── Order.php
│   │   │   ├── OrderItem.php
│   │   │   ├── DeliveryZone.php
│   │   │   ├── DeliverySlot.php
│   │   │   ├── Delivery.php
│   │   │   ├── Payment.php
│   │   │   ├── Promotion.php
│   │   │   ├── PromotionUsage.php
│   │   │   ├── Notification.php
│   │   │   ├── Review.php
│   │   │   ├── Invoice.php
│   │   │   └── AuditLog.php
│   │   │
│   │   ├── Http/
│   │   │   └── Controllers/Api/   # REST API Controllers
│   │   │       ├── AuthController.php
│   │   │       ├── ProductController.php
│   │   │       ├── OrderController.php
│   │   │       ├── DeliveryController.php
│   │   │       ├── PaymentController.php
│   │   │       └── AdminController.php (TODO)
│   │   │
│   │   ├── Services/              # Business Logic Layer
│   │   │   ├── OrderService.php
│   │   │   ├── PaymentService.php
│   │   │   ├── DeliveryService.php
│   │   │   ├── NotificationService.php
│   │   │   └── CartService.php (TODO)
│   │   │
│   │   ├── Listeners/             # Event Listeners
│   │   ├── Jobs/                  # Queued Jobs
│   │   └── Middleware/
│   │
│   ├── database/
│   │   ├── migrations/            # Database migrations
│   │   └── seeders/               # Seed data
│   │       ├── CategorySeeder.php
│   │       ├── ProductSeeder.php
│   │       └── DeliveryZoneSeeder.php
│   │
│   ├── routes/
│   │   └── api.php                # API routes definition
│   │
│   ├── config/                    # Configuration files
│   ├── tests/                     # Unit & Feature tests
│   ├── storage/                   # Log & Cache storage
│   ├── bootstrap/                 # Bootstrap files
│   │
│   ├── artisan                    # Laravel CLI
│   ├── composer.json              # PHP dependencies
│   ├── .env.example               # Example environment
│   └── .env                       # Environment variables (local)
│
├── frontend/                       # Next.js Frontend (Port 3000)
│   ├── app/
│   │   ├── (auth)/               # Authentication routes
│   │   │   ├── login/
│   │   │   │   └── page.js
│   │   │   └── register/
│   │   │       └── page.js
│   │   │
│   │   ├── (client)/             # Client app routes
│   │   │   ├── cart/
│   │   │   │   └── page.js
│   │   │   ├── orders/
│   │   │   │   ├── page.js
│   │   │   │   └── [id]/
│   │   │   │       └── page.js
│   │   │   ├── addresses/
│   │   │   ├── checkout/
│   │   │   └── profile/
│   │   │
│   │   ├── (admin)/              # Admin routes (optional)
│   │   │   ├── dashboard/
│   │   │   ├── products/
│   │   │   ├── menus/
│   │   │   ├── promotions/
│   │   │   └── users/
│   │   │
│   │   ├── components/            # Reusable React components
│   │   │   ├── Header.js
│   │   │   ├── ProductCard.js
│   │   │   ├── OrderCard.js
│   │   │   ├── CartItem.js
│   │   │   ├── Button.js
│   │   │   ├── Modal.js
│   │   │   ├── Loading.js
│   │   │   └── Footer.js
│   │   │
│   │   ├── contexts/              # React Context (State)
│   │   │   ├── AuthContext.js     # User auth state
│   │   │   └── CartContext.js     # Shopping cart state
│   │   │
│   │   ├── services/              # API Service Layer
│   │   │   ├── api.js            # Axios instance
│   │   │   └── index.js          # Service exports
│   │   │
│   │   ├── lib/                  # Utility functions
│   │   │   ├── auth-hoc.js       # Auth HOCs
│   │   │   ├── helpers.js
│   │   │   └── constants.js
│   │   │
│   │   ├── styles/               # Global styles
│   │   │   └── globals.css
│   │   │
│   │   ├── layout.js             # Root layout
│   │   ├── page.js               # Home page
│   │   └── not-found.js          # 404 page
│   │
│   ├── public/
│   │   ├── manifest.json         # PWA manifest
│   │   ├── icons/                # App icons
│   │   └── images/
│   │
│   ├── .env.example              # Example environment
│   ├── .env.local                # Environment variables (local)
│   ├── next.config.js            # Next.js configuration
│   ├── tailwind.config.js        # Tailwind CSS config
│   ├── package.json              # NPM dependencies
│   └── .gitignore
│
├── docs/                          # Documentation
│   ├── API.md                    # Complete API documentation
│   ├── DATABASE.md               # Database schema & migrations
│   ├── ARCHITECTURE.md           # Technical architecture
│   ├── DEPLOYMENT.md             # Deployment guide (TODO)
│   ├── CONTRIBUTING.md           # Contribution guidelines (TODO)
│   └── CHANGELOG.md              # Version history (TODO)
│
└── .gitignore                    # Root .gitignore
```

---

## 📊 File Count Summary

| Directory | Files | Purpose |
|-----------|-------|---------|
| Backend Models | 18 | Database entities |
| Backend Services | 4 | Business logic |
| Backend Controllers | 5 | API endpoints |
| Frontend Components | 8+ | UI components |
| Frontend Pages | 10+ | App routes |
| Documentation | 4 | Guides & specs |

---

## 🔄 Key Relationships

```
Users ──1:N──> Orders
       ──1:N──> Addresses
       ──1:N──> Deliveries
       ──1:N──> Reviews

Orders ──1:N──> OrderItems
      ──1:1──> Delivery
      ──1:1──> Payment
      ──1:1──> Invoice
      ──N:1──> DeliverySlot

Products ──N:1──> Category
        ──N:M──> Menus (via MenuItems)

Deliveries ──1:1──> Order
          ──N:1──> User (livreur)
          ──N:1──> Review
```

---

**Total Files**: ~100+ | **Languages**: PHP, JavaScript/React | **LOC**: ~10,000+
