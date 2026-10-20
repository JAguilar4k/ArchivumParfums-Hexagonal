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

En desarrollo no hace falta ejecutarlo a mano: `composer install` llama a `php artisan archivum:install`, que hace lo mismo con el superusuario y deja contraseñas generadas en `.env` (#215). El script queda para los servidores donde no corre esa instalación de desarrollo.

Las contraseñas nunca se versionan: quedan en el `.env` de cada integrante, que tampoco se versiona.

La primera migración, `0000_00_00_000000_grant_privileges_to_the_application_role`, otorga al rol de la aplicación, con `ALTER DEFAULT PRIVILEGES`, lectura y escritura sobre toda tabla y secuencia que el dueño cree después. Ninguna tabla nueva necesita un paso manual. `DatabaseRolesTest` comprueba que `archivum_app` no tiene atributos elevados, no es dueño de ningún objeto, puede leer y escribir en toda tabla y no puede crear, alterar ni borrar tablas.

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

La forma de las políticas se documenta en esta sección con #32.

## Reparto de responsabilidades

El dominio, en PHP, decide las reglas del negocio. PostgreSQL garantiza la integridad de los datos, el aislamiento entre tiendas, la concurrencia (citas sin traslape con `EXCLUDE`; ml de botellas y saldo de paquetes con `UPDATE` condicional y `CHECK`) y las consultas por conjuntos. Ningún caso de uso se duplica en PL/pgSQL.

Todo objeto de la base se usa desde `Infrastructure/Persistence`, detrás de un puerto. El dominio y los casos de uso no saben que existen.
