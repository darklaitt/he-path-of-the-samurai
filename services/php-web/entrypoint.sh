#!/bin/bash
set -e

# Копируем Laravel приложение в рабочую директорию (поверх тома appdata)
if [ -d "/opt/laravel-patches" ] && [ "$(ls -A /opt/laravel-patches)" ]; then
    cp -r /opt/laravel-patches/* /var/www/html/ 2>/dev/null || true
    cp -r /opt/laravel-patches/.[!.]* /var/www/html/ 2>/dev/null || true
    echo "[INFO] Laravel application copied"
fi

# Переходим в директорию приложения
cd /var/www/html

# Гарантируем наличие необходимых директорий Laravel
mkdir -p bootstrap/cache storage/framework/{cache,sessions,views} storage/logs
chown -R www-data:www-data bootstrap storage 2>/dev/null || true
chmod -R 775 bootstrap storage || true

# Если .env отсутствует, копируем из примера (как в стандартном шаблоне)
if [ ! -f ".env" ] && [ -f ".env.example" ]; then
    cp .env.example .env
    echo "[INFO] .env created from .env.example"
fi

# Если composer.json существует, устанавливаем зависимости
if [ -f "composer.json" ]; then
    echo "[INFO] Installing composer dependencies..."
    composer install --no-dev --optimize-autoloader 2>&1 | tail -20 || echo "[WARN] Composer install had issues"
    echo "[INFO] Composer dependencies installed"
fi

# Генерируем ключ приложения если нужно
if [ -f ".env" ]; then
    if grep -q "APP_KEY=base64:" .env; then
        echo "[INFO] APP_KEY already set"
    else
        php artisan key:generate 2>/dev/null || echo "[WARN] Could not generate key"
    fi
fi

echo "[INFO] Starting PHP-FPM..."

# Запускаем PHP-FPM
exec php-fpm

