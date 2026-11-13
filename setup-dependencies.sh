#!/bin/bash
# Installation automatique des dépendances manquantes pour Axizan

set -e

echo "🍲 Installation des dépendances Axizan..."
echo ""

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# 1. Installer PostgreSQL
echo -e "${BLUE}1️⃣  Installation de PostgreSQL...${NC}"
if ! command -v psql &> /dev/null; then
    sudo apt update
    sudo apt install -y postgresql postgresql-contrib postgresql-client
    sudo systemctl start postgresql
    sudo systemctl enable postgresql
    echo -e "${GREEN}✓ PostgreSQL installé${NC}"
else
    echo -e "${YELLOW}✓ PostgreSQL déjà installé${NC}"
fi
echo ""

# 2. Installer Composer
echo -e "${BLUE}2️⃣  Installation de Composer...${NC}"
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
    echo -e "${GREEN}✓ Composer installé${NC}"
else
    echo -e "${YELLOW}✓ Composer déjà installé${NC}"
fi
echo ""

# 3. Créer la base de données
echo -e "${BLUE}3️⃣  Création de la base de données...${NC}"
if ! sudo -u postgres psql -lqt | cut -d \| -f 1 | grep -qw axizan; then
    sudo -u postgres createdb axizan
    echo -e "${GREEN}✓ Base de données 'axizan' créée${NC}"
else
    echo -e "${YELLOW}✓ Base de données 'axizan' existe déjà${NC}"
fi
echo ""

# 4. Vérifier tout
echo -e "${BLUE}4️⃣  Vérification...${NC}"
echo -n "PHP: "
php --version | head -1
echo -n "Node.js: "
node --version
echo -n "Composer: "
composer --version | head -1
echo -n "PostgreSQL: "
psql --version
echo ""

echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}✨ Tous les prérequis sont installés!${NC}"
echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo "📋 Prochaines étapes:"
echo ""
echo "1️⃣  Initialiser le backend:"
echo "   cd backend"
echo "   cp .env.example .env"
echo "   composer install"
echo "   php artisan migrate"
echo "   php artisan db:seed"
echo "   php artisan serve"
echo ""
echo "2️⃣  Lancer le frontend (nouveau terminal):"
echo "   cd frontend"
echo "   npm install"
echo "   cp .env.example .env.local"
echo "   npm run dev"
echo ""
echo "3️⃣  Ouvrir: http://localhost:3000"
echo ""
