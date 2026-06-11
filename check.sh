#!/bin/bash
# Vérifier l'état de l'application Axizan

echo "🔍 Vérification Axizan..."
echo ""

# Couleurs
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# PHP
echo -n "PHP: "
if command -v php &> /dev/null; then
    VERSION=$(php -v | grep -oP 'PHP \K[0-9.]+')
    echo -e "${GREEN}✓ $VERSION${NC}"
else
    echo -e "${RED}✗ Not installed${NC}"
fi

# Node
echo -n "Node.js: "
if command -v node &> /dev/null; then
    VERSION=$(node -v)
    echo -e "${GREEN}✓ $VERSION${NC}"
else
    echo -e "${RED}✗ Not installed${NC}"
fi

# Composer
echo -n "Composer: "
if command -v composer &> /dev/null; then
    echo -e "${GREEN}✓ Installed${NC}"
else
    echo -e "${RED}✗ Not installed${NC}"
fi

# PostgreSQL
echo -n "PostgreSQL: "
if command -v psql &> /dev/null; then
    VERSION=$(psql --version | grep -oP 'psql \(PostgreSQL\) \K[0-9.]+')
    echo -e "${GREEN}✓ $VERSION${NC}"
else
    echo -e "${RED}✗ Not installed${NC}"
fi

# Check axizan database
echo -n "axizan database: "
if sudo -u postgres psql -lqt | cut -d \| -f 1 | grep -qw axizan; then
    echo -e "${GREEN}✓ Exists${NC}"
else
    echo -e "${YELLOW}⚠ Not created${NC}"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check if services are running
echo ""
echo "🔌 Vérification services..."
echo ""

echo -n "Backend (8000): "
if nc -z localhost 8000 &>/dev/null; then
    echo -e "${GREEN}✓ Running${NC}"
else
    echo -e "${YELLOW}⚠ Not running${NC} (run: cd backend && php artisan serve)"
fi

echo -n "Frontend (3000): "
if nc -z localhost 3000 &>/dev/null; then
    echo -e "${GREEN}✓ Running${NC}"
else
    echo -e "${YELLOW}⚠ Not running${NC} (run: cd frontend && npm run dev)"
fi

echo -n "PostgreSQL (5432): "
if nc -z localhost 5432 &>/dev/null; then
    echo -e "${GREEN}✓ Running${NC}"
else
    echo -e "${RED}✗ Not running${NC} (run: brew services start postgresql)"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "✅ Vérification terminée!"
echo ""
echo "📍 Accéder à l'app:"
echo "   Frontend: http://localhost:3000"
echo "   Backend:  http://localhost:8000"
echo "   DB:       localhost:5432"
