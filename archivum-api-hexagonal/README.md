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

Antes de abrir un pull request deben pasar `composer lint`, `composer analyse` y `composer test`. Si cambió un endpoint, hay que regenerar el contrato con `composer openapi` y versionarlo. La integración continua ejecuta los mismos pasos, más `composer audit`, en cada pull request.

## Endpoints

| Método | Ruta | Quién | Descripción |
|---|---|---|---|
| GET | `/` | Cualquiera | Redirección temporal (302) a `/api/v1`, para que abrir el servidor en el navegador no dé 404. |
| GET | `/up` | Cualquiera | Estado de la aplicación, fuera del prefijo `/api/v1`. |
| GET | `/api/v1` | Cualquiera | Nombre y versión de la API. |

Las rutas sin versión, como `/api`, responden 404: toda ruta de la API lleva el prefijo `/api/v1`.

Cada pull request que agrega un endpoint agrega su fila a esta tabla. La lista prevista está en la sección 8 de la [especificación](../docs/especificacion.md#8-api-inicial).

## Datos de demostración

`php artisan db:seed` crea una cuenta ficticia, `test@example.com`, con la contraseña `password`. Las tiendas de demostración y su personal llegan con #27. Todos los datos de los sembradores son ficticios y sus contraseñas son conocidas: `db:seed` nunca se ejecuta en producción.

## Variables de entorno

`.env.example` lista todas las variables que lee la aplicación. Las propias del producto:

| Variable | Uso |
|---|---|
| `APP_TIMEZONE` | Zona horaria de la aplicación y de las dos conexiones de PostgreSQL. Por defecto, `America/Costa_Rica`. |
| `DB_DATABASE` | Base de desarrollo, `archivum_hexagonal`. Las pruebas usan `archivum_hexagonal_testing`, fijada en `phpunit.xml`. |
| `DB_USERNAME`, `DB_PASSWORD` | Rol de la aplicación, `archivum_app`, y su contraseña. Atiende las peticiones. |
| `DB_ADMIN_USERNAME`, `DB_ADMIN_PASSWORD` | Rol dueño del esquema, `archivum_owner`, y su contraseña. Lo usa la conexión `pgsql_admin` para migrar. |
| `DB_SSLMODE` | Modo SSL de las dos conexiones. Por defecto, `prefer`. |

El código nunca lee variables de entorno directamente: lo hacen los archivos de `config/`, y Larastan falla si una llamada a `env()` aparece fuera de ellos. `.env` nunca se versiona; `.env.example` se actualiza en el mismo pull request que agrega una variable.

## Estructura

```
app/         Arranque del framework
bootstrap/   Rutas, middleware y proveedores de la aplicación
config/      Configuración: aplicación, base de datos y colas
database/    Roles (roles.sql), migraciones, factories y seeders
lang/        Mensajes al usuario en español, con el inglés como respaldo
routes/      Punto de entrada de las rutas de cada módulo
src/         Código del producto por contexto y módulo: Domain, Application e Infrastructure
tests/       Pruebas unitarias, de funcionalidad y de arquitectura
```

La arquitectura se describe en [docs/architecture.md](../docs/architecture.md).
