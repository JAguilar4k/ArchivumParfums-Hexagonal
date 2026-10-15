# Resumen del issue #50 · Generar el contrato OpenAPI y publicar la documentación

Rama: `50-generar-el-contrato-openapi-y-publicar-la-documentacion`. Todo el trabajo está **solo en el equipo local**: no hay commits, ni `push`, ni pull request.

## A. Resumen de lo realizado hasta el momento

El issue pide un contrato OpenAPI generado desde el código, versionado en el repositorio y vigilado por una prueba. Se hizo así:

1. Se instaló **Scramble** (`dedoc/scramble` ^0.13.48), que lee las rutas, los Form Requests y los API Resources de `/api/v1` y produce el contrato.
2. Se configuró en `config/scramble.php`: solo se documenta `api/v1`, se usa OpenAPI 3.1 y el contrato se exporta a `openapi/v1.json`.
3. Se creó el script `composer openapi`, que regenera `archivum-api-hexagonal/openapi/v1.json`. El archivo ya está generado y listo para versionar.
4. Se agregó la clase `ApiContract`, que declara el esquema de seguridad de Sanctum solo en las rutas protegidas, fija el servidor relativo `/api/v1` y define quién puede leer la documentación.
5. Se agregó la clase `ProblemResponses`, que documenta en cada operación las respuestas de error RFC 9457 (`application/problem+json`), incluido el miembro `errores` del 422.
6. Se escribió una prueba de Pest que falla si el contrato generado difiere del versionado, y que además comprueba los demás criterios de aceptación.

`/docs/api` publica la documentación interactiva de Scramble y `/docs/api.json` el mismo contrato en JSON.

### Criterios de aceptación

| Criterio | Cómo se cumple |
| --- | --- |
| `/docs/api` publica la documentación con OpenAPI 3.1 de `/api/v1` | `config/scramble.php` + prueba «publishes the interactive documentation» |
| `composer openapi` regenera `openapi/v1.json` | Script en `composer.json`; el archivo está generado |
| Una prueba falla si el contrato difiere del versionado | `OpenApiContractTest`, primera prueba. Se comprobó que falla al alterar el archivo |
| Errores RFC 9457 en cada operación, con `errores` en el 422 | `ProblemResponses` + dos pruebas |
| Sanctum solo en panel y cuenta | `ApiContract` + prueba «requires the Sanctum token only on the routes behind auth:sanctum» |
| El contrato se regenera en cada corte y en todo PR que cambie un endpoint | La prueba corre en la CI en cada PR: si un endpoint cambia sin regenerar el contrato, la CI falla. Para cada corte ver la sección D |

## B. Archivos creados y modificados

### Modificados

| Archivo | Función exacta en esta tarea |
| --- | --- |
| `archivum-api-hexagonal/composer.json` | Agrega `dedoc/scramble` a `require` y el script `openapi` (`php artisan scramble:export`) |
| `archivum-api-hexagonal/composer.lock` | Fija las versiones de Scramble y de sus dependencias (`spatie/laravel-package-tools`) |
| `archivum-api-hexagonal/src/Shared/Infrastructure/SharedServiceProvider.php` | Agrega `boot()`, que llama a `ApiContract::configure()` |

### Creados

| Archivo | Función exacta en esta tarea |
| --- | --- |
| `archivum-api-hexagonal/config/scramble.php` | `api_path` `api/v1`, OpenAPI 3.1, versión del contrato `1.0.0`, ruta de exportación `openapi/v1.json`, título y descripción en español, middleware de la documentación |
| `archivum-api-hexagonal/src/Shared/Infrastructure/OpenApi/ApiContract.php` | Configura Scramble: esquema `sanctum` (bearer) en las rutas con `auth:sanctum`, servidor relativo `/api/v1` y el gate `viewApiDocs` |
| `archivum-api-hexagonal/src/Shared/Infrastructure/OpenApi/ProblemResponses.php` | Reemplaza los errores que Scramble deduce por los problemas reales de la API: schemas `Problema` y `ProblemaDeValidacion`, y respuestas 401, 403, 404, 422 y 500 según la operación |
| `archivum-api-hexagonal/openapi/v1.json` | El contrato versionado, generado con `composer openapi` |
| `archivum-api-hexagonal/tests/Feature/Shared/OpenApiContractTest.php` | Siete pruebas de Pest sobre el contrato y la documentación |
| `docs/ResumenIssue50.md` | Este informe |

No se tocaron migraciones, rutas, modelos, controladores, tests de otras personas, `ci.yml` ni `.env.example`. Tampoco se modificó el repo guía.

## C. Explicación técnica de los cambios y sus justificaciones

**Scramble en `require` y no en `require-dev`.** El issue pide que en producción la documentación exista pero pida credenciales (#180). Si estuviera en `require-dev`, las rutas `/docs/api` no existirían en producción y esa decisión quedaría tomada sin querer.

**El contrato no depende de la máquina.** Para que la prueba no falle por diferencias del entorno:
- La versión del contrato es un literal (`1.0.0`) y no una variable de entorno.
- El servidor es `/api/v1`, relativo, porque con el valor por defecto aparecería `APP_URL`.
- Los títulos de los errores se leen siempre en español, sin importar `APP_LOCALE`.

**Seguridad solo donde corresponde.** `ApiContract` mira el middleware de cada ruta: si lleva `auth:sanctum`, la operación declara `security: [{sanctum: []}]`. Las públicas (`/`, `POST /sesiones`) no declaran nada, y el contrato no tiene seguridad global. Así el cliente web sabe qué llamadas necesitan el token. Hoy son `GET /cuenta` y `DELETE /sesiones/actual`. Las rutas del panel que llegarán con otros issues quedan cubiertas sin tocar este código.

**Errores RFC 9457 en cada operación.** Scramble deduce errores por su cuenta, pero en `application/json` y con el formato de Laravel, que no es el que responde `ProblemDetailsRenderer`. `ProblemResponses` los reemplaza por los problemas reales:
- Todas las operaciones documentan el 500.
- Las protegidas documentan el 401.
- Las rutas con parámetros documentan el 404, que también cubre el recurso de otra tienda (R56).
- Las que reciben cuerpo o consulta documentan el 422 con `ProblemaDeValidacion`, que agrega el miembro `errores` como objeto de listas de mensajes.
- Las rutas bajo `/panel` documentan 401 y 403 (R3).
- Todas usan el mismo `Content-Type` (`ProblemDetailsRenderer::CONTENT_TYPE`), así que no pueden desalinearse.

**La documentación y el acceso.** El gate `viewApiDocs` permite leer la documentación fuera de producción. En producción la niega a todos hasta que el issue #180 decida cómo pedir credenciales. Es la opción más segura para no publicar el contrato por accidente.

**Dónde vive el código.** `src/Shared/Infrastructure/OpenApi`, la misma carpeta (`OpenApi`) que el repo guía usa dentro de `src/Shared`. Es infraestructura pura: no toca el dominio y la regla de dependencias se mantiene.

**La prueba del contrato.** Genera el contrato a un archivo temporal y lo compara con `openapi/v1.json`. Se eleva el límite de memoria a 1 GB dentro de la prueba, porque Scramble infiere los tipos de todo el framework y con los 128 MB de PHP la suite completa se quedaba sin memoria. La generación se comparte entre las pruebas.

**Cómo ayuda a los demás issues.**
- **#124:** los tipos de TypeScript de `archivum-web` se generarán desde `openapi/v1.json`.
- **Endpoints nuevos:** cada issue que agregue un endpoint verá fallar la prueba hasta que ejecute `composer openapi` y versione el cambio. Así el contrato nunca queda desactualizado.
- **#46 y #45:** el formato de errores queda documentado a partir del renderizador que ya existe.
- **Rúbricas:** suma a «pruebas y documentación» del Avance 1.

## D. Flujo de trabajo y estado

### Flujo seguido

1. Se leyó la documentación del proyecto (`CLAUDE.md`, README, especificación, plan, `database.md`, `ubiquitous-language.md`, ADR 0011 y el reparto del Avance 1).
2. Se verificó que las dependencias existen en esta rama: #8 (`apiPrefix: 'api/v1'` y `ApiRoutingTest`) y #15 (`.github/workflows/ci.yml` corre lint, Larastan, `composer test` y auditoría).
3. Se instaló Scramble, se configuró y se generó el contrato.
4. Se escribió la prueba y se comprobó que falla cuando el contrato versionado cambia.
5. Se corrió Pint, Larastan, la suite completa, `composer validate --strict` y `composer audit`.

### Verificación

| Comando | Resultado |
| --- | --- |
| `composer lint` | Pasa |
| `composer analyse` (Larastan nivel 8) | Sin errores |
| `composer validate --strict` | Válido |
| `composer audit` | Sin avisos de seguridad |
| `OpenApiContractTest` | 7 pruebas pasan |
| `composer test` (suite completa) | 310 pasan y **4 fallan** |

Los 4 fallos son de `tests/Feature/Tenancy/RowLevelSecurityTest.php`, un archivo que este issue no modifica. La causa visible es que este PostgreSQL local responde en español («el nuevo registro viola la política de seguridad de registros…») y la prueba espera el mensaje en inglés (`violates row-level security policy`). La CI usa la imagen `postgres:18` en inglés, así que allí no debería pasar. No lo corregí por la regla de no tocar el trabajo de otros; no ejecuté la suite sin mis cambios para compararla, así que esto es una deducción por el mensaje y conviene confirmarlo.

### Qué falta

- **Revisión tuya** de todos los archivos.
- **Commit y pull request**, que no se hicieron por tu instrucción. Cuando toque: Conventional Commits con `Refs: #50`, un solo PR con `Closes #50`, CI en verde y revisión de otra persona.
- **Comprobar la CI.** El job usa PHP 8.4 y la prueba compara contra un archivo generado en PHP 8.5. Si la CI mostrara diferencias, hay que regenerar el archivo con la misma versión de PHP.
- **Regenerar el contrato en cada corte** (`v-avance1`, `v-avance2`, `v-final`): ejecutar `composer openapi` antes de etiquetar y confirmar que `git diff` no muestre cambios sin versionar.
- **Documentar el flujo** (`composer openapi`, `/docs/api`) en `archivum-api-hexagonal/README.md` y en la sección «Estado» del README raíz al cerrar el módulo. No lo hice porque el issue no lo pide.
- **Fila de «Uso de IA»** en el README: queda por declarar que el contrato, las clases y la prueba se generaron con asistencia de IA y qué verificó el equipo.
- **Acceso con credenciales en producción:** pendiente del issue #180.
- **Tipos de TypeScript:** pendiente del issue #124.
