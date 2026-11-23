# ADR 0013: El sistema se despliega en un VPS de OVHcloud con Caddy

Estado: aceptada. Llega con #21.

## Contexto

El enunciado pide el sistema accesible en internet desde la semana 3, y las pasarelas necesitan una dirección pública con HTTPS para avisar de cada pago: ONVO en `POST /api/v1/webhooks/onvo` y PayPal en `POST /api/v1/webhooks/paypal`. Producción tiene que correr lo mismo que desarrollo y CI: PHP 8.4, PostgreSQL 18 con los dos roles (`archivum_owner` para las migraciones y `archivum_app`, sin `BYPASSRLS`, para las peticiones) y la extensión `btree_gist`, más el worker de la cola `database` (#95, #97), el programador (#96, #156, #158, #159) y la SPA de `archivum-web`.

El repo guía todavía no tiene despliegue (su #30 está pendiente), así que no hay una forma suya que copiar. Su `docs/architecture.md` sí fija dos cosas que se respetan: el programador corre con una entrada de cron que llama a `php artisan schedule:run` cada minuto, y la caché es la tabla `cache` de PostgreSQL en todos los entornos. El curso no usa dinero real y el equipo paga el servidor de su bolsillo.

## Decisión

Un **VPS-1 de OVHcloud** (2 vCores, 4 GB de RAM, 40 GB NVMe, IPv4 propia y respaldo diario incluidos, desde USD 4,54 al mes) con **Ubuntu 24.04**, en la región más cercana a Costa Rica que ofrezca el pedido (EE. UU. Este o Canadá). Todo corre en ese servidor:

| Pieza | Cómo |
| --- | --- |
| HTTPS | Caddy saca y renueva solo el certificado de Let's Encrypt del dominio |
| API | PHP-FPM 8.4 con un pool propio que corre como el usuario `archivum`; Caddy le pasa `/api`, `/up`, `/docs` y `/storage` |
| SPA | Los archivos de `archivum-web/dist`, servidos por Caddy en el mismo dominio; cualquier otra ruta responde `index.html` |
| Base | PostgreSQL 18 del repositorio oficial (PGDG), en el mismo servidor y solo en `localhost`; `database/roles.sql` crea los roles con contraseñas al azar |
| Cola | El servicio `archivum-worker` de systemd con `php artisan queue:work`, que se reinicia solo si falla |
| Programador | `/etc/cron.d/archivum` con `php artisan schedule:run` cada minuto, como el repo guía |

La API y la SPA comparten dominio: `https://<dominio>/` es la tienda y `https://<dominio>/api/v1` la API, así que `ARCHIVUM_WEB_ORIGIN` es el mismo dominio y hace falta un solo certificado. Caddy habla con PHP-FPM por FastCGI, sin otro proxy en medio, de modo que la API ve la IP real del cliente y el esquema HTTPS sin confiar en `X-Forwarded-*`: `ARCHIVUM_TRUSTED_PROXIES` queda vacía.

`deploy/` guarda los archivos del servidor y tres scripts que se pueden leer de arriba abajo:

- `provision.sh` prepara el VPS una sola vez: paquetes, cortafuegos (solo 22, 80 y 443), usuario, roles, `.env` de producción y servicios.
- `release.sh` publica la última versión de `main`: dependencias sin las de desarrollo, migraciones con el rol dueño, `config:cache`, `event:cache` y `route:cache` (la API no tiene vistas que guardar), compilación de la SPA y reinicio del worker.
- `secrets.sh` pide, sin mostrarlas, las llaves de prueba de las pasarelas, la de Gemini y la contraseña de las cuentas de demostración, y las escribe en el `.env` del servidor.

Los secretos viven solo en ese `.env`, con permisos 600 y fuera del repositorio. `docs/deployment.md` tiene los pasos.

## Por qué un VPS de OVHcloud

- Por menos de USD 5 al mes corre todas las piezas del sistema juntas, sin límites de horas, sin dormirse por inactividad y con una IP fija para registrar los webhooks.
- PostgreSQL 18 se instala del repositorio oficial y el rol dueño puede crear `btree_gist`, algo que varias plataformas administradas limitan.
- Cualquier integrante puede entrar por SSH y ver lo mismo que en su computadora: los mismos comandos de `artisan`, los mismos registros.

## Alternativas descartadas

- **Laravel Cloud o Forge.** Hacen esto mismo con menos pasos, pero cobran aparte del servidor y esconden el proceso que el equipo tiene que poder explicar en la defensa.
- **Render, Railway o Fly.io.** Sus planes gratuitos se duermen o caducan y sus bases administradas no siempre ofrecen PostgreSQL 18 el día que sale; el worker y el programador se cobran como servicios aparte.
- **Un hosting compartido con cPanel.** No deja correr un worker permanente ni elegir la versión de PostgreSQL.
- **Docker Compose en el VPS.** Agrega una capa más que aprender y depurar; con tres servicios de systemd y un cron el sistema se entiende leyendo `deploy/`.
- **Nginx con Certbot.** Funciona igual, pero son dos configuraciones y una renovación que vigilar; Caddy resuelve el certificado con la sola línea del dominio.
- **La base en un servicio aparte.** Para una tienda piloto con datos ficticios no compensa el costo; el respaldo diario del VPS cubre la base mientras llegan los respaldos propios.

## Consecuencias

- El equipo administra el servidor: las actualizaciones de Ubuntu (`apt upgrade`) y el reinicio cuando las pide quedan a cargo del dueño del VPS.
- Publicar es entrar por SSH y correr `release.sh`; el despliegue automático desde GitHub llega con #22.
- Un aviso que llega mientras `release.sh` corre las migraciones puede fallar; ONVO y PayPal lo reintentan y la conciliación (#96) lo cubre.
- Los registros de la API están en `storage/logs/laravel.log` y los de Caddy en `/var/log/caddy/archivum.log`.
- Si el sistema necesita más memoria, OVHcloud cambia el VPS-1 por un VPS-2 sin reinstalar.
