# ONVO: configuración y demostración paso por paso

ArchivumParfums · Investigación 1 · Revisión de código y fuentes: **25/10/2026**.

Esta guía permite preparar ONVO sin depender de la guía de PayPal. Recorre un pedido ficticio de una botella de ₡15.000, su depósito de ₡7.500, tarjeta aprobada y rechazada, SINPE Móvil y devolución. **La ejecución contra ONVO sigue pendiente:** todavía no se ha creado la cuenta sandbox del equipo. Los ejemplos de respuesta describen el contrato esperado; no son comprobantes de cobros ejecutados.

## 1. Preparar la API y los datos ficticios

1. Abre la carpeta `archivum-api-hexagonal/` de la copia o worktree que contiene `feature/pagos`. Todos los comandos de esta guía se ejecutan allí. Puedes comprobar la rama con `git branch --show-current`; no confundas esa copia con otra rama del repositorio.
2. Necesitas PHP compatible con el proyecto, Composer y PostgreSQL 18. Si es una instalación nueva, configura el servidor PostgreSQL y ejecuta:

   ```powershell
   composer install
   php artisan archivum:install
   ```

   El segundo comando permite completar interactivamente la preparación si el instalador de Composer no pudo pedir la contraseña en Windows. No hace falta repetirlo si la instalación ya quedó lista.
3. Revisa el archivo **local** `.env`: `APP_ENV=local`, la dirección HTTP en `APP_URL`, `DB_HOST`, `DB_PORT`, la base de desarrollo, `archivum_app` con `DB_PASSWORD` y `archivum_owner` con `DB_ADMIN_PASSWORD`. El puerto PostgreSQL es el de tu instalación; no se copia el puerto HTTP. La conexión de peticiones usa `archivum_app`; las migraciones usan `pgsql_admin`.
4. Si ya estaba instalado, aplica únicamente las migraciones pendientes:

   ```powershell
   php artisan migrate --database=pgsql_admin
   ```

5. La siembra inicial crea cuentas ficticias. Si faltan y estás usando una base local dedicada a la demostración, fija `ARCHIVUM_DEMO_PASSWORD` en `.env`, limpia configuración y ejecuta deliberadamente `php artisan db:seed`. Volver a sembrar repone contraseñas y ajustes de las filas de demo: hazlo **antes** de configurar la pasarela y preparar el recorrido, no entre sus pasos. No necesitas borrar tablas ni usar `migrate:fresh`.

Las cuentas que usaremos son:

| Rol | Correo ficticio | Contraseña |
| --- | --- | --- |
| Dueña | `duena@archivumparfums.test` | El valor de `ARCHIVUM_DEMO_PASSWORD` usado al sembrar. |
| Cliente | `mariana@clientes.test` | El mismo valor de demostración. |

La tienda sembrada tiene el slug `archivumparfums`. El ejemplo del proyecto usa la contraseña local `demo-archivum`; si elegiste otra, usa la tuya. Estas cuentas HTTP son distintas de la cuenta de ONVO y de los roles PostgreSQL.

## 2. Obtener las credenciales de prueba de ONVO

1. Abre el [sitio oficial de ONVO](https://www.onvopay.com/) y registra la cuenta del equipo siguiendo su formulario. Esta guía no crea cuentas ni solicita afiliación comercial por ustedes.
2. Entra al Dashboard y ubica las **API keys de prueba**. La [documentación de autenticación](https://docs.onvopay.com/authentication), consultada el 25/10/2026, indica que se generan automáticamente al registrar la cuenta; no ofrece un plazo garantizado para completar todo el trámite.
3. Copia la llave **secret** de prueba al archivo `.env` local. Debe empezar por `onvo_test_`. El adaptador rechaza otros prefijos. No copies la llave en una solicitud de Postman dirigida a Archivum, en GitHub, en capturas ni en el informe.
4. Conserva por separado la llave pública de prueba si la cuenta la muestra. El recorrido actual usa checkout hospedado y el adaptador necesita la secreta del servidor; no hay que programar un formulario de tarjeta propio para esta demo.

No cambies a modo de dinero real. Las tarjetas y los teléfonos de prueba de los pasos 8 y 10 pertenecen al simulador oficial.

## 3. Editar `.env`, limpiar configuración y arrancar

Abre `.env` en tu editor y completa este bloque. Las líneas vacías representan valores que tú introducirás **solo en ese archivo**:

```dotenv
APP_URL=http://localhost:8000
PAYMENTS_RETURN_URL=http://localhost:8000/api/v1
PAYMENTS_CANCEL_URL=http://localhost:8000/api/v1
ONVO_SECRET_KEY=
ONVO_PUBLISHABLE_KEY=
ONVO_WEBHOOK_SECRET=
```

- Pega la llave secreta de prueba en `ONVO_SECRET_KEY`.
- `ONVO_PUBLISHABLE_KEY` puede contener la pública de prueba; el checkout hospedado de esta guía no la utiliza desde Archivum.
- Deja `ONVO_WEBHOOK_SECRET` vacío para Investigación 1: este corte confirma por consulta del servidor. No necesitas un túnel para recibir webhooks ni registrar una URL de webhook de Archivum.
- Las URLs de retorno y cancelación son navegación del comprador. Volver a `/api/v1` muestra la raíz de la API; **no** confirma el pago. Si ONVO no acepta el retorno local en tu cuenta, configura una URL HTTPS que controles y que pueda abrir el navegador; no inventes una ruta que marque el pago como confirmado.
- No existe una variable `PAYMENT_GATEWAY=onvo` que sustituya el ajuste de la tienda: ese cambio se realiza en el paso 5.

Después de guardar:

```powershell
php artisan config:clear
php artisan serve --host=localhost --port=8000 --no-reload
```

Deja el servidor abierto. Si estaba ejecutándose antes de editar `.env`, detenlo con Ctrl+C y vuelve a iniciarlo después de limpiar configuración. Para usar otro puerto, cambia conjuntamente `APP_URL`, ambas URLs y `base_url` del cliente HTTP. El contrato se puede consultar en `http://localhost:8000/docs/api`.

## 4. Preparar Postman y obtener los dos tokens

Crea un entorno local de Postman con estas variables. Mantén contraseñas y tokens como valores locales privados:

| Variable | Valor inicial |
| --- | --- |
| `base_url` | `http://localhost:8000` |
| `demo_password` | La contraseña ficticia que elegiste al sembrar. |
| `token_duena`, `token_cliente` | Vacíos hasta iniciar sesión. |
| `perfume_id`, `presentacion_id`, `pedido_id`, `pago_id` | Vacíos hasta obtener cada respuesta. |

En todas las solicitudes envía `Accept: application/json`. En las que tengan cuerpo JSON, selecciona **Body → raw → JSON** y envía `Content-Type: application/json`.

Primero inicia sesión como dueña, **sin** Authorization:

```http
POST {{base_url}}/api/v1/sesiones
```

```json
{
  "correo": "duena@archivumparfums.test",
  "contrasena": "{{demo_password}}",
  "dispositivo": "Postman demo ONVO dueña"
}
```

La respuesta esperada es **201** con `data.token` y `data.tipo = "Bearer"`. Copia `data.token` a `token_duena`, sin imprimirlo ni compartirlo. Repite la misma ruta con este cuerpo y guarda el nuevo token en `token_cliente`:

```json
{
  "correo": "mariana@clientes.test",
  "contrasena": "{{demo_password}}",
  "dispositivo": "Postman demo ONVO cliente"
}
```

En adelante, “token de dueña” significa seleccionar **Authorization → Bearer Token → `{{token_duena}}`**; para cliente usa `{{token_cliente}}`. `GET /api/v1/cuenta` con cada token permite revisar qué cuenta inició sesión. No uses el token de dueña para crear o pagar pedidos del cliente.

## 5. Elegir ONVO por configuración de la tienda

Con token de dueña:

```http
PATCH {{base_url}}/api/v1/panel/tienda
```

```json
{
  "ajustes": {
    "pasarela_por_defecto": "onvo"
  }
}
```

Espera **200** y comprueba `data.ajustes.pasarela_por_defecto = "onvo"`. Puedes verificarlo con `GET /api/v1/panel/tienda` usando el mismo token. No envíes `business_id`, un identificador de tienda en el panel ni la llave de ONVO en el cuerpo: la tienda sale de la sesión y los secretos salen de configuración del servidor.

Esta petición es el cambio de proveedor. No se edita ningún controlador ni el caso de uso `StartPayment`.

## 6. Crear un producto y su presentación de ₡15.000

Con token de dueña:

```http
POST {{base_url}}/api/v1/panel/perfumes
```

```json
{
  "marca": "Marca ficticia de clase",
  "nombre": "Aroma demo ONVO",
  "concentracion": "EDP",
  "descripcion": "Producto ficticio para la demostración sandbox.",
  "activo": true
}
```

Espera **201** y guarda `data.id` en `perfume_id`. Luego crea la presentación:

```http
POST {{base_url}}/api/v1/panel/perfumes/{{perfume_id}}/presentaciones
```

```json
{
  "ml": 100,
  "precio_manual": 15000
}
```

Espera **201**, guarda `data.id` en `presentacion_id` y comprueba `data.precio_venta = 15000` y `data.activa = true`. `precio_manual` permite fijar el precio de la demo sin preparar un proveedor comercial. No uses `precio_venta` como campo de escritura: no es el campo que acepta esa petición.

## 7. Crear el pedido e iniciar el checkout con tarjeta

Cambia al token de cliente:

```http
POST {{base_url}}/api/v1/tiendas/archivumparfums/pedidos
```

```json
{
  "lineas": [
    {
      "presentacion_id": "{{presentacion_id}}",
      "cantidad": 1
    }
  ]
}
```

Espera **201** y guarda `data.id` en `pedido_id`. Con el porcentaje predeterminado del proyecto, comprueba:

```json
{
  "estado": "pendiente_de_pago",
  "subtotal": 15000,
  "por_cobrar_ahora": 7500,
  "pagado": 0,
  "saldo": 7500
}
```

Son campos dentro de `data`; la respuesta incluye más información. No envíes monto, precio ni porcentaje para decidir lo que se cobrará. Inicia el pago del pedido recién creado, dentro de su plazo:

```http
POST {{base_url}}/api/v1/cuenta/pedidos/{{pedido_id}}/pagos
```

```json
{
  "medio": "tarjeta"
}
```

La respuesta normal es **201**. Guarda `data.id` en `pago_id` y abre `data.url_pago` en el navegador. Debe indicar `pasarela = "onvo"`, `estado = "pendiente"`, `proposito = "deposito"`, `monto = 7500`, `moneda_cobrada = "CRC"` y `monto_cobrado = 7500`.

**Diferencia de unidades:** en la API propia esos dos montos son colones enteros. El adaptador manda a ONVO `unitAmount = 750000`, en céntimos. No cambies el precio ni multipliques el importe desde Postman.

Repetir esa petición mientras existe un intento pendiente devuelve el mismo pago, normalmente con **200**. Si se perdió comunicación puede responder **202** con `consulta_pendiente = true` y `Retry-After: 30`: conserva el identificador; no abras un pedido nuevo para evadir esa incertidumbre.

## 8. Completar el pago y consultar el resultado desde Archivum

En el checkout **de prueba** usa la Visa `4242424242424242`, fecha futura —por ejemplo, 12/2028—, CVC ficticio válido y nombre ficticio. Es un dato publicado por [ONVO Testing](https://docs.onvopay.com/payments/testing), consultado el 25/10/2026. No ingreses una tarjeta real.

Tras aprobar, vuelve a Postman con token de cliente:

```http
GET {{base_url}}/api/v1/cuenta/pagos/{{pago_id}}
```

- **200 + `estado = "confirmado"`**: la consulta del servidor comprobó el éxito. No basta con la pantalla de ONVO ni con la URL de retorno.
- **200 + `estado = "pendiente"`**: el proveedor todavía informa un estado intermedio; consulta de nuevo más adelante.
- **202 + `consulta_pendiente = true`**: no se logró resolver el resultado. Respeta `Retry-After` y conserva el mismo intento.

Comprueba el pedido con:

```http
GET {{base_url}}/api/v1/cuenta/pedidos/{{pedido_id}}
```

Después de la confirmación, debe mostrar `estado = "confirmado"`, `pagado = 7500`, `por_cobrar_ahora = 0` y `saldo = 7500`. Repetir la consulta del pago no debe duplicar lo pagado. Para esta devolución de ejemplo, no prepares decants ni marques botellas como encargadas.

## 9. Demostrar tarjeta rechazada

1. Crea **otro pedido** repitiendo el paso 7 y conserva por separado sus identificadores; no reutilices el pedido ya aprobado.
2. Inicia su pago con `medio = "tarjeta"` y abre su nueva `url_pago`.
3. Usa la tarjeta oficial de rechazo `4000000000000002`, fecha futura y datos ficticios.
4. Consulta el nuevo `pago_id` desde Archivum. Un rechazo de tarjeta puede dejar la intención remota reutilizable: el adaptador cierra el checkout y la intención y verifica ambos antes de declarar un pago terminal `fallido`.
5. Si esa verificación no puede completarse, verás pendiente/202; no lo presentes como rechazo terminal confirmado y no intentes otro cobro todavía.
6. Verifica que el pedido siga sin pago confirmado. Solo un intento cerrado permite iniciar otro checkout con seguridad.

El número `4000000000000119` simula error del procesador según el mismo manual. No es una demostración de desconexión de red: para esa falla usa las pruebas simuladas del paso 12.

## 10. Demostrar SINPE Móvil sin transferencia real

1. Crea un tercer pedido ficticio y conserva sus IDs aparte.
2. Como cliente, inicia el pago con el mismo endpoint del paso 7, cambiando únicamente:

   ```json
   {
     "medio": "sinpe_movil"
   }
   ```

3. Abre su checkout. El adaptador restringe esta sesión a `mobile_number`. Usa el teléfono ficticio **`+50688888888`**, que el manual oficial asocia con éxito aproximadamente 15 segundos después. Si el formulario separa prefijo y teléfono, elige Costa Rica/+506 e ingresa los ocho dígitos correspondientes. Completa los demás datos con valores ficticios en el formato que el sandbox solicite.
4. No abras una aplicación bancaria ni transfieras dinero. El modo de prueba simula el ingreso.
5. Consulta el pago y luego el pedido desde Archivum, igual que en el paso 8.

Otros escenarios oficiales son `+50688884444` —simulación exitosa tras unos seis minutos— y `+50688889521` —no llega transferencia, el estado no avanza—. El segundo demuestra una espera, **no** un rechazo bancario inmediato. Estos escenarios y el comportamiento efectivo del checkout todavía deben comprobarse con la cuenta del equipo. [Pruebas ONVO](https://docs.onvopay.com/payments/testing), consulta 25/10/2026.

En el flujo real ONVO asocia una intención por identificación y monto exactos, y solo soporta CRC; por eso el pago se confirma desde el servidor. La [guía de SINPE Móvil](https://docs.onvopay.com/payments/sinpe-mobile), consultada el 25/10/2026, describe producción y recomienda webhooks. Para este corte el enunciado exige la consulta de estado ya implementada. No conviertas requisitos de producción en instrucciones para usar datos personales o fondos reales durante la demo.

## 11. Reembolsar el depósito aprobado con tarjeta

Usa el **primer pedido aprobado** y sus identificadores, antes de encargar su botella. Si sobrescribiste `pedido_id` para otros escenarios, repón el de ese primer pedido.

Como cliente, sin monto en el cuerpo:

```http
POST {{base_url}}/api/v1/cuenta/pedidos/{{pedido_id}}/cancelacion
```

La respuesta debe ser **200** con el pedido cancelado. La cancelación registra una devolución pendiente; no prueba que el dinero ya se devolvió. Cambia al token de dueña y procesa lo registrado por el servidor:

```http
POST {{base_url}}/api/v1/panel/pedidos/{{pedido_id}}/reembolsos
```

No envíes `monto`, ID de pago ni datos del proveedor. La respuesta es **200** con una lista `data`; para el ejemplo exitoso su primera fila tiene `monto = 7500` y `estado = "confirmado"`. Si sigue pendiente, repite esta misma operación más adelante: cuando ya existe referencia de ONVO se consulta esa devolución, sin crearla otra vez. Si un POST perdió su respuesta y no dejó referencia conocida, el sistema conserva pendiente y evita reenviarlo a ciegas.

Como cliente puedes consultar nuevamente el pago y pedido originales: tras la devolución confirmada el pago muestra `reembolsado`, el pedido sigue cancelado y `pagado` disminuye. No modifiques `estado`, `pagado` o el registro de reembolso directamente en la base. La [API de reembolsos ONVO](https://docs.onvopay.com/payments/refunds), consultada el 25/10/2026, vincula la devolución a la intención original; Archivum hace esa traducción dentro del adaptador.

## 12. Probar fallas y doble cobro sin cuenta ONVO

En otra terminal, dentro de la API, estas pruebas usan `Http::fake()` y no llaman al proveedor:

```powershell
php vendor/bin/pest tests/Feature/Payments/Gateways/OnvoGatewayTest.php --compact
php vendor/bin/pest tests/Feature/Payments/PaymentEndpointsTest.php --compact
php vendor/bin/pest tests/Feature/Payments/ConcurrentPaymentTest.php --compact
```

La primera cubre creación, importes, estados, rechazo y cierre seguro, error de comunicación y devoluciones. La segunda prueba permisos, persistencia y fallos entre pasos. La tercera usa dos procesos y PostgreSQL para comprobar un solo intento y un solo POST. Los tests de funcionalidad recrean tablas en `archivum_hexagonal_testing`: [`phpunit.xml`](../archivum-api-hexagonal/phpunit.xml#L33) fija `DB_DATABASE` y deja `DB_URL` vacío, sin `force="true"`. Por eso una variable de entorno ya definida en la terminal puede prevalecer; confirma que no apunte a una base que debas conservar, como explica el [README de la API](../archivum-api-hexagonal/README.md#L151).

Para ejecutar solo el error de comunicación del adaptador:

```powershell
php vendor/bin/pest tests/Feature/Payments/Gateways/OnvoGatewayTest.php --filter="communication errors" --compact
```

Las pruebas verdes son evidencia automática. No equivalen a haber cobrado con la cuenta sandbox ni reemplazan la demostración de tarjeta y SINPE del equipo.

## 13. Dónde modificar cada cosa

Las líneas son referencias orientativas del código revisado el 25/10/2026; si cambian, busca el método indicado. Cambiar credenciales, URLs o la pasarela de una tienda no requiere cambiar PHP.

| Necesidad | Archivo o petición | Líneas/método | Cambio concreto |
| --- | --- | --- | --- |
| Llave privada y URLs locales | `.env` dentro de la API | Bloque equivalente a [`.env.example`](../archivum-api-hexagonal/.env.example#L39), líneas 39–43 | Completar valores locales y ejecutar `config:clear`; no versionar secretos. |
| Elegir ONVO | `PATCH /api/v1/panel/tienda` | `ajustes.pasarela_por_defecto` | Enviar `"onvo"` con token de dueña. |
| Cómo se leen las variables y los tiempos | [`config/payments.php`](../archivum-api-hexagonal/config/payments.php#L5) | 5–14 | Mapea `.env`; timeout de 10 s y conexión de 3 s. |
| Construcción del adaptador | [`PaymentsServiceProvider.php`](../archivum-api-hexagonal/src/Payments/Payments/Infrastructure/PaymentsServiceProvider.php#L35) | `register()`, 35–52 aproximadamente | Inyecta configuración. No pegar llaves en esta clase. |
| Payload de checkout y tarjeta/SINPE | [`OnvoGateway.php`](../archivum-api-hexagonal/src/Payments/Payments/Infrastructure/Gateways/Onvo/OnvoGateway.php#L45) | `create()`, 45–74 | `lineItems`, `paymentMethodTypes`, referencias y URLs. Cambiar solo al adaptar un contrato de ONVO verificado. |
| Conversión a céntimos | [`GatewayMoney.php`](../archivum-api-hexagonal/src/Payments/Payments/Infrastructure/Gateways/GatewayMoney.php#L13) | `colonesToMinor()`, 13–20 | CRC enteros × 100; nunca cambiar unidades en el controlador. |
| Consulta y cierre tras rechazo | [`OnvoGateway.php`](../archivum-api-hexagonal/src/Payments/Payments/Infrastructure/Gateways/Onvo/OnvoGateway.php#L85) | `status()` y `closeCheckout()`, 85–165 | Traduce estados y verifica cierre remoto antes de permitir otro intento. |
| Devolución a ONVO | [`OnvoGateway.php`](../archivum-api-hexagonal/src/Payments/Payments/Infrastructure/Gateways/Onvo/OnvoGateway.php#L167) | `refund()`, 167–208 | Pide o reconsulta la devolución por referencia, con monto calculado en el servidor. |
| Persistencia del intento y doble clic | [`StartPayment.php`](../archivum-api-hexagonal/src/Payments/Payments/Application/UseCases/StartPayment.php#L28) | `execute()`, 28–91 | Guarda el intento antes del POST. No eliminar esa protección para “reintentar” una falla. |
| Aplicar confirmación y pedido | [`ObservePayment.php`](../archivum-api-hexagonal/src/Payments/Payments/Application/UseCases/ObservePayment.php#L22) | `execute()`, 22–57 | Consulta autorizada y cambios financieros transaccionales. |
| Procesar devoluciones ya calculadas | [`ProcessOrderRefunds.php`](../archivum-api-hexagonal/src/Payments/Payments/Application/UseCases/ProcessOrderRefunds.php#L23) | `execute()`, 23–95 aproximadamente | Registra el intento de devolución antes de llamar al proveedor. |
| Cuerpo permitido al iniciar pago | [`StorePayment.php`](../archivum-api-hexagonal/src/Payments/Payments/Infrastructure/Http/Requests/StorePayment.php#L18) | `rules()`, 18–21 | Solo `medio`: `tarjeta` o `sinpe_movil`. |
| Nombres y estados JSON | [`PaymentResource.php`](../archivum-api-hexagonal/src/Payments/Payments/Infrastructure/Http/Resources/PaymentResource.php#L17) | `toArray()`, 17–33 | Traducción a `monto`, `url_pago`, `estado`, `consulta_pendiente`, etc. |
| Rutas de pago y permisos | [`routes.php`](../archivum-api-hexagonal/src/Payments/Payments/Infrastructure/Http/routes.php#L8) | 8–14 | Rutas de cliente y de dueña. La tienda del panel sale de la sesión. |
| Mensajes en español | [`lang/es/payments.php`](../archivum-api-hexagonal/lang/es/payments.php#L5) | 5–19 | Editar traducciones; no mensajes incrustados en el dominio. |

## 14. Qué registrar y cómo resolver problemas comunes

| Resultado | Comprobación siguiente |
| --- | --- |
| 401 al iniciar sesión | Revisa correo, contraseña usada al sembrar y `dispositivo`. |
| 403 al crear/pagar pedido | Revisa que el Bearer sea el de cliente; el de dueña se reserva al panel. |
| 404 en pedido o pago | Revisa ID y propiedad; otro cliente o tienda recibe la misma respuesta. |
| 409 al iniciar pago | Revisa que el pedido sea pagable, no haya vencido y no existan devoluciones por resolver. |
| 422 de configuración | Revisa llave de prueba local y configuración limpia. No escribas la llave en el cuerpo de la petición. |
| 202 con intento incierto | Conserva `pago_id`; consulta el mismo recurso. Si se perdió la referencia remota, revisa el Dashboard con los identificadores internos y concilia antes de intentar otro cobro. |
| Regreso al navegador sin pedido confirmado | Ejecuta `GET /cuenta/pagos/{pago}` con token de cliente; el retorno por sí solo no paga. |
| SINPE sigue pendiente | Revisa número simulado y el tiempo de ese escenario. No hagas una transferencia real. |

Para la evidencia final registra fecha, escenario, ID ficticio del pedido/pago, resultado del proveedor y resultado de la consulta de Archivum; evita llaves, tokens, contraseñas y datos personales en capturas. Mantén como pendiente cada escenario que todavía no se haya ejecutado. El [informe comparativo](investigacion-1.md) y el [expediente técnico](payment-gateways.md) explican las decisiones de costos y seguridad por separado.

## 15. Cómo explicarlo en la exposición

Puedes defender el flujo con estas cinco ideas, señalando el código del mapa anterior:

1. **El servidor decide cuánto cobrar.** Una botella de ₡15.000 genera un depósito de ₡7.500 con las reglas del pedido. El cuerpo del pago solo elige el medio.
2. **Primero se guarda el intento.** `StartPayment` conserva un pago pendiente antes de llamar a ONVO; así un doble clic o una respuesta perdida no autoriza otro cobro.
3. **ONVO recibe su propio formato.** El adaptador manda ₡7.500 como `750000` céntimos y abre un checkout hospedado para tarjeta o SINPE Móvil. El resto de la aplicación usa el mismo puerto de pagos.
4. **La vuelta del navegador no confirma.** `ObservePayment` pregunta a ONVO, verifica referencia, moneda e importe y acredita pago y pedido en una transacción. Si no puede conocer el resultado, conserva pendiente.
5. **Cancelar y devolver son pasos diferentes.** La cancelación registra lo que corresponde devolver; la dueña procesa ese registro. Las referencias y el intento persistido evitan duplicar la devolución.

Como cierre: “Podemos explicar y probar el código con HTTP simulado; la evidencia de cobro con la cuenta sandbox se registra cuando se ejecuta, sin presentarla como realizada antes”.
