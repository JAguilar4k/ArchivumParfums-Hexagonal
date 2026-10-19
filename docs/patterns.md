# Patrones de diseño

Ningún patrón se usa por tenerlo. Cada uno resuelve un problema concreto del proyecto; si el problema no existe, el patrón tampoco.

En las rutas, `<Contexto>/<Módulo>` es un módulo de `src/`, por ejemplo `Scheduling/Appointments`.

## Patrones de la arquitectura

Todo módulo los usa desde su primer issue.

| Patrón | Capa | Dónde vive en `src/` | Problema que resuelve |
|---|---|---|---|
| Puertos y adaptadores | Domain e Infrastructure | El puerto en `<Contexto>/<Módulo>/Domain/Contracts`; el adaptador en `<Contexto>/<Módulo>/Infrastructure` | El negocio no depende del framework, de la base de datos ni de un proveedor |
| Repository | Domain e Infrastructure | El contrato en `Domain/Contracts`; el adaptador en `Infrastructure/Persistence/Repositories` | Guardar y recuperar agregados sin exponer la persistencia |
| Data Mapper | Infrastructure | Dentro de cada repositorio Eloquent, en `Infrastructure/Persistence/Repositories` | Separar la entidad del dominio del modelo Eloquent, que vive en `Infrastructure/Persistence/Models` |
| Caso de uso | Application | `Application/UseCases` | Una acción del negocio por clase, con un único método público, que se prueba sin HTTP |
| Value Object | Domain | `Domain/ValueObjects`; los compartidos, como `Identifier` y `BusinessId`, en `Shared/Domain/ValueObjects` | La regla de un dato vive en un solo lugar; es imposible crear uno inválido |
| Capa anticorrupción | Infrastructure | Los adaptadores de las integraciones: `Payments/Payments/Infrastructure`, `Recommendations/Recommender/Infrastructure/Ai`, `Scheduling/Calendar/Infrastructure` y `Notifications/CustomerNotices/Infrastructure/WhatsApp` | Que el modelo de una pasarela, del proveedor de IA, de Google o de Meta no contamine el dominio ([ADR 0008](adr/0008-external-integrations-behind-ports.md)) |
| Aggregate | Domain | `Domain/Entities` | Un solo punto de entrada para cambiar un conjunto de datos que se valida junto |
| Factory Method | Domain | `create()` y `reconstitute()` en cada agregado, en `Domain/Entities` | Distinguir el nacimiento de un agregado de su lectura desde la base |
| DTO / Command | Application | `Application/DTOs` | Cruzar capas con datos simples, sin `Request` ni modelos |
| Inyección de dependencias | Infrastructure | El service provider de cada módulo, en `Infrastructure` | Elegir el adaptador de cada puerto en un solo lugar |
| Cadena de responsabilidad | Infrastructure | Los middleware HTTP, en `Infrastructure/Http` | Tratar cada petición antes y después del controlador |

## Patrones previstos

Llegan con el módulo que los necesita.

| Patrón | Dónde vive en `src/` | Para qué |
|---|---|---|
| Modelo de lectura (CQRS simple) | `Catalog/Showcase`: el puerto `ShowcaseReader` en `Application/Contracts` y la consulta en `Infrastructure/Persistence/Queries` (#48) | Consultas a la medida de la pantalla sin cargar agregados |
| Decorator | La caché de la vitrina decora `ShowcaseReader` (#188) | Agregar caché sin modificar el lector ni el caso de uso |
| Policy | Las policies de Laravel en la `Infrastructure` de cada módulo, registradas en su provider (#104) | Decidir por rol y por propiedad del recurso; lo ajeno responde 404 |
| Estado como enum con transiciones | `Domain/ValueObjects` de `Scheduling/Appointments` y `Sales/Orders` | Impedir un cambio de estado inválido |
| Observer (eventos de dominio) | El evento en `Domain`; sus oyentes en `Infrastructure` de cada módulo (#155, #163, #182) | Avisar a otros contextos sin acoplarlos |
| Strategy | Los canales de `CustomerNotifier` (#161) y la pasarela de cada tienda con `PaymentGateways` (#88) | Cambiar de canal o de pasarela sin tocar el caso de uso |
| Clave de idempotencia | El registro único de cada evento de webhook, en `Payments/Payments/Infrastructure` (#95) | Que un webhook repetido no confirme dos veces el mismo pago |
| Restricción en la base como guardia de concurrencia | Los repositorios de `Scheduling/Appointments`, `Decants/Bottles` y `Decants/Packs`, en `Infrastructure/Persistence` | Que dos peticiones simultáneas no rompan una regla ([ADR 0010](adr/0010-concurrency-guaranteed-in-postgresql.md)) |

## Descartados

| Patrón | Motivo |
|---|---|
| Bus de comandos y manejadores | El controlador llama al caso de uso directamente |
| Repositorio base genérico | Obliga a implementar operaciones que el módulo no usa |
| Una clase por estado (State) | Un enum con sus transiciones válidas cubre el caso |
| Specification y Abstract Factory | No hay un problema en el proyecto que los pida |
| Event Sourcing | Su complejidad supera lo que el negocio necesita; el historial de las botellas es una tabla de movimientos que nunca se edita |
| Capa `Presentation` separada | En una API el controlador es un adaptador más de la infraestructura |
