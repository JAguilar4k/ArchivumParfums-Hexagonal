# ArchivumParfums-Hexagonal

Plataforma multi-inquilino donde perfumerías de nicho agendan citas de prueba de olores, reciben pedidos bajo pedido, venden decants por mililitro y cobran en línea. Es el proyecto integrador del curso ISW-621 Programación en Ambiente Web II (Universidad Técnica Nacional, sede San Carlos, 2026). La tienda piloto es ArchivumParfums.

La estructura, la arquitectura y la forma de trabajo siguen el proyecto guía del curso, [SodaYa-Hexagonal](https://github.com/BryanChavesSalas/SodaYa-Hexagonal).

[![CI](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/actions/workflows/ci.yml/badge.svg)](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/actions/workflows/ci.yml)

## Contenido del repositorio

| Ruta | Contenido |
| --- | --- |
| [`docs/especificacion.md`](docs/especificacion.md) | Roles, glosario, reglas de negocio (R1–R56), estados, modelo de datos y API. |
| [`docs/plan.md`](docs/plan.md) | Módulos, etiquetas, forma de trabajo y cortes. |
| [`CLAUDE.md`](CLAUDE.md) | Instrucciones del proyecto para los asistentes de IA. |
| [`.github/`](.github/) | Plantillas de issue y de pull request, integración continua ([`ci.yml`](.github/workflows/ci.yml)) y Dependabot ([`dependabot.yml`](.github/dependabot.yml)). |
| [`archivum-api-hexagonal/`](archivum-api-hexagonal/) | API REST en Laravel 13 sobre PostgreSQL 18. |
| `archivum-web/` | Cliente web en Vue 3 con TypeScript. Llega con [M16](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/milestone/16). |
| [`docs/architecture.md`](docs/architecture.md) | Estructura, capas, contextos, regla de dependencias y recorrido de una petición. |
| [`docs/database.md`](docs/database.md) | Roles, migraciones y convenciones de la base de datos. |
| `docs/data-model.md` | Modelo de datos en tercera forma normal. Llega con [#35](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/issues/35). |
| [`docs/ubiquitous-language.md`](docs/ubiquitous-language.md) | Glosario del negocio, estados y convenciones de nombres. |
| [`docs/patterns.md`](docs/patterns.md) | Patrones de diseño usados, previstos y descartados. |
| [`docs/adr/`](docs/adr/) | Registro de las decisiones de arquitectura. |

## Arquitectura

`archivum-api-hexagonal` combina arquitectura hexagonal con diseño guiado por el dominio. El código del producto vive en `archivum-api-hexagonal/src/`, bajo el namespace `Src\`, organizado por contexto y módulo y, dentro de cada módulo, en tres capas:

```
Infrastructure  ──▶  Application  ──▶  Domain
```

El dominio no depende de Laravel y una prueba de arquitectura lo verifica en cada cambio. Los pagos, la IA, Google Calendar y WhatsApp quedan detrás de puertos: cambiar de proveedor toca un solo adaptador.

Cada tienda ve solo sus datos gracias a dos barreras: el puerto `BusinessContext` indica a qué tienda pertenece la operación y cada repositorio filtra por ella, y PostgreSQL 18 aplica seguridad a nivel de fila en toda tabla con `business_id`. La aplicación se conecta con `archivum_app`, un rol que no es dueño de las tablas; las migraciones corren con `archivum_owner`.

`archivum-web` es una SPA que consume solo la API, con tipos generados desde el contrato `openapi/v1.json`.

## Estado

Avance 1 está en curso. M01 (proyecto base) y M02 (arquitectura hexagonal y API versionada) tienen todos sus issues integrados: PostgreSQL 18 con roles separados, zona horaria y español, estructura hexagonal, documentación de arquitectura y acceso inicial a `/api/v1`. M03 (calidad e integración continua) y M05 (multi-inquilino y acceso del personal) todavía tienen pull requests en revisión. El despliegue en una dirección pública está pendiente ([#21](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/issues/21)). El trabajo está planificado en 29 milestones, uno por módulo, en el [tablero del proyecto](https://github.com/users/JAguilar4k/projects/4).

| Corte | Fecha | Qué se entrega | Estado |
| --- | --- | --- | --- |
| Avance 1 | 21/10/2026 | Issues `avance-1`: proyecto base, arquitectura, calidad, despliegue, multi-inquilino, catálogo, vitrina, horarios y citas (M01 a M09). | En curso |
| Investigación 1 | 28/10/2026 | Issues `investigacion-1`: botellas, pedidos y cobro con ONVO y PayPal, más el informe de pasarelas (M10 a M12). | Pendiente |
| Avance 2 | 18/11/2026 | Issues `avance-2`: pagos por webhook, cuentas y roles, seguridad, IA, cliente web y agenda del personal (M12 a M17). | Pendiente |
| Investigación 2 | 25/11/2026 | Issues `investigacion-2`: auditoría cruzada (M14). | Pendiente |
| Entrega final | 07/12/2026 | Issues `entrega-final`: paquetes, lista de espera, colas, avisos, agenda en vivo, proveedores y observabilidad, y los hallazgos de la auditoría corregidos. | Pendiente |

Al cerrar un módulo se actualiza esta sección con lo que quedó listo.

## Puesta en marcha

Los requisitos, los pasos de instalación, los comandos y los endpoints están en [archivum-api-hexagonal/README.md](archivum-api-hexagonal/README.md).

## Forma de trabajo

- **Planificación:** cada requisito es un issue dentro de un milestone, uno por módulo, con seguimiento en el [tablero del proyecto](https://github.com/users/JAguilar4k/projects/4).
- **Ramas:** cada issue se trabaja en su propia rama, creada desde el issue con *Create a branch*. Sale de `main` y lleva el número y el título del issue (`23-reservar-una-cita-de-prueba`).
- **Commits:** [Conventional Commits](https://www.conventionalcommits.org/es/v1.0.0/), uno por cambio lógico, con la referencia al issue en el pie (`Refs: #23`).
- **Pull requests:** uno por issue, enlazado con `Closes #N`. Todo cambio entra a `main` por pull request, con la integración continua en verde y la aprobación de otra persona del equipo. Se fusiona con merge commit para conservar los commits atómicos.
- **Etiquetas de los issues:** la del corte en que se entrega (`avance-1`, `investigacion-1`, `avance-2`, `investigacion-2` o `entrega-final`), más las de área que correspondan (`arquitectura`, `base-de-datos`, `documentation`). Los extras llevan `extra` y los hallazgos de la auditoría cruzada, `auditoria`.
- **Etiquetas de git:** cada entrega se marca sobre `main` con `v-avance1`, `v-avance2` y `v-final`, de modo que se puede comparar lo que agregó cada corte:

  ```bash
  git diff v-avance1 v-avance2 --stat
  ```

## Idioma

El código, la base de datos y los comentarios están en inglés. Las rutas, los atributos JSON y los mensajes al usuario están en español. La convención completa está en [docs/ubiquitous-language.md](docs/ubiquitous-language.md).

## Uso de IA

El enunciado permite usar asistentes de IA con una condición: cada integrante debe entender lo que se entrega y poder modificarlo en vivo. Declararlo no baja la nota; ocultarlo tiene consecuencias. Esta tabla declara qué partes se generaron con asistencia y qué verificó el equipo, y cómo.

| Parte | Asistente | Qué generó la IA | Qué verificó el equipo |
| --- | --- | --- | --- |
| README, `.gitignore` y plantillas de `.github/` (commit inicial) | Claude Code | Redacción adaptada del proyecto guía. | Los revisó el PM, Julián Aguilar Salas. |
| Etiquetas, milestones e issues | Claude Code | Redacción de los 29 milestones y los 196 issues a partir de la especificación, el plan y el enunciado, con el estilo descriptivo del proyecto guía, y su creación con `gh`. El tablero KanbanBoard lo creó Julián. | El PM, Julián Aguilar Salas, revisó la redacción de los issues, los milestones y sus formatos para que sigan estándares y buenas prácticas, y aprobó su creación el 10/10/2026. |
| `docs/especificacion.md`, `docs/plan.md` y `CLAUDE.md` | Claude Code | La redacción de la especificación, el plan y las instrucciones del proyecto (commit inicial), bajo la supervisión del PM, a partir del enunciado, de los requisitos de usuario recogidos en la entrevista con el cliente y de los requerimientos que especificó el PM; y la regla de `CLAUDE.md` de seguir el proyecto guía en cada parte ([#199](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/pull/199)). | El PM, Julián Aguilar Salas, revisó y aprobó todo el contenido, y revisó y fusionó #199. |
| Proyecto base y configuración de la API: Laravel 13 sobre la rama de Nelson (#1), PostgreSQL 18 (#2), roles (#3), zona horaria (#5), español (#6), estructura hexagonal (#7), `/api/v1` (#8), Pint (#11), Larastan (#12), integración continua (#15) e instalación con `composer install` (#215) | Claude Code | El código, la configuración, las pruebas de Pest y los commits de los pull requests #200 a #209 y #216, sobre el commit de Nelson. | Pest, Pint, Larastan y `composer audit` pasan en la integración continua de #206, que contiene los anteriores, y de #208, #209 y #216. sanlealc-code revisó y aprobó #207 (#1). Julián fusionó #200 a #206 sin revisión de otra persona, antes de que la regla de `main` la exigiera (#16). #208, #209 y #216: revisión pendiente. |
| Documentación: `docs/database.md` (#4), `docs/ubiquitous-language.md` (#9), `docs/architecture.md`, `docs/adr/` y `docs/patterns.md` (#10), README de la API (#18) y esta sección (#19) | Claude Code | La redacción, adaptada del proyecto guía y de la especificación, y la prueba de reversibilidad de las migraciones (#4). | Revisión de otra persona del equipo: pendiente. Los pasos de instalación los debe seguir alguien que no los escribió (#18). |
| Identificador de las solicitudes (#45, PR #218) | Claude Code; Codex (declaración) | Claude Code generó el middleware `X-Request-Id`, la configuración CORS y las pruebas HTTP con la forma del repo guía; Codex agregó esta declaración. | Revisión humana pendiente. |
| Acceso inicial de la API (#232) | Codex | Redirección a `/api/v1`, prueba HTTP y documentación de las direcciones locales. Integración con el identificador de solicitud y las respuestas RFC 9457, y actualización del estado de M01 y M02. | 46 pruebas HTTP con 214 aserciones y Pint ejecutados automáticamente; aprobación de sanlealc-code en el PR #233. |
| Errores HTTP de la API (#46) | Codex | Preservación de estados HTTP originales en RFC 9457 y regresiones automáticas. | Pendiente de revisión humana del equipo. |
| Instalación repetible y arranque local en Windows (#215) | Codex | Corrección del comando de desarrollo, conservación de credenciales existentes, carga de datos transaccional, diagnóstico SQL sin credenciales y pruebas de regresión. | La regresión del diagnóstico pasó con 4 pruebas y 20 aserciones sin base de datos; Pint ejecutado automáticamente. Validación integral y revisión humana pendientes. |
| Actualización de dependencias (#17, PR #230) | Claude Code; Codex (declaración) | Claude Code adaptó la configuración de Dependabot del repo guía para Composer y GitHub Actions y agregó su enlace al README; Codex agregó esta declaración. | Revisión humana pendiente. |
| Correcciones de migraciones, autenticación, respuestas HTTP, concurrencia del perfil, fixtures por tienda, instalación y acceso inicial (#13, #26, #28, #33, #46, #215 y #232) | Codex | Inspección del código, correcciones y pruebas de regresión; documentación del arranque y de las verificaciones automáticas en los PR correspondientes. | Revisión humana pendiente. Las ejecuciones automáticas se registran por PR y no equivalen a una aprobación del equipo. |

Cómo se mantiene:

- Cada pull request con trabajo asistido marca la casilla "Si usé IA" de la plantilla y agrega o actualiza su fila en el mismo pull request.
- La verificación se describe con hechos comprobables: qué pruebas se corrieron y quién revisó el pull request. Cuando la revisión ocurre, quien revisó actualiza la columna.
- Abarca el código, las pruebas, la documentación y la redacción de los issues, no solo el código.
- Antes de crear cada etiqueta de entrega (`v-avance1`, `v-avance2`, `v-final`), la tabla cubre todos los pull requests fusionados del corte.
