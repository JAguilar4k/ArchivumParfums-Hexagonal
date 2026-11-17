# Administrar los servicios de la tienda (#53)

El dueño crea, edita, desactiva y reactiva los servicios que su tienda ofrece agendar, con su duración, precio, margen entre citas y máximo de perfumes (R11 y R54 de la [especificación](especificacion.md)). La lectura pública de esos servicios llega con #54 y la disponibilidad con #61; este documento cubre solo el panel.

## Flujo

```mermaid
flowchart LR
    P[POST /api/v1/panel/servicios] --> M[auth:sanctum, business:staff, role:owner]
    M --> F[StoreService]
    F -->|CreateServiceCommand| U[CreateService]
    U --> R[EloquentServiceRepository]
    R --> T[(services)]
    G[PATCH /api/v1/panel/servicios/{servicio}] --> F2[PatchService]
    F2 -->|EditServiceCommand| U2[EditService]
    U2 --> R
```

`business:staff` resuelve la tienda de la sesión con el puerto `BusinessContext` y abre la transacción con esa tienda; el cuerpo de la petición nunca decide la tienda. La política de fila `services_business_isolation` es la segunda barrera (#52).

## Endpoints

| Método | Ruta | Respuesta |
|---|---|---|
| `GET` | `/api/v1/panel/servicios` | 200 con `data` de todos los servicios de la tienda, activos e inactivos, ordenados por nombre |
| `POST` | `/api/v1/panel/servicios` | 201 con el servicio y el encabezado `Location` |
| `GET` | `/api/v1/panel/servicios/{servicio}` | 200 con el servicio; 404 `no-encontrado` si es de otra tienda o no existe |
| `PATCH` | `/api/v1/panel/servicios/{servicio}` | 200 con el servicio editado; `activo` lo desactiva o lo reactiva sin borrarlo |

Campos del contrato: `id`, `nombre`, `duracion_minutos`, `precio` (colones enteros), `margen_minutos`, `maximo_perfumes` y `activo`. En la base las columnas se llaman `name`, `duration_minutes`, `price`, `buffer_minutes`, `max_perfumes` e `is_active`.

## Validación

- `nombre` obligatorio, hasta 120 caracteres, no vacío (la regla del agregado responde 422 `datos-invalidos`).
- `duracion_minutos` mayor que 0; `precio`, `margen_minutos` y `maximo_perfumes` no negativos.
- Todo entero se acota al máximo técnico de la columna `INTEGER`, que es `Colones::MAX` (2 147 483 647): un valor mayor responde 422 y no llega a PostgreSQL.
- Los errores salen como `application/problem+json` con `errores` por campo, `instance` correlacionado con `X-Request-Id` y el detalle en español de `lang/es`; no hay respuestas de validación propias del controlador.

## Permisos y aislamiento

- El dueño administra los servicios; el colaborador y el cliente reciben 403 `prohibido` y una petición sin sesión, 401 `no-autenticado`. El grupo provisional `role:owner` se sustituye por las abilities de #102.
- Un servicio de otra tienda responde 404 y nunca 403, para no revelar que existe.
- `tests/Feature/Tenancy/StaffIsolationTest.php` tiene las filas del listado, del detalle, de la edición y de la creación; `tests/Feature/Scheduling/ServicesTest.php` cubre el contrato, la validación y los roles.

## Sembradores

`DemoServicesSeeder` crea «Prueba de olores» en cada tienda de demostración (45 min, ₡0, 15 min de margen y hasta 5 perfumes) dentro de `BusinessTransaction`, con la tienda fijada y el rol de la aplicación, y no duplica el servicio cuando se repite la siembra. `DatabaseSeeder` lo invoca, así que `archivum:install` deja el servicio inicial de R11.

## Pendiente de definir

Siguen abiertas las decisiones del issue: cómo se cobra un servicio con precio mayor que ₡0, si el nombre es único por tienda, qué pasa con las citas futuras de un servicio que se desactiva y si el panel admite borrar un servicio además de desactivarlo.
