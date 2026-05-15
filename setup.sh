#!/bin/bash

# ============================================================
# Aksana Inventory — Setup Script
# Jalankan script ini setelah clone repo
# ============================================================

set -e

echo ""
echo "🚀 Aksana Inventory — Project Setup"
echo "======================================"
echo ""

# 1. Check PHP version
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
echo "✓ PHP version: $PHP_VERSION"
if [[ $(php -r "echo version_compare(PHP_VERSION, '8.2.0', '>=') ? 'ok' : 'fail';") != "ok" ]]; then
    echo "❌ PHP 8.2+ required. Current: $PHP_VERSION"
    exit 1
fi

# 2. Copy .env
if [ ! -f .env ]; then
    cp .env.example .env
    echo "✓ .env created from .env.example"
    echo ""
    echo "⚠️  EDIT .env DULU sebelum lanjut:"
    echo "   DB_DATABASE=aksana_inventory"
    echo "   DB_USERNAME=your_postgres_user"
    echo "   DB_PASSWORD=your_postgres_password"
    echo ""
    read -p "Sudah edit .env? (y/n): " confirm
    if [[ $confirm != "y" ]]; then
        echo "Silakan edit .env dulu lalu jalankan script ini lagi."
        exit 0
    fi
else
    echo "✓ .env already exists"
fi

# 3. Install composer dependencies
echo ""
echo "📦 Installing composer dependencies..."
composer install

# 4. Generate app key
echo ""
echo "🔑 Generating application key..."
php artisan key:generate

# 5. Create PostgreSQL database (manual step)
echo ""
echo "🗄️  Pastikan database PostgreSQL sudah dibuat:"
echo "   createdb aksana_inventory"
echo "   atau via pgAdmin/DBeaver"
echo ""
read -p "Database sudah siap? (y/n): " dbconfirm
if [[ $dbconfirm != "y" ]]; then
    echo "Buat database dulu lalu jalankan: php artisan migrate --seed"
    exit 0
fi

# 6. Run migrations
echo ""
echo "🏗️  Running migrations..."
php artisan migrate

# 7. Run seeders
echo ""
echo "🌱 Running seeders..."
php artisan db:seed

# 8. Storage link
echo ""
echo "🔗 Creating storage link..."
php artisan storage:link

# 9. Install Filament
echo ""
echo "🎨 Installing Filament assets..."
php artisan filament:upgrade 2>/dev/null || true

echo ""
echo "======================================"
echo "✅ Setup selesai!"
echo ""
echo "Langkah berikutnya:"
echo "  php artisan serve        → start development server"
echo "  http://localhost:8000/admin  → web admin (Filament)"
echo ""
echo "Default users (password: 'password'):"
echo "  owner@aksana.id"
echo "  admin@aksana.id"
echo "  gudang@aksana.id"
echo "  picbazar@aksana.id"
echo "  sales@aksana.id"
echo "======================================"
