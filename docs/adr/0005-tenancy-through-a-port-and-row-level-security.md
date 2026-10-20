# ADR 0005: Multi-inquilino por un puerto y por seguridad a nivel de fila

Estado: aceptada e implementada. La primera barrera son los adaptadores `StaffBusinessContext`, `RouteBusinessContext` y `CustomerBusinessContext` del puerto `BusinessContext` con los filtros de los repositorios; la segunda, las políticas `<tabla>_business_isolation` descritas en [database.md](../database.md).

## Contexto

Varias tiendas comparten la misma instalación y la misma base. Un olvido en una consulta no puede exponer los datos de otra tienda (R54 a R56).

## Decisión

Hay dos barreras. La primera está en el código: el puerto `BusinessContext`, en `Src\Shared\Domain\Contracts`, responde a qué tienda pertenece la operación, y cada repositorio filtra por ese `BusinessId`. La segunda está en PostgreSQL: políticas de seguridad a nivel de fila sobre toda tabla con `business_id`. Para que la segunda barrera sirva, la aplicación se conecta con un rol que no es dueño de las tablas ni tiene `BYPASSRLS`.

En el panel, la tienda sale de la sesión del personal: nunca del cuerpo ni de la URL. Las rutas públicas llevan la tienda en la URL solo para leer su vitrina o para que un cliente opere sobre sus propios recursos en ella.

## Alternativas descartadas

- **Tomar la tienda del cuerpo o de la URL de una petición del panel.** Cualquiera podría operar sobre la tienda de otro.
- **Una base o un esquema por tienda.** Resuelve un problema de escala que el proyecto no tiene y complica las migraciones.
- **Solo un filtro global de Eloquent.** Es una sola barrera y se desactiva con una llamada.

## Consecuencias

- Cambiar de dónde sale la tienda afecta a un solo adaptador.
- Toda llave única y foránea de una tabla de una tienda incluye `business_id`.
- Las migraciones corren con un rol (`archivum_owner`) y la aplicación con otro (`archivum_app`).
- Un recurso de otra tienda responde 404, igual que uno que no existe.
- `users` no lleva `business_id`: una cuenta puede ser cliente de varias tiendas, y la pertenencia vive en `members` y `customers`.
