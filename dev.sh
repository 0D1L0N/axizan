#!/bin/bash
# Commandes développement rapides pour Axizan

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

if [ $# -eq 0 ]; then
    echo -e "${BLUE}🍲 Axizan Development Commands${NC}"
    echo ""
    echo "Usage: ./dev.sh [command]"
    echo ""
    echo "Backend Commands:"
    echo "  serve              Lancer backend (port 8000)"
    echo "  migrate            Exécuter migrations"
    echo "  seed               Seeder données test"
    echo "  fresh              Reset BD + migrations + seed"
    echo "  tinker             PHP REPL (interaction interactive)"
    echo "  test               Lancer tests"
    echo "  log                Afficher logs"
    echo ""
    echo "Frontend Commands:"
    echo "  dev                Lancer frontend (port 3000)"
    echo "  build              Build production"
    echo "  lint               Vérifier code"
    echo "  test:fe            Tests frontend"
    echo ""
    echo "Utilities:"
    echo "  check              Vérifier prérequis"
    echo "  clean              Nettoyer cache"
    echo "  db:create          Créer base de données"
    echo "  db:drop            Supprimer base de données"
    echo ""
    exit 0
fi

case "$1" in
    # Backend
    serve)
        echo -e "${GREEN}▶ Lancer Backend...${NC}"
        cd backend && php artisan serve
        ;;
    migrate)
        echo -e "${GREEN}▶ Migrations...${NC}"
        cd backend && php artisan migrate
        ;;
    seed)
        echo -e "${GREEN}▶ Seeding data...${NC}"
        cd backend && php artisan db:seed
        ;;
    fresh)
        echo -e "${GREEN}▶ Reset BD + migrations + seed...${NC}"
        cd backend && php artisan migrate:fresh --seed
        ;;
    tinker)
        echo -e "${GREEN}▶ PHP Interactive Shell...${NC}"
        cd backend && php artisan tinker
        ;;
    test)
        echo -e "${GREEN}▶ Tests Backend...${NC}"
        cd backend && php artisan test
        ;;
    log)
        echo -e "${GREEN}▶ Afficher logs...${NC}"
        tail -f backend/storage/logs/laravel.log
        ;;
    
    # Frontend
    dev)
        echo -e "${GREEN}▶ Lancer Frontend...${NC}"
        cd frontend && npm run dev
        ;;
    build)
        echo -e "${GREEN}▶ Build production...${NC}"
        cd frontend && npm run build && npm start
        ;;
    lint)
        echo -e "${GREEN}▶ Linter...${NC}"
        cd frontend && npm run lint
        ;;
    test:fe)
        echo -e "${GREEN}▶ Tests Frontend...${NC}"
        cd frontend && npm test
        ;;
    
    # Utilities
    check)
        echo -e "${GREEN}▶ Vérification...${NC}"
        chmod +x check.sh && ./check.sh
        ;;
    clean)
        echo -e "${GREEN}▶ Cleaning cache...${NC}"
        cd backend && php artisan cache:clear && php artisan config:cache
        echo -e "${GREEN}✓ Cache cleared${NC}"
        ;;
    db:create)
        echo -e "${GREEN}▶ Créer base de données 'axizan'...${NC}"
        createdb axizan
        echo -e "${GREEN}✓ Base créée${NC}"
        ;;
    db:drop)
        echo -e "${YELLOW}⚠ Suppression de 'axizan'...${NC}"
        dropdb axizan
        echo -e "${GREEN}✓ Base supprimée${NC}"
        ;;
    *)
        echo -e "${YELLOW}Commande inconnue: $1${NC}"
        echo "Tapez './dev.sh' pour voir les commandes disponibles"
        ;;
esac
