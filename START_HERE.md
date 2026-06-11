# ✅ Checklist Démarrage Axizan

Suivez ces étapes pour avoir l'app fonctionnelle en local en **5 minutes** ! 🚀

---

## 📋 Étape 1: Prérequis

Vérifiez que vous avez installé:

```bash
php --version          # PHP 8.2+ (https://www.php.net)
node --version         # Node.js 18+ (https://nodejs.org)
composer --version     # Composer (https://getcomposer.org)
psql --version        # PostgreSQL (https://www.postgresql.org)
```

Si un manque, installez-le avant de continuer ! ✋

---

## 🗄️ Étape 2: Préparer la Base de Données

### Lancez PostgreSQL

**macOS (Homebrew):**
```bash
brew services start postgresql
```

**Linux (Ubuntu/Debian):**
```bash
sudo systemctl start postgresql
```

**Windows:**
- Ouvrir "Services" → PostgreSQL → Start

### Créer la base de données

```bash
createdb axizan
```

**Ou via psql:**
```bash
psql -U postgres
# Dans le prompt:
CREATE DATABASE axizan;
\q
```

---

## ⚙️ Étape 3: Configurer & Lancer Backend

```bash
cd backend

# Copier fichier de config
cp .env.example .env

# Installer dépendances
composer install

# Générer clé
php artisan key:generate

# Initialiser BD
php artisan migrate
php artisan db:seed

# Lancer le serveur
php artisan serve
```

✅ **Backend est maintenant à:** `http://localhost:8000`

**Laissez ce terminal ouvert!**

---

## 🎨 Étape 4: Lancer Frontend

**Ouvrez un nouveau terminal** et:

```bash
cd frontend

# Installer dépendances
npm install

# Copier fichier de config
cp .env.example .env.local

# Lancer le serveur
npm run dev
```

✅ **Frontend est maintenant à:** `http://localhost:3000`

---

## 🧪 Étape 5: Tester l'App

### Ouvrir dans le navigateur

```
http://localhost:3000
```

### S'inscrire avec:

```
Name: Test User
Email: test@example.com
Phone: +22960000000
Password: password123
Role: Client
```

### Tester les fonctionnalités:

- 📦 **Voir les produits** (Home)
- 🛒 **Ajouter au panier** (Cart)
- 📋 **Créer une commande**

---

## 🔐 Comptes Prédéfinis (après db:seed)

Si vous avez fait `php artisan db:seed`, vous avez ces comptes:

| Rôle | Email | Mot de passe |
|------|-------|-------------|
| 👨‍💼 Client | `client@test.com` | `password123` |
| 👨‍🍳 Préparateur | `prep@test.com` | `password123` |
| 🛵 Livreur | `livreur@test.com` | `password123` |
| 🔐 Admin | `admin@test.com` | `password123` |

---

## ⚠️ Erreurs Courantes

### ❌ "php: command not found"
→ PHP n'est pas installé ou pas dans PATH
```bash
# Installer PHP: https://www.php.net/manual/en/install.php
which php  # Vérifier après installation
```

### ❌ "SQLSTATE[08006]" ou "connection refused"
→ PostgreSQL ne tourne pas
```bash
# Redémarrer PostgreSQL
brew services restart postgresql    # macOS
sudo systemctl restart postgresql   # Linux
```

### ❌ "Could not find driver pdo_pgsql"
→ Extension PostgreSQL manquante
```bash
# Installer
php -m | grep pdo_pgsql  # Vérifier

# Sur macOS:
brew install php
# Sur Linux:
apt-get install php-pgsql
```

### ❌ "Port 3000 already in use"
→ Quelque chose occupe le port
```bash
npm run dev -- -p 3001  # Utiliser port 3001
```

### ❌ "ECONNREFUSED - Cannot connect to backend"
→ Backend n'est pas lancé ou API URL incorrecte
```bash
# Vérifier backend tourne
curl http://localhost:8000/api/v1/products

# Vérifier frontend/.env.local
cat frontend/.env.local
```

---

## 📚 Documentation

- **API Endpoints**: [docs/API.md](docs/API.md)
- **Schéma BD**: [docs/DATABASE.md](docs/DATABASE.md)
- **Architecture**: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)
- **Détails Setup**: [SETUP.md](SETUP.md)

---

## 🆘 Besoin d'aide ?

1. **Vérifier les logs:**
   - Backend: `backend/storage/logs/laravel.log`
   - Frontend: Ouvrir Console (F12 / ⌘+⌥+I)

2. **Réinitialiser:**
   ```bash
   # Backend: réinitialiser BD
   cd backend
   php artisan migrate:fresh --seed
   
   # Frontend: réinitialiser node_modules
   cd frontend
   rm -rf node_modules package-lock.json
   npm install
   ```

3. **Vérifier connexion API:**
   ```bash
   curl http://localhost:8000/api/v1/products -H "Accept: application/json"
   ```

---

## ✨ Prêt ?

**Résuméz rapidement:**

| Étape | Commande | Port |
|-------|----------|------|
| 1 | Installer prérequis | - |
| 2 | `createdb axizan` | - |
| 3 | `cd backend && php artisan serve` | **8000** |
| 4 | `cd frontend && npm run dev` | **3000** |
| 5 | Ouvrir http://localhost:3000 | ✅ |

---

**Bon coding! 🍲** 

Toute question → Vérifiez **logs** + **troubleshooting** ci-dessus
