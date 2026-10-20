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
| Pedido | `Order` | `orders` | `pedidos` |
| Línea del pedido | `OrderLine` | `order_lines` | `lineas` |
| Pago | `Payment` | `payments` | `pagos` |
| Reembolso | `Refund` | `refunds` | `reembolsos` |
| Paquete de decants (oferta) | `DecantPackPlan` | `decant_pack_plans` | `paquetes` |
| Paquete comprado | `DecantPack` | `decant_packs` | `paquetes-comprados` |
| Proveedor | `Supplier` | `suppliers` | `proveedores` |
| Costo del proveedor | `SupplierPrice` | `supplier_prices` | `costos` |
| Importación del catálogo | `CatalogImport` | `catalog_imports` | `importaciones` |
| Orden de compra | `PurchaseOrder` | `purchase_orders` | `ordenes-de-compra` |
| Línea de la orden de compra | `PurchaseOrderLine` | `purchase_order_lines` | `lineas` |
| Recomendación | `Recommendation` | — | `recomendaciones` |

El dueño y el colaborador son los dos roles del personal: en la base son los valores `owner` y `staff` de `members.role`. Una cuenta que no está en el personal de ninguna tienda es cliente. En la API, `rol` vale `dueno`, `colaborador` o `cliente`.

Los ajustes de la tienda son claves de `businesses.settings`: la base guarda solo las que difieren de los valores por defecto de la [sección 10 de la especificación](especificacion.md#10-valores-por-defecto).

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
- Los estados del reembolso no están en la sección 6 de la especificación; esta tabla es su definición, y su `CHECK` sobre `refunds.status` llega con #89.
- Las transiciones permitidas están en la sección 6 de [la especificación](especificacion.md#6-estados). Solo `held` y `confirmed` cuentan para la restricción de traslape de las citas.

## Valores en la API

| Valor | Forma | Ejemplo |
|---|---|---|
| Dinero | Colones enteros, sin decimales ni punto flotante | `"precio_venta": 45000` |
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
