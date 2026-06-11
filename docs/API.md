# 🔌 API REST Endpoints - Axizán

## Base URL
```
http://localhost:8000/api/v1
```

## Headers Requis
```
Content-Type: application/json
Authorization: Bearer {jwt_token}
Accept: application/json
```

## Response Format

### Success (200, 201)
```json
{
  "success": true,
  "status": "success",
  "data": { ... },
  "message": "Operation successful"
}
```

### Error (400, 401, 403, 404, 500)
```json
{
  "success": false,
  "status": "error",
  "error": "error_code",
  "message": "Detailed error message",
  "errors": { "field": ["error message"] }
}
```

---

## 🔐 AUTHENTIFICATION

### 1. Inscription
```
POST /auth/register
Content-Type: application/json

{
  "name": "Kofi Mensah",
  "email": "kofi@email.com",
  "phone": "+22965123456",
  "password": "SecurePass123!",
  "role": "client" // ou: preparateur, livreur, admin (invité)
}

Response (201):
{
  "data": {
    "id": 1,
    "name": "Kofi Mensah",
    "email": "kofi@email.com",
    "phone": "+22965123456",
    "role": "client"
  },
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

### 2. Connexion
```
POST /auth/login
Content-Type: application/json

{
  "email": "kofi@email.com",
  "password": "SecurePass123!"
}

Response (200):
{
  "data": {
    "id": 1,
    "name": "Kofi Mensah",
    "role": "client",
    "email": "kofi@email.com"
  },
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

### 3. Rafraîchir Token
```
POST /auth/refresh
Authorization: Bearer {old_token}

Response (200):
{
  "data": { ... },
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

### 4. Déconnexion
```
POST /auth/logout
Authorization: Bearer {jwt_token}

Response (200):
{
  "message": "Logged out successfully"
}
```

### 5. Profil Utilisateur
```
GET /auth/me
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": {
    "id": 1,
    "name": "Kofi Mensah",
    "email": "kofi@email.com",
    "phone": "+22965123456",
    "role": "client",
    "avatar_url": "https://...",
    "rating": 4.8,
    "kyc_verified": true
  }
}
```

---

## 👥 UTILISATEURS (Admin)

### 1. Lister Utilisateurs
```
GET /users?role=client&status=active&page=1&limit=20
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": [
    {
      "id": 1,
      "name": "Kofi Mensah",
      "email": "kofi@email.com",
      "phone": "+22965123456",
      "role": "client",
      "status": "active",
      "created_at": "2024-01-15T10:30:00Z",
      "total_orders": 15,
      "rating": 4.8
    }
  ],
  "pagination": {
    "total": 250,
    "per_page": 20,
    "current_page": 1,
    "last_page": 13
  }
}
```

### 2. Détail Utilisateur
```
GET /users/{id}
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": { ... user details ... }
}
```

### 3. Modifier Utilisateur
```
PATCH /users/{id}
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "name": "Kofi Mensah Updated",
  "avatar_url": "https://...",
  "bio": "Livreur fiable"
}

Response (200):
{
  "data": { ... updated user ... }
}
```

### 4. Bloquer Utilisateur
```
PATCH /users/{id}/status
Authorization: Bearer {jwt_token}

{
  "status": "blocked",
  "reason": "Comportement inapproprié"
}

Response (200):
{
  "data": { ... updated user ... }
}
```

### 5. Mettre à jour KYC
```
POST /users/{id}/kyc
Authorization: Bearer {jwt_token}
Content-Type: multipart/form-data

{
  "id_type": "passport",
  "id_number": "ABC123456",
  "document": <file>
}

Response (200):
{
  "data": {
    "kyc_verified": false,
    "kyc_document_url": "https://...",
    "message": "Document envoyé pour vérification"
  }
}
```

---

## 🛒 PRODUITS & CATALOGUE

### 1. Lister Produits
```
GET /products?category=1&available=true&sort=popularity&page=1&limit=20
Authorization: Bearer {jwt_token}

Query Params:
- category (int) - ID catégorie
- search (string) - Recherche texte
- available (bool) - Seulement disponibles
- bestsellers (bool) - Bestsellers
- sort (string) - 'popularity', 'price_asc', 'price_desc', 'newest'
- page (int) - Numéro page
- limit (int) - Items par page

Response (200):
{
  "data": [
    {
      "id": 5,
      "name": "Riz blanc premium",
      "description": "Riz blanc de haute qualité",
      "category": { "id": 2, "name": "Céréales" },
      "price_type": "kg",
      "base_price": 800,
      "image_url": "https://...",
      "is_available": true,
      "is_bestseller": true,
      "popularity_score": 95,
      "preparation_time_minutes": 0
    }
  ],
  "pagination": { ... }
}
```

### 2. Détail Produit
```
GET /products/{id}
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": {
    "id": 5,
    "name": "Riz blanc premium",
    "description": "Riz blanc de haute qualité",
    "category": { "id": 2, "name": "Céréales" },
    "price_type": "kg",
    "base_price": 800,
    "stock_quantity": 150.5,
    "low_stock_threshold": 5,
    "image_url": "https://...",
    "thumbnail_url": "https://...",
    "is_available": true,
    "is_bestseller": true,
    "preparation_time_minutes": 0
  }
}
```

### 3. Lister Catégories
```
GET /categories
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": [
    {
      "id": 1,
      "name": "Fruits",
      "icon_emoji": "🍎",
      "image_url": "https://...",
      "display_order": 1,
      "is_active": true
    },
    {
      "id": 2,
      "name": "Céréales",
      "icon_emoji": "🌾",
      "display_order": 2,
      "is_active": true
    }
  ]
}
```

### 4. Créer Produit (Admin)
```
POST /products
Authorization: Bearer {jwt_token}
Content-Type: multipart/form-data

{
  "name": "Poulet frais",
  "description": "Poulet fermier frais",
  "category_id": 3,
  "price_type": "kg",
  "base_price": 3500,
  "image": <file>,
  "stock_quantity": 50,
  "is_available": true
}

Response (201):
{
  "data": { ... created product ... }
}
```

### 5. Modifier Produit (Admin)
```
PATCH /products/{id}
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "base_price": 3600,
  "stock_quantity": 45,
  "is_available": true
}

Response (200):
{
  "data": { ... updated product ... }
}
```

### 6. Supprimer Produit (Admin)
```
DELETE /products/{id}
Authorization: Bearer {jwt_token}

Response (204): No Content
```

---

## 🍽️ MENUS

### 1. Lister Menus
```
GET /menus?category=1&featured=false&page=1&limit=20
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": [
    {
      "id": 10,
      "name": "Menu Complet Béninois",
      "description": "Riz, sauce arachide, viande",
      "category": { "id": 2, "name": "Menus" },
      "base_price": 2500,
      "discount_percentage": 10,
      "discounted_price": 2250,
      "image_url": "https://...",
      "is_available": true,
      "daily_limit": 100,
      "daily_sold": 45,
      "is_featured": true,
      "items": [
        {
          "product_id": 5,
          "product_name": "Riz blanc",
          "quantity": 0.25,
          "quantity_unit": "kg"
        },
        {
          "product_id": 12,
          "product_name": "Sauce arachide",
          "quantity": 0.3,
          "quantity_unit": "l"
        }
      ]
    }
  ],
  "pagination": { ... }
}
```

### 2. Détail Menu
```
GET /menus/{id}
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": { ... menu details avec items ... }
}
```

### 3. Créer Menu (Admin)
```
POST /menus
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "name": "Menu Rapide",
  "description": "Prêt en 15 minutes",
  "category_id": 2,
  "base_price": 1800,
  "discount_percentage": 0,
  "image": <file>,
  "preparation_time_minutes": 15,
  "items": [
    {
      "product_id": 5,
      "quantity": 0.2,
      "quantity_unit": "kg"
    },
    {
      "product_id": 8,
      "quantity": 1,
      "quantity_unit": "piece"
    }
  ]
}

Response (201):
{
  "data": { ... created menu ... }
}
```

### 4. Modifier Menu (Admin)
```
PATCH /menus/{id}
Authorization: Bearer {jwt_token}

{
  "base_price": 1900,
  "discount_percentage": 5,
  "items": [ ... ]
}

Response (200):
{
  "data": { ... updated menu ... }
}
```

---

## 🛍️ PANIER & COMMANDES CLIENT

### 1. Obtenir Panier
```
GET /cart
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": {
    "session_id": "sess_123",
    "items": [
      {
        "id": "cart_item_1",
        "product_id": 5,
        "product_name": "Riz blanc",
        "type": "product",
        "quantity": 2,
        "quantity_unit": "kg",
        "unit_price": 800,
        "total_price": 1600
      },
      {
        "id": "cart_item_2",
        "product_id": null,
        "menu_id": 10,
        "menu_name": "Menu Complet",
        "type": "menu",
        "quantity": 1,
        "unit_price": 2250,
        "total_price": 2250,
        "removed_ingredients": ["arachide"]
      }
    ],
    "subtotal": 3850,
    "delivery_fee": 0,
    "tax": 0,
    "discount_amount": 0,
    "total": 3850
  }
}
```

### 2. Ajouter au Panier
```
POST /cart/items
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "product_id": 5,          // OU menu_id
  "quantity": 2,
  "quantity_unit": "kg",
  "special_instructions": "",
  "removed_ingredients": [] // Pour menus
}

Response (200):
{
  "data": { ... updated cart ... }
}
```

### 3. Modifier Item Panier
```
PATCH /cart/items/{cart_item_id}
Authorization: Bearer {jwt_token}

{
  "quantity": 3,
  "removed_ingredients": ["oignon"]
}

Response (200):
{
  "data": { ... updated cart ... }
}
```

### 4. Supprimer Item Panier
```
DELETE /cart/items/{cart_item_id}
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": { ... updated cart ... }
}
```

### 5. Appliquer Code Promo
```
POST /cart/apply-promo
Authorization: Bearer {jwt_token}

{
  "promo_code": "BIENVENUE20"
}

Response (200):
{
  "data": {
    "message": "Promo appliquée",
    "discount_amount": 500,
    "new_total": 3350,
    "promo": {
      "code": "BIENVENUE20",
      "description": "20% de réduction sur première commande"
    }
  }
}
```

### 6. Enlever Code Promo
```
DELETE /cart/promo
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": { ... updated cart ... }
}
```

### 7. Créer Commande
```
POST /orders
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "delivery_address_id": 3,
  "delivery_slot_id": 15,
  "delivery_date": "2024-01-20",
  "payment_method": "mobile_money",
  "client_notes": "Livrer après 18h SVP"
}

Response (201):
{
  "data": {
    "id": 124,
    "order_number": "AXZ-2024-124",
    "client_id": 1,
    "status": "pending",
    "delivery_address": { ... },
    "delivery_slot_time": "14:00-16:00",
    "delivery_date": "2024-01-20",
    "items": [ ... ],
    "subtotal": 3850,
    "delivery_fee": 500,
    "total": 4350,
    "payment_method": "mobile_money",
    "payment_status": "pending",
    "created_at": "2024-01-18T10:30:00Z"
  }
}
```

### 8. Lister Commandes Client
```
GET /orders?status=pending,preparing,ready&page=1&limit=10
Authorization: Bearer {jwt_token}

Query Params:
- status (string) - Statuts séparés par virgule
- sort (string) - 'newest', 'oldest'
- page (int)
- limit (int)

Response (200):
{
  "data": [
    {
      "id": 124,
      "order_number": "AXZ-2024-124",
      "status": "preparing",
      "total": 4350,
      "delivery_date": "2024-01-20",
      "items_count": 3,
      "created_at": "2024-01-18T10:30:00Z"
    }
  ],
  "pagination": { ... }
}
```

### 9. Détail Commande
```
GET /orders/{id}
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": {
    "id": 124,
    "order_number": "AXZ-2024-124",
    "client": { ... },
    "status": "in_delivery",
    "items": [ ... ],
    "delivery": {
      "livreur": { "id": 5, "name": "Adama", "phone": "+229..." },
      "current_latitude": 6.4897,
      "current_longitude": 2.6289,
      "last_location_update": "2024-01-20T15:45:00Z"
    },
    "timeline": [
      {
        "status": "accepted",
        "timestamp": "2024-01-18T11:00:00Z",
        "message": "Commande acceptée"
      },
      {
        "status": "preparing",
        "timestamp": "2024-01-18T11:15:00Z",
        "message": "En préparation"
      },
      {
        "status": "ready",
        "timestamp": "2024-01-18T12:30:00Z",
        "message": "Prête pour livraison"
      }
    ]
  }
}
```

### 10. Annuler Commande
```
DELETE /orders/{id}
Authorization: Bearer {jwt_token}

{
  "reason": "Changement d'avis"
}

Response (200):
{
  "data": {
    "id": 124,
    "status": "cancelled",
    "message": "Commande annulée"
  }
}
```

---

## 📍 ADRESSES

### 1. Lister Adresses
```
GET /addresses
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": [
    {
      "id": 3,
      "label": "Maison",
      "quartier": "Cadjehoun",
      "rue": "Rue de la Paix",
      "numero_rue": "45B",
      "latitude": 6.4897,
      "longitude": 2.6289,
      "landmark": "À côté du marché",
      "detailed_instructions": "Sonnez 3 fois",
      "is_default": true,
      "is_active": true
    }
  ]
}
```

### 2. Créer Adresse
```
POST /addresses
Authorization: Bearer {jwt_token}

{
  "label": "Bureau",
  "quartier": "Cadjehoun",
  "rue": "Avenue Clozel",
  "numero_rue": "102",
  "latitude": 6.4912,
  "longitude": 2.6341,
  "landmark": "Face banco",
  "detailed_instructions": "Escalier au fond"
}

Response (201):
{
  "data": { ... created address ... }
}
```

### 3. Modifier Adresse
```
PATCH /addresses/{id}
Authorization: Bearer {jwt_token}

{
  "detailed_instructions": "Sonnez deux fois"
}

Response (200):
{
  "data": { ... updated address ... }
}
```

### 4. Définir Adresse par Défaut
```
PATCH /addresses/{id}/set-default
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": { ... updated address ... }
}
```

### 5. Supprimer Adresse
```
DELETE /addresses/{id}
Authorization: Bearer {jwt_token}

Response (204): No Content
```

---

## ⏰ CRÉNEAUX DE LIVRAISON

### 1. Lister Créneaux
```
GET /delivery-slots?zone_id=1&date=2024-01-20
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": [
    {
      "id": 10,
      "zone": { "id": 1, "name": "Cotonou Centre" },
      "day_of_week": 6, // 6 = samedi
      "time_start": "09:00",
      "time_end": "11:00",
      "display_label": "09:00 - 11:00",
      "max_orders_per_slot": 50,
      "current_orders_count": 32,
      "available_slots": 18,
      "is_available": true
    },
    {
      "id": 11,
      "zone": { "id": 1, "name": "Cotonou Centre" },
      "time_start": "14:00",
      "time_end": "16:00",
      "display_label": "14:00 - 16:00",
      "max_orders_per_slot": 50,
      "current_orders_count": 50,
      "available_slots": 0,
      "is_available": false
    }
  ]
}
```

### 2. Zones de Livraison
```
GET /delivery-zones
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": [
    {
      "id": 1,
      "name": "Cotonou Centre",
      "base_delivery_fee": 1000,
      "is_active": true
    },
    {
      "id": 2,
      "name": "Cadjehoun",
      "base_delivery_fee": 1200,
      "is_active": true
    }
  ]
}
```

---

## 💳 PAIEMENTS

### 1. Initier Paiement Mobile Money
```
POST /payments/initiate
Authorization: Bearer {jwt_token}

{
  "order_id": 124,
  "payment_method": "mobile_money",
  "payment_provider": "mtn",
  "phone_number": "+22965123456",
  "amount": 4350
}

Response (200):
{
  "data": {
    "payment_id": 234,
    "transaction_id": "TXN-2024-001",
    "status": "processing",
    "ussd_code": "*126#",
    "message": "Entrez le code USSD ou attendez le popup"
  }
}
```

### 2. Vérifier Paiement
```
GET /payments/{payment_id}/status
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": {
    "payment_id": 234,
    "status": "completed",
    "transaction_id": "TXN-2024-001",
    "amount": 4350,
    "completed_at": "2024-01-18T10:45:00Z"
  }
}
```

### 3. Historique Paiements
```
GET /payments?status=completed&page=1&limit=20
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": [
    {
      "id": 234,
      "order_id": 124,
      "amount": 4350,
      "payment_method": "mobile_money",
      "payment_provider": "mtn",
      "status": "completed",
      "transaction_id": "TXN-2024-001",
      "completed_at": "2024-01-18T10:45:00Z"
    }
  ],
  "pagination": { ... }
}
```

---

## 🍽️ PRÉPARATEUR

### 1. Lister Commandes Disponibles
```
GET /preparateur/orders?status=pending,accepted
Authorization: Bearer {jwt_token}
X-User-Role: preparateur

Response (200):
{
  "data": [
    {
      "id": 124,
      "order_number": "AXZ-2024-124",
      "client": { "name": "Kofi", "phone": "+229..." },
      "items": [
        { "product_name": "Riz", "quantity": 2, "unit": "kg" },
        { "product_name": "Sauce", "quantity": 0.5, "unit": "l" }
      ],
      "estimated_prep_time": 45,
      "delivery_time": "14:00-16:00",
      "created_at": "2024-01-18T10:30:00Z"
    }
  ]
}
```

### 2. Accepter Commande
```
PATCH /preparateur/orders/{id}/accept
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": {
    "id": 124,
    "status": "accepted",
    "preparateur_id": 3,
    "accepted_at": "2024-01-18T10:35:00Z"
  }
}
```

### 3. Marquer Comme En Préparation
```
PATCH /preparateur/orders/{id}/start-preparing
Authorization: Bearer {jwt_token}

{
  "preparateur_notes": "Achat au marché en cours"
}

Response (200):
{
  "data": {
    "id": 124,
    "status": "preparing",
    "preparing_started_at": "2024-01-18T10:40:00Z"
  }
}
```

### 4. Marquer Comme Prête
```
PATCH /preparateur/orders/{id}/mark-ready
Authorization: Bearer {jwt_token}

{
  "preparateur_notes": "Commande prête pour livraison"
}

Response (200):
{
  "data": {
    "id": 124,
    "status": "ready",
    "ready_at": "2024-01-18T12:30:00Z"
  }
}
```

### 5. Mes Stats
```
GET /preparateur/stats
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": {
    "total_orders_today": 12,
    "total_orders_completed": 8,
    "average_prep_time_minutes": 42,
    "total_earnings_today": 25000,
    "rating": 4.7
  }
}
```

---

## 🚚 LIVREUR

### 1. Lister Commandes Prêtes
```
GET /livreur/deliveries?status=ready,in_transit
Authorization: Bearer {jwt_token}
X-User-Role: livreur

Response (200):
{
  "data": [
    {
      "id": 156,
      "order_id": 124,
      "order_number": "AXZ-2024-124",
      "client": {
        "name": "Kofi",
        "phone": "+22965123456"
      },
      "delivery_address": {
        "quartier": "Cadjehoun",
        "rue": "Rue de la Paix",
        "numero_rue": "45B",
        "latitude": 6.4897,
        "longitude": 2.6289,
        "landmark": "À côté du marché",
        "detailed_instructions": "Sonnez 3 fois"
      },
      "total": 4350,
      "status": "ready",
      "ready_at": "2024-01-18T12:30:00Z"
    }
  ]
}
```

### 2. Accepter Livraison
```
PATCH /livreur/deliveries/{id}/accept
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": {
    "id": 156,
    "status": "picked_up",
    "order_id": 124,
    "picked_up_at": "2024-01-18T13:00:00Z"
  }
}
```

### 3. Mettre à Jour Position GPS
```
POST /livreur/deliveries/{id}/update-location
Authorization: Bearer {jwt_token}

{
  "latitude": 6.4912,
  "longitude": 2.6341
}

Response (200):
{
  "data": {
    "id": 156,
    "current_latitude": 6.4912,
    "current_longitude": 2.6341,
    "last_location_update": "2024-01-18T14:15:00Z"
  }
}
```

### 4. Marquer Comme En Livraison
```
PATCH /livreur/deliveries/{id}/in-transit
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": {
    "id": 156,
    "status": "in_transit",
    "delivery_started_at": "2024-01-18T14:00:00Z"
  }
}
```

### 5. Confirmer Livraison + Photo Preuve
```
POST /livreur/deliveries/{id}/confirm
Authorization: Bearer {jwt_token}
Content-Type: multipart/form-data

{
  "proof_photo": <file>,
  "delivery_notes": "Livré à la personne",
  "signature_data": "<svg>...</svg>"
}

Response (200):
{
  "data": {
    "id": 156,
    "status": "delivered",
    "delivery_time": "2024-01-18T14:45:00Z",
    "proof_photo_url": "https://...",
    "order_status": "delivered"
  }
}
```

### 6. Mes Stats Livreur
```
GET /livreur/stats
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": {
    "total_deliveries_today": 8,
    "successful_deliveries": 7,
    "failed_deliveries": 1,
    "total_earnings_today": 35000,
    "average_rating": 4.8,
    "total_distance_km": 24.5
  }
}
```

### 7. Historique Livraisons
```
GET /livreur/deliveries/history?date=2024-01-18&page=1
Authorization: Bearer {jwt_token}

Response (200):
{
  "data": [
    {
      "id": 156,
      "order_number": "AXZ-2024-124",
      "client_name": "Kofi",
      "total": 4350,
      "status": "delivered",
      "delivery_time": "2024-01-18T14:45:00Z",
      "client_rating": 5
    }
  ],
  "pagination": { ... }
}
```

---

## 📊 ADMIN DASHBOARD

### 1. Dashboard Statistiques
```
GET /admin/dashboard?period=today
Authorization: Bearer {jwt_token}
X-User-Role: admin

Query Params:
- period: 'today', 'week', 'month', 'year'

Response (200):
{
  "data": {
    "summary": {
      "total_orders": 124,
      "total_revenue": 520000,
      "completed_orders": 118,
      "pending_orders": 6,
      "new_users": 12,
      "active_users": 342
    },
    "orders": {
      "by_status": {
        "pending": 2,
        "accepted": 1,
        "preparing": 3,
        "ready": 5,
        "in_delivery": 8,
        "delivered": 105,
        "completed": 100,
        "cancelled": 3
      },
      "total_orders": 124,
      "completed_percentage": 83,
      "average_order_value": 4194
    },
    "revenue": {
      "total": 520000,
      "breakdown": {
        "food_sales": 480000,
        "delivery_fees": 40000,
        "taxes": 0
      }
    },
    "users": {
      "clients": { "total": 250, "new": 8 },
      "preparateurs": { "total": 15, "active": 14 },
      "livreurs": { "total": 25, "active": 23 },
      "admins": { "total": 3 }
    },
    "top_products": [
      { "id": 5, "name": "Riz blanc", "quantity_sold": 245, "revenue": 196000 }
    ],
    "top_menus": [
      { "id": 10, "name": "Menu Complet", "quantity_sold": 45, "revenue": 101250 }
    ],
    "payment_methods": {
      "mobile_money": 95,
      "cash": 18,
      "card": 11
    },
    "delivery_performance": {
      "average_delivery_time_minutes": 35,
      "on_time_percentage": 89,
      "failed_deliveries": 3
    }
  }
}
```

### 2. Gestion Produits (CRUD complet dans les endpoints products)
### 3. Gestion Menus (CRUD complet dans les endpoints menus)

### 4. Gestion Promotions
```
GET /admin/promotions
POST /admin/promotions
PATCH /admin/promotions/{id}
DELETE /admin/promotions/{id}
```

### 5. Gestion Utilisateurs
```
GET /admin/users
PATCH /admin/users/{id}/status
POST /admin/users/{id}/kyc/verify
```

### 6. Rapports
```
GET /admin/reports/orders?date_from=2024-01-01&date_to=2024-01-31
GET /admin/reports/revenue?period=month
GET /admin/reports/deliveries
GET /admin/reports/users
```

---

## 🔄 WEBSOCKET EVENTS (Real-time)

```javascript
// Client subscribe
socket.on('order:status-updated', (data) => {
  // { order_id: 124, status: 'ready', timestamp: '...' }
})

socket.on('delivery:location-updated', (data) => {
  // { delivery_id: 156, latitude: 6.49, longitude: 2.63, timestamp: '...' }
})

socket.on('notification:new', (data) => {
  // { title: '...', message: '...' }
})
```

---

## 🚨 Codes d'Erreur

| Code | Signification | HTTP |
|------|-------------|------|
| `invalid_credentials` | Email/password incorrect | 401 |
| `token_expired` | JWT token expiré | 401 |
| `unauthorized` | Non authentifié | 401 |
| `forbidden` | Pas de permission | 403 |
| `not_found` | Ressource inexistante | 404 |
| `validation_error` | Validation échouée | 400 |
| `already_exists` | Ressource existe déjà | 400 |
| `promo_invalid` | Code promo invalide/expiré | 400 |
| `delivery_slot_full` | Créneau plein | 400 |
| `insufficient_stock` | Stock insuffisant | 400 |
| `payment_failed` | Paiement échoué | 402 |
| `server_error` | Erreur serveur | 500 |

---

**Version**: 1.0 | Date: Juin 2026 | Production-Ready
