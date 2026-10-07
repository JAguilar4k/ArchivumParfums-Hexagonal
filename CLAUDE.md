# ArchivumParfums · Citas y pedidos para perfumerías

Proyecto integrador del curso ISW-621 Programación en Ambiente Web II (UTN, Sede San Carlos, 2026). Plataforma multi-inquilino donde una perfumería de nicho agenda **citas de prueba de olores**, recibe **pedidos bajo pedido**, vende **decants** por mililitro y cobra en línea. Tienda piloto: **ArchivumParfums**.

Antes de cualquier tarea, leé:

- `docs/especificacion.md`: roles, glosario, reglas de negocio (R1–R56), estados, modelo de datos y API.
- `docs/plan.md`: módulos (un milestone por módulo), etiquetas, forma de trabajo y cortes.
- `docs/enunciado.pdf`: enunciado oficial y rúbricas. Es local y no se versiona.

## Repositorio guía: solo lectura

La estructura, la arquitectura, la documentación y la forma de trabajo copian el proyecto guía del curso: https://github.com/BryanChavesSalas/SodaYa-Hexagonal

**Está prohibido modificarlo de cualquier forma**: ni push, ni ramas, ni issues, ni pull requests, ni comentarios, ni etiquetas, ni milestones, ni reacciones. Solo se lee:

- Se clona con `--depth 1` en una carpeta temporal fuera de este repo y se le quita el remoto (`git remote remove origin`).
- Con `gh` solo se hacen lecturas sobre él. Ningún comando que escriba en GitHub lleva ese repositorio como destino.
- Si una tarea parece requerir escribir en él, se detiene y se pregunta.

## Qué manda en caso de conflicto

1. El enunciado manda en lo que se califica: Pest, Sanctum, cliente en Vue con TypeScript, extras y fechas.
2. El repo guía manda en estructura, arquitectura, convenciones, documentación y forma de trabajo.
3. `docs/especificacion.md` manda en las reglas del negocio.

Si una regla del negocio no está en la especificación, preguntá antes de inventarla.

## Reglas que no se negocian

1. **Se califica el núcleo** (las 11 capacidades). Ningún extra se toca si el núcleo del corte vigente no está completo y probado.
2. **Aislamiento por tienda con dos barreras**, como el ADR 0005 del repo guía: el puerto `BusinessContext` dice a qué tienda pertenece la operación y cada repositorio filtra por ella; PostgreSQL aplica seguridad a nivel de fila en toda tabla con `business_id`, y la aplicación se conecta con un rol que no es dueño de las tablas. En el panel, la tienda nunca sale del cuerpo ni de la URL.
3. **Permisos por rol y por propiedad del recurso.** Que un rol acceda a lo que no le toca es el error más grave del proyecto.
4. **Pagos:** el estado cambia solo por webhook verificado e idempotente, o por el registro manual de un dueño para pagos en la entrega. Nunca por lo que diga el navegador.
5. **Concurrencia garantizada en PostgreSQL**, no solo en PHP: citas sin traslape con restricción de exclusión; ml de botellas y saldo de paquetes con `UPDATE` condicional y `CHECK`.
6. API bajo `/api/v1`. Errores RFC 9457 con los tipos del repo guía (`datos-invalidos`, `no-encontrado`, `conflicto`, `error-interno`…), miembro `errores` en la validación e `instance` con el `X-Request-Id`.
7. Sin dinero real (solo sandbox), sin datos personales reales (seeders ficticios), `.env` nunca en el repo y `.env.example` siempre al día.
8. Todo cambio sale de un issue y entra por pull request revisado por otra persona del equipo.
9. El README tiene una sección **Uso de IA**: qué partes generó la IA y qué verificó el equipo.
10. Cualquier integrante debe poder explicar y modificar cualquier parte en vivo: código simple y explícito antes que abstracciones ingeniosas.

## Stack

| Capa | Tecnología |
|---|---|
| API | Laravel 13 sobre PHP 8.4, PostgreSQL 18 (`uuidv7()`, seguridad a nivel de fila, `btree_gist`), Sanctum, Scramble con el contrato versionado en `openapi/v1.json`, Reverb, colas y scheduler, Pulse, Laravel Boost |
| Calidad | Pest (el repo guía usa PHPUnit; el enunciado exige Pest), Pint, Larastan en nivel 8, `composer audit` |
| Web | `archivum-web`: Vue 3 + TypeScript + Vite, Vue Router, Pinia, tipos generados desde `openapi/v1.json`, Laravel Echo |
| IA | `laravel/ai`, el SDK oficial, con salida estructurada. Proveedor por definir |
| CI | GitHub Actions con la forma del repo guía: PostgreSQL 18 de servicio, roles, lint, análisis, pruebas y auditoría; más un job para `archivum-web` |
| Despliegue | Por definir: PHP 8.4, PostgreSQL 18, worker de colas, scheduler, websockets y HTTPS |

## Estructura

```
archivum-api-hexagonal/   API REST en Laravel 13 (como sodaya-api-hexagonal)
archivum-web/             SPA en Vue 3 + TypeScript
docs/                     architecture, database, data-model, ubiquitous-language,
                          patterns, adr/, especificacion y plan
.github/                  CI, plantillas de issue y de PR, Dependabot
```

`archivum-api-hexagonal/` tendrá su propio `CLAUDE.md` generado por Laravel Boost, como en el repo guía. Este archivo es el del proyecto completo.

## Arquitectura (igual que el repo guía)

- El código del producto vive en `archivum-api-hexagonal/src/`, namespace `Src\`, por contexto, módulo y capa: `Domain`, `Application` e `Infrastructure`. Las dependencias apuntan solo hacia adentro y una prueba de arquitectura lo verifica.
- Un módulo corresponde a un agregado. Un caso de uso por clase, con un único método público; DTO con datos primitivos; puertos en `Domain/Contracts`; adaptadores en `Infrastructure`, enlazados en el service provider del módulo.
- Nombres sin sufijo de tipo (`AppointmentRepository`, `Price`, `BookAppointment`). Las excepciones terminan en `Exception` y los modelos Eloquent en `Model`. Los agregados tienen constructor privado, `create()` y `reconstitute()`.
- Eloquent solo en la infraestructura. El dominio lanza excepciones con claves de traducción y el mensaje sale de `lang/es`.
- Pagos, IA, Google Calendar y WhatsApp quedan detrás de puertos: cambiar de proveedor toca un solo adaptador.

## Idioma

| Dónde | Idioma | Ejemplo |
|---|---|---|
| Clases, métodos, variables y comentarios | Inglés | `DecantBottle`, `remainingMl` |
| Tablas y columnas | Inglés, `snake_case` | `decant_bottles.remaining_ml` |
| Rutas de la API | Español, en plural | `/api/v1/tiendas/{tienda}/perfumes` |
| Atributos JSON | Español, `snake_case` | `ml_restantes` |
| Mensajes al usuario | Español, desde `lang/es` | "Ese horario ya no está disponible." |

El glosario completo está en la sección 4 de la especificación y se pasa a `docs/ubiquitous-language.md`.

## Base de datos

- PostgreSQL 18 en desarrollo, pruebas y producción.
- Dos roles, como en el repo guía: `archivum_owner` ejecuta las migraciones (`--database=pgsql_admin`) y `archivum_app` atiende las peticiones, sin `BYPASSRLS`. `database/roles.sql` los crea.
- Llave primaria `uuid` versión 7. Toda tabla de una tienda lleva `business_id`, incluido en sus llaves únicas y foráneas.
- `timestamptz`, colones enteros en `integer`, `CHECK` con nombre `<tabla>_<columna>_check`, tercera forma normal y migraciones reversibles.

## Pruebas

- Pest, con las carpetas del repo guía: `tests/Unit` (dominio y casos de uso sin framework), `tests/Feature` (endpoints y adaptadores contra PostgreSQL con el rol de la aplicación) y `tests/Architecture`.
- Caminos críticos con prueba propia: doble reserva de una cita, los últimos ml de una botella, canje simultáneo de un paquete, webhook repetido y webhook con firma inválida.
- Las pruebas de concurrencia no funcionan dentro de una transacción de prueba: usar dos conexiones o procesos en paralelo.

## Comandos (en `archivum-api-hexagonal/`)

```bash
psql -U postgres -v owner_password='…' -v app_password='…' -f database/roles.sql
composer install && cp .env.example .env && php artisan key:generate
php artisan migrate --database=pgsql_admin
php artisan db:seed
composer lint        # Pint sin modificar
composer format      # Pint corrigiendo
composer analyse     # Larastan nivel 8
composer test        # Pest
composer openapi     # regenera openapi/v1.json
composer audit
```

Si al crear el proyecto algún comando cambia, actualizá esta sección.

## Forma de trabajo (igual que el repo guía)

- Cada requisito es un issue dentro de un milestone (un milestone por módulo) y está en el tablero del proyecto.
- Cada issue se trabaja en su rama, creada desde el issue con *Create a branch*: sale de `main` y lleva el número y el título (`23-reservar-una-cita-de-prueba`).
- Conventional Commits, uno por cambio lógico, con `Refs: #N` en el pie.
- Un pull request por issue, con `Closes #N`, CI en verde y revisión de otra persona. Se fusiona con merge commit.
- Las etiquetas de corte (`avance-1`…) marcan cuándo se entrega cada issue. Las etiquetas de git `v-avance1`, `v-avance2` y `v-final` marcan las entregas.
- Al cerrar un módulo, se actualiza la sección "Estado" del README.

## Documentación de las integraciones

- ONVO Pay: https://docs.onvopay.com (contexto para agentes en `/llms-full.txt`). Llaves de prueba con prefijo `onvo_test_`; el origen de los webhooks se verifica con el encabezado `X-Webhook-Secret`.
- PayPal: https://developer.paypal.com (índice en `/llms.txt`). Su API estándar no acepta colones: con PayPal se cobra en USD.
- Laravel AI SDK: https://laravel.com/docs/13.x/ai-sdk
- Laravel MCP (extra): https://github.com/laravel/mcp

## Fechas

Las entregas vencen a las 13:00 del día indicado. Entrega tardía: hasta 24 h después, con rebaja de 15 puntos sobre 100.

| Corte | Fecha | Etiqueta de git |
|---|---|---|
| Revisión 1 (en clase) | miércoles 14/10/2026 | — |
| Avance 1 | miércoles 21/10/2026 | `v-avance1` |
| Investigación 1 | miércoles 28/10/2026 | — |
| Revisión 2 (en clase) | miércoles 11/11/2026 | — |
| Avance 2 | miércoles 18/11/2026 | `v-avance2` |
| Investigación 2 | miércoles 25/11/2026 | — |
| Entrega final y defensas | lunes 07/12 y miércoles 09/12/2026 | `v-final` |
