# archivum-api-hexagonal

API REST de ArchivumParfums. Laravel 13, PHP 8.4 y PostgreSQL 18.

## Requisitos

| Herramienta | Versión |
|---|---|
| PHP | 8.4 o superior, con las extensiones `pdo_pgsql`, `intl` y `mbstring` |
| Composer | 2.x |
| PostgreSQL | 18 o superior, con la extensión `btree_gist` del paquete `contrib` |
| Git | Cualquier versión reciente |

`php -v`, `php -m`, `composer --version` y `psql --version` muestran lo instalado.

## Instalación

Los comandos sirven en PowerShell, en Git Bash y en una terminal de Linux o macOS.

1. Clonar el repositorio y entrar al proyecto:

   ```bash
   git clone https://github.com/JAguilar4k/ArchivumParfums-Hexagonal.git
   cd ArchivumParfums-Hexagonal/archivum-api-hexagonal
   ```

2. Instalar las dependencias y preparar el entorno:

   ```bash
   composer install
   ```

   Al terminar de instalar, Composer ejecuta `php artisan archivum:install`, que deja todo listo en este orden: crea `.env` a partir de `.env.example`, genera la clave de la aplicación, crea los roles `archivum_owner` y `archivum_app` y las bases de desarrollo y de pruebas igual que `database/roles.sql`, migra con el rol dueño y carga los datos de demostración. Las contraseñas se generan solo para los roles nuevos y quedan en `.env`. Si los roles ya existen en este servidor, se deben escribir sus contraseñas actuales en `DB_ADMIN_PASSWORD` y `DB_PASSWORD`; el instalador nunca las cambia, de modo que otra copia del proyecto sigue conectándose.

3. La primera vez hace falta la contraseña del superusuario `postgres` para crear los roles y las bases. Se pide sin mostrarla y no se guarda. En Windows, Composer no deja escribir en sus scripts: la instalación termina con un aviso y basta ejecutar una vez

   ```bash
   php artisan archivum:install
   ```

   que sí la pide. Si el superusuario tiene otro nombre, se indica con `--superuser=nombre`; en un script, la contraseña puede venir de la variable de entorno `DB_SUPERUSER_PASSWORD`.

4. Volver a ejecutar `composer install` no borra nada: conserva la clave, aplica solo las migraciones pendientes y completa los datos de demostración que falten sin modificar los existentes. La siembra corre en una transacción: si falla, se revierte y se puede reintentar después de corregir el error, aunque las migraciones ya estén aplicadas. En la integración continua, con `composer install --no-dev` o con `APP_ENV` distinto de `local`, `archivum:install` no hace nada.

5. Levantar el servidor:

   ```bash
   composer run dev
   ```

   Este comando inicia `php artisan serve` y funciona en Windows, Linux y macOS sin Node.js ni la extensión PCNTL. Si se necesita procesar trabajos en cola durante el desarrollo, se abre otra terminal en esta misma carpeta y se ejecuta `php artisan queue:work --tries=1`. Los registros están en `storage/logs/laravel.log`.

6. Comprobar que todo responde:

   ```bash
   curl http://localhost:8000/api/v1
   composer test
   ```

   La primera petición devuelve `{"nombre":"ArchivumParfums","version":"v1"}` y las pruebas deben pasar todas.

La aplicación se conecta con `archivum_app`, que no puede cambiar el esquema; las migraciones corren con `archivum_owner` a través de la conexión `pgsql_admin`. Por eso `php artisan migrate` sin `--database=pgsql_admin` falla con `Insufficient privilege` en cuanto hay una tabla que crear o cambiar. El motivo está en [docs/database.md](../docs/database.md).

Las tablas de una tienda tienen seguridad a nivel de fila forzada: `archivum_app` solo ve y escribe las filas de la tienda fijada en la transacción, y `archivum_owner` tampoco puede escribirlas sin fijarla. `archivum_owner` sí lee todas las filas de `members` y `customers`, por la política `<tabla>_definer_read`, que existe para las funciones `SECURITY DEFINER` que buscan la tienda de una cuenta antes de fijarla. `archivum_app` no es dueño de las tablas ni tiene `BYPASSRLS`, y nunca se le debe otorgar. Para revisar a mano los clientes de la tienda de demostración ArchivumParfums en `psql`, conectado como `archivum_app`:

```sql
BEGIN;
SELECT set_config('app.business_id', '0192f0c4-0000-7000-8000-000000000001', true);
SELECT id, user_id, created_at FROM customers;
COMMIT;
```

## Comandos

| Comando | Qué hace |
|---|---|
| `php artisan archivum:install` | Prepara el entorno local paso a paso. Lo ejecuta `composer install`; a mano solo hace falta para escribir la contraseña de `postgres` en Windows. |
| `composer run dev` | Levanta el servidor local de la API. El worker de colas se inicia en otra terminal. |
| `composer test` | Ejecuta las pruebas de Pest contra la base `archivum_hexagonal_testing`. |
| `composer lint` | Verifica el estilo con Laravel Pint, sin modificar archivos. |
| `composer format` | Corrige el estilo. |
| `composer analyse` | Análisis estático con Larastan en nivel 8. |
| `composer openapi` | Regenera el contrato `openapi/v1.json`. Llega con #50. |
| `composer audit` | Revisa vulnerabilidades conocidas en las dependencias. |
| `php artisan archivum:create-business` | Da de alta una tienda y la cuenta de su dueño. Ver [Dar de alta una tienda](#dar-de-alta-una-tienda). |

Antes de abrir un pull request deben pasar `composer lint`, `composer analyse` y `composer test`. Si cambió un endpoint, hay que regenerar el contrato con `composer openapi` y versionarlo. La integración continua ejecuta los mismos pasos, más `composer audit`, en cada pull request.

## Dar de alta una tienda

La API no ofrece rutas para crear tiendas: se dan de alta desde la consola. Un solo comando crea la tienda, con los ajustes por defecto de la sección 10 de la [especificación](../docs/especificacion.md#10-valores-por-defecto), y la cuenta de su dueño; si alguno de los dos pasos falla, no queda ninguno.

```bash
php artisan archivum:create-business "ArchivumParfums" archivumparfums "Valeria Solano Ruiz" duena@archivumparfums.test
```

| Parámetro | Qué es |
|---|---|
| `name` | Nombre de la tienda, de 100 caracteres como máximo. |
| `slug` | Identificador de la tienda en las direcciones públicas: minúsculas, dígitos y guiones. No puede repetirse. |
| `owner-name` | Nombre del dueño. |
| `owner-email` | Correo del dueño, con el que ingresa. No puede pertenecer a otra cuenta. |
| `--password=` | Contraseña del dueño, de 8 caracteres como mínimo. Si se omite, el comando la pide sin mostrarla; si el terminal no puede ocultar la entrada, el comando falla y debe usarse `--password`. |

El comando pide cada parámetro que falte. Devuelve 0 si da de alta la tienda y 1 si rechaza algún dato, con el motivo en pantalla. Sin `--password` y sin terminal interactiva rechaza la contraseña. Una contraseña escrita en `--password` queda en el historial del terminal; para uso manual conviene dejar que el comando la pida.

## Endpoints

| Método | Ruta | Quién | Descripción |
|---|---|---|---|
| GET | `/` | Cualquiera | Redirección temporal (302) a `/api/v1`, para que abrir el servidor en el navegador no dé 404. |
| GET | `/up` | Cualquiera | Estado de la aplicación, fuera del prefijo `/api/v1`. |
| GET | `/api/v1` | Cualquiera | Nombre y versión de la API. |
| POST | `/api/v1/sesiones` | Cualquiera | Ingresa con `correo`, `contrasena` y el nombre del `dispositivo` y devuelve un token Bearer. |
| DELETE | `/api/v1/sesiones/actual` | Con token | Cierra la sesión: revoca solo el token de la petición y responde 204. |
| GET | `/api/v1/cuenta` | Con token | Nombre, correo y rol de la cuenta (`dueno`, `colaborador` o `cliente`) y, para el personal, su tienda. |
| GET | `/api/v1/panel/tienda` | Dueño | Perfil y ajustes de la tienda de la sesión: nombre, slug, lugar de la cita, zona horaria y ajustes. |
| PATCH | `/api/v1/panel/tienda` | Dueño | Cambia solo los campos enviados del perfil y de los ajustes. El slug no cambia. |

Las rutas sin versión, como `/api`, responden 404: toda ruta de la API lleva el prefijo `/api/v1`.

Cada pull request que agrega un endpoint agrega su fila a esta tabla. La lista prevista está en la sección 8 de la [especificación](../docs/especificacion.md#8-api-inicial).

El token de `POST /api/v1/sesiones` se muestra una sola vez y se envía en cada petición en el encabezado `Authorization: Bearer <token>`; cada ingreso crea un token nuevo con el nombre de su dispositivo, así cada sesión se distingue de las demás, y `DELETE /api/v1/sesiones/actual` borra solo el token de la petición, sin cerrar las demás sesiones de la cuenta. Un correo inexistente y una contraseña incorrecta reciben la misma respuesta 401 `credenciales-invalidas`, sin indicar cuál dato falló, como en el proyecto guía. Los campos ausentes o mal formados responden 422 `datos-invalidos` con sus errores de validación. Sin token, o con uno inválido, las rutas protegidas responden 401 `no-autenticado`. Para ingresar con una cuenta de los [datos de demostración](#datos-de-demostración):

```bash
curl -X POST http://localhost:8000/api/v1/sesiones \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"correo": "duena@archivumparfums.test", "contrasena": "<ARCHIVUM_DEMO_PASSWORD>", "dispositivo": "Computadora"}'
```

La respuesta `201` trae el token en `data.token`. Con él se consulta la cuenta que ingresó:

```bash
curl http://localhost:8000/api/v1/cuenta -H 'Accept: application/json' -H 'Authorization: Bearer <token>'
```

Para cerrar esa sesión se envía el mismo token; la respuesta es `204` y las demás sesiones de la cuenta siguen abiertas:

```bash
curl -X DELETE http://localhost:8000/api/v1/sesiones/actual -H 'Accept: application/json' -H 'Authorization: Bearer <token>'
```

Mientras llegan las abilities y las policies de #102 y #104, el middleware `RequireAccountRole` separa los grupos de rutas según el rol indicado. `role:owner` deja pasar solo a los dueños de la tienda: la configuración (perfil y ajustes, servicios, horarios, bloqueos, perfumes, botellas y proveedores) y las citas que se reservan, reprograman o cancelan en nombre de un cliente. `role:customer` deja pasar solo a las cuentas sin fila en `members`: las reservas, los pedidos y las rutas de `/api/v1/cuenta/...`. Cualquier otra cuenta recibe 403 `prohibido` antes de que la petición escriba algo, y `GET /api/v1/cuenta` sigue abierta a los tres roles. `tests/Feature/Accounts/Sessions/RoleGroupsTest.php` falla si una ruta de esos grupos no lleva su middleware, y cada endpoint nuevo de los dos grupos suma su fila a su dataset, con el estado exacto que recibe el rol permitido.

La tienda de `/api/v1/panel/tienda` siempre es la de la sesión: un `business_id` o un slug en el cuerpo se ignoran. `PATCH` acepta `nombre`, `lugar_cita` (`null` lo deja sin configurar), `zona_horaria` (identificador IANA) y, dentro de `ajustes`, cualquiera de estos campos; los que no se envían conservan su valor:

| Ajuste | Qué define | Por defecto |
|---|---|---|
| `margen_botella` | Margen en colones que se suma al costo del proveedor de cada presentación (R1) | 5000 |
| `tamanos_decant_ml` | Tamaños de decant que se venden, en ml, sin repetir | `[2, 5, 10]` |
| `anticipacion_minima_horas` | Horas mínimas entre la reserva y la cita | 12 |
| `horizonte_reserva_dias` | Días hacia adelante en que se puede reservar; no puede quedar por debajo de la anticipación | 30 |
| `plazo_cambio_cita_horas` | Horas antes de la cita hasta las que se reprograma o cancela | 2 |
| `apartado_lista_espera_horas` | Horas que se aparta un cupo liberado para la lista de espera | 2 |
| `vencimiento_sin_pago_minutos` | Minutos tras los que vence un pedido o paquete sin pagar | 30 |
| `recordatorios_horas` | Horas antes de la cita en que sale cada recordatorio | `[24, 2]` |
| `pasarela_por_defecto` | `onvo` o `paypal` | `onvo` |
| `tipo_cambio_usd` | Colones por dólar para cobrar con PayPal, mayor que cero | `null` |

La base guarda en `businesses.settings` solo los ajustes que difieren de los valores por defecto, así una tienda sigue un cambio posterior de un valor por defecto.

La actualización lee y bloquea la fila de la tienda dentro de una transacción antes de combinar los cambios. Dos peticiones simultáneas que editan ajustes distintos conservan ambos cambios; `UpdateBusinessProfileConcurrencyTest` lo verifica con dos conexiones de PostgreSQL, fuera de una transacción de prueba.

## Datos de demostración

`php artisan db:seed` crea dos tiendas ficticias, solo para desarrollo local y la demostración en clase: `BusinessesSeeder` siembra las tiendas, `StaffSeeder` su personal y `CustomersSeeder` sus clientes, cada tienda en una transacción que la fija. Todas las cuentas usan la contraseña de `ARCHIVUM_DEMO_PASSWORD` en `.env`; sin ella, `db:seed` se detiene sin crear nada. Ejecutarlo otra vez no duplica nada y repone las filas de demostración que falten.

| Tienda | Cuenta | Papel |
|---|---|---|
| ArchivumParfums (`archivumparfums`) | `duena@archivumparfums.test` | Dueña; atiende citas. |
| | `coduena@archivumparfums.test` | Co-dueña; no atiende citas. |
| | `asesor@archivumparfums.test` | Colaborador; atiende citas. |
| | `mariana@clientes.test`, `carlos@clientes.test`, `sofia@clientes.test` | Clientes. |
| Esencias del Norte (`esencias-del-norte`) | `dueno@esenciasdelnorte.test` | Dueño; atiende citas. |
| | `lucia@clientes.test`, `sofia@clientes.test` | Clientes. `sofia@clientes.test` es cliente de las dos tiendas. |

Las tiendas tienen identificadores fijos: `0192f0c4-0000-7000-8000-000000000001` es ArchivumParfums y `0192f0c4-0000-7000-8000-000000000002` es Esencias del Norte. La segunda tienda sirve para demostrar el aislamiento entre tiendas: el dueño de cada una pide en el panel un recurso de la otra y recibe 404 `no-encontrado`, la misma respuesta que con un recurso que no existe. Las pruebas de `tests/Feature/Tenancy` comprueban lo mismo en cada endpoint del panel con dos tiendas propias, creadas con las fábricas, así que no dependen de estos datos. Los nombres son inventados, los correos usan el dominio reservado `.test` y los teléfonos son de prueba. Como las contraseñas de los sembradores son conocidas, `db:seed` nunca se ejecuta en producción.

## Variables de entorno

`.env.example` lista todas las variables que lee la aplicación. Las propias del producto:

| Variable | Uso |
|---|---|
| `APP_TIMEZONE` | Zona horaria de la aplicación y de las dos conexiones de PostgreSQL. Por defecto, `America/Costa_Rica`. |
| `DB_DATABASE` | Base de desarrollo, `archivum_hexagonal`. Las pruebas usan `archivum_hexagonal_testing`, fijada en `phpunit.xml`. |
| `DB_USERNAME`, `DB_PASSWORD` | Rol de la aplicación, `archivum_app`, y su contraseña. Atiende las peticiones. |
| `DB_ADMIN_USERNAME`, `DB_ADMIN_PASSWORD` | Rol dueño del esquema, `archivum_owner`, y su contraseña. Lo usa la conexión `pgsql_admin` para migrar. |
| `DB_SSLMODE` | Modo SSL de las dos conexiones. Por defecto, `prefer`. |
| `ARCHIVUM_DEMO_PASSWORD` | Contraseña de las cuentas de los [datos de demostración](#datos-de-demostración). Se deja vacía fuera del desarrollo local. |

El código nunca lee variables de entorno directamente: lo hacen los archivos de `config/`, porque con `php artisan config:cache` en producción `env()` devuelve `null` fuera de ellos. `tests/Architecture/EnvironmentConfigurationTest.php` falla si se llama `env()` fuera de `config/`, si `config/` lee una variable que `.env.example` no lista o si `.env.example` trae el valor de una llave, un secreto, un token o una contraseña. `config/` solo guarda los archivos que cambian algo del framework, y `.env.example` solo las variables que la API usa: el issue que agrega un servicio (correo, colas en Redis, pasarelas, IA) agrega sus variables a `.env.example` y a esta tabla en el mismo pull request. `.env` nunca se versiona, y las credenciales de producción se cargan en la plataforma de despliegue, nunca en el repositorio.

Los ajustes de cada tienda no son variables de entorno: se guardan en `businesses.settings` y se cambian con `PATCH /api/v1/panel/tienda`. `APP_TIMEZONE` es solo la zona de la instalación; la de cada tienda está en `businesses.timezone`. La configuración del producto que no depende de la tienda, como el tiempo máximo de 10 s de la IA, está fija en `config/archivum.php`.

## Estructura

```
app/         Arranque del framework
bootstrap/   Rutas, middleware y proveedores de la aplicación
config/      Configuración: aplicación, autenticación (auth.php, sanctum.php), base de datos y colas
database/    Roles (roles.sql), migraciones, factories y seeders
lang/        Mensajes al usuario en español, con el inglés como respaldo
routes/      Punto de entrada de las rutas de cada módulo
src/         Código del producto por contexto y módulo: Domain, Application e Infrastructure
tests/       Pruebas unitarias, de funcionalidad y de arquitectura
```

La arquitectura se describe en [docs/architecture.md](../docs/architecture.md).
