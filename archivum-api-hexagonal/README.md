# archivum-api-hexagonal

API REST de ArchivumParfums. Laravel 13, PHP 8.4 y PostgreSQL 18.

## Alcance integrado

La API incluye sesiones del personal, cuenta actual, perfil y ajustes de la tienda, logo, perfumes y presentaciones, fotos, cálculo de precio de venta y vitrina pública, con aislamiento por tienda y contrato OpenAPI. Los servicios y horarios, la disponibilidad, las citas, los pedidos, los pagos y el cliente Vue siguen pendientes de integración. Las pruebas de lo que existe y el avance de las capacidades previstas se consultan por separado en [el plan](../docs/plan.md) y [el tablero](https://github.com/users/JAguilar4k/projects/4).

## Requisitos

| Herramienta | Versión |
|---|---|
| PHP | 8.4 o superior, con las extensiones `pdo_pgsql`, `intl` y `mbstring` |
| Composer | 2.x |
| PostgreSQL | 18 o superior, con la extensión `btree_gist` del paquete `contrib` |
| Git | Cualquier versión reciente |

`php -v`, `php -m`, `composer --version` y `psql --version` muestran lo instalado.

## Instalación

Los comandos de Composer y Artisan sirven en PowerShell, en Git Bash y en una terminal de Linux o macOS. Abre una terminal con PHP y PostgreSQL en el `PATH`; utiliza PHP 8.4, igual que CI. No se necesita Node.js ni Vite para iniciar esta API.

1. Clonar el repositorio y entrar al proyecto:

   ```bash
   git clone https://github.com/JAguilar4k/ArchivumParfums-Hexagonal.git
   cd ArchivumParfums-Hexagonal/archivum-api-hexagonal
   ```

2. Preparar la configuración local. Este comando copia el ejemplo solo si todavía no existe `.env`:

   ```bash
   php -r "file_exists('.env') || copy('.env.example', '.env');"
   ```

   Edita `.env` antes de instalar si PostgreSQL usa otro host o puerto. `APP_URL` debe contener la dirección real por la que se accederá a la API; `http://localhost:8000` es solo un ejemplo local, no una dirección obligatoria. Las conexiones de la aplicación y del dueño del esquema comparten host, puerto y base:

   ```dotenv
   APP_ENV=local
   APP_URL=http://localhost:8000
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=archivum_hexagonal
   DB_USERNAME=archivum_app
   DB_ADMIN_USERNAME=archivum_owner
   ```

   Si esos roles ya existen, escribe sus contraseñas actuales en `DB_PASSWORD` y `DB_ADMIN_PASSWORD`. Si son nuevos, el instalador puede generarlas. `ARCHIVUM_DEMO_PASSWORD` define la contraseña de las cuentas ficticias; el ejemplo usa `demo-archivum` para desarrollo local. Las contraseñas SQL y las de las cuentas HTTP cumplen funciones distintas. `DB_URL`, si se configura, debe ser coherente con los campos `DB_*`; para esta instalación por campos separados se puede dejar comentada.

3. Instalar las dependencias y preparar el entorno:

   ```bash
   composer install
   ```

   Al terminar de instalar, Composer ejecuta `php artisan archivum:install`, que deja todo listo en este orden: crea `.env` a partir de `.env.example`, genera la clave de la aplicación, crea los roles `archivum_owner` y `archivum_app` y las bases de desarrollo y de pruebas igual que `database/roles.sql`, migra con el rol dueño y carga los datos de demostración. Las contraseñas se generan solo para los roles nuevos y quedan en `.env`. Si los roles ya existen en este servidor, se deben escribir sus contraseñas actuales en `DB_ADMIN_PASSWORD` y `DB_PASSWORD`; el instalador nunca las cambia, de modo que otra copia del proyecto sigue conectándose.

4. La primera vez hace falta la contraseña del superusuario `postgres` para crear los roles y las bases. Se pide sin mostrarla y no se guarda. Si el script de Composer no dispone de una terminal interactiva, como puede ocurrir en Windows, completa la preparación ejecutando:

   ```bash
   php artisan archivum:install
   ```

   que sí la pide. Si el superusuario tiene otro nombre, se indica con `--superuser=nombre`; en un script, la contraseña puede venir de la variable de entorno `DB_SUPERUSER_PASSWORD`.

   Volver a ejecutar `composer install` conserva la clave y aplica solo las migraciones pendientes. La siembra automática se ejecuta únicamente cuando la tabla `users` está vacía; si ya hay cuentas, no vuelve a sembrar ni cambia sus datos. `php artisan db:seed` vuelve a aplicar los datos de demostración y puede reasignar valores de esas filas: se utiliza deliberadamente en una base local de demo, no en cada arranque. La siembra corre en una transacción: si falla, se revierte y se puede reintentar después de corregir el error, aunque las migraciones ya estén aplicadas. Con `CI=true` o `CI=1`, con `composer install --no-dev` o con `APP_ENV` distinto de `local`, el instalador omite la preparación local.

5. Preparar las direcciones de archivos públicos y levantar el servidor:

   ```bash
   php artisan config:clear
   php artisan storage:link
   composer run dev
   ```

   `composer run dev` inicia `php artisan serve --no-reload` y funciona en Windows, Linux y macOS sin Node.js ni la extensión PCNTL. La opción conserva el entorno del proceso, incluidas las carpetas temporales que PHP necesita para subir archivos en Windows. Deja abierta esta terminal; **Ctrl+C** detiene el servidor. El enlace `storage:link` publica los logos y las fotos; si ya existe, no hay que recrearlo. Después de cambiar `.env`, limpia la configuración y reinicia el servidor para que las peticiones HTTP usen el mismo host, puerto y credenciales que la consola. Los registros están en `storage/logs/laravel.log`.

6. En Postman, crea una petición **GET** a `/api/v1` sobre la dirección real del servidor, sin autorización, y pulsa **Send**. Con la configuración local de ejemplo sería `http://localhost:8000/api/v1`. Debe responder **200** con `{"nombre":"ArchivumParfums","version":"v1"}`. Consulta también `/up`, `/docs/api`, `/docs/api.json` y `/api/v1/tiendas/archivumparfums/perfumes`. Los dos endpoints de documentación están disponibles fuera de producción.

   Para usar la terminal, abre **otra terminal en esta misma carpeta** y ejecuta:

   ```bash
   curl http://localhost:8000/api/v1
   composer validate --strict
   composer lint
   composer analyse
   composer test
   composer audit --locked
   ```

   En Windows PowerShell, usa `curl.exe` si `curl` es un alias de `Invoke-WebRequest`. La suite comprueba la implementación integrada; la finalización de horarios, disponibilidad, reservas y otros módulos se sigue en sus issues.

### Cambiar dirección o puerto

Las peticiones de la [colección de Postman](tests/Postman/README.md) usan `{{base_url}}`; cambia esa variable una sola vez para apuntar a otra instalación. Las direcciones de los ejemplos cURL también deben sustituirse si tu servidor tiene otra dirección.

El host y el puerto del servidor de desarrollo se eligen al iniciarlo. Por ejemplo:

```bash
php artisan serve --host=localhost --port=8080 --no-reload
```

En ese caso configura `APP_URL=http://localhost:8080` en `.env` y `base_url=http://localhost:8080` en Postman. `APP_URL` no cambia por sí solo el puerto donde escucha Artisan; se utiliza, entre otras cosas, para generar las URL de los archivos públicos. Si Postman corre en otra computadora, su `base_url` debe ser la dirección accesible del servidor, no `localhost` del cliente. En un alojamiento con dominio usa su URL pública; el servidor debe estar configurado para recibir esas peticiones.

`DB_HOST` y `DB_PORT` son la conexión privada a PostgreSQL y no tienen por qué coincidir con la dirección HTTP. No hay IP del equipo de un integrante requerida en el código del producto. Las rutas del contrato OpenAPI son relativas y la paginación genera enlaces para la petición recibida.

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
| `composer validate --strict` | Verifica que el manifiesto y el archivo de bloqueo de Composer sean coherentes. |
| `composer openapi` | Regenera el contrato versionado `openapi/v1.json` con Scramble. |
| `composer audit --locked` | Revisa vulnerabilidades conocidas de las dependencias del archivo de bloqueo, con todas las severidades. |
| `php artisan config:clear` | Limpia la configuración cacheada después de editar `.env`; reinicia también el servidor HTTP. |
| `php artisan storage:link` | Hace accesibles los logos y fotos guardados en el disco público. Se ejecuta una vez por instalación. |
| `php artisan migrate --database=pgsql_admin` | Aplica migraciones pendientes con el rol dueño del esquema, sin volver a sembrar. |
| `php artisan route:list --path=api --except-vendor` | Muestra las rutas realmente cargadas de la API. Añade `-vv` para consultar sus middleware. |
| `php artisan archivum:create-business` | Da de alta una tienda y la cuenta de su dueño. Ver [Dar de alta una tienda](#dar-de-alta-una-tienda). |

Antes de abrir un pull request deben pasar `composer validate --strict`, `composer lint`, `composer analyse` y `composer test`. La auditoría completa se ejecuta con `composer audit --locked`; CI ejecuta `composer audit --locked --ignore-severity=low --ignore-severity=medium` después de las pruebas, por lo que una ejecución local completa puede informar avisos adicionales.

Si cambió un endpoint, regenera el contrato, revisa el diff y versiona el JSON en el mismo PR:

```bash
composer openapi
git diff -- openapi/v1.json
```

Para comprobar que el contrato versionado ya coincide con el generador, usa `git diff --exit-code -- openapi/v1.json` después de exportarlo. Un diff requiere revisar y actualizar el contrato; no significa que se haya ejecutado el comportamiento HTTP.

Cuando se implemente trabajo en cola, el worker local se ejecuta en otra terminal con `php artisan queue:work --tries=1`; el scheduler usa `php artisan schedule:work`. Son procesos separados de `composer run dev`, y se inician cuando el módulo que se prueba los necesita. Este arranque no incorpora pagos, recordatorios o websockets que todavía estén pendientes.

## Pruebas

Las pruebas usan Pest y se ordenan en tres carpetas de `tests/`:

La base de pruebas es `archivum_hexagonal_testing`, configurada en `phpunit.xml`. Las pruebas de funcionalidad recrean tablas y revierten transacciones allí. Ejecuta la suite en una terminal sin valores heredados de `DB_DATABASE` o `DB_URL` que sustituyan la base de pruebas. Si usaste esas variables para otra ejecución, puedes quitarlas solo de la terminal de verificación:

```powershell
Remove-Item Env:DB_DATABASE, Env:DB_URL, Env:APP_ENV -ErrorAction SilentlyContinue
composer test
```

En Bash, el equivalente para una única ejecución es `env -u DB_DATABASE -u DB_URL -u APP_ENV composer test`. El host, puerto y contraseñas de `.env` deben permitir conectar ambos roles al servidor que contiene la base de pruebas. Estas instrucciones no usan `migrate:fresh` sobre la base de desarrollo.

Si la suite agota la memoria del PHP de consola, puedes ejecutar Pest directamente con un límite temporal de 1 GB, conservando `phpunit.xml` y la base de pruebas:

```bash
php artisan config:clear
php -d memory_limit=1G vendor/bin/pest
```

El ajuste pertenece a ese proceso y no modifica `php.ini`. Poner `-d memory_limit=1G` solo delante de `artisan test` no lo garantiza para Pest, porque Artisan lo inicia en otro proceso PHP.

| Carpeta | Qué prueba | Cómo |
|---|---|---|
| `tests/Unit` | El dominio y los casos de uso | Sin arrancar Laravel, con los repositorios en memoria de `tests/Support`. Una prueba de arquitectura falla si un archivo de esta carpeta importa Laravel o código de infraestructura. |
| `tests/Feature` | Los endpoints y los adaptadores | Contra PostgreSQL con el rol `archivum_app`. `RefreshDatabaseAsOwner` migra con `archivum_owner` y envuelve cada prueba en una transacción que se deshace al terminar. |
| `tests/Architecture` | Las reglas de la arquitectura | Con `arch()` de Pest y leyendo los archivos del proyecto. |

Ayudas para las pruebas de `tests/Feature`:

- `actingAsOwner()`, `actingAsStaff()` y `actingAsCustomer()` crean una tienda con su dueño, un colaborador y un cliente (`StaffedBusiness`, con las fábricas de miembros y clientes), inician sesión como esa persona con `Sanctum::actingAs()` y devuelven la tienda. Si reciben una tienda, la reutilizan. `actingAsRole('dueno')` hace lo mismo con el nombre del rol que usa la API; con `visitante` no inicia sesión.
- `DemoAccounts::seed()` siembra los datos de demostración y `DemoAccounts::signIn($correo)` ingresa por `POST /api/v1/sesiones`, como lo hace un cliente de la API, y devuelve el encabezado `Authorization`. Sirve a las pruebas que necesitan un token real, como las de `GET /api/v1/cuenta` en `tests/Feature/Accounts/Sessions/CurrentAccountTest.php`.
- Los datasets de `tests/Datasets` recorren los roles de la matriz de permisos (`roles`) y el personal de otra tienda (`staff of another business`).
- `inParallel($tablas, $operacion1, $operacion2)` ejecuta cada operación en su propio proceso, con su propia conexión y fuera de la transacción de la prueba, todas en el mismo instante, y devuelve sus resultados en orden. Lo que las operaciones comparten se inserta antes con `DB::connection('pgsql_admin')`, y las tablas de `$tablas` se vacían al terminar la prueba. Cada operación se escribe en su propia línea y no usa `$this`. Es la ayuda de las pruebas de concurrencia; el ejemplo está en `tests/Feature/Shared/InParallelTest.php`.

Cada prueba que demuestra una regla de la sección 5 de la [especificación](../docs/especificacion.md) la nombra al inicio de su descripción, por ejemplo `it('R14: ...')`, de modo que una búsqueda de `R14:` en `tests/` encuentra sus pruebas. `tests/Architecture/BusinessRulesTest.php` falla si una regla no aparece en ninguna prueba ni en la lista de pendientes `tests/Architecture/pending-rules.php`, o si sigue en esa lista cuando una prueba ya la nombra. El issue que prueba una regla la quita de la lista en el mismo pull request.

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
| POST | `/api/v1/panel/tienda/logo` | Dueño | Sube el logo mediante `multipart/form-data`. |
| GET | `/api/v1/panel/perfumes` | Dueño | Lista perfumes de su tienda, incluidos los inactivos, con búsqueda por marca/nombre y paginación. |
| POST | `/api/v1/panel/perfumes` | Dueño | Crea un perfume con sus notas y etiquetas. |
| GET | `/api/v1/panel/perfumes/{perfume}` | Dueño | Consulta un perfume de su tienda. |
| PATCH | `/api/v1/panel/perfumes/{perfume}` | Dueño | Edita los campos enviados; `activo=false` lo desactiva. |
| POST | `/api/v1/panel/perfumes/{perfume}/foto` | Dueño | Sube la foto mediante `multipart/form-data`. |
| GET | `/api/v1/panel/perfumes/{perfume}/presentaciones` | Dueño | Lista las presentaciones de ese perfume. |
| POST | `/api/v1/panel/perfumes/{perfume}/presentaciones` | Dueño | Crea una presentación con volumen y precio. |
| PATCH | `/api/v1/panel/perfumes/{perfume}/presentaciones/{presentacion}` | Dueño | Edita la presentación o cambia su estado activo. |
| GET | `/api/v1/tiendas/{tienda}` | Cualquiera | Perfil público de la tienda identificada por su slug. |
| GET | `/api/v1/tiendas/{tienda}/perfumes` | Cualquiera | Vitrina pública de perfumes activos, con búsqueda, filtros y paginación. |
| GET | `/api/v1/tiendas/{tienda}/perfumes/{perfume}` | Cualquiera | Detalle público del perfume activo con sus presentaciones activas. |
| GET | `/docs/api` | Desarrollo local | Documentación interactiva de Scramble; el gate la deniega en producción. |
| GET | `/docs/api.json` | Desarrollo local | Contrato OpenAPI generado de las rutas. |

Las rutas sin versión, como `/api`, responden 404: toda ruta de la API lleva el prefijo `/api/v1`.

Cada pull request que agrega un endpoint agrega su fila a esta tabla. La lista prevista está en la sección 8 de la [especificación](../docs/especificacion.md#8-api-inicial). Las rutas de servicios, horarios, disponibilidad, reservas y pagos de esa lista siguen pendientes de integración y no forman parte de esta tabla de rutas disponibles.

## Recorrido básico en Postman

La colección y el entorno se incluyen en [`tests/Postman/`](tests/Postman/README.md), con 35 peticiones ordenadas, datos ficticios y captura de variables desde las respuestas. Al clonar el repositorio, importa ambos JSON y ajusta `base_url` a la dirección real de tu API. La carpeta contiene las instrucciones y el alcance; las pruebas HTTP no añaden dependencias a las capas del producto.

Realiza este recorrido con las cuentas ficticias de una instalación local. Configura **Accept: application/json**. En los POST y PATCH con JSON, usa **Body → raw → JSON**; en archivos, usa **Body → form-data** y deja que Postman genere `Content-Type` con su boundary.

1. Envía `GET /api/v1/tiendas/archivumparfums/perfumes` sin autorización. Debe responder 200.
2. Envía `POST /api/v1/sesiones` con este cuerpo, usando la contraseña de `ARCHIVUM_DEMO_PASSWORD` de tu instalación:

   ```json
   {
     "correo": "duena@archivumparfums.test",
     "contrasena": "demo-archivum",
     "dispositivo": "Postman local"
   }
   ```

3. La respuesta 201 contiene `data.token`. Cópialo completo, sin las comillas, en **Authorization → Bearer Token**. Envía `GET /api/v1/cuenta` y confirma el correo, el rol `dueno` y la tienda. El token HTTP se obtiene del login; la contraseña del rol SQL se utiliza únicamente para conectar PostgreSQL.
4. Con ese token, envía `POST /api/v1/panel/perfumes` con datos ficticios:

   ```json
   {
     "marca": "Laboratorio local",
     "nombre": "Ensayo de catálogo",
     "familia": "Amaderada",
     "descripcion": "Perfume ficticio para la demostración",
     "activo": true
   }
   ```

5. Conserva `data.id` de la respuesta 201. Sustituye `{perfume}` en `POST /api/v1/panel/perfumes/{perfume}/presentaciones` y envía `{"ml":50,"precio_manual":9000}`. Debe crear una presentación con 201. Son 9000 colones enteros.
6. Sin autorización, consulta `GET /api/v1/tiendas/archivumparfums/perfumes/{perfume}`. El mismo UUID bajo `esencias-del-norte` debe recibir 404. La prueba usa un recurso cuya existencia acabas de confirmar.
7. Ingresa como `asesor@archivumparfums.test` en una petición separada. Confirma su identidad con `GET /api/v1/cuenta` y su token; `GET /api/v1/panel/tienda` debe devolver 403. Un 401 indica un problema de token, no una comprobación correcta del permiso por rol.
8. Con el token de la dueña, envía `DELETE /api/v1/sesiones/actual`. Responde 204 sin cuerpo. Reutiliza **ese mismo token** en `GET /api/v1/cuenta`: ahora debe recibir 401.

Las validaciones fallidas deben responder 422 con `errores`, y los errores usan `application/problem+json`, `type`, `title`, `status`, `detail` e `instance`, correlacionado con `X-Request-Id`. La tienda del panel se toma de la sesión, por lo que cambiar `business_id` en un cuerpo no cambia su alcance. Verifica los dos dueños y las operaciones prohibidas cuando pruebes aislamiento privado; el recorrido público anterior no sustituye esa comprobación.

Para probar fotos, añade `foto` como archivo en el POST de foto; para el logo, el campo es `logo`. Envía una imagen ficticia del tipo y tamaño admitidos por el contrato y abre la URL devuelta. Ese segundo paso comprueba el enlace público y `APP_URL`, además de la respuesta de subida. PHP necesita una carpeta temporal existente y escribible; si informa `unable to create a temporary file`, revisa `upload_tmp_dir` en el PHP que atiende HTTP y utiliza el comando de arranque documentado con `--no-reload`.

Los ejemplos cURL de la sección siguiente usan sintaxis Bash para continuar líneas; en PowerShell puedes crear las mismas solicitudes en Postman o escribir el comando `curl.exe` en una sola línea.

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
