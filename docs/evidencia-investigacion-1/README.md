# Evidencia de Investigación 1

**Ejecución: 25 y 26/10/2026 · zona horaria America/Costa_Rica.**

ONVO completó en su sandbox los escenarios de tarjeta aprobada, tarjeta rechazada, SINPE Móvil y reembolso. PayPal completó la aprobación con tarjeta ficticia y una captura por USD 15,00. Ambas integraciones confirmaron el depósito en la API local.

La ejecución la realizó **Codex**, como agente, mediante navegador y solicitudes HTTP reales contra la API local y los proveedores en modo de prueba, con datos ficticios y sin fondos reales. No se registra revisión humana de estos resultados. El [registro JSON sanitizado](resultados-sandbox-2026-10-25.json) conserva las observaciones HTTP disponibles.

## Resultados observados

| Escenario | Resultado del proveedor y de la API | Evidencia |
| --- | --- | --- |
| ONVO · tarjeta aprobada | Checkout de prueba aprobado por ₡7.500. Dos GET del mismo pago devolvieron HTTP 200, `confirmado` y `consulta_pendiente=false`; el pedido registró `pagado=7500` y `saldo=7500`. | [Pantalla de aprobación](onvo-tarjeta-aprobada.png) y JSON, escenario `tarjeta_aprobada`. |
| ONVO · tarjeta rechazada | La tarjeta ficticia terminada en 0002 produjo rechazo. La consulta devolvió HTTP 200 con pago `fallido`; el pedido siguió `pendiente_de_pago`, con `pagado=0`. | [Pantalla de rechazo](onvo-tarjeta-rechazada.png) y JSON, escenario `tarjeta_rechazada`. |
| ONVO · SINPE Móvil | El checkout presentó únicamente SINPE Móvil. El teléfono ficticio `+50688888888` completó la simulación; la API confirmó el pago de ₡7.500 y el pedido registró `pagado=7500`. No se abrió una aplicación bancaria ni se realizó una transferencia. | [Medio SINPE](onvo-sinpe-unico-medio.png), [pantalla de aprobación](onvo-sinpe-confirmado.png) y JSON, escenario `sinpe_movil_aprobado`. |
| ONVO · reembolso | Se canceló el primer pedido aprobado y se procesó su devolución de ₡7.500. El pago pasó a `reembolsado`; el pedido permaneció `cancelado` y su `pagado` bajó a 0. | JSON, escenario `reembolso_tarjeta_aprobada`. |
| PayPal · tarjeta y captura | Se seleccionó Visa ficticia terminada en 5083 por USD 15,00. Dos GET del pago devolvieron HTTP 200, `confirmado`, `monto=7500` CRC y `monto_cobrado=1500` centavos USD. El pedido registró `pagado=7500`. La consulta directa al proveedor devolvió `COMPLETED` y exactamente una captura `COMPLETED` por USD 15,00. | [Tarjeta seleccionada](paypal-tarjeta-seleccionada.png) y JSON, sección `paypal`. |
| PayPal · SINPE no admitido | Otro pedido recibió HTTP 422 al solicitar `sinpe_movil`; permaneció `pendiente_de_pago` con `pagado=0`. | JSON, escenario `sinpe_no_admitido`. |
| Postman sin cuenta · API disponible | En Lightweight, GET `/api/v1` devolvió HTTP 200. Las comprobaciones «API disponible», «Nombre de la aplicación» y «Versión de la API» mostraron PASS. | [Petición manual y resultados](postman-sin-cuenta.png). |

Las capturas muestran el navegador del sandbox; la confirmación local se sustenta además en las consultas del servidor conservadas en el JSON. La captura de rechazo muestra el error de checkout; la respuesta HTTP posterior distingue el fallo terminal de un resultado pendiente.

## Secuencia e identificadores

El pedido de tarjeta aprobada es `01a17646-d392-734c-b38c-f47dcd3a11cc` y su pago es `01a17646-dc12-7270-a38d-26d4424892b5`. Las consultas de aprobación y las de reembolso corresponden a esos mismos recursos. Su estado final es pedido cancelado, pago reembolsado y pagado cero; el registro de aprobación describe el momento anterior a la devolución.

Los escenarios de tarjeta rechazada y SINPE usaron pedidos independientes. Los extractos sanitizados de esos dos casos no conservan todos sus UUID; no se inventan identificadores para completarlos.

PayPal usó el pedido `01a17883-ec63-7394-8ecc-db7db1c1ca09` y el pago `01a17883-f52c-73ec-9249-a832506e6396`. La captura muestra la tarjeta seleccionada antes de aprobar; el resultado final se acredita con las consultas HTTP del servidor y del proveedor.

Un checkout PayPal anterior quedó sin completar. Al consultarlo se observó HTTP 404 `INVALID_RESOURCE_ID`; no se atribuye la causa a un timeout sin evidencia adicional. La API conservó ese pago pendiente y no acreditó dinero. Se canceló su pedido local sin pago antes de preparar el nuevo pedido independiente. No se reutilizó una URL antigua ni se interpretó el 404 como rechazo definitivo.

Después del reembolso, `saldo=7500` permanece en la representación del pedido según el cálculo actual de la parte posterior al depósito. El pedido cancelado tiene `por_cobrar_ahora=0`; ese saldo no habilita un nuevo cobro.

## Alcance de Postman

Estos resultados **no afirman una ejecución completa nativa de las colecciones en Postman**. Se validaron la sintaxis y los scripts en VM, y se hicieron solicitudes HTTP reales y pasos de navegador. La preparación de PayPal reutilizó el perfume y la presentación existentes mediante HTTP 200; eso no equivale a ejecutar sus peticiones de creación esperando HTTP 201.

El equipo eligió usar Postman sin cuenta, en modo Lightweight. Las colecciones y entornos quedaron preparados como archivos, pero no se importaron en esa aplicación. Se verificó allí la petición manual de salud con tres comprobaciones; eso no acredita el recorrido nativo completo de pagos. La guía de Postman explica cómo enviar las peticiones manuales sustituyendo las variables. El ensayo del equipo y la revisión humana permanecen pendientes.

El JSON omite credenciales, cuerpos de autenticación, cookies, tokens de sesión o API, URLs de checkout y rutas absolutas de la computadora. Conserva únicamente importes, estados, rutas relativas de la API y los UUID públicos ficticios disponibles.

Recorridos: [guía ONVO](../demostracion-onvo.md), [guía PayPal](../demostracion-paypal.md) y [colecciones Postman](../../archivum-api-hexagonal/tests/Postman/README.md).
