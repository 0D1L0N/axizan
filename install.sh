#!/bin/bash
# Installation script for Axizán

set -e

echo "🍲 Installing Axizán..."
echo ""

# Check prerequisites
if ! command -v php &> /dev/null; then
    echo "❌ PHP is required but not installed. Please install PHP 8.2+"
    exit 1
fi

if ! command -v node &> /dev/null; then
    echo "❌ Node.js is required but not installed. Please install Node.js 18+"
    exit 1
fi

if ! command -v composer &> /dev/null; then
    echo "❌ Composer is required but not installed. Please install Composer"
    exit 1
fi

echo "✅ Prerequisites check passed"
echo ""

# Backend setup
echo "📦 Setting up Backend..."
cd backend

if [ ! -f .env ]; then
    cp .env.example .env
    echo "✅ Created .env file"
else
    echo "ℹ️  .env already exists, skipping..."
fi

composer install
php artisan key:generate

echo "✅ Backend setup complete"
cd ..
echo ""

# Frontend setup
echo "🎨 Setting up Frontend..."
cd frontend

if [ ! -f .env.local ]; then
    cp .env.example .env.local
    echo "✅ Created .env.local file"
else
    echo "ℹ️  .env.local already exists, skipping..."
fi

npm install

echo "✅ Frontend setup complete"
cd ..
echo ""

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✨ Installation complete!"
echo ""
echo "📋 Next steps:"
echo ""
echo "1. Configure PostgreSQL and update .env:"
echo "   cd backend"
echo "   nano .env"
echo ""
echo "2. Run migrations:"
echo "   php artisan migrate"
echo "   php artisan db:seed"
echo ""
echo "3. Start backend:"
echo "   php artisan serve"
echo ""
echo "4. In another terminal, start frontend:"
echo "   cd frontend"
echo "   npm run dev"
echo ""
echo "📱 Frontend: http://localhost:3000"
echo "🔌 Backend:  http://localhost:8000"
echo ""
echo "📚 See SETUP.md for detailed instructions"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
