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
| [`.github/`](.github/) | Plantillas de issue y de pull request e integración continua ([`ci.yml`](.github/workflows/ci.yml)). Dependabot llega con [M03](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/milestone/3). |
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

Avance 1 está en curso y todavía no hay módulos cerrados. El proyecto base de la API, su configuración (PostgreSQL 18, zona horaria y español), la estructura hexagonal, la API bajo `/api/v1`, Pint, Larastan y la integración continua están en pull requests en revisión. El despliegue en una dirección pública está pendiente ([#21](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/issues/21)). El trabajo está planificado en 29 milestones, uno por módulo, en el [tablero del proyecto](https://github.com/users/JAguilar4k/projects/4).

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

El enunciado permite usar asistentes de IA con una condición: cada integrante debe entender lo que se entrega y poder modificarlo en vivo. Esta tabla declara qué partes se generaron con asistencia y qué verificó el equipo. Quien integre trabajo asistido actualiza su fila en el mismo pull request.

| Parte | Asistente | Qué generó la IA | Qué verificó el equipo |
| --- | --- | --- | --- |
| README, `.gitignore` y plantillas de `.github/` | Claude Code (Claude Opus 5.5) | Redacción adaptada del proyecto guía. | Pendiente |
| Etiquetas, milestones, issues y tablero | Claude Code (Claude Opus 5.5) | Redacción de milestones e issues a partir de la especificación, el plan y el enunciado, y su creación con `gh`. | Pendiente |
| `docs/especificacion.md`, `docs/plan.md` y `CLAUDE.md` | Por declarar | Por declarar | Por declarar |
| Correcciones de API, cuentas, migraciones y perfil de tienda | Codex | Conservación de estados HTTP, transición de cuentas a UUID sin pérdida de datos, validación de contraseñas incompatibles, lectura del lugar de cita opcional y actualización concurrente del perfil, con sus pruebas automatizadas de regresión. | Revisión humana del equipo y ejecución integrada de las pruebas pendientes. |
