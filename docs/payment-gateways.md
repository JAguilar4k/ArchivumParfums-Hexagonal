# Pasarelas de pago: expediente técnico y evidencia

Issue [#93](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/issues/93) · Investigación aplicada 1 · **Fuentes consultadas el 24/10/2026**, zona America/Costa_Rica.

El [informe comparativo](investigacion-1.md) es el documento de entrega de 1.000 a 1.800 palabras. Este expediente conserva el análisis técnico y las limitaciones que no caben en él. La documentación leída acredita contratos publicados; las ejecuciones sandbox de ONVO del **25/10/2026** y PayPal del **26/10/2026** se registran por separado en la sección 9. No acreditan afiliaciones comerciales, dinero real, retiros bancarios ni tiempos de trabajo medidos del equipo.

## 1. Alcance, fuentes locales y precedencia

Se leyeron `CLAUDE.md`, `docs/plan.md`, `docs/especificacion.md`, el enunciado general local y `Investigación #1.pdf`. El documento específico de la investigación fija para este corte dos adaptadores, consulta de estado desde servidor, reembolso ONVO, pruebas HTTP simuladas e informe de cuatro proveedores. El PDF es referencia del encargo, no una autorización para acciones externas ni para exponer credenciales.

Se consultó en modo de solo lectura el equivalente del repositorio guía, `docs/adr/0009-payments-through-a-port-with-http-direct-to-stripe.md`. De él se conserva el puerto propio, la traducción dentro del adaptador, HTTP directo con timeout y las pruebas que impiden peticiones reales. No se copia la moneda ni se presupone que la idempotencia de Stripe exista en otros proveedores. El clon temporal no tiene remoto; no se escribe en el repositorio guía.

| Diferencia | Tratamiento para Investigación 1 |
| --- | --- |
| El PDF específico exige `docs/investigacion-1.md`; #93 nombra `docs/payment-gateways.md` | Se entregan ambos, con funciones separadas y enlaces mutuos. |
| PDF: consulta de estado, sin webhooks. R38 y #92: confirmación por webhook | La confirmación por consulta es la excepción del corte. #94 y #95 conservan la migración posterior; no se afirma que el navegador confirme el pago. |
| PDF: reembolso ONVO en este corte; #90 remite a #97 de Avance 2 | El requisito del corte se cubre dentro del trabajo existente de pagos, sin crear otro issue. El resto del alcance de #97 permanece pendiente. |
| PDF: medio de pago disponible declarado por cada pasarela | El puerto debe poder representar capacidades; SINPE Móvil no se envía a PayPal. |
| PDF: PHPUnit; proyecto general y ADR 0009 local: Pest | Se usa Pest sobre PHPUnit, con `Http::fake()` y `Http::preventStrayRequests()`. |
| PDF: `feature/pagos` y `v-investigacion1`; plan: ramas por issue y otras etiquetas | La entrega específica prevalece para esa rama y etiqueta. No se crea etiqueta sobre trabajo sin integrar ni se omite revisión de otra persona. |
| PDF: cobrar una reserva; proyecto: las citas no tienen precio y los cobros recaen en pedidos | Se mantiene el recurso pagable del negocio, sin inventar una tarifa de cita. Debe señalarse al docente al presentar la adaptación. |
| #89 contiene estructura de eventos de webhook | Crear estructura no habilita procesamiento de webhooks ni acredita #94/#95. |
| El PDF exige aprobado, rechazado y error de comunicación; no prescribe cerrar sesiones ONVO | Expirar el checkout y verificar la intención antes de habilitar otro intento es una decisión de seguridad de Archivum, para evitar que el checkout anterior todavía pueda cobrar. |

Las decisiones de redondeo USD, momento de pago del saldo y transiciones tras reembolso parcial siguen lo que se acuerde y documente en el issue correspondiente. Este expediente no resuelve reglas de negocio pendientes por su cuenta.

Actualización del 25/10/2026: se confirmaron ml enteros, capacidad de la presentación con remanente inicial configurable, depósito sobre el total de botellas con redondeo superior al colón y conversión USD al centavo más cercano (mitades hacia arriba). También se confirmaron saldo solo con pedido listo, cancelación antes de preparación/encargo con devolución completa, cancelación al quedar sin líneas disponibles y acumulación de reembolsos parciales hasta el total. Un cobro tardío de un pedido cancelado o vencido se registra y se reembolsa sin reactivar el pedido ni su reserva de ml. La especificación y los issues recogen esas decisiones.

En la revisión de código del 25/10 se agregaron dos defensas: `validateCharge()` comprueba la representabilidad del importe dentro del adaptador antes de confirmar el intento local; y un rechazo recuperable de tarjeta ONVO no permite abrir otro checkout hasta cerrar y verificar la sesión anterior y su intención. Si el cierre es incierto se conserva el pago pendiente; si ganó un cobro concurrente, se registra su resultado real. Fuentes técnicas adicionales consultadas el 25/10/2026: [expirar sesión ONVO](https://docs.onvopay.com/api/expirar-una-sesion-de-checkout) y [cancelar intención ONVO](https://docs.onvopay.com/api/cancelar-una-intencion-de-pago). No se equipara `requires_payment_method` a una cancelación remota definitiva.

## 2. Registro de fuentes

Las consultas iniciales tienen fecha **24/10/2026**; las reconsultas del **25/10/2026** se indican en el texto. Las páginas comerciales y técnicas pueden cambiar; prevalece el contrato de la cuenta para costos finales.

| ID | Fuente oficial | Evidencia utilizada |
| --- | --- | --- |
| O1 | [Autenticación ONVO](https://docs.onvopay.com/authentication) | Llaves de prueba al registrar cuenta; llaves de servidor y públicas. |
| O2 | [Precios ONVO](https://www.onvopay.com/pricing) | Tarjetas, SINPE, SINPE Móvil y costo por liquidación. |
| O3 | [Intenciones ONVO](https://docs.onvopay.com/payments/payment-intents) | Flujo y estados, tokenización y referencia del pago. |
| O4 | [Consulta de intención](https://docs.onvopay.com/api/obtener-una-intencion-de-pago) | Consulta autenticada de estado. |
| O5 | [SINPE Móvil ONVO](https://docs.onvopay.com/payments/sinpe-mobile) | CRC y asociación por identificación y monto. |
| O6 | [Pruebas ONVO](https://docs.onvopay.com/payments/testing) | Tarjetas ficticias y simulaciones SINPE. |
| O7 | [Reembolsos ONVO](https://docs.onvopay.com/payments/refunds) | Reembolso ligado a intención; identificador para conciliación. |
| O8 | [Webhooks ONVO](https://docs.onvopay.com/webhooks) | Secreto de origen y eventos. |
| O9 | [Marketplaces ONVO](https://docs.onvopay.com/payments/marketplaces) | Cuentas conectadas y enrutamiento al vendedor. |
| O10 | [ONVO para servicios](https://www.onvopay.com/business-industry-services) | Liquidación bancaria local. |
| O11 | [OpenAPI ONVO](https://docs.onvopay.com/openapi.yaml) | Esquemas separados de intención, sesión y reembolso; reconsultado el 25/10/2026. |
| P1 | [Sandbox PayPal](https://developer.paypal.com/sandbox-testing/overview) | Cuentas ficticias y alta del desarrollador. |
| P2 | [Orders v2](https://developer.paypal.com/api/orders/v2) | Crear, consultar y capturar órdenes. |
| P3 | [Monedas PayPal](https://developer.paypal.com/api/codes/currency) | USD admitido; CRC ausente. |
| P4 | [Tarifas PayPal CR](https://www.paypal.com/cr/business/paypal-business-fees) | Cobro, retiro bancario y conversión. Actualización publicada: 28/09/2026. |
| P5 | [Webhooks PayPal](https://developer.paypal.com/api/rest/webhooks/rest/) | Verificación local y por API. |
| P6 | [Payments v2](https://developer.paypal.com/docs/api/payments/v2/) | Reembolso sobre una captura. |
| P7 | [Idempotencia PayPal](https://developer.paypal.com/api/rest/reference/idempotency/) | `PayPal-Request-Id`, sujeto al endpoint y período de retención. |
| P8 | [Plataformas PayPal](https://developer.paypal.com/platforms/overview) | Solución para múltiples vendedores, distinta del cobro estándar. |
| T1 | [Tarifas Tilopay](https://tilopay.com/tarifas) | Precio de tarjeta y SINPE Móvil; ausencia de mensualidad y anualidad. |
| T2 | [Developers Tilopay](https://tilopay.com/developers) | Registro individual para credenciales sandbox. |
| T3 | [SDK Tilopay](https://tilopay.com/developers/sdk) | SINPE Móvil y consulta de resultado desde servidor. |
| T4 | [Webhooks Tilopay](https://tilopay.com/developers/api/webhooks) | `orderHash`, especificación a solicitud y reintentos. |
| T5 | [Modificaciones Tilopay](https://tilopay.com/developers/api/procesos-operativos/process-modification) | Captura, reembolso y reverso. |
| T6 | [Comparación comercial Tilopay](https://tilopay.com/blog/stripe-o-paypal) | Depósito a banco local. Se usa solo para la oferta propia de Tilopay. |
| G1 | [Tarifario GreenPay](https://greenpay.me/faq/cuales-son-los-costos-de-los-servicios-de-greenpay-2/) | Alta y cuota mensual de la pasarela, alcance y negociación. |
| G2 | [Cuenta sandbox GreenPay V1](https://support.greenpay.me/portal/es/kb/articles/sobre-la-cuenta-de-sandbox) | Datos solicitados y envío de credenciales. |
| G3 | [Preguntas GreenPay](https://greenpay.me/preguntas-frecuentes/) | Liquidación, banco, devolución y oferta marketplace. |
| G4 | [SINPE GreenPay](https://greenpay.me/soluciones/transferencia-sinpe/) | Transferencias IBAN y cotización por caso. |
| G5 | [Webhook GreenPay V1](https://support.greenpay.me/portal/es/kb/articles/respuesta-d-e) | Ejemplo con `_signature`. |
| G6 | [Webhook GreenPay V2](https://support.greenpay.me/portal/es/kb/articles/webhook-v2) | Configuración y headers de la notificación. |
| G7 | [Respuestas API V2](https://support.greenpay.me/portal/es/kb/articles/api-pagos-v2-webhook) | Payloads de autorización y reversión. |
| G8 | [Callback GreenPay](https://support.greenpay.me/portal/es/kb/articles/payment-process-with-form-28-3-2020) | Redirección Base64 y distinción de webhook. |

ONVO comercial devolvió 403 en la apertura directa; el buscador devolvió el contenido indexado de la misma página oficial y su [versión inglesa](https://onvopay.com/en/pricing). Parte del soporte GreenPay también presenta bloqueos intermitentes; se recuperó contenido indexado de sus páginas oficiales. Estas limitaciones no permiten asegurar que se haya leído una cotización contractual de cada comercio.

## 3. Credenciales y capacidades

| Tema | ONVO | PayPal | Tilopay | GreenPay |
| --- | --- | --- | --- | --- |
| Alta de prueba | Cuenta y Dashboard, llaves automáticas [O1] | Developer, app y cuentas personal/business ficticias [P1] | Registro individual developer [T2] | Cuenta sandbox autogestionada [G2] |
| Tiempo comprobado por el equipo | No medido | No medido | No medido | No medido |
| SLA sandbox publicado localizado | No | No | No | No |
| Moneda útil para Archivum | CRC | USD, convertido desde CRC [P3] | CRC/USD según configuración [T3] | CRC/USD [G3] |
| SINPE Móvil en el flujo publicado | Sí [O5] | No en el producto estudiado | Sí [T3] | No confirmado; [G4] describe IBAN |
| Devolución documentada | API de reembolso [O7] | API sobre captura [P6] | `processModification` [T5] | Trámite de devolución descrito por soporte [G3] |

No se confunden plazos de afiliación comercial, transferencia bancaria o devolución con tiempo de obtener credenciales sandbox. El 25/10/2026 se dispuso de credenciales ONVO y PayPal y se autenticaron correctamente; no se midió cuánto tardó obtenerlas ni se deduce un SLA de ese hecho. Los trámites de Tilopay y GreenPay no se ejecutaron. No se solicitaron cotizaciones ni se contactó soporte como parte de esta investigación.

## 4. Cálculo de costos y límites

### Variables y criterio de comparación

```text
n = 100 pagos mensuales
a = CRC 15.000 por pago
V = n * a = CRC 1.500.000
r = CRC 500 por USD (supuesto ilustrativo, no cotización)
k = 1 liquidación mensual
b = comisión adquirente GreenPay expresada como proporción (desconocida)
f = cargo fijo adquirente por operación, en CRC (desconocido)
L_T = costo mensual de liquidación Tilopay en CRC (no cotizado)
```

La comparación base usa 100 pagos exitosos; no atribuye costo de cero a comisiones desconocidas. No modela contracargos, devoluciones, reservas bancarias ni retenciones tributarias. Una retención acreditable no debe confundirse automáticamente con una comisión de la pasarela. Las liquidaciones frecuentes aumentan el costo cuando se cobra por transferencia.

### Resultados reproducibles

| Canal | Fórmula en CRC | Resultado con r=500 y k=1 |
| --- | --- | --- |
| ONVO tarjeta [O2] | `0,039V + n*0,35r + 3kr` | 77.500 |
| ONVO Móvil [O2] | `0,015V + 3kr` | 24.000 |
| Tilopay tarjeta [T1] | `0,0425V + n*0,35r + L_T` | `81.250 + L_T` |
| Tilopay Móvil [T1] | `0,02V + n*0,35r + L_T` | `47.500 + L_T` |
| GreenPay recurrente [G1, G3] | `30r + IVA_mensual + bV + nf + 2kr` si liquida a otro banco | `16.000 + IVA_mensual + 1.500.000b + 100f` |
| Alta GreenPay [G1] | `150r + impuesto_alta_si_corresponde` | `75.000 + impuesto_alta_no_confirmado` |

El subtotal de GreenPay para el primer mes es `91.000 + IVA_mensual + impuesto_alta + 1.500.000b + 100f` bajo esa liquidación interbancaria. Las 100 operaciones caben dentro de las 200 incluidas para el servicio, sujeto a restricciones del contrato; el tarifario no permite convertir eso en comisión bancaria cero. El IVA de USD 30 está publicado; no se aplica un porcentaje tributario supuesto al alta o a todos los proveedores como si estuviera verificado. Para cuantificar IVA se necesita confirmar el tratamiento aplicable en la factura comercial.

PayPal [P4] necesita un cálculo separado porque cobra USD:

```text
Cada pago: 15.000 / 500 = USD 30,00
Cobrado al mes: USD 3.000,00
Comisión por pago: 30*0,054 + 0,30 = USD 1,92
Comisión de los 100 pagos: USD 192,00 = CRC 96.000
Saldo tras comisión: USD 2.808,00
Retiro a banco local en USD: max(1% de 2.808, USD 10) = USD 28,08
Cobro + retiro USD: USD 220,08 = CRC 110.040
```

Si se elige retiro en CRC, la comisión publicada por retiro es CRC 625: el subtotal de cobro y retiro es CRC 96.625 **más el efecto del tipo de cambio**. Para conversión de fondos PayPal publica un margen de 3,5 % sobre el tipo base; no se transforma en un recargo independiente de 3,5 % del volumen bruto. Falta el tipo ofrecido y pueden existir cargos del banco. Tampoco se usa la tarifa estadounidense de PayPal para una cuenta CR.

Los porcentajes y fijos no incluyen una interpretación de impuestos no confirmada. Los acuerdos comerciales pueden alterar estas cifras; solicitar una cotización contractual es una diligencia futura, no una gestión externa ya realizada.

### Sensibilidad y discrepancias

- Con 22 liquidaciones ONVO de USD 3, su costo bancario pasa de CRC 1.500 a CRC 33.000 en el mismo supuesto cambiario; agrupar liquidaciones debe equilibrarse con liquidez.
- Si una reserva genera depósito y saldo en pagos distintos, no pueden mantenerse 100 cargos fijos por 100 reservas. Con 200 pagos ONVO o Tilopay, el componente fijo sería CRC 35.000, a igual volumen cobrado.
- [Un artículo de KMSOFT](https://kmsoftcr.com/blog/pasarela-pago-costa-rica-comisiones/) presenta ONVO con USD 0,25 fijo. [O2] publica USD 0,35. Para el cálculo se usa la fuente oficial; ambas se consultaron el 24/10/2026. No se usa ese blog como evidencia de contratos técnicos.
- [O9] contiene porcentajes numéricos en un ejemplo marketplace. Son un ejemplo del flujo, no sustituyen el tarifario comercial de [O2].

## 5. Autenticidad de notificaciones

La comparación distingue autenticación del emisor, integridad del contenido, repetición y confirmación del estado. Un evento auténtico puede llegar duplicado o fuera de orden; el estado final y los importes siguen requiriendo validación local.

| Proveedor | Evidencia pública | Evaluación y tratamiento |
| --- | --- | --- |
| PayPal [P5] | Firma con certificado; identificador/hora, webhook registrado y checksum del cuerpo crudo. Alternativa de API verificadora. | Mayor especificación pública verificable entre los cuatro. Validar certificado y origen de la URL al implementar verificación local; deduplicar y comprobar datos del pago. |
| ONVO [O8] | Secreto estático en header `X-Webhook-Secret`. | No es una firma documentada del cuerpo. Custodiar y comparar el secreto, exigir HTTPS, consultar el recurso cuando corresponda y deduplicar sin inventar un ID de entrega no documentado. |
| Tilopay [T4] | `orderHash`; algoritmo entregado a solicitud del comercio. | No se evaluó el algoritmo. Sin él, la guía exige confirmar con API antes de efectos irreversibles. No afirmar que un hash es automáticamente una firma segura o insegura. |
| GreenPay [G5–G8] | V1 muestra `_signature`. Documentación V2 consultada no expone un procedimiento de autenticidad; el callback usa Base64. | No intercambiar versiones ni concluir que no existe firma. Base64 solo codifica. Antes de operar, obtener especificación verificable o confirmar estado por API autenticada. |

Conclusión de seguridad: PayPal ofrece el mecanismo públicamente más verificable; ONVO documenta un secreto compartido sencillo; Tilopay requiere documentación adicional y GreenPay necesita aclarar versión y procedimiento. Esto no constituye prueba de penetración ni ranking global de seguridad. No se verificó ningún webhook real, y su implementación no forma parte de esta entrega.

## 6. Contratos de integración para los adaptadores

### ONVO

La API base es `https://api.onvopay.com/v1`; las llaves de prueba se reconocen por `onvo_test_`. El adaptador recibe CRC enteros y convierte a unidad menor mediante multiplicación por 100; CRC 15.000 se envían como `1500000`. [O1, O3]

| Operación | API documentada | Interpretación de Archivum |
| --- | --- | --- |
| Crear intención | `POST /payment-intents` | Persistir referencia; crear no equivale a confirmar. |
| Confirmar | `POST /payment-intents/{id}/confirm` | Usa un método tokenizado; puede requerir acción adicional. |
| Consultar | `GET /payment-intents/{id}` | Leer desde servidor y traducir el estado. |
| Reembolsar | `POST /refunds` | Referencia al pago original y monto en unidad menor [O7]. |
| Consultar reembolso | `GET /refunds/{id}` | Conservar el resultado para conciliación [O7]. |

#### Estados de intención, sesión y reembolso

Los estados pertenecen al recurso consultado; no se intercambia el enum de `Refund` con el de `PaymentIntent`. El OpenAPI reconsultado el 25/10/2026 incluye `failed` en **ambos**, con significados diferentes. La tabla de estados comunes de la guía de intenciones es un resumen, no el enum completo. [O3, O7, O11]

| Recurso y estado remoto | Interpretación y tratamiento |
| --- | --- |
| `PaymentIntent.status = succeeded` | Cobro exitoso, después de validar identidad, moneda e importe. Si aparece durante el cierre, prevalece sobre el rechazo observado antes. |
| `PaymentIntent.status = failed` | Fallo definitivo documentado. Tiene tratamiento explícito de pago fallido; no debe caer en `unknownStatus()` como si fuera un valor no reconocido. Antes de habilitar otro intento se verifica también el cierre del checkout. |
| `PaymentIntent.status = requires_payment_method` | Puede ser inicial o volver aquí tras un rechazo recuperable. Sin evidencia de rechazo permanece pendiente; con error se cierra el checkout y se cancela la intención reutilizable antes de dar por terminado el pago local. |
| `PaymentIntent.status = requires_action` o `processing` | Sigue pendiente; no acredita cobro ni autoriza otro intento a ciegas. |
| `CheckoutSession.status = expired` | Sesión cerrada. Por sí sola no prueba que la intención no se haya cobrado; se consulta también la intención. |
| `Refund.status = pending`, `succeeded` o `failed` | Resultado de la devolución, no del cobro original. Un reembolso fallido no demuestra que el pago original fallara ni que se haya devuelto dinero. |

`lastPaymentError` está descrito en el esquema de intención. `confirmationAttempts` aparece en esquemas y ejemplos de webhooks, pero no en el esquema de la respuesta GET de intención: es un indicio opcional, no un campo cuya presencia se deba exigir para detectar rechazo. Tampoco el nombre del evento `payment-intent.failed` equivale a `PaymentIntent.status = failed`; el ejemplo oficial del evento usa `requires_payment_method`. [O8, O11]

El cierre es una política de seguridad de Archivum, **no una exigencia expresa del PDF**: impide abrir otro intento mientras el checkout anterior siga siendo pagable. `closeCheckout()` expira la sesión, solicita cancelar la intención cuando corresponde y vuelve a consultar ambas. El cierre se acepta con sesión `expired` e intención terminal `failed` o `canceled`; no se exige convertir un fallo definitivo en una cancelación que ONVO no promete. Si la reconsulta informa `succeeded`, se conserva ese éxito; si no permite comprobar un cierre seguro, el pago sigue pendiente de conciliación. No se presupone que expirar la sesión cancele automáticamente su intención. Los endpoints de [expiración](https://docs.onvopay.com/api/expirar-una-sesion-de-checkout) y [cancelación](https://docs.onvopay.com/api/cancelar-una-intencion-de-pago) fueron reconsultados el 25/10/2026.

Los estados no reconocidos conservan la incertidumbre y se registran sin secretos; `failed`, al estar documentado y tener traducción explícita, no se clasifica como desconocido. El recorrido externo de la sección 9 acredita un pago local `fallido` tras rechazo de tarjeta; no demuestra que se hayan forzado todas las variantes remotas de esta tabla ni identifica por sí solo cuál fue el estado intermedio de la intención.

SINPE utiliza `mobile_number`; no debe mandarse ese medio a una pasarela sin la capacidad. La validación real de identificación y monto exacto de [O5] no debe reemplazarse con una suposición basada en el simulador parcial de [O6].

#### Aclaraciones de la revisión: `mode` y SINPE Móvil

El [OpenAPI oficial](https://docs.onvopay.com/openapi.yaml), vuelto a consultar el 25/10/2026, vincula tanto el POST de `checkout/sessions/one-time-link` como el GET de `checkout/sessions/{id}` con `CheckoutSession`. Ese esquema **sí describe `mode` en la raíz**, con valores `test` y `live`; no tiene una lista `required`. La referencia HTML resumida omite detalles que aparecen en el YAML. Esto no demuestra que una respuesta real omita el campo, pero tampoco garantiza formalmente su presencia.

La política del adaptador distingue la navegación del efecto financiero: el checkout puede omitir `mode`, mientras que un valor presente distinto de `test`, incluido `null`, se rechaza. Las claves continúan limitadas al prefijo de prueba, con HTTPS y host fijos. Para la intención que acredita dinero y el reembolso que lo descuenta se mantiene la exigencia conservadora de `mode: "test"`, además de ID, relación, moneda, importe y estado coincidentes. Esa exigencia financiera es una decisión de Archivum; los esquemas `PaymentIntent` y `Refund` tampoco marcan sus propiedades como requeridas. Si esas respuestas reales omiten el modo, el sistema conserva la operación pendiente para conciliación. El 25/10/2026 funcionaron creación, consulta y reembolso con la cuenta sandbox configurada; ello confirma compatibilidad en esos escenarios, no todas las variantes posibles del contrato.

La prueba de regresión reproduce el caso de checkout sin `mode`: antes devolvía 202 al crear; ahora entrega el checkout y puede confirmar al consultar una intención de prueba válida. También prueba el cierre de un checkout rechazado y la recuperación de su intención para un reembolso cuando las sesiones omiten el campo.

En la API, el cliente elige `{"medio":"sinpe_movil"}` al iniciar el pago. `PaymentController::store()` lo convierte en `PaymentMethod::SinpeMobile`; `OnvoGateway::supportedMethods()` declara la capacidad y `create()` envía **solo** `paymentMethodTypes: ["mobile_number"]`. Tarjeta envía **solo** `["card"]`. El esquema `CreateOneTimeLink` define ese parámetro como la lista de métodos permitidos. No se envía una lista con ambos para que el comprador cambie el medio elegido. PayPal rechaza SINPE antes del POST externo.

La aplicación de este corte ofrece ese recorrido por API y el checkout hospedado de ONVO; el selector de una interfaz propia sigue dentro del cliente web pendiente. La respuesta local `medio` representa la elección guardada, no una inspección independiente del instrumento final: la intención documenta `paymentMethod.type`, pero su presencia tampoco es obligatoria y el adaptador actual no la usa para verificar el instrumento. El 25/10/2026 se observó el [checkout con SINPE como único medio](evidencia-investigacion-1/onvo-sinpe-unico-medio.png), se completó el simulador y la consulta autenticada de Archivum confirmó ₡7.500 con `medio = "sinpe_movil"`. Ese recorrido combina evidencia visual y HTTP; no se atribuye a la respuesta local una verificación adicional de `paymentMethod.type`. Para repetirlo se eligieron peticiones manuales en Postman Lightweight; la colección y el entorno JSON están preparados, pero no se importaron ni se ejecutaron en Postman nativo.

No se encontró en el contexto completo consultado una garantía de `Idempotency-Key` de ONVO. La idempotencia local debe reservar el intento antes de llamar y conservar su referencia. Si hay timeout al crear y no llega referencia, se requiere conciliación; repetir `POST` a ciegas no queda justificado. No se copia el header de Stripe del repo guía.

### PayPal

Usar únicamente el host sandbox configurado, OAuth servidor y Orders v2. El ciclo es crear orden, obtener aprobación, capturar y consultar el resultado. Una orden `APPROVED` no equivale a una captura `COMPLETED`. La URL de regreso solo orienta al comprador; nunca autoriza el cambio de estado. [P1, P2]

El adaptador transforma CRC a USD con el tipo aprobado para la tienda y conserva ambos montos y el tipo usado. El formato de USD es texto decimal de dos cifras; no viaja un flotante de PHP ni el entero CRC. Ejemplo exclusivamente ilustrativo: CRC 15.000 con tipo 500 da `"30.00"`. R37 y [P3] justifican esa conversión. La regla de redondeo debe quedar aprobada antes de tratar importes no divisibles exactamente.

`PayPal-Request-Id` se conserva por operación idempotente de los endpoints que lo admiten; no equivale a una garantía de almacenamiento ilimitado ni reemplaza el registro local. [P7]

El reembolso previsto se solicita con `POST /v2/payments/captures/{capture_id}/refund`, parcial con monto o total según el contrato de Payments v2. Se conserva el ID de la captura y el de devolución. El PDF permite documentarlo sin implementarlo en este corte; el flujo completo del proyecto corresponde a #97. [P6]

### Política común ante fallos

| Situación | Resultado de negocio esperado |
| --- | --- |
| Aprobación confirmada por API y datos coincidentes | Registrar pago y aplicar confirmación al pedido en la misma transacción. |
| Rechazo explícito | Traducir a resultado fallido, con mensaje español; no convertirlo en 500 genérico. |
| Timeout, desconexión o 5xx | Mantener pendiente de conciliación; no confundir falta de respuesta con rechazo. |
| Doble clic o solicitudes simultáneas | Recuperar el mismo intento pendiente y aplicar unicidad en PostgreSQL. |
| Se pagó fuera y falla la transacción local | La referencia debe permitir consultar y aplicar después, sin cobrar otra vez. |
| Vence el pedido mientras no hay resultado final externo | Resolver la carrera por conciliación y estado persistido; no prometer cancelación del cobro externo solo por vencer localmente. |

## 7. Pago dividido y alcance de #100

ONVO ofrece cuentas conectadas y cobro en nombre de un vendedor con comisión marketplace [O9]. PayPal tiene una oferta para plataformas [P8]. GreenPay publica marketplace y distribución por comercio [G3]; sus transferencias IBAN son otro producto [G4]. No se encontró en las fuentes consultadas una garantía equivalente de Tilopay para repartir el mismo cobro entre asesores.

Nada de esto demuestra que una cuenta sandbox ordinaria pueda repartir automáticamente comisiones a cualquier colaborador. Marketplace distingue vendedores afiliados y operaciones de plataforma; una comisión interna por asesor es una regla del negocio diferente. Para #100 se recomienda registrar la comisión y liquidarla internamente mientras no se prueben la habilitación, elegibilidad, costos y conciliación del proveedor. Es una recomendación técnica, no la activación del extra ni una regla nueva ya adoptada.

## 8. Trazabilidad del trabajo existente

| Issue | Aporte de este expediente | Evidencia de aceptación que debe agregarse |
| --- | --- | --- |
| [#88](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/issues/88) | Puerto, estados propios, capacidades y diferencias por proveedor | Pruebas de dominio y bindings de configuración. |
| [#89](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/issues/89) | Importes, referencia, moneda y trazabilidad de reembolso | Migraciones reversibles, RLS y restricciones probadas con rol aplicación. |
| [#90](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/issues/90) | ONVO, conversión de unidades, medios y simulaciones | Suite con `Http::fake()` y comprobantes ficticios de tarjeta/SINPE. |
| [#91](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/issues/91) | USD, aprobación/captura, idempotencia y reembolso documentado | Conversión aprobada, captura y errores cubiertos; demostración sandbox. |
| [#92](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/issues/92) | Monto servidor, doble clic y confirmación por consulta para este corte | Autorización, propiedad, aislamiento, concurrencia y transacción pedido/pago. |
| [#93](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/issues/93) | Informe comparativo y expediente separados | Revisión de cifras, enlaces desde README y anexos de evidencia del equipo. |
| #94–#97 | Hallazgos para verificación, eventos, conciliación y reembolso posterior | No se declaran completados por publicar esta investigación. |

No se crearon issues nuevos para resolver diferencias del encargo. La extensión del informe y los cálculos se verifican de forma local; la conformidad final depende del código integrado y de las pruebas y la demostración del equipo.

## 9. Evidencia empírica y defensa

Actualización del **25/10/2026**: se ejecutaron los escenarios ONVO de tarjeta aprobada, tarjeta rechazada, SINPE Móvil y reembolso mediante la API local y el checkout en navegador, con credenciales sandbox. No hubo dinero real ni transferencia bancaria SINPE. La evidencia de ejecución se distingue de los ejemplos de documentación y de `Http::fake()`. Los JSON de colección y entorno quedaron listos como apoyo, pero no fueron importados ni ejecutados en Postman nativo; el guion manual se ofrece para Lightweight sin exigir importación ni cuenta Postman. No se hicieron retiros ni se midieron tiempos de integración.

| Verificación | Estado de evidencia en este expediente |
| --- | --- |
| ONVO tarjeta aprobada y rechazada | Ejecutadas el 25/10/2026: confirmación de ₡7.500 y repetición sin duplicación; rechazo con pedido sin importe pagado. |
| ONVO SINPE Móvil | Ejecutado el 25/10/2026: único medio en checkout, simulación y confirmación de ₡7.500 desde servidor. |
| ONVO reembolso del depósito | Ejecutado el 25/10/2026: devolución confirmada por ₡7.500; pago reembolsado y pedido cancelado con pagado cero. |
| PayPal tarjeta, aprobación y captura | Ejecutadas el 26/10/2026: dos consultas confirmadas por ₡7.500, cobro de USD 15,00 y exactamente una captura `COMPLETED` comprobada directamente con el proveedor. |
| Cambio de pasarela solo por configuración | Debe enlazarse a prueba y demostración del código integrado. |
| Pruebas locales de adaptadores | 47 casos, 135 aserciones aprobados el 24/10/2026; comando indicado abajo. |
| CI verde | Debe enlazarse a ejecución identificada de CI; no se infiere del resultado local. |
| Pasarela que llevó más tiempo | No medido; se necesita bitácora real de ambos adaptadores. |

### Resultados externos ONVO del 25/10/2026

La autenticación ONVO se comprobó con HTTP 200 antes del recorrido. Después, la API de Archivum creó los checkouts con HTTP 201 y el navegador permitió completar los escenarios ficticios. Los registros sanitizados revisados fueron `onvo-card-result.json`, `onvo-declined-result.json`, `onvo-sinpe-result.json` y `onvo-refund-result.json`; no se consultaron ni incorporaron archivos privados de credenciales.

| Escenario | Resultado del servidor de Archivum | Evidencia visual |
| --- | --- | --- |
| Visa `4242424242424242` | Dos GET del mismo pago devolvieron HTTP 200, `confirmado`, CRC 7.500 y `consulta_pendiente = false`. El pedido quedó confirmado con `pagado = 7500` y `saldo = 7500`, sin duplicar el importe. | [Checkout aprobado](evidencia-investigacion-1/onvo-tarjeta-aprobada.png) |
| Tarjeta `4000000000000002` | Tras el rechazo en navegador, GET del pago devolvió HTTP 200, `fallido` y consulta resuelta; GET del pedido devolvió `pendiente_de_pago` y `pagado = 0`. | [Checkout rechazado](evidencia-investigacion-1/onvo-tarjeta-rechazada.png) |
| SINPE `+50688888888` | Se usó identificación ficticia `1-1111-1111` en el simulador, sin transferencia real. GET del pago devolvió HTTP 200, `confirmado`, `medio = "sinpe_movil"` y ₡7.500; GET del pedido mostró confirmado, pagado ₡7.500 y saldo ₡7.500. | [SINPE como único medio](evidencia-investigacion-1/onvo-sinpe-unico-medio.png) · [Confirmación](evidencia-investigacion-1/onvo-sinpe-confirmado.png) |
| Cancelación y reembolso del primer aprobado | Cancelación del cliente HTTP 200, procesamiento de la dueña HTTP 200 con devolución `confirmado` por ₡7.500; GET posteriores: pago `reembolsado`, pedido `cancelado` y `pagado = 0`. | Sin captura específica; resultado HTTP sanitizado. |

Las imágenes muestran el checkout, mientras que los resultados HTTP demuestran qué confirmó y registró Archivum. No se extrapola esta ejecución a retiros, operación en producción, todos los estados remotos posibles o los simuladores SINPE adicionales. El [paso a paso ONVO](demostracion-onvo.md#16-evidencia-de-la-ejecución-sandbox-del-25102026) conserva el recorrido reproducible y sus límites.

### Resultados externos PayPal del 26/10/2026

Se completó la aprobación del comprador en sandbox con una Visa ficticia terminada en `5083`, seleccionada por USD 15,00. La [captura del checkout](evidencia-investigacion-1/paypal-tarjeta-seleccionada.png) documenta esa selección; el éxito financiero se acredita con las consultas posteriores, no solo con esa pantalla.

Los registros sanitizados `paypal-confirmation-result.json` y `paypal-provider-confirmation.json` corresponden al pedido local `01a17883-ec63-7394-8ecc-db7db1c1ca09` y al pago `01a17883-f52c-73ec-9249-a832506e6396`. Dos GET del mismo pago devolvieron HTTP 200, `estado = "confirmado"`, `monto = 7500`, `moneda_cobrada = "USD"`, `monto_cobrado = 1500` y `consulta_pendiente = false`. En este recurso, `monto` conserva colones y `monto_cobrado` son centavos USD: `1500` representa USD 15,00. El pedido devolvió HTTP 200, `confirmado`, `pagado = 7500` y `saldo = 7500`.

Una consulta autenticada directa a PayPal devolvió HTTP 200 y orden `COMPLETED`, con **exactamente una captura** `COMPLETED` por `USD 15.00` y `final_capture = true`. Así se comprobó la captura y que la consulta repetida no añadió una segunda. El recorrido fue API local más navegador; las colecciones JSON no se importaron ni se ejecutaron en Postman nativo.

Durante el recorrido, una referencia anterior devolvió `404 INVALID_RESOURCE_ID`; el pago local se mantuvo pendiente y se canceló su pedido local sin importe pagado antes de crear el nuevo. Ese incidente no demuestra que cualquier timeout permita repetir un POST sin riesgo: la falta de certeza conserva la necesidad de conciliación y de las protecciones de idempotencia.

Para la bitácora, cada sesión registra fecha, adaptador, tarea, minutos, error encontrado, solución y evidencia sin secretos. Separar tiempo de leer documentos, desarrollo, espera de cuenta y ejecución evita atribuir al proveedor problemas del entorno local. La complejidad documental de PayPal (OAuth y captura) y ONVO (SINPE asíncrono) sirve como hipótesis, no como medición.

### Bitácora técnica verificable de los adaptadores, 24/10/2026

| Proveedor | Dificultad encontrada | Resolución y evidencia local |
| --- | --- | --- |
| ONVO | El identificador de checkout no es el de intención. El estado `paid` de sesión no basta para aplicar la confirmación prevista. | `OnvoGatewayTest` verifica sesión seguida de intención, conserva ambas referencias y prueba éxito, rechazo, procesamiento y cancelación. |
| ONVO | El esquema permite números JSON para algunos importes; confundir centavos con colones cobraría cien veces diferente. | El adaptador acepta un número semánticamente entero solo si coincide exactamente con los céntimos esperados; la prueba acepta `500000.0` y rechaza `500000.01`, una cadena y una moneda incorrecta. |
| ONVO | Repetir una devolución pendiente puede duplicarla. Perder respuesta de su consulta no debe borrar la referencia ya creada. | Las pruebas reconsultan un reembolso existente con GET sin POST y conservan ID/pendiente cuando falla la consulta posterior. |
| PayPal | `APPROVED` todavía no demuestra captura. | Se consulta antes de capturar; el resultado exige captura `COMPLETED` por el monto esperado. La prueba cuenta cuatro peticiones por incluir OAuth en las dos operaciones actuales. |
| PayPal | La clave idempotente debe repetirse para el mismo intento, pero crear y capturar son operaciones distintas. | La prueba compara dos claves de creación iguales y una de captura diferente. Consultar una orden ya completada no envía otra captura. |
| PayPal | El monto debe ser decimal USD exacto y el cambio puede producir medio centavo. | La aritmética es entera con tipo de seis decimales; CRC 10.000 a 640 producen USD 15,63 por HALF_UP. |
| PayPal | Reencodificar el JSON del evento puede cambiar la verificación de su firma. | La prueba conserva el texto original de `webhook_event` en la consulta verificadora. Este método existe en el adaptador, sin activar rutas de webhook de Avance 2. |

Comando ejecutado desde `archivum-api-hexagonal/`: `php vendor/bin/pest tests/Feature/Payments/Gateways --compact`. Resultado local: **47 pruebas, 135 aserciones**, sin acceso a proveedores reales. Pint se ejecutó sobre los adaptadores y sus pruebas. No se cronometrajeó el trabajo; los problemas y el número de peticiones son observables en código y pruebas, no evidencia de latencia o demora del proveedor.

Antes de la presentación de cinco minutos, falta enlazar la demostración del cambio de configuración, el CI y una decisión del informe; los escenarios ONVO y la aprobación/captura PayPal ya tienen los resultados anteriores. Las capturas excluyen llaves, tokens, datos personales y credenciales de comprador ficticio. Los números de tarjeta del manual pueden usarse en sandbox, sin transcribir secretos de cuentas.

## 10. Uso de inteligencia artificial y verificación pendiente

La IA ayudó a localizar y contrastar fuentes oficiales, estructurar ambos documentos y calcular escenarios con supuestos explícitos. Se revisaron documentalmente tarifas publicadas, contratos de API, diferencias entre callback y webhook, y la correspondencia con los issues. No se atribuye a integrantes humanos una revisión que no está registrada.

El equipo debe verificar manualmente las cifras contra las condiciones efectivas de su cuenta y revisar y explicar la conversión monetaria, idempotencia y transacción. Las ejecuciones ONVO del 25/10/2026 y PayPal del 26/10/2026 ya quedaron registradas en la sección empírica; las demás verificaciones se añaden con autor y fecha cuando se completen. Las conclusiones comerciales siguen condicionadas a los contratos efectivos, no a que un simulador acepte el cobro.
