#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

# Dependensi PHP di host (vendor tidak di-commit)
if [ ! -d vendor ]; then
  composer install --no-dev --optimize-autoloader
fi

if git rev-parse HEAD >/dev/null 2>&1; then
  git rev-parse HEAD > GIT_COMMIT
else
  echo "tidak-diketahui" > GIT_COMMIT
fi

# Bangun image dan jalankan stack
./dc build
./dc up -d

# Terapkan migrasi basis data
./dc exec -T app php artisan migrate --force

# Muat ulang konfigurasi reverse proxy
docker exec web-proxy nginx -s reload
