# Seguridad de la API

Este documento relaciona cada riesgo del [OWASP API Security Top 10 (2023)](https://owasp.org/API-Security/editions/2023/en/0x11-t10/) con la defensa y la prueba que lo cubre, o explica por qué no aplica. Las pruebas propias de estos riesgos viven en `archivum-api-hexagonal/tests/Feature/Security`, con un archivo por riesgo, y su descripción empieza con el riesgo que cubren (`API1:`).

| Riesgo | Defensa | Prueba |
| --- | --- | --- |
| **API1** Broken Object Level Authorization | Dos barreras por tienda (ADR 0005): el puerto `BusinessContext` y los repositorios, y la seguridad a nivel de fila de PostgreSQL. Las policies `OrderPolicy` y `PaymentPolicy` verifican la propiedad del recurso (#104). Un recurso ajeno responde 404, igual que uno inexistente. | `Security/BrokenObjectLevelAuthorizationTest.php`, `Tenancy/CustomerIsolationTest.php` |
| **API2** Broken Authentication | Sanctum con tokens guardados como hash, contraseñas con bcrypt, la misma respuesta para un correo inexistente y para una contraseña incorrecta, revocación al cerrar sesión y vencimiento de tokens (#103). El límite de intentos por IP y por correo llega con #108. | `Accounts/Sessions/StartSessionTest.php`, `Accounts/Sessions/EndSessionTest.php` |
| **API3** Broken Object Property Level Authorization | Cada Form Request deja pasar solo lo que valida y los DTO no tienen campos de tienda, estado ni montos que lleguen del cliente. Las respuestas salen de una lista blanca de campos. | `Security/MassAssignmentTest.php`, `Security/DataExposureTest.php` |
| **API4** Unrestricted Resource Consumption | Listas paginadas, límites de tamaño y de tipo real en los archivos (R53) y el límite de peticiones de #108. | `Businesses/Logo/UploadBusinessLogoTest.php`; los límites, en #108 |
| **API5** Broken Function Level Authorization | Cada ruta declara la ability que exige y el token lleva las de su rol (#102). | `Security/BrokenFunctionLevelAuthorizationTest.php`, `PermissionMatrixTest.php` |
| **API6** Unrestricted Access to Sensitive Business Flows | Los ml de una botella se reservan con `UPDATE` condicional y `CHECK` (R9), los pedidos sin pagar vencen (R25) y los límites de #108 frenan el registro masivo y el abuso del recomendador. | `Decants/Bottles/ConcurrentMlReservationTest.php`, `Sales/Orders/OrderConcurrencyTest.php` |
| **API7** Server Side Request Forgery | **No aplica hoy.** Ningún endpoint recibe una URL para que el servidor la consulte: las direcciones de las pasarelas son fijas en cada adaptador. Hay que revisarlo si una integración nueva acepta direcciones del cliente. | No requiere |
| **API8** Security Misconfiguration | Errores RFC 9457 sin detalles internos y variables de entorno documentadas, sin secretos en `.env.example`. Los encabezados, el CORS y el HTTPS se cubren en #109. | `Architecture/EnvironmentConfigurationTest.php`, `Shared/ProblemTypeTest.php`; el resto, en #109 |
| **API9** Improper Inventory Management | API versionada bajo `/api/v1` y contrato en `openapi/v1.json`, generado desde el código. | `Shared/OpenApiContractTest.php`, `ApiRoutingTest.php` |
| **API10** Unsafe Consumption of APIs | Las pasarelas quedan detrás de puertos y el estado de un pago se confirma consultando al proveedor. El webhook verificado llega con #94 y la salida de la IA, como entrada no confiable, con #119. | `Payments/ConcurrentPaymentTest.php`; el resto, en #94 y #119 |

Las rutas de las pruebas son relativas a `archivum-api-hexagonal/tests/Feature`, salvo las de `Architecture`, relativas a `archivum-api-hexagonal/tests`.

## Cómo distinguir 404 de 422

Un identificador de la **URL** que apunta a otra tienda o a otro cliente responde 404, sin decir si el recurso existe. Un identificador del **cuerpo** que apunta a otra tienda responde 422 `datos-invalidos` en ese campo (#46). `BrokenObjectLevelAuthorizationTest` prueba los dos casos.

## Lo que falta

- **Citas.** Las pruebas de BOLA sobre citas quedan como `todo` hasta que existan sus endpoints (#62, #65 y #66).
- **Pago manual.** La prueba de BFLA queda como `todo` porque `POST /panel/pedidos/{pedido}/pagos-manuales` todavía no existe.
- **Cuenta nueva.** La prueba de `MassAssignmentTest` sobre `POST /cuentas` se omite hasta integrar #101, que crea la ruta.
- **Respuestas sin Resource.** Cinco controladores arman su respuesta con un arreglo de campos elegidos, sin pasar por un Resource: `BusinessLogoController`, `PerfumePhotoController`, `DecantMovementController`, `PaymentController::refund` y `ApiIndexController`. Ninguno expone un modelo completo, pero no cumplen al pie de la letra la regla de #107. No se cambiaron porque son código ya validado del equipo; `DataExposureTest` los fija en una lista para que no aparezca otro sin que se note.
