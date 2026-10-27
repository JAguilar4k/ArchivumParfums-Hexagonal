# Investigación aplicada 1: comparación de pasarelas de pago

ISW-621 · ArchivumParfums · Issue [#93](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/issues/93). Fecha de consulta de todas las fuentes: **24/10/2026**. Entrega: 28/10/2026, antes de las 13:00, Costa Rica.

## Alcance y método

ArchivumParfums necesita cobrar depósitos de botellas y el total de decants, con precios calculados en el servidor. Comparamos ONVO Pay, PayPal, Tilopay y GreenPay con documentación oficial. ONVO y PayPal son las integraciones exigidas; las otras dos se estudian sin implementarlas. Separamos hechos publicados, cálculos con supuestos y evidencia pendiente. El [expediente técnico](payment-gateways.md) conserva fuentes, fórmulas y trazabilidad a los issues existentes.

Este informe no acredita cobros en vivo, retiros bancarios ni tiempos de integración medidos. La ejecución externa de sandbox y su evidencia deben incorporarse antes de presentar la entrega. Las pruebas HTTP simuladas demuestran comportamiento del código, pero no sustituyen la demostración exigida.

## Credenciales, medios y acceso desde Costa Rica

**ONVO** genera llaves de prueba automáticamente al registrar una cuenta; completar el onboarding habilita producción, fuera del alcance del curso. No publica un plazo garantizado para el alta de prueba. Sus llaves separan servidor y navegador. [Autenticación ONVO](https://docs.onvopay.com/authentication), consulta 24/10/2026.

**PayPal** requiere acceso a Developer, cuentas ficticias compradora y vendedora y una aplicación con credenciales sandbox. El correo de la cuenta debe verificarse; no encontramos un SLA para todo el proceso. La configuración debe apuntar exclusivamente al sandbox. [Guía sandbox](https://developer.paypal.com/sandbox-testing/overview), consulta 24/10/2026.

**Tilopay** solicita registro individual como desarrollador y envía credenciales propias; no ofrece credenciales compartidas ni un plazo público de sandbox. La afiliación comercial y su aprobación son trámites diferentes. [Portal de desarrolladores](https://tilopay.com/developers), consulta 24/10/2026.

**GreenPay** permite autogestionar una cuenta de prueba. Su guía solicita datos del comercio y administrador, país, teléfono, correo y selección bancaria, y describe el envío de credenciales y tarjetas ficticias. No documenta un plazo garantizado de sandbox. [Cuenta de prueba](https://support.greenpay.me/portal/es/kb/articles/sobre-la-cuenta-de-sandbox), consulta 24/10/2026.

ONVO admite SINPE Móvil en CRC: debe coincidir la identificación del emisor y el monto exacto de una intención pendiente de transferencia. Tilopay también lo admite y documenta una ventana de asociación de 25 minutos. PayPal no ofrece ese medio dentro del flujo estudiado y su lista de monedas no incluye CRC: usaremos USD. GreenPay publica transferencias SINPE a cuentas IBAN; eso no demuestra soporte de SINPE **Móvil** para este checkout. [ONVO](https://docs.onvopay.com/payments/sinpe-mobile), [Tilopay](https://tilopay.com/developers/sdk), [monedas PayPal](https://developer.paypal.com/api/codes/currency), [SINPE GreenPay](https://greenpay.me/soluciones/transferencia-sinpe/), consulta 24/10/2026.

Los cuatro contemplan operación costarricense. ONVO liquida a banco local; Tilopay describe depósito directo en banco local; GreenPay pide cuenta local y permite indicar el IBAN de liquidación. PayPal publica retiro a banco local en CRC o USD, sujeto a disponibilidad de la cuenta, comisiones y posible conversión. Tener sandbox no acredita que la afiliación comercial esté aprobada. [ONVO](https://www.onvopay.com/business-industry-services), [Tilopay](https://tilopay.com/blog/stripe-o-paypal), [GreenPay](https://greenpay.me/preguntas-frecuentes/), [PayPal CR](https://www.paypal.com/cr/business/paypal-business-fees), consulta 24/10/2026.

## Costos sobre el mismo negocio

El escenario es **100 reservas mensuales de ₡15.000: ₡1.500.000**. Para convertir cargos fijos adoptamos **₡500/USD como supuesto didáctico**, no como cotización del BCCR ni tipo contractual. Consideramos una liquidación mensual, ningún contracargo y un pago por reserva. Separar depósito y saldo en dos transacciones incrementaría los cargos fijos.

| Proveedor y canal | Base publicada | Cálculo mensual con ese supuesto |
| --- | --- | --- |
| ONVO, tarjeta | 3,9 % + USD 0,35 por éxito; USD 3 por liquidación | ₡58.500 + ₡17.500 + ₡1.500 = **₡77.500** |
| ONVO, SINPE Móvil | 1,5 %; misma liquidación | ₡22.500 + ₡1.500 = **₡24.000** |
| PayPal, USD | 5,4 % + USD 0,30; retiro local USD: 1 %, mínimo USD 10 | USD 192 de cobro + USD 28,08 de retiro sobre el saldo USD 2.808 = **₡110.040** |
| Tilopay, tarjeta | 4,25 % + USD 0,35 | ₡63.750 + ₡17.500 = **₡81.250**, más liquidación no cotizada |
| Tilopay, SINPE Móvil | 2 % + USD 0,35 | ₡30.000 + ₡17.500 = **₡47.500**, más liquidación no cotizada |
| GreenPay, pasarela | USD 30 + IVA/mes, incluye 200 transacciones; alta USD 150 | **₡15.000 + IVA + comisión adquirente**, más eventual liquidación interbancaria USD 2; alta adicional **₡75.000** |

Fuentes del cuadro, consultadas el 24/10/2026: [ONVO](https://www.onvopay.com/pricing), [PayPal CR](https://www.paypal.com/cr/business/paypal-business-fees), [Tilopay](https://tilopay.com/tarifas), [tarifario GreenPay](https://greenpay.me/faq/cuales-son-los-costos-de-los-servicios-de-greenpay-2/) y [liquidaciones GreenPay](https://greenpay.me/preguntas-frecuentes/).

Son costos parciales defendibles, no promesas de costo final. ONVO anuncia ecommerce sin instalación; Tilopay declara ausencia de mensualidad y anualidad. No se confirmó cómo facturan el IVA de sus comisiones. GreenPay sí explicita IVA mensual, pero no publica en ese tarifario la comisión adquirente ni el impuesto del alta; 200 transacciones incluidas no significa adquisición bancaria gratuita. Su precio es negociable. No lo calificamos como el más barato por su mensualidad.

En PayPal, retirar en CRC cuesta ₡625, además de conversión: el subtotal alternativo es ₡96.625 **más** el efecto del cambio. Su tarifario publica 3,5 % por encima del tipo base para convertir fondos; sin el tipo efectivo no calculamos un neto exacto. El escenario USD evita atribuir ese margen al importe bruto incorrectamente, pero el banco receptor todavía puede cobrar. [Tarifario CR](https://www.paypal.com/cr/business/paypal-business-fees), consulta 24/10/2026.

## Verificación de notificaciones

**PayPal** documenta firma asimétrica, certificado, identificador y hora de transmisión, webhook registrado y checksum del cuerpo original. Permite verificación local o mediante su API. Es el mecanismo con garantías públicas más completas de los cuatro: vincula el mensaje con la firma del emisor. Aun así, verificarlo no reemplaza deduplicar eventos ni comprobar monto, moneda y destinatario. [Verificación PayPal](https://developer.paypal.com/api/rest/webhooks/rest/).

**ONVO** envía un secreto estático en `X-Webhook-Secret`. HTTPS y la custodia del secreto permiten autenticar a quien lo presenta; la documentación consultada no describe una firma individual del cuerpo ni una marca temporal firmada. Un secreto filtrado podría reutilizarse. Esta limitación explica por qué debemos verificar estado y aplicar idempotencia, sin describir el header como HMAC. [Webhooks ONVO](https://docs.onvopay.com/webhooks).

**Tilopay** documenta `orderHash`, cuyo algoritmo se entrega al comercio que lo solicita. Sin esa especificación no podemos evaluar su resistencia criptográfica. Su propia guía recomienda consultar la API antes de acciones irreversibles cuando no se dispone del algoritmo. [Webhooks Tilopay](https://tilopay.com/developers/api/webhooks).

**GreenPay** distingue versiones. Los ejemplos V1 contienen `_signature`; las páginas V2 consultadas describen headers JSON y respuestas sin publicar un procedimiento de verificación del emisor. El callback del navegador codificado en Base64 tampoco prueba autenticidad. La ausencia de especificación localizada no demuestra una vulnerabilidad: impide certificar esas garantías y obliga a obtenerlas antes de confiar en eventos. [V1](https://support.greenpay.me/portal/es/kb/articles/respuesta-d-e), [V2](https://support.greenpay.me/portal/es/kb/articles/webhook-v2), [callback](https://support.greenpay.me/portal/es/kb/articles/payment-process-with-form-28-3-2020).

Todas estas fuentes de seguridad se consultaron el 24/10/2026. La clasificación compara evidencia pública, no constituye una auditoría de los proveedores.

## Integración, fallos y decisión

El enunciado específico pide confirmar consultando al proveedor en esta investigación y migrar a webhooks en Avance 2. Aplicamos esa excepción de entrega a R38: el navegador nunca decide que un pago fue exitoso. Un timeout conserva la incertidumbre; no equivale a rechazo ni autoriza un segundo cobro. El diseño exige conservar referencia e identidad del intento, consultar de nuevo y confirmar pago y pedido dentro de una transacción. La expiración local con resultado externo todavía desconocido necesita conciliación antes de liberar recursos definitivamente.

ONVO recibe céntimos enteros; PayPal recibe texto decimal USD y requiere crear, aprobar y capturar una orden. La conversión pertenece al adaptador. [Intenciones ONVO](https://docs.onvopay.com/payments/payment-intents), [órdenes PayPal](https://developer.paypal.com/api/orders/v2), consulta 24/10/2026.

La implementación y sus pruebas revelaron dificultades concretas: ONVO exige distinguir sesión e intención, consultar ambas y conservar la referencia de reembolso si falla su consulta posterior. PayPal requiere OAuth antes de cada llamada en el adaptador actual y distingue orden aprobada de captura completada. Su prueba de aprobación seguida de captura verifica cuatro peticiones; ONVO verifica dos consultas para conocer el resultado. Por esos pasos adicionales, **PayPal tuvo mayor complejidad de coordinación en la implementación probada**. La suite de adaptadores ejecutada el 24/10/2026 pasó 47 casos con 135 aserciones, siempre simulando HTTP. No medimos horas ni tiempos de afiliación: esos hechos no permiten afirmar cuál tomó más tiempo cronológico. La bitácora técnica está en el expediente; la [evidencia sandbox del 25 y 26/10](evidencia-investigacion-1/README.md) registra ONVO con tarjeta, SINPE y reembolso, y PayPal con tarjeta y captura.

Recomendamos **ONVO por defecto** para el mercado local, por CRC y SINPE Móvil, con PayPal como alternativa para clientes que prefieran su ecosistema. La diferencia de seguridad documentada exige compensaciones al operar ONVO. Cambiaríamos si la cotización final, disponibilidad observada, conciliación o atención de incidentes empeoran, o si otro proveedor acredita mejores condiciones y autenticidad verificable. No activaremos pago dividido por inferencia: las opciones marketplace requieren habilitación y evaluación propia; el extra #100 queda fuera de esta investigación. La recomendación sigue condicionada a completar y registrar la demostración sandbox de ambas integraciones.
