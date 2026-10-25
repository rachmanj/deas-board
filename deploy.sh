#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

# Dependensi PHP di host (vendor tidak di-commit)
if [ ! -d vendor ]; then
  composer install --no-dev --optimize-autoloader
fi

# Bangun image dan jalankan stack
docker compose -f docker-compose.s6.yml build
docker compose -f docker-compose.s6.yml up -d

# Terapkan migrasi basis data
docker compose -f docker-compose.s6.yml exec -T app php artisan migrate --force

# Muat ulang konfigurasi reverse proxy
docker exec web-proxy nginx -s reload
