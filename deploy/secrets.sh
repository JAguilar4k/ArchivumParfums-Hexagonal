#!/usr/bin/env bash
# Pone en el .env del servidor las llaves de prueba de las pasarelas, la de Gemini y la contraseña de las
# cuentas de demostración (#21). Lo corre el dueño del servidor, por SSH, como el usuario archivum:
#
#   sudo -u archivum bash /srv/archivum/app/deploy/secrets.sh
#
# Cada valor se escribe sin mostrarse en pantalla. Enter deja el valor que ya tenía. Nada de esto entra al
# repositorio: el .env está en .gitignore y queda con permisos 600.
set -euo pipefail

API_DIR=/srv/archivum/app/archivum-api-hexagonal
ENV_FILE="$API_DIR/.env"

if [[ "$(id -un)" != "archivum" ]]; then
    echo "Corra este script como archivum: sudo -u archivum bash $0" >&2
    exit 1
fi

ask() {
    local name="$1" label="$2" value
    read -rsp "$label ($name, Enter para no cambiarlo): " value
    echo
    if [[ -n "$value" ]]; then
        if [[ "$value" == *'|'* || "$value" == *$'\n'* ]]; then
            echo "  $name no puede llevar | ni saltos de línea; no se cambió." >&2
            return
        fi
        sed -i "s|^$name=.*|$name=$value|" "$ENV_FILE"
        echo "  $name actualizado."
    fi
}

ask ONVO_SECRET_KEY "Llave secreta de prueba de ONVO (onvo_test_...)"
ask ONVO_PUBLISHABLE_KEY "Llave pública de prueba de ONVO"
ask ONVO_WEBHOOK_SECRET "Secreto de firma del webhook de ONVO (whsec_...)"
ask PAYPAL_CLIENT_ID "Client ID del sandbox de PayPal"
ask PAYPAL_CLIENT_SECRET "Secret del sandbox de PayPal"
ask PAYPAL_WEBHOOK_ID "Webhook ID del sandbox de PayPal"
ask GEMINI_API_KEY "Llave de Gemini"
ask ARCHIVUM_DEMO_PASSWORD "Contraseña de las cuentas de demostración"

cd "$API_DIR"
# La API no tiene vistas, así que se guardan en caché solo la configuración, los eventos y las rutas.
php artisan config:cache
php artisan event:cache
php artisan route:cache
sudo /usr/bin/systemctl reload php8.4-fpm
sudo /usr/bin/systemctl restart archivum-worker
echo "Listo. Si la base está vacía, cargue los datos de demostración con:"
echo "  cd $API_DIR && php artisan db:seed --force"
