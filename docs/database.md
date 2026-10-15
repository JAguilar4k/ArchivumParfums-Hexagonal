# Base de datos: PostgreSQL 18

ArchivumParfums usa PostgreSQL 18 como único motor, en desarrollo, en pruebas y en producción. Este documento fija cómo se conecta la aplicación y cómo se crean y versionan los objetos de la base. El detalle de cada tabla y la verificación de la tercera forma normal están en `docs/data-model.md` (#35).

## Versión

La versión mínima es PostgreSQL 18. `DatabaseConnectionTest` falla si el servidor es anterior, porque el proyecto usa funciones que no existen antes de esa versión, como `uuidv7()`. SQLite queda descartado: no entiende `uuidv7()`, las restricciones de exclusión ni las políticas de fila.

| Base | Para qué |
|---|---|
| `archivum_hexagonal` | Desarrollo; la nombra `DB_DATABASE` en `.env` |
| `archivum_hexagonal_testing` | Pruebas de Pest; la fija `phpunit.xml` y cada corrida la migra desde cero |

## Roles

La aplicación nunca se conecta con el rol dueño de las tablas.

| Rol | Conexión de Laravel | Variables de `.env` | Qué hace | Qué no puede hacer |
|---|---|---|---|---|
| `archivum_owner` | `pgsql_admin` | `DB_ADMIN_USERNAME`, `DB_ADMIN_PASSWORD` | Es dueño de las dos bases y ejecuta las migraciones | No atiende peticiones |
| `archivum_app` | `pgsql` (por defecto) | `DB_USERNAME`, `DB_PASSWORD` | Lee y escribe filas | Crear, alterar o borrar tablas; no es superusuario ni tiene `BYPASSRLS`, `CREATEDB` ni `CREATEROLE` |

El script `database/roles.sql` crea los dos roles y las dos bases, le quita a `PUBLIC` todo permiso sobre ellas y le da a `archivum_app` solo `CONNECT`. Lo ejecuta una sola vez por servidor un superusuario, con las contraseñas como variables de `psql`:

```bash
psql -U postgres -v owner_password='…' -v app_password='…' -f database/roles.sql
```

Las contraseñas nunca se versionan: cada integrante las escribe en su propio `.env`, que tampoco se versiona.

La primera migración, `0001_01_01_000000_grant_privileges_to_the_application_role`, otorga al rol de la aplicación, con `ALTER DEFAULT PRIVILEGES`, lectura y escritura sobre toda tabla y secuencia que el dueño cree después. Ninguna tabla nueva necesita un paso manual. `DatabaseRolesTest` comprueba que `archivum_app` no tiene atributos elevados, no es dueño de ningún objeto, puede leer y escribir en toda tabla y no puede crear, alterar ni borrar tablas.

### Por qué las migraciones corren con `--database=pgsql_admin`

`archivum_app` no tiene permiso para cambiar el esquema: una migración con la conexión por defecto falla con `Insufficient privilege`. Por eso las migraciones usan la conexión `pgsql_admin`, que entra como `archivum_owner`.

La separación importa por la seguridad a nivel de fila. En PostgreSQL los superusuarios y los roles con `BYPASSRLS` no pasan por las políticas, y el dueño de una tabla tampoco, salvo que la tabla las fuerce. Las tablas de una tienda las fuerzan (#32), pero la aplicación igual se conecta con un rol que no es dueño ni tiene `BYPASSRLS` (R55): el aislamiento entre tiendas no depende de una sola línea de una migración.

Un tercer rol, de mantenimiento, llega con #157 para las tareas que recorren todas las tiendas.

## Migraciones

- Se ejecutan siempre con el rol dueño: `php artisan migrate --database=pgsql_admin`.
- En las pruebas, el trait `Tests\Support\RefreshDatabaseAsOwner` migra con `pgsql_admin` y la prueba corre con `archivum_app`.
- Cada migración tiene su `down()`. `MigrationReversibilityTest` revierte las migraciones una por una con `pgsql_admin` sobre la base de pruebas, comprueba que no queden tablas, vistas, tipos, funciones ni privilegios por defecto, las vuelve a aplicar y exige el mismo esquema que había. Falla si alguna migración no es reversible.
- Las extensiones no se borran al revertir (ver [Extensiones](#extensiones)) y la prueba ignora sus objetos.
- Un cambio que rompe la versión anterior del código se hace en dos despliegues: primero se expande el esquema y, cuando el código viejo ya no corre, se contrae.

## Convenciones del esquema

| Tema | Convención |
|---|---|
| Nombres | Inglés, `snake_case`; tablas en plural |
| Llave primaria | `id` de tipo `uuid`, versión 7, con `DEFAULT uuidv7()` |
| Tienda | Toda tabla que pertenece a una tienda lleva `business_id` y una política de seguridad a nivel de fila (R55) |
| Llaves únicas y foráneas | Incluyen `business_id`, para que el aislamiento no dependa del código (R56) |
| Fechas | `timestamptz`; las dos conexiones trabajan en la zona `America/Costa_Rica` |
| Dinero | Colones enteros en `integer`; nunca decimales ni punto flotante |
| Rangos del negocio | Restricción `CHECK` con nombre `<tabla>_<columna>_check` |
| Intervalos que no se traslapan | Restricción `EXCLUDE` con nombre `<tabla>_no_overlap_excl` |
| Normalización | Tercera forma normal; cada excepción se justifica en `docs/data-model.md` |

La aplicación genera el identificador antes de guardar, con el objeto de valor `Src\Shared\Domain\ValueObjects\Identifier`, así el agregado tiene identidad desde que nace. El `DEFAULT uuidv7()` es el respaldo para las filas que no pasan por la aplicación.

Las excepciones a las llaves con `business_id`, como la llave única global `members(user_id)` o las foráneas hacia `users`, se justifican en `docs/data-model.md`.

## Objetos programables

El constructor de esquemas de Laravel no cubre funciones, procedimientos, triggers, políticas ni vistas. Se crean dentro de una migración con `DB::statement()` o `DB::unprepared()`, y su `down()` los elimina.

| Objeto | Prefijo | Regla |
|---|---|---|
| Función | `fn_` | `search_path` fijo. `SECURITY INVOKER` por defecto; `SECURITY DEFINER` solo cuando debe leer filas que las políticas esconden, y entonces es propiedad de `archivum_owner` y solo `archivum_app` tiene `EXECUTE` |
| Procedimiento | `sp_` | Solo para tareas por lotes; se invoca con `CALL` fuera de una transacción |
| Trigger | `trg_` | `BEFORE` por fila para validar o completar; `AFTER` por fila para propagar a otras tablas |
| Vista | `v_` | `security_invoker = true`, para que apliquen las políticas de quien consulta |
| Vista materializada | `mv_` | Índice único para refrescar con `CONCURRENTLY`; se consulta a través de una vista |

## Extensiones

| Extensión | Migración | Para qué |
|---|---|---|
| `btree_gist` | La del esquema de horarios (#52); la de citas (#59) la reutiliza | Comparar con `=` columnas escalares (`business_id`, `member_id`, `weekday`) dentro del índice GiST de una restricción de exclusión |

Se crea con `CREATE EXTENSION IF NOT EXISTS` y ninguna reversión la borra, porque otra migración puede depender de ella. Es una extensión de confianza: el rol dueño de la base la instala sin ser superusuario. Viene en el paquete `contrib` de PostgreSQL.

## Seguridad a nivel de fila

Es la segunda barrera del aislamiento entre tiendas. La primera está en el código: el puerto `BusinessContext` dice a qué tienda pertenece la operación y cada repositorio filtra por `business_id`. Si una consulta olvida ese filtro, PostgreSQL igual devuelve solo las filas de la tienda en curso.

### Qué protege

Toda tabla con `business_id` tiene la seguridad a nivel de fila habilitada y forzada, con la política `<tabla>_business_isolation`. Hoy son `members` y `customers`; la migración `enable_row_level_security_on_business_tables` las protege:

```sql
ALTER TABLE customers ENABLE ROW LEVEL SECURITY;
ALTER TABLE customers FORCE ROW LEVEL SECURITY;
CREATE POLICY customers_business_isolation ON customers
    USING (business_id = (SELECT fn_current_business()))
    WITH CHECK (business_id = (SELECT fn_current_business()));
```

| Parte | Qué hace |
|---|---|
| `ENABLE ROW LEVEL SECURITY` | Aplica las políticas a todo rol que no sea el dueño de la tabla; sin una política que lo permita, no se ve ninguna fila |
| `FORCE ROW LEVEL SECURITY` | Las aplica también al dueño, `archivum_owner` |
| `USING` | Decide qué filas existentes se pueden leer, actualizar o borrar |
| `WITH CHECK` | Valida cada fila insertada o modificada: no se puede crear ni mover una fila a otra tienda |
| `fn_current_business()` | La función común de toda política: devuelve la tienda de la transacción |
| `(SELECT ...)` | PostgreSQL evalúa la función una vez por consulta y no una vez por fila |

`fn_current_business()` devuelve `NULLIF(current_setting('app.business_id', true), '')::uuid`. Con `true`, `current_setting` devuelve `NULL` en lugar de fallar si la variable no existe. Después de la primera transacción de una conexión la variable queda como texto vacío y no como `NULL`; sin el `NULLIF`, la conversión a `uuid` fallaría en lugar de no mostrar filas. Como `business_id = NULL` nunca es verdadero, sin tienda no se ve ninguna fila.

Una escritura que no cumple `WITH CHECK` falla con el SQLSTATE `42501` y el mensaje `new row violates row-level security policy`. Un `UPDATE` o un `DELETE` sin tienda no falla: no encuentra filas.

### Cómo se fija la tienda

La tienda vive en la variable `app.business_id` y se fija con `set_config('app.business_id', ?, true)`. El tercer argumento la limita a la transacción en curso: al confirmar o deshacer, desaparece, y una conexión reutilizada nunca hereda la tienda de la petición anterior.

`BusinessTransaction`, en `Src\Shared\Infrastructure\Tenancy`, abre una transacción y fija la tienda antes de cualquier consulta. El middleware `business` la usa con el adaptador de `BusinessContext` de cada grupo de rutas:

| Rutas | Middleware | Tienda de la transacción |
|---|---|---|
| Panel, `/api/v1/panel` | `auth:sanctum` y `business:staff` | La de la persona del personal autenticada |
| Públicas, `/api/v1/tiendas/{tienda}` | `business:route` | La del slug de la URL |
| Un recurso del cliente, `/api/v1/cuenta/...` | `auth:sanctum` y `business:account` | La del recurso, si es de la cuenta autenticada |
| Un listado del cliente, `/api/v1/cuenta/...` | `auth:sanctum` | Cada tienda donde la cuenta es cliente, una transacción por tienda con `eachBusiness()` |

Como el dueño de las tablas también está sujeto a las políticas, los sembradores y el comando `archivum:create-business` (#27) fijan la tienda antes de escribir el personal y los clientes. En las pruebas, `TwoBusinesses::scopeQueriesTo()` fija la tienda antes de preparar o leer filas.

### Funciones que leen sin tienda

Hay preguntas que se responden antes de saber la tienda: al ingresar, de qué tienda es una persona del personal; en las rutas del cliente, en qué tiendas es cliente una cuenta y de qué tienda es uno de sus recursos. Las responden tres funciones:

| Función | Devuelve | La usa |
|---|---|---|
| `fn_member_business(user_id)` | La tienda y el rol de la persona del personal | `EloquentMemberRepository::findByUser`, al ingresar y en el panel |
| `fn_customer_businesses(user_id)` | Las tiendas donde la cuenta tiene fila en `customers` | Los listados de `/api/v1/cuenta` |
| `fn_customer_resource_business(tipo, id, user_id)` | La tienda de un recurso propio, o `NULL` si es de otro cliente o no existe | Las rutas de un recurso de `/api/v1/cuenta` |

Las tres son `SECURITY DEFINER` con `search_path` vacío y nombres calificados, propiedad de `archivum_owner` y con `EXECUTE` solo para `archivum_app`. Devuelven identificadores y el rol, nunca filas completas. Como `FORCE` también somete a `archivum_owner`, las tablas que consultan agregan la política de solo lectura `<tabla>_definer_read`, `FOR SELECT TO archivum_owner USING (true)`; `archivum_app` sigue sin ver filas sin tienda.

`fn_customer_resource_business` acepta una lista cerrada de tipos y rechaza cualquier otro con el SQLSTATE `22023`. Hoy solo conoce `customer`, la ficha del cliente en una tienda; cada esquema con recursos del cliente agrega el suyo a la función, al enum `CustomerResourceType` y a `CustomerBusinessContext::RESOURCES`: `appointment` con #59 y `order` con #78. El mecanismo está registrado en el [ADR 0011](adr/0011-customer-routes-resolve-the-business-through-definer-functions.md).

`php artisan migrate:fresh` borra las tablas pero no las funciones. Por eso cada migración elimina sus funciones antes de crearlas.

### Excepciones

| Tabla | Por qué no tiene política |
|---|---|
| `users` | No tiene `business_id`: una cuenta puede ser cliente de varias tiendas, y el ingreso la busca por su correo antes de saber la tienda |
| `businesses` | No tiene `business_id`: cada fila es una tienda, y las rutas públicas la buscan por el slug de la URL |
| `personal_access_tokens` | No tiene `business_id`: el token pertenece a la cuenta |

### Llaves únicas y foráneas

PostgreSQL revisa las llaves únicas, las restricciones de exclusión y las llaves foráneas sin aplicar las políticas. Por eso todas incluyen `business_id`. Una llave única sin la tienda rechazaría un valor que ya usa otra tienda y revelaría que existe; una llave foránea sin la tienda permitiría que una fila apunte a la de otra tienda.

`BusinessKeysTest` lee `pg_constraint` y `pg_index` y falla si una de esas llaves no incluye `business_id`, salvo una lista de excepciones que `docs/data-model.md` (#35) justifica: la llave única global `members(user_id)` (una tienda por persona del personal) y las foráneas de `members` y `customers` hacia `users`.

### Cómo agregar una tabla de una tienda

1. La tabla lleva `business_id`, y sus llaves únicas y foráneas lo incluyen.
2. Una migración habilita y fuerza la seguridad a nivel de fila y crea la política `<tabla>_business_isolation` con `business_id = (SELECT fn_current_business())` en `USING` y en `WITH CHECK`; su `down()` deshace los tres pasos.
3. Las rutas que la usan pasan por el middleware `business` y sus pruebas fijan la tienda antes de preparar filas.
4. La tabla se suma a `protectedTables()` y su modelo a `protectedModels()` en `RowLevelSecurityTest`, que revisa el catálogo de PostgreSQL y falla si una tabla con `business_id` no tiene la seguridad habilitada, forzada y con su política, o si falta en esas listas.

## Reparto de responsabilidades

El dominio, en PHP, decide las reglas del negocio. PostgreSQL garantiza la integridad de los datos, el aislamiento entre tiendas, la concurrencia (citas sin traslape con `EXCLUDE`; ml de botellas y saldo de paquetes con `UPDATE` condicional y `CHECK`) y las consultas por conjuntos. Ningún caso de uso se duplica en PL/pgSQL.

Todo objeto de la base se usa desde `Infrastructure/Persistence`, detrás de un puerto. El dominio y los casos de uso no saben que existen.
