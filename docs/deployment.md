# Despliegue en producción (#21)

ArchivumParfums corre en un VPS de OVHcloud con Ubuntu 24.04, Caddy, PHP 8.4, PostgreSQL 18, un worker de la cola y el programador de Laravel. La decisión y sus alternativas están en el [ADR 0013](adr/0013-deploy-on-an-ovhcloud-vps.md). Los archivos del servidor están en [`deploy/`](../deploy/).

| Dirección | Qué responde |
| --- | --- |
| `https://<dominio>/` | La tienda, `archivum-web` |
| `https://<dominio>/api/v1` | La API |
| `https://<dominio>/up` | El chequeo de salud |
| `https://<dominio>/api/v1/webhooks/onvo` | Los avisos de ONVO |
| `https://<dominio>/api/v1/webhooks/paypal` | Los avisos de PayPal |

## 1. Comprar el servidor y apuntar el dominio

1. En OVHcloud, pedir un **VPS-1** con **Ubuntu 24.04** en la región más cercana (EE. UU. Este o Canadá). En el pedido, pegar la llave SSH pública de la computadora desde la que se va a entrar (`ssh-keygen -t ed25519` la crea; la pública es el archivo `.pub`).
2. Anotar la IPv4 que OVHcloud manda por correo.
3. En el proveedor del dominio, crear un registro **A** del dominio (o del subdominio) hacia esa IP. Sin dominio propio, `<ip-con-guiones>.sslip.io` (por ejemplo `51-79-1-2.sslip.io`) ya apunta a la IP y también recibe un certificado válido.
4. Comprobar desde la computadora que el nombre ya resuelve: `nslookup <dominio>` debe responder la IP del VPS.

## 2. Preparar el servidor

Desde la computadora, entrar con el usuario que OVHcloud crea en Ubuntu (`ubuntu`):

```bash
ssh ubuntu@<dominio>
curl -fsSL https://raw.githubusercontent.com/JAguilar4k/ArchivumParfums-Hexagonal/main/deploy/provision.sh -o provision.sh
less provision.sh                      # leerlo antes de correrlo como root
sudo bash provision.sh <dominio>
```

El script tarda unos minutos. Instala los paquetes, cierra el cortafuegos salvo 22, 80 y 443, crea el usuario `archivum`, clona el repositorio en `/srv/archivum/app`, crea los roles `archivum_owner` y `archivum_app` con contraseñas al azar, escribe el `.env` de producción (`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `ARCHIVUM_WEB_ORIGIN` y las direcciones de regreso de los pagos con el dominio) y publica la primera versión. Las contraseñas de la base quedan solo en ese `.env`, que tiene permisos 600.

## 3. Poner las llaves

```bash
sudo -u archivum bash /srv/archivum/app/deploy/secrets.sh
```

Pide, sin mostrarlas, la llave secreta y la pública de prueba de ONVO, el secreto de firma del webhook de ONVO, el Client ID, el Secret y el Webhook ID del sandbox de PayPal, la llave de Gemini y la contraseña de las cuentas de demostración. Enter deja un valor como estaba, así que se puede correr otra vez cuando llegue el secreto de un webhook.

Con la contraseña de demostración puesta, cargar una sola vez los datos ficticios de los seeders (#27):

```bash
cd /srv/archivum/app/archivum-api-hexagonal && sudo -u archivum php artisan db:seed --force
```

## 4. Registrar los webhooks

**ONVO** (panel de ONVO, modo de prueba, *Desarrolladores > Webhooks*):

1. Crear un webhook con la URL `https://<dominio>/api/v1/webhooks/onvo`.
2. Elegir los eventos `checkout_session.succeeded`, `payment_intent.succeeded`, `payment_intent.failed` y `payment_intent.processing`.
3. Copiar el secreto de firma (empieza por `whsec_`) y ponerlo en `ONVO_WEBHOOK_SECRET` con `secrets.sh`.
4. Usar *Probar conexión*: la API responde 200 a `webhook_endpoint.test` sin cambiar ningún pago.

ONVO firma cada intento en `X-Onvo-Signature` con HMAC-SHA256 sobre `t.cuerpo`; la API rechaza con 401 una firma que no coincide o que tiene más de cinco minutos.

**PayPal** (developer.paypal.com, *Apps & Credentials*, la app del sandbox):

1. En *Webhooks*, agregar `https://<dominio>/api/v1/webhooks/paypal` con los eventos `CHECKOUT.ORDER.APPROVED`, `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.PENDING`, `PAYMENT.CAPTURE.DENIED`, `PAYMENT.CAPTURE.DECLINED` y `PAYMENT.CAPTURE.REFUNDED`.
2. Copiar el *Webhook ID* y ponerlo en `PAYPAL_WEBHOOK_ID` con `secrets.sh`.

## 5. Comprobar

```bash
curl -i https://<dominio>/up               # 200
curl -i https://<dominio>/api/v1           # 200
curl -i https://<dominio>/api/v1/nada      # 404 problem+json, sin traza
curl -i http://<dominio>/api/v1            # 308 a https
sudo systemctl status archivum-worker      # active (running)
```

Un pago de prueba desde la tienda debe terminar confirmado por el aviso de la pasarela: `webhook_events` guarda el aviso como `processed`.

## Publicar una versión nueva

Después de fusionar en `main`:

```bash
ssh ubuntu@<dominio>
sudo -u archivum bash /srv/archivum/app/deploy/release.sh
```

Trae `main`, instala las dependencias sin las de desarrollo (así `composer` no corre `archivum:install`), corre las migraciones con `archivum_owner`, guarda la configuración y las rutas en caché, compila la SPA y reinicia el worker. Al final consulta `/up`.

Si se cambia el `.env` a mano, correr `php artisan config:cache` y reiniciar el worker (`sudo systemctl restart archivum-worker`), porque la configuración queda en caché.

## Dónde mirar cuando algo falla

| Qué | Dónde |
| --- | --- |
| Errores de la API | `/srv/archivum/app/archivum-api-hexagonal/storage/logs/laravel.log` |
| Worker | `sudo journalctl -u archivum-worker -f` |
| Peticiones y certificado | `/var/log/caddy/archivum.log` y `sudo journalctl -u caddy` |
| Avisos de pago | La tabla `webhook_events`: su `status` y su `error` |
