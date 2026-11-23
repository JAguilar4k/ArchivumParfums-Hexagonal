# Base de datos

El repositorio de Base de datos mantiene el esquema PostgreSQL, los roles y las migraciones. [Convenciones y procedimientos](https://github.com/JAguilar4k/ArchivumParfums-Database/blob/main/docs/database.md).

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
