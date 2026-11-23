# Base de datos

El repositorio de Base de datos mantiene el esquema PostgreSQL, los roles y las migraciones. [Convenciones y procedimientos](https://github.com/JAguilar4k/ArchivumParfums-Database/blob/main/docs/database.md).

## Preparar la base de datos local

En Windows, [`instalar.bat`](../instalar.bat) prepara la base junto con la API y el cliente web, y se puede repetir sin duplicar datos:

1. Antes de instalar, revisa que `psql` y `pg_isready` sean de PostgreSQL 18 y que el servidor responda en `DB_HOST` y `DB_PORT` del único `.env` (`archivum-api-hexagonal/.env`). Si algo falta, se detiene sin tocar nada.
2. Descarga el submódulo con las migraciones y `roles.sql`, si todavía no está.
3. Ejecuta `php artisan archivum:install`, que se detiene si el servidor es anterior a PostgreSQL 18 antes de crear roles o migrar. Luego crea o reutiliza los roles `archivum_owner` y `archivum_app` y las bases de desarrollo y de pruebas, aplica solo las migraciones pendientes con `archivum_owner` y siembra solo si la tabla `users` está vacía.
4. Comprueba que la aplicación se conecta con `archivum_app`.

En otros sistemas, o a mano, se siguen los mismos pasos con `git submodule update --init --recursive` y `composer install` desde `archivum-api-hexagonal/`, como explica la [puesta en marcha](../README.md#puesta-en-marcha).

## Actualizar la revisión del esquema

Una vez integrado el PR del esquema, actualiza el submódulo desde la raíz del backend. Sustituye `SHA_PUBLICADO` por el commit revisado de Base de datos.

```bash
git submodule update --init --recursive
git -C archivum-api-hexagonal/database/migrations fetch origin main
git -C archivum-api-hexagonal/database/migrations checkout --detach SHA_PUBLICADO
git add -- archivum-api-hexagonal/database/migrations
```

El PR de backend fija esa revisión. El check `Quality` aprovisiona PostgreSQL 18, aplica las migraciones con `archivum_owner` y ejecuta la suite de Pest.

## Seguridad a nivel de fila

[Políticas de PostgreSQL](https://github.com/JAguilar4k/ArchivumParfums-Database/blob/main/docs/database.md#seguridad-a-nivel-de-fila).
