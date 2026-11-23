#!/usr/bin/env bash
# Publica en el servidor la última versión de main (#21). Corre como el usuario archivum:
#
#   sudo -u archivum bash /srv/archivum/app/deploy/release.sh
#
# Trae main, instala las dependencias de producción, corre las migraciones con el rol dueño,
# guarda en caché la configuración y las rutas, compila la SPA y reinicia el worker.
set -euo pipefail

APP_DIR=/srv/archivum/app
API_DIR="$APP_DIR/archivum-api-hexagonal"
WEB_DIR="$APP_DIR/archivum-web"

if [[ "$(id -un)" != "archivum" ]]; then
    echo "Corra este script como archivum: sudo -u archivum bash $0" >&2
    exit 1
fi

echo "==> Código de main"
git -C "$APP_DIR" pull --ff-only origin main

echo "==> API"
cd "$API_DIR"
# --no-dev hace que composer no llame a archivum:install, que solo prepara equipos de desarrollo.
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi
[[ -L public/storage ]] || php artisan storage:link
php artisan migrate --database=pgsql_admin --force
# La API no tiene vistas, así que se guardan en caché solo la configuración, los eventos y las rutas.
php artisan config:cache
php artisan event:cache
php artisan route:cache

echo "==> SPA"
cd "$WEB_DIR"
npm ci --no-audit --no-fund
npm run build

echo "==> Servicios"
sudo /usr/bin/systemctl reload php8.4-fpm
sudo /usr/bin/systemctl restart archivum-worker

if [[ "${1:-}" != "--first" ]]; then
    APP_URL="$(grep '^APP_URL=' "$API_DIR/.env" | cut -d= -f2-)"
    curl -fsS -o /dev/null "$APP_URL/up" && echo "Publicado: $APP_URL responde en /up."
fi
