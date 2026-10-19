# Arquitectura de archivum-api-hexagonal

`archivum-api-hexagonal` combina arquitectura hexagonal (puertos y adaptadores) con diseño guiado por el dominio, como el repo guía. El objetivo es que las reglas del negocio se puedan leer, probar y cambiar sin depender de Laravel.

## Estructura

Todo el código del producto vive en `archivum-api-hexagonal/src/`, bajo el namespace `Src\`. La forma es siempre contexto, módulo y capa:

```
src/
├── Shared/                      Núcleo compartido entre contextos
│   ├── Domain/
│   └── Infrastructure/
└── <Contexto>/                  Por ejemplo, Scheduling
    ├── Shared/                  Lo común a los módulos del contexto
    └── <Módulo>/                Un agregado; por ejemplo, Appointments
        ├── Domain/
        │   ├── Entities/
        │   ├── ValueObjects/
        │   ├── Contracts/       Puertos (interfaces)
        │   └── Exceptions/
        ├── Application/
        │   ├── UseCases/
        │   └── DTOs/
        └── Infrastructure/
            ├── Http/            Controllers, Requests, Resources y routes.php
            └── Persistence/     Models, Repositories y Queries
```

Un módulo corresponde a un agregado, no a una tabla. Un módulo de solo lectura, como la vitrina pública, no tiene capa `Domain`: su puerto vive en `Application/Contracts`.

Las carpetas `app/`, `bootstrap/`, `config/`, `database/`, `lang/` y `routes/` conservan su función en Laravel. `app/` solo contiene el arranque del framework. Las migraciones, las fábricas y los sembradores se quedan en `database/`.

## Contextos

Hoy existe `src/Shared`. Cada contexto se crea con el primer issue de uno de sus módulos, con estos nombres. Los módulos marcados como extra solo se construyen si el núcleo del corte está completo:

| Contexto | Módulos | Qué agrupa |
|---|---|---|
| `Shared` | — | Identificadores (`Identifier`, `BusinessId`), excepciones base del dominio y los puertos que usan varios contextos, como `BusinessContext` y `CustomerNotifier` |
| `Businesses` | `Profile`, `Members`, `PublicProfile` | La tienda, sus ajustes y su personal |
| `Accounts` | `Users`, `Sessions` | Las cuentas, el registro de clientes y las sesiones |
| `Catalog` | `Perfumes`, `Showcase` y el extra `Reviews` | Perfumes y presentaciones, la vitrina pública y las reseñas |
| `Scheduling` | `Services`, `BusinessHours`, `StaffSchedules`, `ScheduleBlocks`, `Availability`, `Appointments`, `Waitlist` y el extra `Calendar` | Servicios, horarios, disponibilidad, citas, lista de espera y Google Calendar |
| `Decants` | `Bottles`, `Packs` | Botellas para decants, sus movimientos y los paquetes de decants |
| `Sales` | `Orders` y el extra `Invoicing` | Pedidos, sus líneas y el comprobante electrónico |
| `Payments` | `Payments` y el extra `Commissions` | Pagos, reembolsos, pasarelas y las comisiones del colaborador |
| `Purchasing` | `Suppliers`, `SupplierPrices`, `CatalogImports`, `PurchaseOrders` | Proveedores, costos, importaciones del catálogo y órdenes de compra |
| `Notifications` | `CustomerNotices` | Los avisos al cliente y sus canales |
| `Recommendations` | `Recommender` | El recomendador de perfumes con IA y su respaldo por reglas |
| `Reporting` | `Revenue`, `BestSellers` | Los reportes del panel |

Los nombres del negocio de cada módulo están en [ubiquitous-language.md](ubiquitous-language.md).

## Regla de dependencias

Las dependencias apuntan siempre hacia adentro:

```
Infrastructure  ──▶  Application  ──▶  Domain
```

| Capa | Puede depender de | No puede depender de |
|---|---|---|
| Domain | PHP y `Src\Shared\Domain` | Application, Infrastructure, Laravel |
| Application | Domain | Infrastructure, Laravel |
| Infrastructure | Application, Domain, Laravel | — |

El dominio de un módulo solo puede depender de su propio módulo, del `Shared` de su contexto y de `Src\Shared`.

`tests/Architecture/LayerDependencyTest.php` revisa cada `Domain` y cada `Application` de `src/` y falla si alguno rompe la regla. `tests/Architecture/UseCaseTest.php` falla si un caso de uso tiene más de un método público además del constructor.

## Qué va en cada capa

| Capa | Contenido |
|---|---|
| Domain | Entidades y agregados, objetos de valor, excepciones del dominio e interfaces de los puertos (por ejemplo, un repositorio). |
| Application | Un caso de uso por clase, con un único método público. Recibe los puertos por constructor y no conoce HTTP ni Eloquent. Los DTO llevan datos primitivos. |
| Infrastructure | Controladores, Form Requests, API Resources, rutas, modelos Eloquent, implementaciones de los puertos y el service provider del módulo. |

Un agregado se crea con `create()` y se rearma desde la base con `reconstitute()`; su constructor es privado. Las convenciones de nombres están en [ubiquitous-language.md](ubiquitous-language.md#convenciones-de-nombres).

## Flujo de una petición

1. La ruta del módulo (`Infrastructure/Http/routes.php`) entrega la petición a un controlador.
2. En las rutas del panel, `auth:sanctum` identifica a la persona del personal y `BusinessContext` entrega su tienda. En las rutas públicas, la tienda sale de la URL, y en `/api/v1/cuenta`, del recurso propio del cliente (#32). La transacción de la petición fija esa tienda en PostgreSQL.
3. Un Form Request valida la forma de los datos antes de llegar al controlador.
4. El controlador invoca un caso de uso con un DTO de datos primitivos.
5. El caso de uso trabaja con el dominio y con los puertos.
6. El adaptador de persistencia traduce entre el dominio y Eloquent, y filtra por la tienda.
7. Un API Resource construye la respuesta, traduce los estados al español y expone solo los campos permitidos.

## Puertos y adaptadores

Un puerto es una interfaz definida en `Domain/Contracts`. Su adaptador vive en `Infrastructure` y se enlaza en el service provider del módulo, que se registra en `bootstrap/providers.php`. Cambiar de proveedor afecta a un solo adaptador: pagos, IA, Google Calendar y WhatsApp siguen esta regla ([ADR 0008](adr/0008-external-integrations-behind-ports.md)).

### Colaboración entre contextos

Cuando dos contextos necesitan colaborar lo hacen por un puerto o por un evento de dominio, nunca importando el dominio del otro. Dos ejemplos:

- Una cita solo puede incluir perfumes de botellas con ml disponibles, pero las botellas son de `Decants`. `Scheduling/Appointments` declara el puerto de lectura `TestablePerfumes` y su adaptador consulta `decant_bottles` (#76).
- Un webhook confirma el pago, pero el pedido es de `Sales`. `Payments` referencia lo pagado con el objeto de valor `Payable` y confirma el pedido por un puerto que implementa `Sales`, en la misma transacción que el pago (#88, #94).

Si mañana uno de los dos contextos cambiara, solo cambiaría el adaptador.

### Alta de una tienda con su dueño

El caso de uso `RegisterBusiness`, del módulo `Businesses/Profile`, crea la tienda y la cuenta de su dueño en una sola transacción. Colabora con otras piezas por dos puertos: `TransactionRunner`, en `Shared/Domain/Contracts`, con el adaptador `DatabaseTransactionRunner`; y `OwnerAccounts`, en `Application/Contracts` del módulo, cuyo adaptador `AccountsOwnerAccounts` fija la tienda nueva en la transacción con `BusinessTransaction`, abre la cuenta con el caso de uso `RegisterAccount` de `Accounts/Users` y la agrega al personal como dueño con el caso de uso `AddMember` de `Businesses/Members`. El dominio y la aplicación de `Businesses/Profile` no importan clases de esos módulos: solo las importa ese adaptador. Si cualquiera de los dos pasos falla, la transacción se revierte y no queda ni la tienda ni la cuenta. El comando `archivum:create-business` es la entrada de este caso de uso (#27).

### El tiempo es un parámetro

El dominio nunca pregunta la hora. El momento actual se crea en el borde (la petición HTTP, el trabajo en cola o el comando) en la zona horaria de la aplicación, `America/Costa_Rica`, y viaja hasta el caso de uso y el dominio como parámetro o por un puerto de reloj de `Src\Shared`. Así las reglas que dependen del reloj, como el vencimiento de un apartado o la anticipación mínima de una cita, se prueban con momentos fijos y sin esperar.

## Aislamiento entre tiendas

Toda operación del panel se limita a la tienda que entrega `BusinessContext` (R54). La regla tiene cuatro partes (#31):

1. Cada repositorio de una tabla con `business_id` filtra por el `BusinessId` en cada lectura y escritura. El filtro se escribe en cada consulta, no con un scope global que se desactiva con una llamada.
2. Un identificador de otra tienda o de otro cliente en la URL responde 404 `no-encontrado`, con el mismo cuerpo que uno inexistente (R56): los dos lanzan la misma excepción del dominio. Uno en el cuerpo o en la consulta, como `servicio` y `persona` al consultar la disponibilidad, responde 422 `datos-invalidos` con el mismo error en ese campo que uno inexistente. La respuesta no revela que el recurso existe en otra tienda.
3. Los listados nunca muestran filas de otra tienda y ninguna escritura las toca. Los datos de otra tienda tampoco impiden nada: dos tiendas pueden tener el mismo perfume, porque las llaves únicas incluyen `business_id`.
4. Cada endpoint del panel tiene su prueba de aislamiento con dos tiendas, y cada endpoint nuevo agrega la suya.

PostgreSQL es la segunda barrera: las políticas de seguridad a nivel de fila ocultan las filas de las demás tiendas aunque una consulta olvide el filtro. Los repositorios conservan su filtro: la política no lo reemplaza. El detalle está en [database.md](database.md) y la decisión, en el [ADR 0005](adr/0005-tenancy-through-a-port-and-row-level-security.md).

## Errores

El dominio lanza excepciones que extienden `Src\Shared\Domain\Exceptions\DomainException`. Cada excepción lleva una clave de traducción, no un texto: el mensaje en español se resuelve en la infraestructura desde `lang/es` ([ADR 0006](adr/0006-user-messages-from-translation-files.md)).

Toda respuesta de error sigue RFC 9457 (`application/problem+json`). `ProblemDetailsRenderer` convierte cada excepción en un documento con `type`, `title`, `status`, `detail` e `instance`:

| Excepción | Tipo de problema | HTTP |
| --- | --- | --- |
| Validación de un Form Request | `datos-invalidos`, con `errores` por campo | 422 |
| `InvalidValueException` | `datos-invalidos` | 422 |
| `NotFoundException`, también para un recurso de otra tienda o de otro cliente | `no-encontrado` | 404 |
| `AuthenticationFailedException`, como un correo o una contraseña incorrectos | `credenciales-invalidas` | 401 |
| Ruta protegida sin un token válido (`AuthenticationException` del framework) | `no-autenticado` | 401 |
| Otra excepción del dominio, como un horario que ya no está disponible | `conflicto` | 409 |
| Excepción HTTP del framework | El tipo de su código de estado | 4xx o 5xx |
| Cualquier otra | `error-interno`, sin detalles técnicos | 500 |

El `type` es la URL de la aplicación más `/problemas/<tipo>`. El catálogo de tipos está en `Src\Shared\Infrastructure\Http\Problem\ProblemType`, y el título y el detalle de cada uno, en `lang/es/problems.php`.

El campo `instance` repite el identificador de la solicitud como `urn:uuid:<id>`, el mismo que viaja en el encabezado `X-Request-Id` (#45).

## Contrato de la API

Scramble genera el contrato OpenAPI 3.1 desde el código, y el contrato se versiona en `archivum-api-hexagonal/openapi/v1.json`. Tras cambiar un endpoint hay que regenerarlo con `composer openapi` (#50). `archivum-web` genera sus tipos desde ese archivo (#124).

## Rutas

`routes/api.php` incluye el archivo de rutas de cada módulo. Todas se sirven bajo `/api/v1`, prefijo que se configura una sola vez en `bootstrap/app.php`. Un cambio incompatible se publica como `/api/v2`. La ruta de salud, `/up`, queda fuera del prefijo.

| Grupo | Rutas | Quién |
|---|---|---|
| Público | `/api/v1/tiendas/{tienda}/...` y los webhooks de pago | Cualquiera; la tienda sale de la URL |
| Cuenta y sesión | `/api/v1/cuentas`, `/api/v1/sesiones` y `/api/v1/cuenta/...` | El cliente, solo sobre sus propios recursos |
| Panel | `/api/v1/panel/...` | El dueño y el colaborador, con `auth:sanctum`; la tienda sale de la sesión |

La lista inicial de endpoints está en la sección 8 de la [especificación](especificacion.md#8-api-inicial).

## Pruebas

Las pruebas se escriben con Pest ([ADR 0009](adr/0009-pest-instead-of-phpunit.md)):

| Carpeta | Alcance |
|---|---|
| `tests/Unit` | Dominio y casos de uso, sin arrancar el framework. |
| `tests/Feature` | Endpoints y adaptadores contra PostgreSQL, con el rol de la aplicación. |
| `tests/Architecture` | Regla de dependencias entre capas y entre módulos, y la forma de los casos de uso. |

Los caminos críticos tienen prueba propia: la doble reserva de una cita, los últimos ml de una botella, el canje simultáneo de un paquete, el webhook repetido y el webhook con firma inválida ([ADR 0010](adr/0010-concurrency-guaranteed-in-postgresql.md)).

## Base de datos y decisiones

Los roles, las migraciones y las convenciones del esquema están en [database.md](database.md); el modelo de datos, en `data-model.md` (#35). Las convenciones de idioma y de nombres están en [ubiquitous-language.md](ubiquitous-language.md). El motivo de cada decisión de arquitectura está en [adr/](adr/) y los patrones usados, en [patterns.md](patterns.md).

Un ADR aceptado no se edita: si la decisión cambia, un ADR nuevo la reemplaza y el anterior se marca como sustituido. Los ADR se numeran en orden y su archivo lleva un nombre en inglés.
