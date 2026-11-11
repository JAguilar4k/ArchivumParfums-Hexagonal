# Lenguaje ubicuo y convenciones de nombres

La misma palabra significa lo mismo en la conversación con el dueño o el cliente, en el contrato de la API, en el código y en la base de datos. Este documento relaciona cada término del negocio con su nombre en cada lugar.

Los issues de cada módulo usan estas tablas en lugar de redefinirlas. Un término o un estado nuevo se agrega aquí en el mismo pull request que lo introduce.

## Convención de idioma

| Dónde | Idioma | Ejemplo |
|---|---|---|
| Clases, métodos, variables y comentarios | Inglés | `DecantBottle`, `remainingMl` |
| Tablas y columnas | Inglés, `snake_case` | `decant_bottles.remaining_ml` |
| Rutas de la API | Español, en plural | `/api/v1/tiendas/{tienda}/perfumes` |
| Atributos JSON | Español, `snake_case` | `ml_restantes` |
| Mensajes al usuario | Español, desde `lang/es` | "Ese horario ya no está disponible." |

Las rutas y los atributos JSON son lo que ve quien consume la API, por eso usan el vocabulario del negocio. La traducción entre los dos idiomas ocurre en un solo lugar: los Form Requests de entrada y los API Resources de salida.

## Glosario

| Negocio | Código | Base de datos | API |
|---|---|---|---|
| Tienda | `Business` | `businesses` | `tiendas` |
| Nombre | `BusinessName`, `UserName` | `name` | `nombre` |
| Slug | `BusinessSlug` | `businesses.slug` | `slug`, `{tienda}` en las rutas públicas |
| Zona horaria | `BusinessTimezone` | `businesses.timezone` | `zona_horaria` |
| Lugar de la cita | `AppointmentPlace` | `businesses.location_text` | `lugar_cita` |
| Ajustes de la tienda | `BusinessSettings` | `businesses.settings` | `ajustes` |
| Margen por botella | `bottleMarginColones` | `settings.bottle_margin_colones` | `margen_botella` |
| Tamaños de decant | `decantSizesMl` | `settings.decant_sizes_ml` | `tamanos_decant_ml` |
| Anticipación mínima | `minimumNoticeHours` | `settings.minimum_notice_hours` | `anticipacion_minima_horas` |
| Horizonte de reserva | `bookingHorizonDays` | `settings.booking_horizon_days` | `horizonte_reserva_dias` |
| Plazo para cambiar la cita | `appointmentChangeCutoffHours` | `settings.appointment_change_cutoff_hours` | `plazo_cambio_cita_horas` |
| Apartado de la lista de espera | `waitlistHoldHours` | `settings.waitlist_hold_hours` | `apartado_lista_espera_horas` |
| Vencimiento sin pago | `unpaidExpiryMinutes` | `settings.unpaid_expiry_minutes` | `vencimiento_sin_pago_minutos` |
| Recordatorios | `reminderHours` | `settings.reminder_hours` | `recordatorios_horas` |
| Pasarela por defecto | `defaultGateway` | `settings.default_gateway` | `pasarela_por_defecto` |
| Tipo de cambio | `usdExchangeRate` | `settings.usd_exchange_rate` | `tipo_cambio_usd` |
| Personal | `Member` | `members` | `personal` |
| Dueño | `Owner` | — | `dueno` |
| Colaborador | `Staff` | — | `colaborador` |
| Cliente | `Customer` | `customers` | `cliente` |
| Visitante | `Visitor` | — | — |
| Cuenta | `User` | `users` | `cuenta` |
| Correo | `Email` | `email` | `correo` |
| Contraseña | `PlainPassword` | `password`, solo el hash | `contrasena` |
| Sesión | `StartSession`, `EndSession` | `personal_access_tokens` | `sesiones` |
| Token de acceso | `StartedSession` | `personal_access_tokens.token`, solo el hash | `token` |
| Dispositivo | `deviceName` | `personal_access_tokens.name` | `dispositivo` |
| Rol | `AccountRole` | `members.role` | `rol` |
| Servicio | `Service` | `services` | `servicios` |
| Horario de la tienda | `BusinessHours` | `business_hours` | `horario` |
| Horario de atención | `StaffSchedule` | `staff_schedules` | `horario` (bajo `personal/{persona}`) |
| Bloqueo | `ScheduleBlock` | `schedule_blocks` | `bloqueos` |
| Disponibilidad | `Availability` | — | `disponibilidad` |
| Cita de prueba | `Appointment` | `appointments` | `citas` |
| Lista de espera | `WaitlistEntry` | `waitlist_entries` | `lista-de-espera` |
| Perfume | `Perfume` | `perfumes` | `perfumes` |
| Presentación | `PerfumeVariant` | `perfume_variants` | `presentaciones` |
| Botella para decants | `DecantBottle` | `decant_bottles` | `botellas` |
| Movimiento de botella | `DecantMovement` | `decant_movements` | `movimientos` |
| Capacidad de la botella | `totalMl` | `decant_bottles.total_ml` | `ml_totales` |
| Ml restantes | `remainingMl` | `decant_bottles.remaining_ml` | `ml_restantes` |
| Ml reservados | `reservedMl` | `decant_bottles.reserved_ml` | `ml_reservados` |
| Ml disponibles | `availableMl()` | Restantes menos reservados; no hay columna | `ml_disponibles` |
| Precio del decant | `DecantPrice` | Se congela en `order_lines.unit_price` al vender | `precio` en la vitrina; `precio_unitario` en el pedido |
| Motivo del ajuste | `reason` | `decant_movements.reason` | `motivo` |
| Autor del movimiento | `userId` | `decant_movements.user_id`, cuenta global | `hecho_por`, nombre o nulo si es automático |
| Pedido | `Order` | `orders` | `pedidos` |
| Línea del pedido | `OrderLine` | `order_lines` | `lineas` |
| Precio congelado | `unitPrice` | `order_lines.unit_price` | `precio_unitario` |
| Porcentaje del depósito | `depositPercent` | `orders.deposit_percent`, congelado al crear | Se aplica en `por_cobrar_ahora`; no se recibe como importe del navegador |
| Cobro inicial | `dueNow` | `orders.due_now` | `por_cobrar_ahora`, descontando lo ya pagado mientras sigue pendiente |
| Importe pagado | `paid` | `orders.paid` | `pagado` |
| Saldo del pedido | `balance` | `orders.balance` | `saldo` |
| Pago | `Payment` | `payments` | `pagos` |
| Recurso que se cobra | `Payable` | Actualmente `payments.order_id` con llave foránea compuesta | El pedido `{pedido}` de la ruta |
| Propósito del pago | `PaymentPurpose` | `payments.purpose` | `proposito`: `deposito`, `saldo` o `total` |
| Medio de pago | `PaymentMethod` | `payments.method` | `medio`: `tarjeta` o `sinpe_movil`; nulo en un pago en la entrega |
| Pago en la entrega | `Payment::recordManually()`, pasarela `manual` | `payments` con `gateway = 'manual'`, ya `succeeded` y con propósito `balance` | `POST /api/v1/panel/pedidos/{pedido}/pagos-manuales`; `pasarela`: `manual` |
| Registro del pago en la entrega | `ManualRecord` | `payments.recorded_by`, `manual_method`, `manual_reference` y `created_at` | `registrado_por` (`id` y `nombre`), `metodo`, `referencia` y `registrado_en` |
| Medio del pago en la entrega | `ManualPaymentMethod` | `payments.manual_method`: `sinpe` o `cash` | `metodo`: `sinpe` o `efectivo` |
| Principal del pago | `amount` | `payments.amount`, colones enteros | `monto` |
| Moneda del cobro externo | `chargedCurrency` | `payments.charged_currency` | `moneda_cobrada`: `CRC` o `USD` |
| Importe del cobro externo | `chargedAmount` | `payments.charged_amount` | `monto_cobrado`: colones enteros en CRC; centavos en USD |
| Sesión u orden de checkout | `checkoutReference` | `payments.checkout_reference` | Su dirección se entrega como `url_pago`; la referencia no acredita el cobro |
| Intención ONVO o captura PayPal | `captureReference` | `payments.capture_reference` | No se acepta del navegador como confirmación |
| Resultado externo pendiente de consultar | `communicationError` | Mantiene el pago `pending` | `consulta_pendiente: true`; no es un estado financiero nuevo |
| Reembolso | `Refund` | `refunds` | `reembolsos` |
| Intento de reembolso enviado | `attempted` | `refunds.attempted_at` | La devolución sigue `pendiente` si falta una respuesta verificable |
| Importe pedido a la pasarela | `chargedAmount` de `Refund` | `refunds.charged_amount`: colones enteros con ONVO, centavos con PayPal | Se fija al enviar la devolución y se compara con la respuesta y el aviso de la pasarela |
| Envío del reembolso en la cola | `RequestRefundJob`, `RequestRefund` | La fila del reembolso y su trabajo en `jobs` | Un trabajo por reembolso; uno que ya salió solo se vuelve a pedir a PayPal, con el mismo `PayPal-Request-Id`, mientras no tenga respuesta |
| Reembolso sin respuesta de la pasarela | `awaitsAnswer` de `OrderRefund` | Reembolso `pending` con `refunds.attempted_at` y sin `provider_reference` | `consulta_pendiente: true` en la lista del dueño; con ONVO, o con PayPal después de los reintentos, lo revisa una persona en la pasarela |
| Conciliación de los reembolsos pendientes | `ReconcileRefund`, `PendingRefunds` | Los reembolsos `pending` con referencia del proveedor que ya esperaron su confirmación | La misma corrida de `archivum:reconcile-payments` |
| Principal reembolsado | `refundedAmount` | `payments.refunded_amount`, colones enteros | Se refleja en el estado del pago y en `pagado` del pedido después de confirmarse |
| Aviso de la pasarela (evento de webhook) | `PaymentEvent` | `webhook_events`, global, `pending` hasta procesarse y luego `processed` o `failed` con su error | `POST /api/v1/webhooks/onvo` y `/paypal`; responde `evento` y `estado`: `recibido` o `repetido` |
| Procesamiento del aviso en la cola | `ProcessPaymentEventJob`, `ProcessPaymentEvent` | La fila del aviso en `webhook_events` y su trabajo en `jobs` | — |
| Conciliación de los pagos pendientes | `ReconcilePayment`, `PendingPayments` | Los pagos `pending` con referencia del proveedor que ya esperaron su aviso | `php artisan archivum:reconcile-payments`, cada cinco minutos |
| Paquete de decants (oferta) | `DecantPackPlan` | `decant_pack_plans` | `paquetes` |
| Paquete comprado | `DecantPack` | `decant_packs` | `paquetes-comprados` |
| Compra de un paquete | `PurchaseDecantPack`, `PurchasedPack` | Una fila de `decant_packs` y su pago en `payments.decant_pack_id` | `POST /api/v1/tiendas/{tienda}/paquetes/{paquete}/compra` |
| Clave de idempotencia de la compra | `IdempotencyKey` | `decant_packs.idempotency_key`, única por cliente y tienda | encabezado `Idempotency-Key`, opcional |
| Canje de un decant del paquete | `RedeemPackDecant`, `RecordPackRedemption`, `DecantPackRedemption` | `decant_pack_redemptions` y `order_lines.decant_pack_id` | `paquete_id` de una línea de decant del pedido; `canjeado` en los movimientos de la botella |
| Saldo del paquete | `DecantPackBalance`, `DecantPack::remainingDecants` | `decant_packs.remaining_decants`, que descuenta un `UPDATE` condicional | `decants_restantes` |
| Devolución de un decant al paquete | `ReturnPackDecant`, `DecantPack::returnDecant` | Borra la fila de `decant_pack_redemptions` y suma el decant a `remaining_decants` | Al cancelar el pedido (`POST /api/v1/cuenta/pedidos/{pedido}/cancelacion`) o cuando vence sin pago (#158) |
| Estado del paquete en un momento | `DecantPack::statusAt` y la misma expresión en `PostgresDecantPackReader` | Un paquete `pending_payment` o `active` cuyo `expires_at` ya pasó se lee `expired`, aunque la cola todavía no lo haya cambiado | `estado` de las consultas de paquetes |
| Mis paquetes | `ListMyDecantPacks`, `PurchasedPackView` | Los `decant_packs` de la cuenta en cada tienda donde es cliente, con su oferta y su tienda | `GET /api/v1/cuenta/paquetes-comprados` |
| Paquetes vendidos | `ListSoldDecantPacks`, `SoldPackView`, `SoldPackPage` | Los `decant_packs` de la tienda, con el nombre del cliente y su oferta | `GET /api/v1/panel/paquetes-vendidos`, con `estado`, `pagina` y `por_pagina` |
| Proveedor | `Supplier` | `suppliers` | `proveedores` |
| Costo del proveedor | `SupplierPrice` | `supplier_prices` | `costos` |
| Importación del catálogo | `CatalogImport` | `catalog_imports` | `importaciones` |
| Orden de compra | `PurchaseOrder` | `purchase_orders` | `ordenes-de-compra` |
| Línea de la orden de compra | `PurchaseOrderLine` | `purchase_order_lines` | `lineas` |
| Recomendación | `Recommendation` | — | `recomendaciones` |
| Catálogo del recomendador | `RecommendableCatalog`, `CatalogPerfume` | — | No viaja: es la vitrina de la tienda que recibe la IA y contra la que se valida su sugerencia |
| Preferencias del cliente | `CustomerPreferences` | — | `preferencias` |
| Perfume sugerido | `SuggestedPerfume` | — | `perfumes`, cada uno con su `razon` |
| Origen de la recomendación | `RecommendationOrigin` | — | `origen` |
| Validación de la sugerencia | `SuggestionValidator`, `SuggestionValidation` | — | No viaja: decide qué parte de la salida de la IA se usa |
| Causa de descarte | `DiscardCause` | — | No viaja: el log cuenta los descartes por causa |
| Máscara de datos personales | `PersonalDataMask` | — | No viaja: cambia los correos y teléfonos del texto por `[correo]` y `[teléfono]` antes de que llegue a un recomendador |
| Recomendador por reglas | `RuleBasedRecommender` | — | Su respuesta lleva `origen: "reglas"` |
| Coincidencia con el texto | `RuleMatch` | — | No viaja: da el puntaje y la razón del perfume |

El dueño y el colaborador son los dos roles del personal: en la base son los valores `owner` y `staff` de `members.role`. Una cuenta que no está en el personal de ninguna tienda es cliente. En la API, `rol` vale `dueno`, `colaborador` o `cliente`.

Los ajustes de la tienda son claves de `businesses.settings`: la base guarda solo las que difieren de los valores por defecto de la [sección 10 de la especificación](especificacion.md#10-valores-por-defecto).

Los ml se manejan como enteros. Al registrar una botella, `ml_totales` debe coincidir con la capacidad de la presentación; puede omitirse porque el servidor la obtiene. `ml_restantes` inicial es opcional y no puede superar esa capacidad. Una botella vacía conserva su historial y no admite ajustes. La oferta de decants exige una presentación activa con precio y suficientes ml disponibles.

El depósito usa el porcentaje vigente al crear el pedido y lo congela en `orders.deposit_percent`; el precio de cada línea también queda congelado. Ningún importe de pedido o pago se calcula a partir de un precio recibido del navegador. En Investigación 1 el objeto `Payable` representa un pedido; desde #143 un pago también puede pertenecer a un paquete de decants, con su propia llave foránea compuesta con la tienda, y desde #145 un `Payable` de tipo `decant_pack` cobra completo el precio de la oferta guardada del paquete.

`payments.recorded_by` guarda al dueño que registró un pago en la entrega (#98) y referencia a `members(id, business_id)`, no a `users`: solo un dueño de la tienda registra ese pago. El autor de un movimiento de ml sí es una cuenta global porque puede ser personal, cliente o nulo en una operación automática.

## Estados

El dominio y la base guardan el estado en inglés. La API lo muestra en español: los API Resources lo traducen al responder y los filtros por estado reciben el valor en español. El dominio y la base nunca guardan el nombre en español.

| Entidad | Código y base de datos | API |
|---|---|---|
| Cita | `held` | `apartada` |
| | `confirmed` | `confirmada` |
| | `cancelled` | `cancelada` |
| | `completed` | `atendida` |
| | `no_show` | `no_asistio` |
| | `expired` | `vencida` |
| Pedido | `pending_payment` | `pendiente_de_pago` |
| | `confirmed` | `confirmado` |
| | `ordered` | `encargado` |
| | `ready` | `listo` |
| | `delivered` | `entregado` |
| | `cancelled` | `cancelado` |
| | `expired` | `vencido` |
| Línea del pedido (botella) | `pending` | `pendiente` |
| | `ordered` | `encargada` |
| | `received` | `recibida` |
| | `unavailable` | `no_disponible` |
| Línea del pedido (decant) | `reserved` | `reservada` |
| | `prepared` | `preparada` |
| | `released` | `liberada` |
| Pago | `pending` | `pendiente` |
| | `succeeded` | `confirmado` |
| | `failed` | `fallido` |
| | `expired` | `vencido` |
| | `refunded` | `reembolsado` |
| | `partially_refunded` | `reembolsado_parcialmente` |
| Reembolso | `pending` | `pendiente` |
| | `succeeded` | `confirmado` |
| | `failed` | `fallido` |
| Paquete comprado | `pending_payment` | `pendiente_de_pago` |
| | `active` | `activo` |
| | `exhausted` | `agotado` |
| | `expired` | `vencido` |
| Entrada de la lista de espera | `waiting` | `en_espera` |
| | `offered` | `ofrecida` |
| | `accepted` | `aceptada` |
| | `expired` | `vencida` |
| | `cancelled` | `cancelada` |
| Orden de compra | `draft` | `borrador` |
| | `sent` | `enviada` |
| | `paid` | `pagada` |
| | `received` | `recibida` |
| | `cancelled` | `cancelada` |
| Botella para decants | `open` | `abierta` |
| | `empty` | `vacia` |
| Movimiento de botella (tipo) | `reservation` | `reserva` |
| | `release` | `liberacion` |
| | `consumption` | `consumo` |
| | `adjustment` | `ajuste` |

- Un pago confirmado viaja como `confirmado`, nunca como `pagado`, para no chocar con el monto `pagado` del pedido.
- Los estados del reembolso no estaban en la sección 6 inicial de la especificación; esta tabla es su definición y `refunds_status_check` ya los protege. Una solicitud pendiente no reduce el importe pagado hasta que el proveedor confirma la devolución.
- El pago se confirma por consulta autenticada del servidor al proveedor, que piden un aviso de origen verificado (#94) o la conciliación de los pagos pendientes (#96); la consulta del cliente solo lee el pago guardado. La vuelta del navegador y una orden PayPal `APPROVED` no equivalen a un pago `succeeded`: debe comprobarse el resultado de la captura. El registro único de cada aviso llega con #95.
- La vigencia de un paquete comprado cuenta desde que se confirma su pago y pasa a `active`, no desde la compra. Un paquete vence sin pago al pasar el plazo de la tienda, o al cumplir su vigencia aunque le queden decants; uno `exhausted` ya no vence.
- Un canje descuenta el decant del paquete al crear el pedido, pero sus ml se reservan y se consumen al preparar el decant, como los de cualquier decant. Un decant devuelto deja `active` el paquete que su canje había agotado, salvo que la vigencia ya terminara: entonces el paquete queda `expired` (R35).
- Una oferta de paquetes no se borra: se desactiva y deja de aparecer en la lista pública de la tienda. Sus ml por decant son uno de los tamaños de decant de la tienda cuando se define o se cambian, y los conserva si la tienda deja de vender ese tamaño.
- Las transiciones permitidas están en la sección 6 de [la especificación](especificacion.md#6-estados). Solo `held` y `confirmed` cuentan para la restricción de traslape de las citas.
- La sección 6 no tiene `expired` → `succeeded` para un pago: #192 la suma solo para un cobro que la pasarela confirma después de que el vencimiento del pedido cerró su pago, para que el pago coincida con la pasarela (R39). Ese cobro se reembolsa completo y el pedido sigue vencido.

Los valores cerrados adicionales se traducen en la frontera HTTP:

| Concepto | Código y base de datos | API |
|---|---|---|
| Tipo de línea | `bottle`, `decant` | `botella`, `decant` |
| Propósito del pago | `deposit`, `balance`, `full` | `deposito`, `saldo`, `total` |
| Medio del pago | `card`, `sinpe_mobile` | `tarjeta`, `sinpe_movil` |
| Origen de la recomendación | `ai`, `rules` | `ia`, `reglas` |

## Valores en la API

| Valor | Forma | Ejemplo |
|---|---|---|
| Dinero | Colones enteros, sin decimales ni punto flotante | `"precio_venta": 45000` |
| Importe externo en CRC | `monto_cobrado` en colones enteros; el adaptador ONVO multiplica por 100 solo para su API | `"moneda_cobrada": "CRC", "monto_cobrado": 5000` |
| Importe externo en USD | `monto_cobrado` en centavos; el adaptador PayPal forma el texto decimal | `"moneda_cobrada": "USD", "monto_cobrado": 2500` representa USD 25,00 |
| Tipo de cambio del pago | Colones por USD, congelado en `payments.exchange_rate`; el agregado lo conserva como texto decimal | El pago guarda su conversión aunque cambie después el ajuste `tipo_cambio_usd` |
| Ml | Enteros; el signo solo se usa en ajustes manuales | `"ml": -2, "motivo": "Conteo físico"` |
| Fechas y horas | ISO 8601 con el desplazamiento de la zona `America/Costa_Rica` | `"inicia_en": "2026-10-21T14:30:00-06:00"` |
| Identificadores | UUID versión 7, en minúscula y con guiones | `"id": "019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6f70"` |

## Convenciones de nombres

- **Sin sufijo de tipo**: las interfaces, los objetos de valor, los DTO y los casos de uso no lo llevan (`AppointmentRepository`, `Price`, `BookAppointment`). Las excepciones terminan en `Exception` (`InvalidValueException`) y los modelos de Eloquent en `Model` (`AppointmentModel`). Las clases del framework en `Infrastructure` conservan el sufijo de Laravel (`AppointmentController`, `AppointmentResource`).
- **Casos de uso con verbo**: una clase por acción, nombrada con lo que hace (`BookAppointment`, `CancelOrder`, `ImportCatalog`).
- **Puertos con el nombre del concepto**: `AppointmentRepository`, `PaymentGateway`, `BusinessContext`. El adaptador lleva el nombre de la tecnología o del proveedor (`EloquentAppointmentRepository`, `OnvoGateway`, `LaravelAiRecommender`).
- **Un solo nombre por concepto**, en todo el proyecto:

| Concepto | Nombre único | Para no usar |
|---|---|---|
| Identidad de la presentación | `PerfumeVariantId` | `VariantId`, `PresentationId` |
| Lugar de la cita, en toda respuesta de la API | `lugar_cita` (en la base, `businesses.location_text`) | `ubicacion`, `direccion`, `lugar` |
| "Noche", en toda tabla (`perfume_tags`, `waitlist_entries`) | `night` | `evening` |
| Principal del pago en colones | `amount` / `monto` | Usar `monto_cobrado` sin atender `moneda_cobrada` |
| Referencia de creación y referencia del cargo efectivo | `checkoutReference` y `captureReference` | Tratar el identificador de checkout como intención ONVO o captura PayPal |
