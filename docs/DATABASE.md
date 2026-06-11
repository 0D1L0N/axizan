# 🗄️ Schéma Base de Données - Axizán

## Modèle de Données Complet

### 1. USERS - Gestion Utilisateurs

```sql
CREATE TABLE users (
  id SERIAL PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  phone VARCHAR(20) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('client', 'preparateur', 'livreur', 'admin') NOT NULL,
  status ENUM('active', 'inactive', 'blocked') DEFAULT 'active',
  
  -- Profil
  avatar_url VARCHAR(255),
  bio TEXT,
  kyc_verified BOOLEAN DEFAULT FALSE,
  kyc_document_url VARCHAR(255),
  
  -- Stats
  total_orders INT DEFAULT 0,
  total_deliveries INT DEFAULT 0,
  rating DECIMAL(3,2) DEFAULT 0,
  
  -- Timestamps
  email_verified_at TIMESTAMP,
  phone_verified_at TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  
  INDEX idx_role (role),
  INDEX idx_status (status),
  INDEX idx_email (email),
  INDEX idx_phone (phone)
);
```

### 2. ADDRESSES - Adresses de Livraison

```sql
CREATE TABLE addresses (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL,
  label VARCHAR(100) NOT NULL, -- "Maison", "Bureau", etc
  
  -- Localisation
  quartier VARCHAR(255) NOT NULL,
  rue VARCHAR(255),
  numero_rue VARCHAR(50),
  latitude DECIMAL(10,8),
  longitude DECIMAL(11,8),
  
  -- Instructions
  detailed_instructions TEXT,
  landmark VARCHAR(255), -- "à côté de...")
  
  is_default BOOLEAN DEFAULT FALSE,
  is_active BOOLEAN DEFAULT TRUE,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_coordinates (latitude, longitude)
);
```

### 3. PRODUCTS - Catalogue Produits

```sql
CREATE TABLE products (
  id SERIAL PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  category_id INT NOT NULL,
  
  -- Types et Prix
  price_type ENUM('kg', 'amount', 'unit') DEFAULT 'unit',
  base_price DECIMAL(10,2) NOT NULL,
  
  -- Stock et Disponibilité
  stock_quantity DECIMAL(10,3),
  low_stock_threshold INT DEFAULT 5,
  is_available BOOLEAN DEFAULT TRUE,
  
  -- Image
  image_url VARCHAR(255),
  thumbnail_url VARCHAR(255),
  
  -- Métadonnées
  preparation_time_minutes INT DEFAULT 30,
  is_bestseller BOOLEAN DEFAULT FALSE,
  popularity_score INT DEFAULT 0,
  
  -- Admin
  created_by_id INT,
  status ENUM('active', 'discontinued') DEFAULT 'active',
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (created_by_id) REFERENCES users(id),
  INDEX idx_category_id (category_id),
  INDEX idx_available (is_available),
  INDEX idx_bestseller (is_bestseller)
);
```

### 4. CATEGORIES - Catégories de Produits

```sql
CREATE TABLE categories (
  id SERIAL PRIMARY KEY,
  name VARCHAR(255) NOT NULL UNIQUE,
  description TEXT,
  icon_emoji VARCHAR(10),
  image_url VARCHAR(255),
  display_order INT DEFAULT 0,
  is_active BOOLEAN DEFAULT TRUE,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_display_order (display_order),
  INDEX idx_active (is_active)
);
```

### 5. MENUS - Templates de Menus

```sql
CREATE TABLE menus (
  id SERIAL PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  category_id INT,
  
  -- Prix
  base_price DECIMAL(10,2) NOT NULL,
  discount_percentage DECIMAL(5,2) DEFAULT 0,
  discounted_price DECIMAL(10,2),
  
  -- Disponibilité
  is_available BOOLEAN DEFAULT TRUE,
  daily_limit INT,
  daily_sold INT DEFAULT 0,
  
  -- Image
  image_url VARCHAR(255),
  
  preparation_time_minutes INT DEFAULT 30,
  is_featured BOOLEAN DEFAULT FALSE,
  display_order INT DEFAULT 0,
  
  created_by_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (created_by_id) REFERENCES users(id),
  INDEX idx_available (is_available),
  INDEX idx_featured (is_featured)
);
```

### 6. MENU_ITEMS - Items dans un Menu

```sql
CREATE TABLE menu_items (
  id SERIAL PRIMARY KEY,
  menu_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity DECIMAL(10,3) NOT NULL,
  quantity_unit VARCHAR(20), -- "kg", "l", "piece"
  display_order INT DEFAULT 0,
  
  FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  INDEX idx_menu_id (menu_id),
  UNIQUE (menu_id, product_id)
);
```

### 7. ORDERS - Commandes

```sql
CREATE TABLE orders (
  id SERIAL PRIMARY KEY,
  order_number VARCHAR(20) UNIQUE NOT NULL, -- "AXZ-2024-001234"
  
  -- Client et Livraison
  client_id INT NOT NULL,
  preparateur_id INT,
  livreur_id INT,
  delivery_address_id INT NOT NULL,
  
  -- Statut Workflow
  status ENUM(
    'pending',        -- En attente d'acceptation
    'accepted',       -- Acceptée
    'preparing',      -- En préparation
    'ready',          -- Prête
    'in_delivery',    -- En cours de livraison
    'delivered',      -- Livrée
    'completed',      -- Complétée (paiement OK)
    'cancelled',      -- Annulée
    'returned'        -- Retournée
  ) DEFAULT 'pending',
  
  -- Créneau de Livraison
  delivery_slot_id INT,
  delivery_slot_time VARCHAR(50), -- "14:00-16:00"
  delivery_date DATE,
  estimated_delivery_time TIMESTAMP,
  actual_delivery_time TIMESTAMP,
  
  -- Pricing
  subtotal DECIMAL(10,2) NOT NULL,
  delivery_fee DECIMAL(10,2) DEFAULT 0,
  tax DECIMAL(10,2) DEFAULT 0,
  discount_amount DECIMAL(10,2) DEFAULT 0,
  total DECIMAL(10,2) NOT NULL,
  
  -- Promo
  promo_code_id INT,
  promo_discount DECIMAL(10,2) DEFAULT 0,
  
  -- Payment
  payment_method ENUM('mobile_money', 'cash', 'card') DEFAULT 'mobile_money',
  payment_status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
  payment_transaction_id VARCHAR(255),
  
  -- Notes
  client_notes TEXT,
  preparateur_notes TEXT,
  livreur_notes TEXT,
  cancellation_reason TEXT,
  
  -- Tracking
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  accepted_at TIMESTAMP,
  preparing_started_at TIMESTAMP,
  ready_at TIMESTAMP,
  delivery_started_at TIMESTAMP,
  delivered_at TIMESTAMP,
  completed_at TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (client_id) REFERENCES users(id),
  FOREIGN KEY (preparateur_id) REFERENCES users(id),
  FOREIGN KEY (livreur_id) REFERENCES users(id),
  FOREIGN KEY (delivery_address_id) REFERENCES addresses(id),
  FOREIGN KEY (delivery_slot_id) REFERENCES delivery_slots(id),
  FOREIGN KEY (promo_code_id) REFERENCES promotions(id),
  
  INDEX idx_client_id (client_id),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at),
  INDEX idx_delivery_date (delivery_date),
  INDEX idx_preparateur_id (preparateur_id),
  INDEX idx_livreur_id (livreur_id)
);
```

### 8. ORDER_ITEMS - Items dans une Commande

```sql
CREATE TABLE order_items (
  id SERIAL PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  
  -- Quantité et Prix
  quantity DECIMAL(10,3) NOT NULL,
  quantity_unit VARCHAR(20), -- "kg", "l", "piece"
  unit_price DECIMAL(10,2) NOT NULL,
  total_price DECIMAL(10,2) NOT NULL,
  
  -- Modif Menu
  special_instructions TEXT,
  removed_ingredients TEXT, -- Pour les menus modifiés
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id),
  INDEX idx_order_id (order_id)
);
```

### 9. DELIVERY_SLOTS - Créneaux de Livraison

```sql
CREATE TABLE delivery_slots (
  id SERIAL PRIMARY KEY,
  zone_id INT NOT NULL,
  day_of_week INT, -- 0=lundi, 6=dimanche
  
  time_start TIME NOT NULL,
  time_end TIME NOT NULL,
  display_label VARCHAR(50), -- "09:00 - 11:00"
  
  max_orders_per_slot INT DEFAULT 50,
  current_orders_count INT DEFAULT 0,
  
  is_active BOOLEAN DEFAULT TRUE,
  priority INT DEFAULT 0,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (zone_id) REFERENCES delivery_zones(id),
  INDEX idx_zone_id (zone_id),
  INDEX idx_day_of_week (day_of_week)
);
```

### 10. DELIVERY_ZONES - Zones de Livraison

```sql
CREATE TABLE delivery_zones (
  id SERIAL PRIMARY KEY,
  name VARCHAR(255) NOT NULL UNIQUE, -- "Cotonou Centre", "Cadjehoun"
  
  -- Géolocalisation
  bounds_ne_lat DECIMAL(10,8),
  bounds_ne_lon DECIMAL(11,8),
  bounds_sw_lat DECIMAL(10,8),
  bounds_sw_lon DECIMAL(11,8),
  
  -- Pricing
  base_delivery_fee DECIMAL(10,2) DEFAULT 1000,
  extra_fee_per_km DECIMAL(10,2) DEFAULT 0,
  
  is_active BOOLEAN DEFAULT TRUE,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_active (is_active)
);
```

### 11. DELIVERIES - Assignations Livreur

```sql
CREATE TABLE deliveries (
  id SERIAL PRIMARY KEY,
  order_id INT NOT NULL,
  livreur_id INT NOT NULL,
  
  status ENUM(
    'assigned',
    'picked_up',
    'in_transit',
    'delivered',
    'failed'
  ) DEFAULT 'assigned',
  
  -- Tracking
  pickup_time TIMESTAMP,
  delivery_time TIMESTAMP,
  
  -- Localisation en temps réel
  current_latitude DECIMAL(10,8),
  current_longitude DECIMAL(11,8),
  last_location_update TIMESTAMP,
  
  -- Photo Preuve
  proof_photo_url VARCHAR(255),
  signature_data TEXT, -- SVG signature
  delivery_notes TEXT,
  
  -- Rating
  client_rating INT, -- 1-5 stars
  client_feedback TEXT,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (livreur_id) REFERENCES users(id),
  INDEX idx_livreur_id (livreur_id),
  INDEX idx_status (status),
  UNIQUE (order_id)
);
```

### 12. PROMOTIONS - Codes Promo

```sql
CREATE TABLE promotions (
  id SERIAL PRIMARY KEY,
  code VARCHAR(50) UNIQUE NOT NULL,
  description TEXT,
  
  -- Type de promo
  discount_type ENUM('percentage', 'fixed_amount', 'free_delivery') DEFAULT 'percentage',
  discount_value DECIMAL(10,2) NOT NULL,
  max_discount_amount DECIMAL(10,2),
  
  -- Conditions
  min_order_amount DECIMAL(10,2) DEFAULT 0,
  max_usage_total INT,
  max_usage_per_user INT DEFAULT 1,
  usage_count INT DEFAULT 0,
  
  -- Restrictions
  applicable_to ENUM('all', 'first_order', 'specific_products') DEFAULT 'all',
  applicable_categories TEXT, -- JSON: [1, 2, 3]
  applicable_products TEXT,   -- JSON: [5, 10, 15]
  
  -- Dates
  start_date DATETIME NOT NULL,
  end_date DATETIME NOT NULL,
  
  -- Status
  is_active BOOLEAN DEFAULT TRUE,
  
  created_by_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (created_by_id) REFERENCES users(id),
  INDEX idx_code (code),
  INDEX idx_active (is_active),
  INDEX idx_start_date (start_date)
);
```

### 13. PROMOTION_USAGE - Usage des Promos

```sql
CREATE TABLE promotion_usage (
  id SERIAL PRIMARY KEY,
  promotion_id INT NOT NULL,
  user_id INT NOT NULL,
  order_id INT NOT NULL,
  discount_amount DECIMAL(10,2) NOT NULL,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (promotion_id) REFERENCES promotions(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (order_id) REFERENCES orders(id),
  INDEX idx_promotion_id (promotion_id),
  INDEX idx_user_id (user_id),
  UNIQUE (promotion_id, order_id)
);
```

### 14. PAYMENTS - Historique Paiements

```sql
CREATE TABLE payments (
  id SERIAL PRIMARY KEY,
  order_id INT NOT NULL,
  user_id INT NOT NULL,
  
  amount DECIMAL(10,2) NOT NULL,
  currency VARCHAR(3) DEFAULT 'XOF', -- Francs CFA
  
  payment_method ENUM('mobile_money', 'cash', 'card') NOT NULL,
  payment_provider VARCHAR(50), -- 'mtn', 'moov', 'celtiis'
  
  status ENUM(
    'initiated',
    'processing',
    'completed',
    'failed',
    'cancelled',
    'refunded'
  ) DEFAULT 'initiated',
  
  transaction_id VARCHAR(255) UNIQUE,
  reference_number VARCHAR(255),
  
  -- Métadonnées Mobile Money
  phone_number VARCHAR(20),
  mobile_money_transaction_id VARCHAR(255),
  
  error_message TEXT,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (order_id) REFERENCES orders(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_order_id (order_id),
  INDEX idx_status (status),
  INDEX idx_user_id (user_id),
  INDEX idx_created_at (created_at)
);
```

### 15. INVOICES - Factures

```sql
CREATE TABLE invoices (
  id SERIAL PRIMARY KEY,
  invoice_number VARCHAR(50) UNIQUE NOT NULL, -- "INV-2024-001"
  order_id INT NOT NULL,
  
  -- Client
  client_id INT NOT NULL,
  client_name VARCHAR(255),
  client_phone VARCHAR(20),
  client_email VARCHAR(255),
  
  -- Items
  subtotal DECIMAL(10,2),
  tax_amount DECIMAL(10,2),
  delivery_fee DECIMAL(10,2),
  discount_amount DECIMAL(10,2),
  total DECIMAL(10,2),
  
  -- Status
  status ENUM('draft', 'issued', 'paid', 'cancelled') DEFAULT 'draft',
  issued_at TIMESTAMP,
  due_at TIMESTAMP,
  paid_at TIMESTAMP,
  
  -- PDF
  pdf_url VARCHAR(255),
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (order_id) REFERENCES orders(id),
  FOREIGN KEY (client_id) REFERENCES users(id),
  INDEX idx_invoice_number (invoice_number),
  INDEX idx_status (status)
);
```

### 16. NOTIFICATIONS - Journal Notifications

```sql
CREATE TABLE notifications (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL,
  
  type ENUM(
    'order_accepted',
    'order_preparing',
    'order_ready',
    'order_out_for_delivery',
    'order_delivered',
    'payment_confirmed',
    'promo_available',
    'new_product',
    'delivery_assigned',
    'review_request',
    'admin_alert'
  ) NOT NULL,
  
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  data JSON, -- {"order_id": 123, "status": "ready", ...}
  
  -- Channels
  is_sent_sms BOOLEAN DEFAULT FALSE,
  is_sent_email BOOLEAN DEFAULT FALSE,
  is_sent_in_app BOOLEAN DEFAULT FALSE,
  
  is_read BOOLEAN DEFAULT FALSE,
  read_at TIMESTAMP,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_created_at (created_at),
  INDEX idx_type (type)
);
```

### 17. REVIEWS - Avis et Ratings

```sql
CREATE TABLE reviews (
  id SERIAL PRIMARY KEY,
  order_id INT NOT NULL,
  reviewer_id INT NOT NULL,
  reviewee_id INT NOT NULL, -- livreur ou client
  
  type ENUM('delivery', 'product_quality', 'overall') DEFAULT 'overall',
  rating INT NOT NULL, -- 1-5
  comment TEXT,
  
  -- Validations
  is_verified_purchase BOOLEAN DEFAULT TRUE,
  is_approved BOOLEAN DEFAULT TRUE,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewer_id) REFERENCES users(id),
  FOREIGN KEY (reviewee_id) REFERENCES users(id),
  INDEX idx_reviewee_id (reviewee_id),
  INDEX idx_rating (rating)
);
```

### 18. AUDIT_LOGS - Journal Audit

```sql
CREATE TABLE audit_logs (
  id SERIAL PRIMARY KEY,
  user_id INT,
  action VARCHAR(100) NOT NULL,
  
  entity_type VARCHAR(50), -- 'order', 'product', 'user'
  entity_id INT,
  
  old_values JSON,
  new_values JSON,
  
  ip_address VARCHAR(45),
  user_agent TEXT,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_user_id (user_id),
  INDEX idx_created_at (created_at),
  INDEX idx_entity_type (entity_type)
);
```

## Indexes de Performance

```sql
-- Search indexes
CREATE INDEX idx_products_name_tsearch ON products USING GIN (to_tsvector('french', name));
CREATE INDEX idx_users_name_tsearch ON users USING GIN (to_tsvector('french', name));

-- Geospatial indexes (si PostGIS est utilisé)
CREATE INDEX idx_addresses_location ON addresses USING GIST(ll_to_earth(latitude, longitude));

-- Composite indexes
CREATE INDEX idx_orders_client_date ON orders(client_id, created_at DESC);
CREATE INDEX idx_orders_livreur_date ON orders(livreur_id, delivery_date DESC);
CREATE INDEX idx_deliveries_livreur_date ON deliveries(livreur_id, created_at DESC);
```

## Vues Utiles

```sql
-- Dashboard Stats
CREATE VIEW v_daily_orders_stats AS
SELECT 
  DATE(created_at) as order_date,
  COUNT(*) as total_orders,
  COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
  SUM(total) as total_revenue
FROM orders
GROUP BY DATE(created_at);

-- Préparateur Stats
CREATE VIEW v_preparateur_stats AS
SELECT 
  preparateur_id,
  COUNT(*) as orders_count,
  AVG(EXTRACT(EPOCH FROM (ready_at - accepted_at))/60) as avg_prep_minutes
FROM orders
WHERE status IN ('ready', 'in_delivery', 'delivered', 'completed')
GROUP BY preparateur_id;

-- Livreur Stats
CREATE VIEW v_livreur_stats AS
SELECT 
  d.livreur_id,
  COUNT(*) as deliveries_count,
  COUNT(CASE WHEN d.status = 'delivered' THEN 1 END) as successful_deliveries,
  AVG(r.rating) as avg_rating
FROM deliveries d
LEFT JOIN reviews r ON d.order_id = r.order_id AND r.type = 'delivery'
GROUP BY d.livreur_id;
```

---

**Notes de Conception:**

1. **Soft Deletes**: Les colonnes `deleted_at` permettent l'archivage
2. **Timestamps**: `created_at`, `updated_at` pour tracking
3. **Enums**: Pour validations au niveau BD
4. **Indexes**: Sur colonnes fréquemment queryées
5. **Foreign Keys**: Intégrité référentielle garantie
6. **JSON Fields**: Pour données flexibles (promotions conditions, etc)
7. **Partitioning**: Pour `orders` et `payments` si volume important

**Version**: 1.0 | Date: Juin 2026 | Bénin 🇧🇯
