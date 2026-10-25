# Demostración de PayPal sandbox · Investigación 1

Esta guía prepara y demuestra un depósito de un pedido de ArchivumParfums mediante PayPal. Se usa Postman para la API y el navegador para el checkout hospedado. Los pasos están contrastados con el código del corte y con documentación oficial consultada el **25/10/2026**.

**Estado de la evidencia:** todavía faltan las cuentas sandbox del equipo y la ejecución contra PayPal. Las pruebas automatizadas usan respuestas simuladas; no prueban que las credenciales, la cuenta vendedora o el checkout externo del equipo ya funcionen. Al terminar el recorrido, registrar la evidencia indicada en el paso 10.

## 1. Preparar las cuentas y la aplicación sandbox

Hay tres identidades diferentes: tu acceso al Developer Dashboard; la cuenta **Business sandbox** que vende; y la cuenta **Personal sandbox** que compra. Las cuentas de Archivum del paso 4 pertenecen a nuestra API.

1. Abrir el [Developer Dashboard](https://developer.paypal.com/dashboard/) e iniciar sesión o registrarse. Para probar una integración de un comercio fuera de Estados Unidos, PayPal indica que se necesita una cuenta Business. Este requisito de acceso es distinto de las dos cuentas ficticias sandbox. [Inicio de las API REST](https://developer.paypal.com/api/rest/).
2. Entrar a **Testing Tools → Sandbox Accounts**; la documentación también lo denomina **Sandbox → Accounts**. Revisar las cuentas predeterminadas **Business** y **Personal**. Si falta alguna, usar **Create Account**, seleccionar su tipo y país y crearla. Usar Costa Rica cuando esté disponible; registrar el país realmente usado. [Cuentas sandbox](https://developer.paypal.com/sandbox-testing/accounts).
3. En **⋮ → View/Edit Account**, guardar de forma privada el correo y la contraseña ficticios de ambas cuentas. La Personal será el comprador; la Business permite revisar la actividad del vendedor. No pagar con la misma cuenta que recibe. [Cuentas de prueba](https://developer.paypal.com/sandbox-testing/accounts).
4. En **Apps & Credentials**, seleccionar **Sandbox**. Abrir la aplicación predeterminada o **Create App**, ponerle un nombre como `Archivum Investigación 1` y asociarla a la cuenta Business sandbox vendedora cuando el formulario lo solicite. Copiar su **Client ID** y **Secret**. [Obtención de credenciales REST](https://developer.paypal.com/api/rest/).
5. Comprobar que las cuentas se abren en [sandbox.paypal.com](https://www.sandbox.paypal.com/), usando el comprador en una ventana privada y el vendedor en otra sesión. Sus saldos y movimientos son ficticios. [Guía de cuentas sandbox](https://developer.paypal.com/sandbox-testing/accounts).

No hace falta crear un webhook para este recorrido: Investigación 1 confirma por consulta del servidor. `PAYPAL_WEBHOOK_ID` puede quedar vacío. La verificación y recepción de webhooks corresponde al corte posterior.

## 2. Configurar los archivos locales

Trabajar en la carpeta `archivum-api-hexagonal/`. Si la API todavía no está instalada, seguir su [puesta en marcha](../archivum-api-hexagonal/README.md#instalación): PHP 8.4, Composer y PostgreSQL 18, con los roles de aplicación y dueño del esquema. No sustituir PostgreSQL por SQLite.

Editar **únicamente** `archivum-api-hexagonal/.env` para poner los datos privados de la aplicación **sandbox**:

```dotenv
APP_ENV=local
APP_URL=http://localhost:8000
ARCHIVUM_DEMO_PASSWORD=demo-archivum

PAYPAL_CLIENT_ID=REEMPLAZAR_POR_CLIENT_ID_SANDBOX
PAYPAL_CLIENT_SECRET=REEMPLAZAR_POR_SECRET_SANDBOX
PAYPAL_WEBHOOK_ID=
PAYMENTS_RETURN_URL=http://localhost:8000/api/v1
PAYMENTS_CANCEL_URL=http://localhost:8000/api/v1
```

Conservar los valores `DB_*` que conectan tu instalación. `ARCHIVUM_DEMO_PASSWORD` debe coincidir con la contraseña que se usó al sembrar las cuentas. `demo-archivum` es el valor local de ejemplo, no una contraseña de PayPal.

El host REST ya está fijado en `config/payments.php` a `https://api-m.sandbox.paypal.com`. No hay una variable para habilitar producción. El adaptador rechaza hosts de producción. El Client Secret se conserva en `.env`, excluido de Git; nunca se copia a `.env.example`, al informe o a capturas. [Uso de Client ID y Secret](https://developer.paypal.com/api/rest/).

Los dos destinos locales del ejemplo existen: muestran los metadatos de `/api/v1` al regresar del checkout. No confirman ni cancelan un pedido por sí mismos. Si se cambia el puerto, modificar las tres URL locales y arrancar Artisan con ese puerto. Esta prueba se realiza en el navegador de la misma computadora que sirve la API.

## 3. Aplicar migraciones, datos ficticios y configuración

En una base **local de demostración**, ejecutar desde `archivum-api-hexagonal/`:

```bash
php artisan migrate --database=pgsql_admin
php artisan db:seed
php artisan config:clear
php artisan serve --host=localhost --port=8000 --no-reload
```

Dejar la última terminal abierta. `db:seed` crea las cuentas ficticias y puede restablecer ajustes de las tiendas de demo: ejecutarlo **antes** del paso 5. No hace falta repetirlo para cada cobro ni borrar tablas.

Si ya hay un servidor funcionando después de cambiar `.env`, detenerlo, ejecutar `php artisan config:clear` y arrancarlo de nuevo. En Postman comprobar:

```http
GET http://localhost:8000/api/v1
Accept: application/json
```

Resultado esperado: **200**, con `{"nombre":"ArchivumParfums","version":"v1"}`. La documentación local está en `http://localhost:8000/docs/api`.

## 4. Obtener los tokens de Archivum

Crear un entorno **local y privado** de Postman con estas variables:

| Variable | Valor inicial o procedencia |
|---|---|
| `base_url` | `http://localhost:8000`, sin `/api/v1` final |
| `demo_password` | El valor local de `ARCHIVUM_DEMO_PASSWORD` usado al sembrar |
| `owner_token` | `data.token` del login de la dueña |
| `customer_token` | `data.token` del login de Mariana |
| `perfume_id`, `variant_id` | Identificadores devueltos en el paso 6 |
| `order_id`, `payment_id` | Identificadores devueltos en los pasos 7 y 8 |

Para las peticiones con cuerpo usar **Body → raw → JSON**, `Accept: application/json` y `Content-Type: application/json`.

Primero iniciar sesión como dueña, sin Bearer Token:

```http
POST {{base_url}}/api/v1/sesiones
```

```json
{
  "correo": "duena@archivumparfums.test",
  "contrasena": "{{demo_password}}",
  "dispositivo": "Postman PayPal dueña"
}
```

La respuesta **201** contiene `data.token`. Copiar el valor completo a `owner_token`. Crear otra petición de login con el mismo endpoint:

```json
{
  "correo": "mariana@clientes.test",
  "contrasena": "{{demo_password}}",
  "dispositivo": "Postman PayPal cliente"
}
```

Guardar su `data.token` como `customer_token`. Comprobar cada uno con `GET {{base_url}}/api/v1/cuenta`, seleccionando **Authorization → Bearer Token** y el token correspondiente. La dueña debe tener rol `dueno`; Mariana, `cliente`.

Estos son tokens **Sanctum de Archivum**. El adaptador obtiene por su cuenta el token OAuth de PayPal mediante `/v1/oauth2/token`; no hay que pegar ese token OAuth en Postman ni poner allí la contraseña del comprador sandbox.

## 5. Seleccionar PayPal en la tienda

Con **Bearer `{{owner_token}}`**:

```http
PATCH {{base_url}}/api/v1/panel/tienda
```

```json
{
  "ajustes": {
    "pasarela_por_defecto": "paypal",
    "tipo_cambio_usd": 500
  }
}
```

Esperar **200** y comprobar los ajustes con `GET {{base_url}}/api/v1/panel/tienda`, usando el mismo token. La tienda se resuelve desde la sesión. No enviar `business_id` ni editar una tabla para seleccionarla.

Crear un **pedido nuevo** para esta demostración. Un intento pendiente anterior conserva su pasarela e identidad aunque se cambien los ajustes; cambiar de ONVO a PayPal no convierte ese intento existente.

**500 CRC por USD es un escenario didáctico elegido para obtener cifras exactas**, no una cotización actual ni una tarifa de PayPal. El sistema guarda ese tipo en el intento para conservar su conversión. PayPal REST admite USD; CRC no figura entre sus monedas de pago admitidas. [Monedas oficiales](https://developer.paypal.com/api/codes/currency).

## 6. Crear el producto ficticio de ₡15 000

Con **Bearer `{{owner_token}}`**, crear un perfume activo:

```http
POST {{base_url}}/api/v1/panel/perfumes
```

```json
{
  "marca": "Laboratorio ficticio",
  "nombre": "Ensayo PayPal Investigación 1",
  "descripcion": "Producto ficticio para demostrar cobros sandbox",
  "activo": true
}
```

Guardar `data.id` de la respuesta **201** en `perfume_id`. Crear su presentación:

```http
POST {{base_url}}/api/v1/panel/perfumes/{{perfume_id}}/presentaciones
```

```json
{
  "ml": 100,
  "precio_manual": 15000
}
```

Guardar `data.id` de la respuesta **201** en `variant_id`; comprobar `data.precio_venta = 15000` y `data.activa = true`. Así el resultado no depende del cálculo del catálogo del proveedor.

Opcionalmente consultar la vitrina sin autorización:

```http
GET {{base_url}}/api/v1/tiendas/archivumparfums/perfumes/{{perfume_id}}
```

Para esta demostración se encarga una presentación completa. No crear una botella de decants: una línea de decant se cobra completa y cambiaría el cálculo mostrado.

## 7. Crear el pedido como Mariana

Cambiar a **Bearer `{{customer_token}}`**:

```http
POST {{base_url}}/api/v1/tiendas/archivumparfums/pedidos
```

```json
{
  "lineas": [
    {
      "presentacion_id": "{{variant_id}}",
      "cantidad": 1
    }
  ]
}
```

Guardar `data.id` de la respuesta **201** como `order_id`. Con los ajustes de demo, el depósito por defecto es **50 %**, congelado al crear el pedido. Antes de continuar, verificar:

| Campo del pedido | Valor esperado |
|---|---:|
| `estado` | `pendiente_de_pago` |
| `subtotal` | `15000` |
| `por_cobrar_ahora` | `7500` |
| `pagado` | `0` |
| `saldo` | `7500` |

Revisar `vence_en` y completar el pago dentro del plazo. Si las cifras son distintas, revisar el producto y los ajustes antes de iniciar el intento. El navegador envía identidad y cantidad del producto; el precio y el depósito los calcula el servidor.

## 8. Iniciar el intento y abrir PayPal

Con **Bearer `{{customer_token}}`**:

```http
POST {{base_url}}/api/v1/cuenta/pedidos/{{order_id}}/pagos
```

```json
{
  "medio": "tarjeta"
}
```

Esperar **201**, guardar `data.id` como `payment_id` y verificar:

```json
{
  "proposito": "deposito",
  "monto": 7500,
  "moneda_cobrada": "USD",
  "monto_cobrado": 1500,
  "pasarela": "paypal",
  "estado": "pendiente",
  "medio": "tarjeta",
  "consulta_pendiente": false
}
```

Esos campos aparecen dentro de `data`, junto con `id` y `url_pago`. El cálculo es **₡7 500 ÷ 500 = USD 15,00**. `monto` usa colones enteros; `monto_cobrado` usa **centavos USD: 1500**. El adaptador envía a PayPal `amount.value: "15.00"` y `currency_code: "USD"`.

Antes de aprobar, repetir **una vez** el mismo POST. Debe responder **200** con el mismo `data.id` y la misma URL: reutiliza el intento pendiente. Esta repetición se hace antes de confirmar el pedido.

Abrir el `data.url_pago` devuelto, con host `www.sandbox.paypal.com`, en la ventana privada del comprador. Iniciar sesión con el correo y la contraseña de la cuenta **Personal sandbox** del paso 1, comprobar **USD 15,00** y aprobar. La integración crea una orden PayPal con intención `CAPTURE` y redirige a su experiencia hospedada. [Flujo oficial de Checkout](https://developer.paypal.com/studio/checkout/standard/integrate).

En nuestra API `medio: "tarjeta"` selecciona el método admitido por el adaptador PayPal. El código usa `payment_source.paypal`: **no integra Advanced Card Fields**. La opción de pagar con tarjeta como invitado depende de la elegibilidad que determine PayPal; no se promete que aparezca para toda cuenta o país. Para la ejecución reproducible usar la cuenta Personal sandbox. [Condiciones de Guest Checkout](https://www.paypal.com/c2/cshelp/article/how-do-i-accept-cards-with-checkout-using-the-guest-checkout-option--help307?locale.x=en_C2).

Para demostrar específicamente **pago con tarjeta**:

1. Abrir el [generador oficial de tarjetas](https://developer.paypal.com/credit-card-number-generator) y generar una tarjeta ficticia. PayPal documenta que las tarjetas generadas pueden agregarse a cuentas sandbox.
2. Iniciar sesión como **Personal sandbox en sandbox.paypal.com** y, cuando esté disponible, abrir **Wallet/Cartera → Link a debit or credit card/Asociar tarjeta**. Introducir allí los datos ficticios generados. Es la opción descrita en la [ayuda oficial para asociar tarjetas](https://www.paypal.com/us/cshelp/article/how-do-i-link-a-debit-or-credit-card-to-my-paypal-account-help155); en este recorrido se usa únicamente dentro del sitio sandbox.
3. Volver al checkout y seleccionar esa tarjeta como fuente de fondos. Alternativamente, usar la opción de tarjeta como invitado si PayPal la ofrece. Conservar evidencia anonimizada de la fuente seleccionada y del resultado.

Si el checkout solo ofrece saldo o impide usar la tarjeta ficticia, registrar esa limitación: una operación financiada con saldo demuestra PayPal Checkout, pero el **requisito de pago con tarjeta queda pendiente**. El valor `medio: "tarjeta"` de nuestra respuesta no demuestra por sí mismo qué fuente eligió PayPal.

## 9. Consultar, capturar y confirmar desde el servidor

Después de aprobar y regresar a la URL local, volver a Postman con **Bearer `{{customer_token}}`**:

```http
GET {{base_url}}/api/v1/cuenta/pagos/{{payment_id}}
```

No enviar `estado`, `monto` ni el `token` que aparece en la URL de retorno. `payment_id` es el UUID de Archivum obtenido del POST, no el identificador de la orden PayPal.

En esta petición el servidor consulta la orden PayPal; si está `APPROVED` y el pedido admite el cobro, ejecuta la captura desde el servidor. **La aprobación sola no acredita el pedido:** exige una captura `COMPLETED` con identificadores, moneda e importe coincidentes. La API actualiza pago y pedido en una misma transacción. [Crear y capturar desde el backend](https://developer.paypal.com/studio/checkout/standard/integrate).

Si la captura terminó, la respuesta es **200**, con `data.estado = "confirmado"` y `data.consulta_pendiente = false`. Consultar el pedido:

```http
GET {{base_url}}/api/v1/cuenta/pedidos/{{order_id}}
```

Comprobar `estado = "confirmado"`, `pagado = 7500`, `por_cobrar_ahora = 0`, `subtotal = 15000` y `saldo = 7500`. Se confirmó el depósito, no el pago total de la presentación.

Repetir el **GET del pago dos veces** y volver a consultar el pedido: `pagado` debe seguir en `7500`. Después de la confirmación local no vuelve a capturar. Cuando aún está pendiente, el adaptador reutiliza una clave `PayPal-Request-Id` estable por operación; las claves de creación y captura son distintas. [Idempotencia de PayPal](https://developer.paypal.com/api/rest/reference/idempotency/).

| Resultado | Qué observar y hacer |
|---|---|
| Orden externa todavía no aprobada o captura `PENDING` | La API conserva `estado: "pendiente"`. No presentar el depósito como cobrado; consultar otra vez cuando cambie el proveedor. |
| Rechazo explícito reconocido, como `INSTRUMENT_DECLINED` | `estado: "fallido"`; el pedido no recibe ese importe. Un rechazo se distingue de una caída de red. |
| Error de comunicación | **202**, `consulta_pendiente: true`, `Retry-After: 30`. Conservar el intento y consultar de nuevo; no borrar el registro para forzar otro cargo. |
| Error al crear y `url_pago` nula | El intento ya quedó guardado, pero el resultado remoto es incierto. Repetir el POST conserva su identidad; no reconstruye una URL ni hace otro POST de creación. Revisar la configuración y los registros para conciliarlo. |
| `422` al solicitar SINPE o sin conversión válida | Corregir el medio o el ajuste. PayPal en este proyecto admite `tarjeta`, con conversión a USD; no admite `sinpe_movil`. |
| Retorno a la URL de cancelación | No es una confirmación de pago ni una cancelación local automática. Consultar el estado real. Para la demostración de aceptación usar un pedido nuevo y vigente. |

Para comprobar deliberadamente el `422` de SINPE, usar **otro pedido nuevo sin intento pendiente**, con la tienda configurada en PayPal y el tipo de cambio establecido. Enviar `{"medio":"sinpe_movil"}` debe fallar sin crear un cargo. Un intento pendiente ya existente se reutiliza antes de evaluar un medio nuevo.

## 10. Reunir la evidencia externa pendiente

En la cuenta **Business sandbox**, revisar la actividad para comprobar el importe recibido y su referencia; en la cuenta Personal, revisar el movimiento del comprador. Es el control externo además del resultado local. [Ver movimientos entre cuentas de prueba](https://developer.paypal.com/api/rest/).

Guardar evidencia anonimizada de: país y tipo de las cuentas sandbox; creación del pedido con depósito `7500`; POST de pago con `monto_cobrado: 1500`; pantalla del checkout por USD 15,00 y su fuente de fondos, especialmente la tarjeta ficticia si está disponible; captura completada en sandbox; GET confirmado; y dos consultas repetidas que mantienen `pagado: 7500`. Ocultar contraseñas, Client Secret, tokens Bearer y OAuth. Registrar fecha, resultado real, fuente efectivamente usada y errores observados en el expediente de [pasarelas](payment-gateways.md).

Hasta completar estos pasos, el resultado es **implementación y pruebas simuladas verificadas; demostración externa pendiente**.

## 11. Mostrar aceptación, rechazo y error sin cuentas externas

Desde `archivum-api-hexagonal/`, ejecutar las pruebas del adaptador:

```bash
php vendor/bin/pest tests/Feature/Payments/Gateways/PayPalGatewayTest.php
```

El archivo usa `Http::fake()` y bloquea solicitudes no simuladas. No necesita Client ID real y **no envía cargos a PayPal**. Incluye estos escenarios reproducibles:

| Caso | Prueba existente y resultado esperado |
|---|---|
| Aceptación | `captures only after a server query confirms approval and requires a completed capture`: `COMPLETED` produce `Succeeded`; `PENDING` conserva pendiente. |
| Rechazo | `reports an explicit PayPal instrument decline as failed` y `distinguishes an explicit capture refusal from a network timeout`: rechazo reconocido produce `Failed`. |
| Error de red | `reports a network error without losing the distinction from a decline`: produce `GatewayUnavailableException`, sin convertir la caída en rechazo. |
| Repetición | `does not capture again when an order already contains its completed capture`: lee la captura existente sin otro POST de captura. |
| Importe incorrecto | `rejects a mismatched order amount before capture`: impide capturar un importe diferente. |

Para comprobar además respuestas HTTP, permisos y persistencia, con PostgreSQL de **pruebas** configurado según `phpunit.xml`:

```bash
php vendor/bin/pest tests/Feature/Payments/PaymentEndpointsTest.php
```

Estas pruebas usan la base `archivum_hexagonal_testing`, aplican las migraciones con el rol dueño y las consultas con el rol de la aplicación. No constituyen evidencia de que se haya abierto el checkout externo. No se necesita modificar el adaptador para fingir un rechazo en una presentación: los escenarios ya están en las pruebas.

## 12. Reembolso de PayPal: documentación y límite de este corte

PayPal ofrece `POST /v2/payments/captures/{capture_id}/refund` y la consulta de su resultado. Esto es la capacidad documentada del proveedor. [Referencia oficial de Payments v2](https://developer.paypal.com/api/payments/v2).

**El adaptador PayPal del proyecto no implementa esa operación**: `PayPalGateway::refund()` lanza `payments.refund_not_supported`; el procesador de reembolsos solo envía a ONVO. Una devolución registrada para PayPal conserva su pendiente y no debe marcarse como confirmada por completar una devolución manual en el panel externo. Investigación 1 permite dejar el reembolso de PayPal documentado para el corte posterior. No afirmar que la API ya devuelve dinero con PayPal ni intentar demostrarlo con el endpoint de ONVO.

## 13. Qué archivo cambiar en cada caso

Las rutas de esta tabla son relativas a `archivum-api-hexagonal/`. Para **ejecutar** esta guía se edita `.env` y se actualizan datos por la API. La implementación ya existe; los demás archivos se indican para ubicar y explicar cambios futuros.

| Necesidad | Archivo o lugar | Acción concreta |
|---|---|---|
| Poner credenciales y URL locales | `.env` | Cambiar solo valores sandbox privados; limpiar configuración y reiniciar HTTP. |
| Documentar nuevas variables para el equipo | [.env.example](../archivum-api-hexagonal/.env.example) | Añadir nombres y ejemplos sin secretos, si cambian los requisitos de configuración. |
| Elegir pasarela o tipo de cambio | `PATCH /api/v1/panel/tienda`; [UpdateBusinessProfileRequest.php](../archivum-api-hexagonal/src/Businesses/Profile/Infrastructure/Http/Requests/UpdateBusinessProfileRequest.php) | Para la demo usar la API. La Request define `pasarela_por_defecto` y `tipo_cambio_usd`; no cambiar controladores para elegir tienda o proveedor. |
| Consultar el host y los nombres de variables | [config/payments.php](../archivum-api-hexagonal/config/payments.php) | Revisar el mapa de configuración. Mantener el host sandbox del corte. |
| Cambiar la comunicación con PayPal | [PayPalGateway.php](../archivum-api-hexagonal/src/Payments/Payments/Infrastructure/Gateways/PayPal/PayPalGateway.php) | Ajustar OAuth, creación, consulta y captura dentro de este adaptador. Es donde se implementaría el reembolso pendiente. |
| Cambiar validación/conversión de dinero | [GatewayMoney.php](../archivum-api-hexagonal/src/Payments/Payments/Infrastructure/Gateways/GatewayMoney.php); [ExchangeRate.php](../archivum-api-hexagonal/src/Payments/Payments/Domain/ValueObjects/ExchangeRate.php) | Conservar colones enteros y centavos USD; validar antes de guardar un nuevo intento. Añadir evidencia de redondeo y límites. |
| Cambiar el contrato común del proveedor | [PaymentGateway.php](../archivum-api-hexagonal/src/Payments/Payments/Domain/Contracts/PaymentGateway.php) | Modificar el puerto solo si la capacidad común cambia, junto con adaptadores y pruebas. No hace falta para poner credenciales. |
| Cambiar inicio y confirmación del pago | [StartPayment.php](../archivum-api-hexagonal/src/Payments/Payments/Application/UseCases/StartPayment.php); [ObservePayment.php](../archivum-api-hexagonal/src/Payments/Payments/Application/UseCases/ObservePayment.php) | Conservar el intento antes del POST externo y el efecto pago/pedido atómico tras la consulta verificada. |
| Revisar estados y reglas del pago | [Payment.php](../archivum-api-hexagonal/src/Payments/Payments/Domain/Entities/Payment.php) | El dominio no depende de PayPal ni debe recibir estados enviados por el navegador. Cambiarlo solo por una regla de negocio acordada. |
| Revisar el depósito por defecto | [BusinessSettings.php](../archivum-api-hexagonal/src/Businesses/Profile/Domain/ValueObjects/BusinessSettings.php); [OrderAmounts.php](../archivum-api-hexagonal/src/Sales/Orders/Domain/ValueObjects/OrderAmounts.php) | El 50 % se toma al crear el pedido y queda congelado. Para esta demo comprobar la respuesta; no alterar precios ni estado del pago a mano. |
| Cambiar datos de demo permanentes | [CatalogDemoSeeder.php](../archivum-api-hexagonal/database/seeders/CatalogDemoSeeder.php); [StaffSeeder.php](../archivum-api-hexagonal/database/seeders/StaffSeeder.php); [CustomersSeeder.php](../archivum-api-hexagonal/database/seeders/CustomersSeeder.php) | Usar únicamente identidades ficticias. Esta guía crea su producto por HTTP, sin modificar los sembradores. |
| Mostrar o cambiar campos HTTP | [PaymentResource.php](../archivum-api-hexagonal/src/Payments/Payments/Infrastructure/Http/Resources/PaymentResource.php) | Aquí se traducen estados y se expone `monto_cobrado` entero, no el decimal de PayPal. |
| Verificar un cambio del adaptador | [PayPalGatewayTest.php](../archivum-api-hexagonal/tests/Feature/Payments/Gateways/PayPalGatewayTest.php) | Cubrir aceptación, rechazo, caída de red, importe, sandbox e idempotencia con HTTP simulado. |
| Verificar API, reglas y aislamiento | [PaymentEndpointsTest.php](../archivum-api-hexagonal/tests/Feature/Payments/PaymentEndpointsTest.php); [PaymentsSchemaTest.php](../archivum-api-hexagonal/tests/Feature/Payments/PaymentsSchemaTest.php) | Ejecutar contra PostgreSQL de pruebas. Si cambia el contrato HTTP, regenerar [openapi/v1.json](../archivum-api-hexagonal/openapi/v1.json) con `composer openapi`. |

La explicación del diseño y la comparación de proveedores están separadas en [arquitectura](architecture.md), [investigación técnica](payment-gateways.md) e [informe comparativo](investigacion-1.md).

## Cómo explicarlo en la exposición

> El servidor toma el precio guardado del producto y calcula el depósito, sin aceptar el monto que envíe el navegador. En este ejemplo, una presentación de ₡15 000 requiere un depósito de ₡7 500, que con el tipo ilustrativo de 500 se cobra como USD 15,00. La aprobación del comprador en PayPal todavía no es la captura del dinero. Al consultar el pago por GET, nuestro servidor verifica la orden en PayPal y captura si está aprobada; solo una captura completada acredita el pedido. Las consultas repetidas conservan el mismo pago y no vuelven a cobrar el depósito confirmado.
