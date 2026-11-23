# ADR 0013 — Separar Base de datos, Backend y Web

Estado: aceptada para la separación de #338.

## Contexto

El repositorio guía mantiene las migraciones y `roles.sql` junto a la API. ArchivumParfums también desarrolló la SPA en el mismo repositorio. Los tres componentes tienen entregables, pruebas y ritmos de cambio propios. La separación debe conservar las versiones exactas del esquema y del contrato que consume cada componente.

## Decisión

Web y Base de datos se mantienen en repositorios propios. Backend conserva la API y publica el contrato `archivum-api-hexagonal/openapi/v1.json`. El submódulo `archivum-api-hexagonal/database/migrations` fija el commit del esquema de PostgreSQL utilizado por Backend. Web versiona una copia del contrato y anota su commit de origen en `openapi/source.txt`.

Los cambios que abarcan los tres repositorios se integran en el orden Base de datos → Backend → Web. Primero se integra la migración. Luego el PR de Backend actualiza el submódulo, prueba contra PostgreSQL 18 y publica el contrato compatible. Web toma ese contrato y regenera sus tipos después de que se publique el cambio de Backend.

## Alternativa descartada

- **Conservar un repositorio único.** El esquema y el cliente podrían cambiar en el mismo PR que Backend. La separación permite que cada componente tenga sus propios permisos, pruebas e historial, con las revisiones del esquema y del contrato fijadas por commit.

## Consecuencias

- El clon de Backend inicializa el submódulo con `git submodule update --init --recursive`. El instalador indica ese comando si no encuentra las migraciones.
- El check `Web` de Backend usa el cliente en `main` de Web. Regenera sus tipos con el contrato del PR y ejecuta sus pruebas y build.
- Los cambios de contrato se implementan primero de forma compatible con el cliente publicado. El procedimiento para coordinar cambios de contrato se describe en el [plan de trabajo](../plan.md#repositorios-e-integración).
