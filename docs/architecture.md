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
        │   ├── Services/        Servicios de dominio, si el módulo los necesita
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

La base compartida y los contextos de cuentas, tiendas y catálogo ya están implementados. Investigación 1 agrega `Decants/Bottles`, `Sales/Orders` y `Payments/Payments`; `Decants/Packs` ya tiene su dominio (#142), su esquema (#143), las ofertas del panel y de la tienda (#144), la compra con su pago (#145) y el canje en un pedido (#146); el canje simultáneo y las consultas llegan con #147 y #148. Avance 2 crea `Recommendations/Recommender` con el puerto de la IA (#117). Cada contexto restante se crea con el primer issue de uno de sus módulos, con estos nombres. Los módulos marcados como extra solo se construyen si el núcleo del corte está completo:

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
| Domain | Entidades y agregados, objetos de valor, excepciones del dominio, interfaces de los puertos (por ejemplo, un repositorio) y servicios de dominio: reglas que no pertenecen a una sola entidad, como `SuggestionValidator`. |
| Application | Un caso de uso por clase, con un único método público. Recibe los puertos por constructor y no conoce HTTP ni Eloquent. Los DTO llevan datos primitivos. |
| Infrastructure | Controladores, Form Requests, API Resources, rutas, modelos Eloquent, implementaciones de los puertos y el service provider del módulo. |

Un agregado se crea con `create()` y se rearma desde la base con `reconstitute()`; su constructor es privado. Las convenciones de nombres están en [ubiquitous-language.md](ubiquitous-language.md#convenciones-de-nombres).

## Flujo de una petición

1. La ruta del módulo (`Infrastructure/Http/routes.php`) entrega la petición a un controlador.
2. En las rutas del panel, `auth:sanctum` identifica a la persona del personal y `BusinessContext` entrega su tienda. En las rutas públicas, la tienda sale de la URL, y en `/api/v1/cuenta`, del recurso propio del cliente (#32). La transacción de la petición fija esa tienda en PostgreSQL; los cobros usan la variante de contexto descrita en [Pagos y límites de transacción](#pagos-y-límites-de-transacción), para confirmar el intento antes del HTTP externo.
3. Un Form Request valida la forma de los datos antes de llegar al controlador.
4. El controlador invoca un caso de uso con un DTO de datos primitivos.
5. El caso de uso trabaja con el dominio y con los puertos.
6. El adaptador de persistencia traduce entre el dominio y Eloquent, y filtra por la tienda.
7. Un API Resource construye la respuesta, traduce los estados al español y expone solo los campos permitidos.

## Puertos y adaptadores

Un puerto es una interfaz definida en `Domain/Contracts`. Su adaptador vive en `Infrastructure` y se enlaza en el service provider del módulo, que se registra en `bootstrap/providers.php`. Cambiar de proveedor afecta a un solo adaptador: pagos, IA, Google Calendar y WhatsApp siguen esta regla ([ADR 0008](adr/0008-external-integrations-behind-ports.md)).

### Colaboración entre contextos

Cuando dos contextos necesitan colaborar lo hacen por un puerto o por un evento de dominio. Sus dominios no importan el dominio del otro: la colaboración concreta queda en los adaptadores. Ejemplos:

- Una cita solo puede incluir perfumes de botellas con ml disponibles, pero las botellas son de `Decants`. `Scheduling/Appointments` declara el puerto de lectura `TestablePerfumes` y su adaptador consulta `decant_bottles` (#76).
- `Sales/Orders` necesita reservar ml. Declara `DecantStock` en `Application/Contracts`; `BottleDecantStock` llama a `MlReservation` de `Decants/Bottles`. Los dominios de pedidos y botellas siguen separados y una operación fallida revierte el pedido y sus reservas.
- `Payments/Payments` referencia lo cobrado con `Payable` y colabora con pedidos por `PayableReader`, implementado por `SalesPayableReader`. La persistencia guarda lo pagado en `payments.order_id` o, desde #143, en `payments.decant_pack_id`, cada una con su llave foránea compuesta con `business_id`. Desde #145, `Payments/Payments` cobra también un paquete de decants: lo lee, lo bloquea y lo activa por el puerto `PayablePackReader`, que implementa `DecantsPayablePackReader` llamando al caso de uso `ActivateDecantPack`; en sentido contrario, `Decants/Packs` inicia el pago de una compra por su puerto `PackPayments`, que implementa `PaymentPackPayments` llamando a `StartPayment`. Los dos adaptadores viven en `Payments/Payments/Infrastructure/Decants`, así que ni el dominio ni la aplicación de un contexto importan los del otro. El pago se confirma con un aviso verificado de la pasarela (#94), que se guarda una sola vez y se procesa en la cola (#95), con la conciliación periódica de los pagos pendientes (#96) o con el registro de un dueño cuando el cliente paga el saldo en la entrega (#98); la consulta que pide el cliente solo lee el pago guardado (R38).
- `Sales/Orders` paga decants con un paquete por `DecantPackRedemption`, declarado en `Application/Contracts`. `PackDecantRedemption`, en `Sales/Orders/Infrastructure/Decants`, llama a `RedeemPackDecant`, `RecordPackRedemption` y `ReturnPackDecant` de `Decants/Packs` (#146).
- `Sales/Orders` solicita devoluciones por `RefundRequests`. `PaymentRefundRequests` reparte el monto entre los pagos confirmados del pedido, del más antiguo al más reciente, y registra cada parte con el caso de uso `RefundPayment` de `Payments/Payments` en la transacción del cambio del pedido; pedir la devolución a la pasarela es otra operación, en la cola (#97). Cancelar o marcar una línea como no disponible no declara que el dinero ya se devolvió.
- `Sales/Orders` cierra el pago pendiente de un pedido que vence por `OrderPayments` (#158). `PaymentOrderPayments` pasa a `expired`, con el agregado `Payment` y el `UPDATE` condicionado a `status = 'pending'` de `saveIfPending`, el pago que todavía espera a la pasarela, en la misma transacción del vencimiento.

Si mañana uno de los dos contextos cambiara, solo cambiaría el adaptador.

### Botellas, movimientos y precios de decants

`Decants/Bottles` tiene a `DecantBottle` como agregado. El dominio trabaja con enteros y separa capacidad, ml restantes y ml reservados; calcula los disponibles como restantes menos reservados. Cada reserva, liberación, consumo o ajuste produce un `DecantMovement`. Las excepciones llevan claves de `lang/es/bottles.php`.

`EloquentDecantBottleRepository` agrega y reconstituye botellas filtradas por `BusinessContext`; la modificación ordinaria cambia únicamente las notas y nunca reescribe los saldos. `PostgresMlReservation` implementa las operaciones de ml con un `UPDATE` condicional y la inserción del movimiento en una sola transacción. La decisión de si alcanza el saldo la toma PostgreSQL; el `CHECK` impide cantidades imposibles y el estado pasa a `empty` dentro del mismo `UPDATE` al llegar a 0. El rol de la aplicación solo puede leer e insertar movimientos: no puede modificarlos, borrarlos ni vaciar la tabla.

`DecantPrice`, del núcleo compartido `Decants/Shared`, calcula el precio proporcional y lo redondea hacia arriba con enteros. La vitrina consulta las botellas elegibles en conjunto, con sus presentaciones y los ajustes de la tienda, sin una consulta por botella. Solo ofrece tamaños que caben en los ml disponibles; las presentaciones inactivas o sin precio y las botellas vacías quedan fuera. El registro toma la capacidad de la presentación y admite un saldo inicial menor; una botella vacía no se reabre por ajuste.

### Paquetes de decants

`Decants/Packs` tiene dos agregados: `DecantPackPlan`, la oferta que define el dueño, y `DecantPack`, el paquete que compra el cliente (R32). Los objetos de valor rechazan cantidades, ml, precios, vigencias y topes que no sean enteros positivos o que pasen sus límites. Desactivar una oferta la saca de la venta sin borrarla, porque los paquetes comprados apuntan a ella. Las excepciones llevan claves de `lang/es/packs.php`.

El dueño define las ofertas en `/api/v1/panel/paquetes`, con la ability `packs:manage` que solo tiene su rol (#144). `CreateDecantPackPlan` y `UpdateDecantPackPlan` construyen los ml con `MlPerDecant::soldBy()`, que exige uno de los tamaños de decant de la tienda (R4), leídos por el puerto `DecantSizes` de los ajustes de la tienda; el controlador informa ese error en el campo `ml_por_decant`, como hace el catálogo con los ml repetidos. Una oferta guardada se reconstituye con el constructor, así que conserva sus ml aunque la tienda deje de vender ese tamaño, y editar otro campo no obliga a cambiarlos. Editar una oferta no toca los paquetes ya comprados, que guardan su propia copia. La lista pública de `/api/v1/tiendas/{tienda}/paquetes` es una lectura sin dominio, como la vitrina: `PublicPackPlanReader` responde solo las ofertas a la venta de la tienda de la dirección.

Un paquete nace `pending_payment` de una oferta a la venta, con el plazo de pago de la tienda en `expires_at`, y copia de la oferta sus decants, sus ml y su tope: editar la oferta después no cambia los paquetes ya comprados. Al confirmarse el pago pasa a `active` y su vigencia cuenta desde ese momento, hasta la misma hora cuando pasan sus días (R33). Cada canje descuenta un decant de un paquete activo y vigente, solo para un decant de exactamente sus ml y, si tiene tope, de una presentación cuyo precio de venta no supere el tope por sus ml; el último decant lo deja `exhausted` (R34). Un paquete vence sin pago al pasar su plazo, o con decants restantes al cumplir su vigencia; desde ese momento `statusAt()` lo muestra vencido y ningún canje lo usa, aunque la cola todavía no lo haya procesado. Un paquete agotado ya no vence. El agregado no ofrece reembolsar un paquete ni cambiarlo por dinero (R35), y una prueba de su lista de operaciones lo protege.

El cliente compra un paquete en `POST /api/v1/tiendas/{tienda}/paquetes/{paquete}/compra` (#145). `PurchaseDecantPack` lo registra como cliente de la tienda si todavía no lo era, por el puerto `PackCustomers`, y crea el paquete de la oferta a la venta con el plazo de pago que da el puerto `PaymentDeadline`: los mismos minutos de `unpaid_expiry_minutes` que esperan los pedidos, 30 si la tienda no los cambió. Con el paquete ya guardado, y fuera de su transacción, inicia el pago del precio completo de la oferta guardada por el puerto `PackPayments`; el navegador no envía ningún monto, y si lo envía se ignora. La ruta lleva `business:route,context`: la tienda sale del slug y el caso de uso abre sus transacciones con `PaymentTransactionRunner`, como los cobros, para que el pago pendiente quede confirmado antes del HTTP a la pasarela.

Un doble toque en «comprar» no compra dos paquetes, como el pedido del repo guía. La petición puede traer el encabezado `Idempotency-Key`, una clave que el cliente elige para esa compra, y el paquete la guarda como el objeto de valor `IdempotencyKey`. Antes de crear nada, `PurchaseDecantPack` busca por el puerto `DecantPackRepository` el paquete que el cliente ya compró con esa clave: si es de la misma oferta, lo devuelve con `PurchasedPack::created` en `false` y la API responde 200; si es de otra oferta, lanza `IdempotencyKeyReusedException`, que la API publica como 409 `conflicto`. Dos compras con la misma clave en el mismo instante pueden pasar las dos esa búsqueda; la llave única `(business_id, customer_id, idempotency_key)` rechaza la segunda, el repositorio inserta en un punto de guardado propio y traduce el rechazo en esa excepción, la transacción se deshace y el caso de uso vuelve a buscar en otra transacción y responde el paquete que ganó. La clave es opcional, porque así lo pide el issue: una compra sin clave, o con otra, es una compra nueva. Una clave vacía o de más de 100 caracteres responde 400 `solicitud-invalida`. Mientras el paquete espera su pago, la compra repetida devuelve el intento pendiente por el índice de un pago pendiente por paquete, o inicia otro si el anterior falló; cuando ya no espera, porque se pagó o pasó su plazo, responde su último intento sin cobrar otra vez. Si la pasarela no responde al crear el cobro, la compra responde 202 con `consulta_pendiente` y `Retry-After`, como `StartPayment`.

El paquete se activa solo con el pago confirmado por un aviso verificado (#94) o por la conciliación (#96): `PaymentSettlement` llama a `PayablePackReader::applyPayment`, que corre `ActivateDecantPack` en la misma transacción que cierra el pago, y la vigencia cuenta desde ese momento. Como con los pedidos, decide el estado guardado: un pago que se confirma cuando el paquete ya quedó vencido sin pago no lo activa y queda registrado; su devolución completa llega con la parte de los paquetes de #192 (P11). La respuesta de la compra y la URL de retorno nunca activan un paquete.

El cliente paga un decant con su paquete al crear el pedido: la línea de decant lleva `paquete_id` (#146). `PlaceOrder` cotiza las líneas, arma el pedido y, antes de guardarlo, pide por el puerto `DecantPackRedemption` un decant de cada paquete, en el orden del identificador del paquete. `RedeemPackDecant` bloquea el paquete, comprueba que sea del cliente en esa tienda y descuenta el decant con `DecantPack::redeem`. Después se guardan el pedido y sus líneas, `RecordPackRedemption` deja cada canje en `decant_pack_redemptions` y se reservan los ml de todos los decants, canjeados o vendidos (R25). El orden importa: al guardar una línea que nombra un paquete, la llave foránea toma en PostgreSQL un bloqueo compartido de la fila del paquete, y si dos pedidos lo tomaran antes del `FOR UPDATE` del canje, cada uno esperaría al otro. Por eso los paquetes se bloquean primero y las botellas después, como pide #147.

Un paquete de otro cliente, de otra tienda o que no existe responde 422 en `lineas.N.paquete_id`; otros ml, en `lineas.N.ml`, y una presentación sobre el tope, en `lineas.N.botella_id`. Un paquete pendiente de pago, vencido o agotado, o con menos decants que las líneas que lo usan, responde 409 y la transacción deshace todo el pedido. Si la tienda deja de vender el tamaño del paquete, el canje sigue cubriendo sus ml. La línea canjeada conserva su precio, pero no se cobra (R24): un pedido solo de canjes queda confirmado con ₡0 sin pasar por la pasarela. Al prepararla se consumen sus ml como los de cualquier decant (R26), y el historial de la botella marca `canjeado` en los movimientos de esa línea (R8).

Cancelar el pedido devuelve cada decant canjeado a su paquete en la misma transacción que libera sus ml, también con los paquetes bloqueados antes que las botellas. `ReturnPackDecant` borra el canje bajo el bloqueo del paquete, así que un decant vuelve una sola vez, y `DecantPack::returnDecant` deja `active` un paquete que ese canje había agotado mientras siga vigente, o `expired` si su vigencia ya terminó: el decant vuelve, pero el paquete no se usa ni se reembolsa (R35). Si el paquete vence con un decant canjeado que todavía no se preparó, el canje se mantiene. El vencimiento de los pedidos sin pagar (#158) los devuelve por el mismo puerto y bloquea en el mismo orden.

### Pedidos y precios congelados

`Sales/Orders` reúne `Order` y sus `OrderLine`. `CreateOrder` obtiene precios y condiciones desde los puertos de catálogo y ajustes; el navegador solo identifica los productos y las cantidades. `OrderAmounts` calcula subtotal, cobro inicial, pagado y saldo. `order_lines.unit_price` y `orders.deposit_percent` conservan lo aceptado al crear el pedido: cambiar después el catálogo o el porcentaje de la tienda no cambia esa venta.

El pedido y todas sus reservas se escriben en la misma transacción. El adaptador reserva varias botellas en orden por identificador para evitar interbloqueos. Cancelar libera las reservas; preparar los decants consume a la vez ml restantes y reservados y registra el movimiento ligado a la línea. La entrega exige el pago completo. Los endpoints del cliente comprueban la propiedad del pedido y los del panel usan la tienda de la sesión.

Un pedido que espera su pago vence solo (R25, #158), como los pedidos del repo guía. Al crearlo `pending_payment`, `PlaceOrder` deja con el puerto `OrderDeadlines` un `ExpireUnpaidOrderJob` que la cola escribe al confirmarse el pedido y que solo queda disponible en `orders.expires_at`: a los 30 minutos, o a los minutos que fije la tienda. Un pedido con ₡0 por cobrar se confirma al crearlo y no deja trabajo. A su hora, el trabajo corre `ExpireUnpaidOrder` en una transacción de su tienda: bloquea el pedido y, si todavía espera su pago y su plazo ya llegó, lo pasa a `expired`, devuelve a su paquete el decant de cada línea canjeada y a la botella los ml de cada línea de decant reservada, que pasa a `released`, con los paquetes bloqueados antes que las botellas y cada paquete en el orden de su identificador, y cierra su pago pendiente. El movimiento de liberación lleva la línea, el motivo de `lang/es/orders.php` y ningún usuario, porque lo hizo el sistema. Un pedido pagado, cancelado o ya vencido no cambia, así que una segunda corrida del trabajo no devuelve nada otra vez, y una copia que corra antes del plazo tampoco cambia nada.

El vencimiento y la confirmación del pago por aviso (#94) o por conciliación (#96) bloquean la fila del pedido antes de cambiarla, así que si llegan a la vez uno espera al otro y lee lo que dejó. Además, los dos guardan el pedido con `saveIfStatus`, un `UPDATE` condicionado al estado con que lo leyeron (`pending_payment`): si otra operación lo hubiera cambiado primero, la transacción se revierte con todo lo que hizo. Termina de una sola de dos formas: o el pago confirma el pedido y el vencimiento lo encuentra pagado, o el pedido vence primero y el aviso, que encuentra su pago `expired`, igual pregunta a la pasarela y, como cobró, conserva el cobro y pide su reembolso completo (#192). `ConcurrentOrderExpiryTest` lo comprueba con dos procesos, en los dos órdenes. Si la cola perdiera el trabajo, el barrido de #160 recoge el pedido.

### Pagos y límites de transacción

`PaymentGateway` es el contrato único: declara medios admitidos y operaciones de crear cobro, consultar estado y reembolsar; también verifica el origen de un aviso de la pasarela (`verifyWebhook`) y lo lee (`parseWebhook`). `OnvoGateway` admite tarjeta y SINPE Móvil; `PayPalGateway`, tarjeta. `ConfiguredPaymentGateways`, resuelto por el contenedor, selecciona el adaptador con la configuración de la tienda y vuelve a usar el proveedor guardado en cada intento existente. Los controladores solo conocen casos de uso.

Los dos adaptadores usan el cliente HTTP de Laravel, con límites explícitos de conexión y respuesta y credenciales de pruebas. La conversión vive en ellos: ONVO recibe la unidad mínima de CRC; PayPal usa Orders v2, aprobación y captura con un importe decimal en USD. El principal del negocio continúa en colones enteros; `charged_amount` conserva colones enteros para CRC y centavos para USD, junto con el tipo de cambio congelado en el intento.

Las rutas de cobro llevan `business:account,context`, además de autenticación y rol de cliente. El middleware resuelve la tienda del recurso propio y enlaza `BusinessContext`, pero deja los límites de transacción al caso de uso. `PaymentsServiceProvider` inyecta `PaymentTransactionRunner` como implementación contextual de `TransactionRunner` en `StartPayment`, `ObservePayment`, `ApplyPaymentEvent`, `ReconcilePayment` y los casos de uso de los reembolsos (`RefundPayment`, `RequestRefund`, `ReconcileRefund` y `RequestOrderRefunds`), y `PacksServiceProvider` hace lo mismo en `PurchaseDecantPack`. Cada llamada de ese puerto abre una transacción con la tienda fijada mediante `BusinessTransaction`; no se mantiene una transacción exterior que pueda revertir un cobro ya enviado.

`StartPayment` bloquea lo que se cobra, el pedido o, desde #145, el paquete, calcula el importe en el servidor y guarda una intención `pending` antes de enviar el HTTP de creación. Un paquete se cobra completo, por el precio de su oferta guardada, y solo mientras espera su pago y no pasó su plazo. El índice parcial por tienda, pedido o paquete y propósito impide dos intentos pendientes iguales. Si ya existe un intento, se devuelve ese intento. Tras la llamada externa se guardan las referencias del proveedor en otra transacción; la creación por sí sola no confirma el pedido. Un timeout conserva la intención pendiente y evita repetir un POST cuyo resultado se desconoce.

`ObservePayment` solo lee: en una transacción que fija la tienda, comprueba sin bloquear nada que el pedido del pago sea del cliente de la sesión y devuelve el pago guardado, sin consultar al proveedor ni cambiarlo (R38, desde #96). Mientras siga pendiente, el cliente muestra "procesando"; lo cierran un aviso verificado (#94) o la conciliación (#96), que hacen la consulta autenticada con `PaymentSettlement`. Un pago pendiente que todavía no tiene referencia del proveedor responde 202 con `consulta_pendiente` y `Retry-After`, igual que el intento incierto de `StartPayment`. El navegador nunca aporta el estado financiero.

Los reembolsos se piden a la pasarela en la cola (#97). `RefundPayment`, el único caso de uso que registra uno, bloquea la fila del pago (`SELECT ... FOR UPDATE`) y le pide el reembolso a `Payment::requestRefund()`: el agregado rechaza con 409 `conflicto` un monto mayor que lo pagado menos lo ya devuelto y los reembolsos pendientes, así que dos reembolsos simultáneos del mismo pago tampoco lo superan. El puerto `OutgoingRefunds` deja un `RequestRefundJob` por reembolso, que la cola escribe cuando se confirma la transacción: un reembolso revertido no deja trabajo (#156). Si el proceso cayera justo entre la confirmación y la escritura del trabajo, el reembolso quedaría `pending` sin salir, y el dueño lo vuelve a encolar desde el pedido. Lo usan la cancelación (R30), la línea no disponible (R31) y el cobro que se confirma con el pedido ya cerrado, siempre con el monto que calcula el servidor.

El trabajo lleva la tienda del reembolso, que `ScopeJobToBusiness` enlaza como `BusinessContext` (#156), y corre `RequestRefund`, que, con el pedido, el pago y el reembolso bloqueados en ese orden, el mismo de toda operación sobre un pago, confirma `refunds.attempted_at` y el monto en la unidad de la pasarela (`charged_amount`) antes del HTTP. ONVO devuelve colones enteros. PayPal convierte a dólares el total acumulado de los reembolsos del pago con el tipo de cambio del pago original y pide la diferencia: la suma de los reembolsos nunca pasa de los centavos capturados y coincide con ellos cuando se devuelve todo (R37). Un reembolso que ya salió no se envía otra vez a ciegas. Con la respuesta, guarda la referencia del proveedor en una transacción propia y aplica el resultado en otra con `RefundSettlement`. Un reembolso confirmado pasa a `succeeded`, el pago a `partially_refunded` o `refunded` y el pedido resta lo devuelto de lo pagado. Un rechazo claro de la pasarela lo deja `failed`, sin referencia si la pasarela no creó nada, y el pago y el pedido no cambian: con ONVO, cualquier error 4xx salvo 408, 409 y 429, como un reembolso declinado (402), una llave que no acepta (401) o una intención que no conoce (404); con PayPal, 400, 403, 404 y 422. Sin conexión, con 408, 409 o 429 o con un error del servidor de la pasarela, la respuesta se pierde y el reembolso queda `pending` sin referencia. PayPal reconoce la misma petición por su `PayPal-Request-Id` y responde el reembolso que ya creó (`PaymentGateway::resendsRefunds()`), así que `RequestRefund` deja fallar el intento y los reintentos del trabajo lo piden otra vez con el mismo monto. ONVO no documenta una llave de idempotencia: su reembolso no se reenvía, y otro trabajo lo encuentra iniciado y no hace nada. Uno que se queda sin respuesta, con ONVO o con PayPal después de los cinco intentos, espera que una persona lo revise en la pasarela, y la lista del dueño lo marca con `consulta_pendiente: true`; el trabajo de PayPal que agotó sus intentos queda en `failed_jobs`, y `php artisan queue:retry` puede pedirlo otra vez.

Un reembolso que la pasarela aceptó y todavía procesa se confirma después. PayPal avisa con `PAYMENT.CAPTURE.REFUNDED`, que trae el reembolso, el `custom_id` con la tienda y el reembolso que Archivum envió y la captura en su enlace `up`. `ApplyPaymentEvent` comprueba que el aviso nombre la captura del pago y los centavos exactos y aplica la consulta autenticada del reembolso, con la idempotencia de #95. ONVO no avisa reembolsos por webhook: el adaptador consulta el reembolso justo después de crearlo y, si sigue pendiente, lo confirma la conciliación. `archivum:reconcile-payments` revisa también los reembolsos `pending` con referencia que esperaron `Payment::MINUTES_FOR_THE_NOTICE` minutos desde que salieron, con el puerto `PendingRefunds` y el caso de uso `ReconcileRefund`. El endpoint del dueño `POST /api/v1/panel/pedidos/{pedido}/reembolsos` lista los reembolsos del pedido con su estado (`pendiente`, `confirmado` o `fallido`) y `consulta_pendiente`, y vuelve a encolar los que nunca salieron, como los de un trabajo que agotó sus intentos antes de enviarlos; si el trabajo de un reembolso todavía espera en la cola, no deja otro. Usa `business:staff` y corre en una sola transacción, porque ya no llama a la pasarela.

Los avisos de las pasarelas llegan a `POST /api/v1/webhooks/onvo` y `POST /api/v1/webhooks/paypal`, sin sesión ni token (#94). `ReceivePaymentEvent` verifica el origen antes de leer o escribir nada: ONVO compara `X-Webhook-Secret` con el secreto configurado mediante `hash_equals`, y PayPal pide a su API de verificación que compruebe la firma. Un origen no verificado responde 401 `credenciales-invalidas` sin decir qué falló, y el aviso no se registra; una pasarela sin sus credenciales de prueba tampoco verifica nada. El adaptador lee el aviso verificado como `PaymentEvent`: su identificador (PayPal trae uno; ONVO no, y se usa el tipo con el objeto), la referencia del proveedor del cobro, los identificadores que Archivum envió al crear el cobro y el monto en la unidad que guarda el pago.

El primer aviso de cada evento se guarda en `webhook_events` como `pending` antes de procesarse (#95): `INSERT ... ON CONFLICT DO NOTHING` sobre la llave única `(gateway, event_id)` solo registra la primera llegada, aunque dos lleguen a la vez, porque la segunda espera a que termine la primera y luego no inserta nada. El puerto `PendingPaymentEvents` deja `ProcessPaymentEventJob` en la cola, que lo escribe cuando se confirma la transacción que guardó el aviso (#156). Si el proceso cayera justo entre la confirmación y la escritura del trabajo, el aviso quedaría `pending`, y la conciliación (#96) cierra igual el pago o el reembolso. El webhook responde 200 de inmediato con `estado: recibido`, o `repetido` cuando el evento ya estaba guardado, y un aviso repetido no cambia nada. Solo si PayPal no puede verificar la firma se responde 503, para que reenvíe el aviso.

El trabajo no lleva tienda: enlaza `PaymentEventBusinessContext` como el `BusinessContext` de los repositorios y corre `ProcessPaymentEvent`, que bloquea el aviso guardado y, si todavía no está procesado, encuentra su tienda sin sesión: la que Archivum envió con el cobro (los metadatos del checkout de ONVO, el `custom_id` de PayPal) o, si el aviso no la trae, la de `fn_payment_business`, que solo responde la tienda de una referencia del proveedor. `PaymentEventBusinessContext` fija esa tienda mientras corre `ApplyPaymentEvent`, en una transacción de la tienda: bloquea el pedido o el paquete y luego el pago, comprueba que el aviso nombre una referencia de ese pago con su monto y su moneda exactos y, si el pago sigue pendiente, aplica la respuesta de la consulta autenticada al proveedor con `PaymentSettlement`, el mismo servicio que usa la conciliación (#96). El aviso solo dice que algo pasó; el estado lo decide la consulta del servidor. Con PayPal, la orden aprobada no confirma el pago: la consulta la captura y el pago se confirma cuando la captura queda completada. Aplicar el aviso y marcarlo `processed` con `processed_at` ocurre en una sola transacción. Si falla, queda `failed` con la clave de su error, como un monto distinto, y termina ahí; si el proveedor no responde o el error es inesperado, el trabajo vuelve a la cola y se reintenta con las esperas de `config/archivum.php` (10, 60 y 300 segundos, hasta cinco intentos), y uno que los agota queda en `failed_jobs`. Un intento nuevo nunca aplica dos veces un aviso ya procesado, y `PaymentSettlement` guarda el pago con un `UPDATE` condicionado a `status = 'pending'`: aunque dos operaciones tomaran el mismo pago, solo una lo cambia. En producción, el worker supervisado de `php artisan queue:work` se configura con la plataforma de #21. Las fuentes y decisiones técnicas están en [payment-gateways.md](payment-gateways.md); la comparación de proveedores queda en [investigacion-1.md](investigacion-1.md) y la puesta en marcha se documenta en el [README de la API](../archivum-api-hexagonal/README.md).

Si un aviso nunca llega, la conciliación lo suple (#96). El comando `archivum:reconcile-payments` corre cada cinco minutos, programado en `PaymentsServiceProvider` con `withoutOverlapping`, como los barridos del repo guía. Pide al puerto `PendingPayments` los pagos `pending` con referencia del proveedor que ya esperaron su aviso `Payment::MINUTES_FOR_THE_NOTICE` minutos, para no adelantarse al webhook. Hasta el rol de mantenimiento de #157, su adaptador `PostgresPendingPayments` recorre las tiendas con `archivum_app` y lee los pagos de cada una en una transacción que la fija, porque la seguridad a nivel de fila no deja leer los de todas a la vez; solo lee. Por cada pago, `ReconcilePayment` fija su tienda con `PaymentEventBusinessContext`, bloquea el pedido o el paquete y luego el pago, en el mismo orden que `ApplyPaymentEvent`, y si sigue pendiente lo cierra con `PaymentSettlement`, el mismo servicio del aviso; con PayPal, la consulta del servidor captura la orden aprobada. Desde #96, solo el aviso y la conciliación preguntan al proveedor por un cobro pendiente; la consulta del cliente ya no lo hace. Así un pago que el aviso ya cerró no cambia otra vez, y un aviso que llega después de la conciliación lo encuentra cerrado y no aplica nada nuevo. Si el proveedor no responde, el pago sigue `pending` para la siguiente corrida y los demás se revisan igual; otro rechazo, como un monto distinto, también lo deja pendiente y queda en el log. En producción, `php artisan schedule:run` corre cada minuto con la plataforma de #21.

El saldo que el cliente paga al recibir su pedido, por SINPE o en efectivo, lo registra un dueño con `POST /api/v1/panel/pedidos/{pedido}/pagos-manuales` (#98, R29). La ruta lleva `abilities:payments:record`, `business:staff` sin la variante `context` y `AuthorizeOrder:manage`, como los reembolsos (#104): no llama a ninguna pasarela, así que la petición corre en una sola transacción de la tienda. `RecordManualPayment` busca a la cuenta entre los dueños de la tienda con el puerto `PaymentRecorders`, de modo que un token con la habilidad cuya cuenta no es dueña también recibe 403. Luego bloquea el pedido y exige que esté `ready` y con saldo, que no tenga un reembolso pendiente ni un pago en línea del saldo en curso, cuyo aviso ya no cabría en el saldo, y que el monto no lo supere. Guarda un pago `manual`, ya `succeeded`, con quién lo registró, el medio, la referencia y el momento, y descuenta el saldo con `PayableReader::applyPayment`, el mismo puerto que usa un aviso verificado. Un segundo registro simultáneo espera el bloqueo del pedido, encuentra el saldo ya descontado y responde 409; `orders_balance_check` impide además un saldo negativo. Cada registro deja en la misma transacción su entrada `payment.manual_recorded` en la bitácora de #113, con el pedido, el método y el monto: si la entrada falla, el pago no se guarda.

Un cobro que la pasarela confirma con el pedido ya cancelado o vencido se conserva y se reembolsa completo (#192), como dice la especificación: el pedido no cambia de estado ni vuelve a reservar ml, cuenta lo cobrado en lo pagado hasta que el reembolso se confirma y, en la misma transacción, `RefundPayment` registra el reembolso del cobro entero, ligado a su pago, con el sistema como autor en la bitácora. El vencimiento de un pedido (#158) cierra su pago pendiente sin preguntar a la pasarela, así que el aviso o la conciliación pueden encontrarlo `expired`: `PaymentSettlement::settleLate()` pregunta otra vez a la pasarela y, solo si cobró, pasa el pago a `succeeded` con `Payment::confirmLate()`, la única salida de `expired`, para que coincida con la pasarela (R39). Ese camino guarda el pago con `saveIfPendingOrExpired`, un `UPDATE` condicionado a `status IN ('pending', 'expired')`: el mismo aviso repetido, o aplicado también por la conciliación, registra un solo reembolso. Por si el aviso nunca llega, `PostgresPendingPayments` también devuelve los pagos `expired` de los pedidos vencidos durante `Payment::HOURS_FOR_A_LATE_CHARGE` horas (24) desde que el vencimiento los cerró, después de esperar su aviso los mismos `Payment::MINUTES_FOR_THE_NOTICE` minutos que un pago pendiente. Una orden de PayPal solo aprobada no se captura con el pedido cerrado. `LatePaymentRefundTest` lo comprueba con ONVO, PayPal y dos tiendas, y `ConcurrentLatePaymentTest` y `ConcurrentOrderExpiryTest`, con la cancelación y con el vencimiento en dos procesos.

### Alta de una tienda con su dueño

El caso de uso `RegisterBusiness`, del módulo `Businesses/Profile`, crea la tienda y la cuenta de su dueño en una sola transacción. Colabora con otras piezas por dos puertos: `TransactionRunner`, en `Shared/Domain/Contracts`, con el adaptador `DatabaseTransactionRunner`; y `OwnerAccounts`, en `Application/Contracts` del módulo, cuyo adaptador `AccountsOwnerAccounts` fija la tienda nueva en la transacción con `BusinessTransaction`, abre la cuenta con el caso de uso `RegisterAccount` de `Accounts/Users` y la agrega al personal como dueño con el caso de uso `AddMember` de `Businesses/Members`. El dominio y la aplicación de `Businesses/Profile` no importan clases de esos módulos: solo las importa ese adaptador. Si cualquiera de los dos pasos falla, la transacción se revierte y no queda ni la tienda ni la cuenta. El comando `archivum:create-business` es la entrada de este caso de uso (#27).

### Recomendador con IA

`Recommendations/Recommender` consulta a la IA por el puerto `AiRecommender`, en `Domain/Contracts`. Recibe el texto del cliente y el catálogo de la tienda como objetos del dominio (`CatalogPerfume` con sus `CatalogVariant`) y devuelve una `Recommendation`: las `CustomerPreferences` leídas del texto, hasta 5 `SuggestedPerfume` con su razón y el `RecommendationOrigin` que dice si la produjo la IA o las reglas. Lo que devuelve la IA no es confiable: se compara con el catálogo antes de usarse (#119), y si la IA falla responde el recomendador por reglas (#120).

El adaptador `LaravelAiRecommender`, en `Infrastructure/Ai`, usa `laravel/ai` con Gemini ([ADR 0012](adr/0012-ai-recommender-through-laravel-ai-with-gemini.md)). Su agente `RecommendationAgent` lleva las reglas y el catálogo en las instrucciones, recibe el texto del cliente como el mensaje y pide salida estructurada con un esquema declarado, sin herramientas ni conversación. El adaptador corta la solicitud a los 10 s fijos de `config/archivum.php` y convierte cualquier excepción del SDK o del cliente HTTP, una respuesta tardía o una que no tiene la forma del esquema en `AiUnavailableException`. El proveedor y el modelo se cambian en `config/ai.php`, y la llave vive en `.env`; ni el dominio ni los casos de uso conocen el SDK.

Antes de usarse, lo que sugiere la IA pasa por el servicio de dominio `SuggestionValidator`, en `Domain/Services` (#119). La sugerencia se trata como datos no confiables: se compara en memoria con el catálogo que recibió la IA y nada de ella llega a una consulta. Se descarta cada perfume que no está en ese catálogo (no existe, es de otra tienda o no está activo), el repetido, el que no tiene ninguna presentación que cueste a lo sumo el presupuesto (el mismo criterio del precio máximo de la vitrina) y el que trae una razón vacía o de más de 200 caracteres. En las preferencias se descarta un momento o un clima fuera de sus valores y un presupuesto que no es un entero positivo. El módulo tiene sus propios `TimeOfDay` y `Climate`, con los valores con que el catálogo etiqueta sus perfumes: el dominio de un módulo no depende del de otro, aunque nombren los mismos valores. El agente los usa en su esquema, así lo que pide y lo que acepta salen de la misma fuente.

`SuggestionValidator` devuelve una `SuggestionValidation`: la recomendación con lo que quedó y una `DiscardCause` por cada descarte, para que el log cuente cuántos hubo y por qué, sin el texto del cliente (#121). Si no queda ningún perfume, la sugerencia no valida (`passed()` es falso) y responde el recomendador por reglas (#120). Marca, nombre y precios no forman parte de una sugerencia: siempre salen del catálogo.

Cuando la IA falla, tarda más de 10 s o su sugerencia no valida, responde sin IA el servicio de dominio `RuleBasedRecommender` (#120). Compara el texto, en minúsculas y sin tildes, con las notas y las etiquetas de la vitrina: lee las notas que nombra, el momento del día y el clima que menciona primero (por las palabras de `TimeOfDay` y `Climate`), la ocasión que menciona primero y el presupuesto. El presupuesto es el mayor monto de dinero del texto: un número con ₡ antes, con «colones», «mil» o «k» después, con separador de miles o de al menos cinco cifras; los ml y los números cortos, como una edad o un año, no cuentan. Cada perfume suma 2 puntos por nota en común y 1 por el momento, el clima, la ocasión y el presupuesto (`RuleMatch`), y se recomiendan hasta 5, de mayor a menor puntaje y, si empatan, en el orden de la vitrina. Un perfume sin ninguna coincidencia no se recomienda, y uno sin una presentación dentro del presupuesto tampoco, con el mismo `CatalogPerfume::fitsBudget()` de la validación. La razón de cada perfume la escribe el puerto `RuleReasons`, cuyo adaptador `TranslatedRuleReasons` usa las plantillas de `lang/es/recommendations.php`: el dominio no guarda textos para el cliente.

`LaravelAiRecommender` deja en el log cada falla de la IA con su causa (`ai_failed`, `ai_timed_out` o `ai_invalid_output`) y su duración en milisegundos, nunca con el texto del cliente.

El caso de uso `RecommendPerfumes`, en `Application/UseCases`, une esas piezas detrás de `POST /api/v1/tiendas/{tienda}/recomendaciones` (#118). Lee la vitrina por el puerto `RecommendableCatalog`, en `Application/Contracts`, cuyo adaptador `ShowcaseRecommendableCatalog` usa los modelos de lectura de la vitrina (#48): los mismos perfumes y presentaciones que ve un visitante, con sus notas (cada una una sola vez, aunque esté en más de una capa) y sus etiquetas, y nada más. Después pregunta a la IA y valida su sugerencia; si la IA no está disponible o no queda ningún perfume válido, responden las reglas. Una tienda con la vitrina vacía no tiene nada que recomendar, así que responden las reglas sin preguntar al proveedor. Marca, nombre y presentaciones de cada perfume salen del catálogo, y `origen` dice quién produjo la respuesta (`ia` o `reglas`). El caso de uso solo recibe el texto: no conoce la cuenta de quien pregunta, y por eso un visitante, un cliente, un colaborador y un dueño reciben lo mismo (sección 9 de la especificación).

La ruta no pide autenticación y usa `business:route,context`: resuelve la tienda del slug y responde 404 si no existe, pero no abre la transacción de la petición, porque la IA puede tardar hasta su límite. El adaptador del catálogo lee en una transacción corta propia, fijada a la tienda para las políticas de fila, igual que los pagos antes de sus llamadas externas. El texto admite hasta 1000 caracteres (`StoreRecommendation::MAX_TEXT_LENGTH`), el largo que eligió Julián, y la ruta aplica el limitador `recomendaciones` de #108.

Antes de que el texto llegue a un recomendador, el servicio de dominio `PersonalDataMask` cambia cada correo y cada teléfono que traiga por `[correo]` o `[teléfono]`, con los mismos patrones con que los registros ocultan esos datos (#110). Así ni el proveedor ni las reglas los ven, y un teléfono de ocho cifras tampoco se lee como presupuesto (R51, #121). Al proveedor solo viajan ese texto, como el mensaje, y en las instrucciones el catálogo con los campos que ya publica la vitrina: identificador, marca, nombre, concentración, familia, notas, etiquetas y presentaciones con su precio de venta. `RecommendationPrivacyTest` lo comprueba con un cliente autenticado, la solicitud simulada al proveedor y el log. Cuando la validación descarta algo de la sugerencia, el puerto `DiscardedSuggestions`, en `Application/Contracts`, con su adaptador `LogDiscardedSuggestions`, deja en el log cuántos valores y perfumes se descartaron, contados por causa, sin el texto ni la sugerencia.

### El tiempo es un parámetro

El dominio nunca pregunta la hora. El momento actual se crea en el borde (la petición HTTP, el trabajo en cola o el comando) en la zona horaria de la aplicación, `America/Costa_Rica`, y viaja hasta el caso de uso y el dominio como parámetro o por un puerto de reloj de `Src\Shared`. Así las reglas que dependen del reloj, como el vencimiento de un apartado o la anticipación mínima de una cita, se prueban con momentos fijos y sin esperar.

### Cuentas y autenticación

El contexto `Accounts` guarda las cuentas en el módulo `Accounts/Users` y el ingreso en `Accounts/Sessions`. El agregado `User` es PHP puro: no conoce Eloquent ni Sanctum. Dos puertos lo separan del framework: `UserRepository`, con su adaptador `EloquentUserRepository`, y `PasswordHasher`, cuyo adaptador `BcryptPasswordHasher` usa la fachada `Hash` de Laravel con bcrypt.

El modelo `UserModel` vive en la infraestructura de `Accounts/Users`, extiende el usuario autenticable de Laravel y usa `HasApiTokens` de Sanctum. `config/auth.php` lo declara como el proveedor `users`. El guard `sanctum` autentica el token Bearer de las rutas que piden `auth:sanctum` y entrega la cuenta dueña del token.

El caso de uso `StartSession` emite el token por el puerto `TokenIssuer`. Su adaptador, `SanctumTokenIssuer`, crea un token de Sanctum con el nombre del dispositivo, y Sanctum guarda solo su hash SHA-256. `GetCurrentAccount` pregunta el rol y la tienda de la cuenta al puerto `StaffMemberships`, en `Application/Contracts` del módulo. Su adaptador, `BusinessesStaffMemberships`, los obtiene con los casos de uso `FindMemberByAccount` de `Businesses/Members` y `FindBusiness` de `Businesses/Profile`; una cuenta que no está en el personal de ninguna tienda es cliente. El dominio y la aplicación de `Accounts/Sessions` no importan clases de `Businesses`: solo las importa ese adaptador.

El caso de uso `EndSession` cierra la sesión por el puerto `TokenRevoker`. Su adaptador, `SanctumTokenRevoker`, borra la fila del token de la petición, así las demás sesiones de la cuenta siguen abiertas. El mismo puerto borra todos los tokens de una cuenta con `revokeAll`; lo usarán el cambio de rol y la desactivación del personal (#106) y la eliminación de la cuenta (#112).

Las operaciones de una tienda preguntan su tienda al puerto `BusinessContext` de `Shared`. Su enlace por defecto, `UnresolvedBusinessContext`, falla a propósito, porque fuera de un grupo de rutas con el middleware `business` no se sabe de qué tienda se trata. Ese middleware, `ScopeRequestToBusiness`, recibe como parámetro el origen de la tienda y, durante la petición, enlaza el puerto al adaptador que un módulo registra para ese origen en su service provider, con la clave `ScopeRequestToBusiness::contextFor()`:

| Middleware | Rutas | Adaptador | De dónde sale la tienda |
| --- | --- | --- | --- |
| `business:staff` | `/api/v1/panel/...`, después de `auth:sanctum` | `StaffBusinessContext`, de `Businesses/Members` | De la persona autenticada, con el caso de uso `FindMemberByAccount`. Un cliente, que no está en el personal de ninguna tienda, recibe 403 `prohibido` |
| `business:route` | `/api/v1/tiendas/{tienda}/...` | `RouteBusinessContext`, de `Businesses/Profile` | Del slug `{tienda}` de la URL, con el caso de uso `FindBusinessBySlug`. Un slug que no existe responde 404 `no-encontrado` |
| `business:account` | `/api/v1/cuenta/...` que nombran un recurso del cliente, como `{cliente}`, después de `auth:sanctum` | `CustomerBusinessContext`, de `Accounts/Users` | Del recurso de la URL, con el caso de uso `FindCustomerResourceBusiness`. Un recurso de otro cliente responde 404 `no-encontrado`, igual que uno que no existe |
| `business:account,context` | Crear o consultar un pago de un pedido propio | El mismo `CustomerBusinessContext` | Del pedido o pago propio; el caso de uso abre sus transacciones con `PaymentTransactionRunner` |
| `business:route,context` | Comprar un paquete de decants, después de `auth:sanctum` (#145) | El mismo `RouteBusinessContext` | Del slug `{tienda}`; el caso de uso guarda el paquete y el intento de pago antes del HTTP |

El middleware resuelve la tienda antes de llegar a cualquier caso de uso y, por defecto, corre la petición en una sola transacción que la fija con `BusinessTransaction`. La variante `context` deja esa responsabilidad al puerto transaccional de pagos, como se describe arriba. El dueño y el colaborador trabajan siempre sobre su propia tienda: ninguna ruta del panel toma la tienda de la URL, de los parámetros ni del cuerpo. Los listados de `/api/v1/cuenta` no nombran un recurso y no llevan `business:account`: `CustomerBusinessContext::eachBusiness()` corre la operación una vez por cada tienda donde la cuenta es cliente, cada vez en su propia transacción con esa tienda fijada. La tienda de una persona del personal, las tiendas de un cliente y la de uno de sus recursos se buscan antes de fijar una tienda, así que las responden funciones `SECURITY DEFINER` de PostgreSQL ([ADR 0011](adr/0011-customer-routes-resolve-the-business-through-definer-functions.md)). La función de recursos del cliente incorpora `order` y `payment` mediante sus uniones con `customers` y el usuario autenticado. `tests/Feature/Tenancy/BusinessOfTheSessionTest.php` y `tests/Feature/Tenancy/CustomerIsolationTest.php` comprueban estas reglas, y `tests/Feature/Shared/ScopeRequestToBusinessTest.php` comprueba el middleware.

### Trabajos en cola

Todo envío y todo vencimiento corre en la cola (R46, #156). `config/queue.php` sigue al repo guía: la conexión `database` guarda cada trabajo en la tabla `jobs` y lo escribe solo cuando se confirma la transacción que lo despachó (`after_commit`), así que una operación revertida no deja trabajo. Un trabajo que agota sus intentos queda en `failed_jobs` con su excepción, y `php artisan queue:retry` lo vuelve a encolar. El rol dueño corre las migraciones de `jobs`, `job_batches`, `failed_jobs` y `cache_locks`, y `archivum_app` lee y escribe en ellas.

Cada trabajo de las carpetas `Infrastructure/Jobs` extiende `IdempotentJob`, de `Src\Shared\Infrastructure\Queue`. Declara su clave con `uniqueId()` (`ShouldBeUnique`) y toma sus intentos y esperas de `config/archivum.php` con `tries()` y `backoff()`: cinco intentos, con esperas de 10, 60 y 300 segundos, y la última se repite. Mientras una copia no termina, despacharla otra vez con la misma clave no deja nada nuevo en la cola. Una copia que de todos modos corre dos veces, como la que `queue:retry` vuelve a encolar, no cambia el resultado, porque cada caso de uso solo cambia lo que sigue pendiente, con `UPDATE` condicionales y llaves únicas.

La clave usa los bloqueos de la caché, en `cache_locks`, por una conexión propia: `pgsql_locks`, con la misma base y el mismo rol de la aplicación. Laravel toma el bloqueo con un `INSERT` y, si ya existe, responde que está tomado después de que el `INSERT` falla; PostgreSQL anula toda la transacción ante ese error. Un trabajo despachado otra vez dentro de la transacción de una operación, como cuando el dueño vuelve a encolar los reembolsos de un pedido, haría fallar la operación con 500. Con su propia conexión, el bloqueo queda fuera de esa transacción, y Laravel lo libera si la transacción se revierte.

Un trabajo de una tienda lleva el id de su tienda, y su middleware `ScopeJobToBusiness`, el equivalente de `ScopeRequestToBusiness` en la cola, enlaza `BusinessContext` a `JobBusinessContext` antes de que el trabajo corra. Por defecto corre el trabajo en una sola transacción que fija `app.business_id` con `BusinessTransaction`, como `ExpireUnpaidOrderJob` (#158). La variante `context` deja las transacciones al caso de uso, como en las rutas de pagos: la usa `RequestRefundJob`, que confirma su intento antes de llamar a la pasarela. Al terminar, el middleware devuelve el contexto anterior, porque el worker conserva su contenedor para el siguiente trabajo. Un trabajo sin una tienda válida falla de inmediato, sin correr ni reintentarse, así que no escribe nada. La excepción es `ProcessPaymentEventJob` (#95): el aviso llega sin sesión y su tienda sale del pago que nombra. `SendPasswordResetLink` (#193) tampoco lleva tienda, porque las cuentas no tienen una; vive en `Accounts/Users/Infrastructure/Queue` y no extiende esta base. `tests/Architecture/QueuedJobsTest.php` comprueba las dos reglas en las clases de `Infrastructure/Jobs`, y `tests/Feature/Shared/ScopeJobToBusinessTest.php` comprueba el middleware con dos tiendas.

En desarrollo, el worker corre con `php artisan queue:work` en otra terminal, como explica el [README de la API](../archivum-api-hexagonal/README.md).

## Aislamiento entre tiendas

Toda operación del panel se limita a la tienda que entrega `BusinessContext` (R54). La regla tiene cuatro partes (#31):

1. Cada repositorio de una tabla con `business_id` filtra por el `BusinessId` en cada lectura y escritura. El filtro se escribe en cada consulta, no con un scope global que se desactiva con una llamada.
2. Un identificador de otra tienda o de otro cliente en la URL responde 404 `no-encontrado`, con el mismo cuerpo que uno inexistente (R56): los dos lanzan la misma excepción del dominio. Uno en el cuerpo o en la consulta, como `servicio` y `persona` al consultar la disponibilidad, responde 422 `datos-invalidos` con el mismo error en ese campo que uno inexistente. La respuesta no revela que el recurso existe en otra tienda.
3. Los listados nunca muestran filas de otra tienda y ninguna escritura las toca. Los datos de otra tienda tampoco impiden nada: dos tiendas pueden tener el mismo perfume, porque las llaves únicas incluyen `business_id`.
4. La suite `tests/Feature/Tenancy/StaffIsolationTest.php` lo comprueba con dos tiendas, un caso por cada endpoint del personal. Un endpoint nuevo del personal agrega su caso a esa suite.

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
| Persona autenticada sin permiso: un token sin la ability que declara la ruta (`abilities:` o `ability:` de Sanctum, #102), como un colaborador o un cliente en una ruta del dueño. Es `MissingAbilityException`, una `AuthorizationException` del framework | `prohibido` | 403 |
| Otra excepción del dominio, como un horario que ya no está disponible | `conflicto` | 409 |
| Excepción HTTP del framework | El tipo de su código de estado | 4xx o 5xx |
| Cualquier otra | `error-interno`, sin detalles técnicos | 500 |

Laravel convierte toda `AuthorizationException` en una excepción HTTP 403 antes de llamar a `ProblemDetailsRenderer`, que la traduce al tipo de su código de estado. Por eso esa excepción no necesita una rama propia en el renderizador.

El `type` es la URL de la aplicación más `/problemas/<tipo>`. El catálogo de tipos está en `Src\Shared\Infrastructure\Http\Problem\ProblemType`, y el título y el detalle de cada uno, en `lang/es/problems.php`.

El campo `instance` repite el identificador de la solicitud como `urn:uuid:<id>`, el mismo que viaja en el encabezado `X-Request-Id` (#45).

## Contrato de la API

Scramble genera el contrato OpenAPI 3.1 desde el código, y el contrato se versiona en `archivum-api-hexagonal/openapi/v1.json`. Tras cambiar un endpoint hay que regenerarlo con `composer openapi` (#50). `archivum-web` genera sus tipos desde ese archivo con `npm run api:types` y `npm run api:check` falla si quedaron desactualizados (#124).

## Rutas

`routes/api.php` incluye el archivo de rutas de cada módulo. Todas se sirven bajo `/api/v1`, prefijo que se configura una sola vez en `bootstrap/app.php`. Un cambio incompatible se publica como `/api/v2`. La ruta de salud, `/up`, queda fuera del prefijo.

| Grupo | Rutas | Quién |
|---|---|---|
| Público | `/api/v1/tiendas/{tienda}/...` | Cualquiera; la tienda sale de la URL |
| Cuenta y sesión | `/api/v1/cuentas`, `/api/v1/sesiones` y `/api/v1/cuenta/...` | El cliente, solo sobre sus propios recursos |
| Panel | `/api/v1/panel/...` | El dueño y el colaborador, con `auth:sanctum`; la tienda sale de la sesión |
| Avisos de pago | `/api/v1/webhooks/onvo` y `/api/v1/webhooks/paypal` | La pasarela, con el origen verificado; la tienda sale del pago que nombra el aviso |

La lista inicial de endpoints está en la sección 8 de la [especificación](especificacion.md#8-api-inicial).

## Pruebas

Las pruebas se escriben con Pest ([ADR 0009](adr/0009-pest-instead-of-phpunit.md)):

| Carpeta | Alcance |
|---|---|
| `tests/Unit` | Dominio y casos de uso, sin arrancar el framework. |
| `tests/Feature` | Endpoints y adaptadores contra PostgreSQL, con el rol de la aplicación. `tests/Feature/Tenancy` reúne las pruebas de aislamiento entre tiendas. |
| `tests/Architecture` | Regla de dependencias entre capas y entre módulos, y la forma de los casos de uso. |

Los caminos críticos tienen prueba propia: la doble reserva de una cita, los últimos ml de una botella, el canje simultáneo de un paquete, el webhook repetido y el webhook con firma inválida ([ADR 0010](adr/0010-concurrency-guaranteed-in-postgresql.md)).

En M10, `ConcurrentMlReservationTest` usa dos procesos y conexiones reales con datos confirmados fuera de la transacción de prueba. `BottleSchemaTest` comprueba restricciones, RLS e historial inmutable con el rol de la aplicación. Las pruebas de pedidos también ejercitan la reserva por HTTP. Los adaptadores de pagos y el de la IA usan `Http::fake`; se impiden las solicitudes externas sin fake. Las pruebas que solo necesitan una respuesta de la IA usan `FakeAiRecommender`, que devuelve una salida fija, falla, agota el tiempo sin esperar 10 s o devuelve datos que el catálogo no admite. Las pruebas de concurrencia y durabilidad del pago verifican que una respuesta perdida no permita repetir el cobro ni aplicar dos veces el resultado.

## Base de datos y decisiones

Los roles, las migraciones y las convenciones del esquema están en [database.md](database.md); el modelo de datos, en `data-model.md` (#35). Las convenciones de idioma y de nombres están en [ubiquitous-language.md](ubiquitous-language.md). El motivo de cada decisión de arquitectura está en [adr/](adr/) y los patrones usados, en [patterns.md](patterns.md).

Un ADR aceptado no se edita: si la decisión cambia, un ADR nuevo la reemplaza y el anterior se marca como sustituido. Los ADR se numeran en orden y su archivo lleva un nombre en inglés.
