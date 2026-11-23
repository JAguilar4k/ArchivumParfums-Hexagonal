#!/usr/bin/env bash
# Prepara un VPS nuevo con Ubuntu 24.04 para ArchivumParfums (#21). Se corre una sola vez, como root:
#
#   curl -fsSL https://raw.githubusercontent.com/JAguilar4k/ArchivumParfums-Hexagonal/main/deploy/provision.sh -o provision.sh
#   sudo bash provision.sh archivum.ejemplo.com
#
# Instala PHP 8.4, PostgreSQL 18, Caddy y Node; crea el usuario archivum, los dos roles de la base con
# contraseñas al azar y el .env de producción sin las llaves de las pasarelas, que se ponen con deploy/secrets.sh.
# Ningún secreto se imprime ni sale del servidor.
set -euo pipefail

DOMAIN="${1:?Indique el dominio, por ejemplo: sudo bash provision.sh archivum.ejemplo.com}"
REPOSITORY="https://github.com/JAguilar4k/ArchivumParfums-Hexagonal.git"
HOME_DIR=/srv/archivum
APP_DIR="$HOME_DIR/app"
API_DIR="$APP_DIR/archivum-api-hexagonal"

if [[ $EUID -ne 0 ]]; then
    echo "Corra este script como root (sudo)." >&2
    exit 1
fi

export DEBIAN_FRONTEND=noninteractive

echo "==> Paquetes del sistema"
apt-get update
apt-get -y upgrade
apt-get install -y ca-certificates curl gnupg git unzip ufw software-properties-common debian-keyring debian-archive-keyring apt-transport-https

echo "==> Repositorios de PHP 8.4, PostgreSQL 18, Caddy y Node 26"
# Ubuntu 24.04 trae PHP 8.3 y PostgreSQL 16; el proyecto exige PHP 8.4 y PostgreSQL 18 (uuidv7()).
add-apt-repository -y ppa:ondrej/php
install -d /usr/share/postgresql-common/pgdg
curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc
echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt noble-pgdg main" > /etc/apt/sources.list.d/pgdg.list
curl -fsSL https://dl.cloudsmith.io/public/caddy/stable/gpg.key | gpg --dearmor --yes -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -fsSL https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt -o /etc/apt/sources.list.d/caddy-stable.list
curl -fsSL https://deb.nodesource.com/setup_26.x | bash -

apt-get update
apt-get install -y \
    php8.4-fpm php8.4-cli php8.4-pgsql php8.4-intl php8.4-mbstring php8.4-xml php8.4-zip php8.4-bcmath php8.4-curl php8.4-gd \
    postgresql-18 caddy nodejs

echo "==> Composer"
EXPECTED="$(curl -fsSL https://composer.github.io/installer.sig)"
curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
if [[ "$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")" != "$EXPECTED" ]]; then
    echo "La firma del instalador de Composer no coincide." >&2
    exit 1
fi
php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
rm /tmp/composer-setup.php

echo "==> Memoria de intercambio para compilar la SPA"
if [[ ! -f /swapfile ]]; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

echo "==> Cortafuegos: solo SSH, HTTP y HTTPS"
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

echo "==> Usuario archivum y código"
id archivum >/dev/null 2>&1 || useradd --system --create-home --home-dir "$HOME_DIR" --shell /bin/bash archivum
chmod 750 "$HOME_DIR"
if [[ ! -d "$APP_DIR/.git" ]]; then
    sudo -u archivum git clone "$REPOSITORY" "$APP_DIR"
fi

echo "==> Roles y base de datos"
if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname = 'archivum_owner'" | grep -q 1; then
    OWNER_PASSWORD="$(openssl rand -hex 24)"
    APP_PASSWORD="$(openssl rand -hex 24)"
    sudo -u postgres psql -v ON_ERROR_STOP=1 -v owner_password="$OWNER_PASSWORD" -v app_password="$APP_PASSWORD" -f "$API_DIR/database/roles.sql"
else
    echo "Los roles ya existen; se conservan las contraseñas del .env."
fi

echo "==> .env de producción"
if [[ ! -f "$API_DIR/.env" ]]; then
    cp "$API_DIR/.env.example" "$API_DIR/.env"
    set_env() { sed -i "s|^#\? \?$1=.*|$1=$2|" "$API_DIR/.env"; }
    set_env APP_ENV production
    set_env APP_DEBUG false
    set_env APP_URL "https://$DOMAIN"
    set_env LOG_LEVEL warning
    set_env DB_PASSWORD "${APP_PASSWORD:?Los roles ya existían: ponga DB_PASSWORD y DB_ADMIN_PASSWORD a mano}"
    set_env DB_ADMIN_PASSWORD "$OWNER_PASSWORD"
    set_env ARCHIVUM_DEMO_PASSWORD ""
    set_env ARCHIVUM_WEB_ORIGIN "https://$DOMAIN"
    set_env PAYMENTS_RETURN_URL "https://$DOMAIN/pagos/procesando"
    set_env PAYMENTS_CANCEL_URL "https://$DOMAIN/cuenta/pedidos"
    chown archivum:archivum "$API_DIR/.env"
    chmod 600 "$API_DIR/.env"
fi
# La SPA lee sus variables al compilarse: las de su .env.example, con la API en el mismo dominio.
sed "s|^VITE_API_URL=.*|VITE_API_URL=https://$DOMAIN/api/v1|" "$APP_DIR/archivum-web/.env.example" > "$APP_DIR/archivum-web/.env.production.local"
chown archivum:archivum "$APP_DIR/archivum-web/.env.production.local"

echo "==> Servicios"
install -m 644 "$APP_DIR/deploy/php-fpm-archivum.conf" /etc/php/8.4/fpm/pool.d/archivum.conf
install -m 644 "$APP_DIR/deploy/archivum-worker.service" /etc/systemd/system/archivum-worker.service
install -m 644 "$APP_DIR/deploy/archivum-cron" /etc/cron.d/archivum
install -m 440 "$APP_DIR/deploy/sudoers-archivum" /etc/sudoers.d/archivum
visudo -cf /etc/sudoers.d/archivum
install -m 644 "$APP_DIR/deploy/Caddyfile" /etc/caddy/Caddyfile
mkdir -p /etc/systemd/system/caddy.service.d
printf '[Service]\nEnvironment=ARCHIVUM_DOMAIN=%s\n' "$DOMAIN" > /etc/systemd/system/caddy.service.d/archivum.conf
# Caddy lee la SPA y la carpeta public de la API, nada más.
usermod -aG archivum caddy
systemctl daemon-reload
systemctl enable php8.4-fpm archivum-worker caddy

echo "==> Primera publicación"
sudo -u archivum bash "$APP_DIR/deploy/release.sh" --first
systemctl restart php8.4-fpm caddy

echo
echo "Listo: https://$DOMAIN"
echo "Falta poner las llaves de las pasarelas y la contraseña de las cuentas de demostración:"
echo "  sudo -u archivum bash $APP_DIR/deploy/secrets.sh"
