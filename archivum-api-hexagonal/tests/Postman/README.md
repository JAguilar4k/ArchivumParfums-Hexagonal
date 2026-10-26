# Pruebas HTTP con Postman

Este cliente de pruebas recorre la API desde fuera, a través de su adaptador HTTP. Vive en `tests/`, separado de `src/`: no introduce dependencias de Postman en Domain, Application ni Infrastructure. Los datos iniciales pertenecen a los seeders de `database/seeders`; la colección los utiliza mediante la API y crea datos ficticios adicionales.

Los JSON están preparados en esta carpeta del repositorio; **no están importados en la aplicación Postman**. Puedes usarlos como guion para crear pestañas manuales, igual que en Avance 1, sin abrir una cuenta de Postman. La importación queda como opción para quien inicie sesión.

## Preparar la API

1. Completa la [instalación de la API](../../README.md#instalación), incluidas las migraciones y la demo. Usa una base local de demostración con las tiendas y cuentas ficticias de los seeders.
2. Inicia el servidor y conserva abierta su terminal. Usa su dirección real; en los ejemplos siguientes es `http://localhost:8000`. Configura `APP_URL` en `.env` con esa dirección y reinicia después de `php artisan config:clear`.
3. Ten disponible la contraseña `ARCHIVUM_DEMO_PASSWORD` **usada al sembrar**. El ejemplo es `demo-archivum`, distinta de las contraseñas SQL y de las cuentas de ONVO o PayPal. Cambiar `.env` después de sembrar no cambia por sí solo las contraseñas de las cuentas existentes.

## Sin cuenta: pestañas manuales en Lightweight

En Postman de escritorio, continúa con **Lightweight API Client** sin iniciar sesión. Permite crear peticiones y consultar sus respuestas e historial local. Las colecciones y los entornos requieren sesión; por eso este recorrido manual no depende de importarlos. [Documentación oficial de Lightweight](https://learning.postman.com/latest-v-12/docs/getting-started/basics/using-api-client).

Para cada caso del guion:

1. Pulsa **New → HTTP** para abrir una pestaña. Puedes volver a una petición desde **History** para repetirla.
2. Selecciona el método **GET**, **POST**, **PATCH** o **DELETE** que indique la colección o la guía. Copia la URL y sustituye todos los marcadores por sus valores concretos antes de enviar.
3. En **Headers**, añade `Accept: application/json`. Para cuerpos JSON, añade `Content-Type: application/json` y usa **Body → raw → JSON**.
4. En los logins usa **Authorization → No Auth**. En las peticiones protegidas selecciona **Bearer Token** y pega únicamente el token de la persona correspondiente, sin comillas y sin escribir otra vez `Bearer`.
5. Copia el cuerpo JSON de la guía y reemplaza contraseña, UUID y demás valores. En los archivos de colección, método, URL, cabeceras y cuerpo están en `request.method`, `request.url`, `request.header` y `request.body.raw`. Este último es una cadena JSON: el cuerpo que se pega no debe conservar sus comillas externas ni las secuencias literales `\n`; las guías muestran los cuerpos ya formateados.
6. Pulsa **Send** y comprueba el código HTTP, el cuerpo y `X-Request-Id`. Copia los valores devueltos en las pestañas que los necesiten. No pegues los scripts de la colección: usan entornos y referencias que aquí se manejan a mano.

Por ejemplo, abre primero **GET** `http://localhost:8000/api/v1`, sin autorización. Debe devolver **200** con `{"nombre":"ArchivumParfums","version":"v1"}`. Después crea una pestaña **POST** a `http://localhost:8000/api/v1/sesiones`, con las cabeceras JSON y este cuerpo:

```json
{
  "correo": "duena@archivumparfums.test",
  "contrasena": "demo-archivum",
  "dispositivo": "Postman manual dueña"
}
```

Sustituye la contraseña si tu siembra utilizó otra. La respuesta **201** contiene `data.token`: cópialo en **Bearer Token** de una nueva pestaña **GET** `http://localhost:8000/api/v1/cuenta`. Debe responder **200**, con el correo de la dueña y `rol: "dueno"`. Para Investigación 1, abre otro login con `correo: "mariana@clientes.test"`, la contraseña de demo y `dispositivo: "Postman manual cliente"`; conserva su token separado y comprueba `rol: "cliente"` con otra petición a `/api/v1/cuenta`.

En las pestañas manuales **no dejes textos como `{{base_url}}` o `{{paypal_card_order_id}}`**: no hay un entorno que los resuelva. Usa esta correspondencia:

| Marcador del JSON | Valor que se copia manualmente |
| --- | --- |
| `base_url` | Dirección local de la API, por ejemplo `http://localhost:8000`, sin `/api/v1` ni barra final |
| `demo_password` | Contraseña local usada al sembrar las cuentas ficticias |
| `token_owner`, `token_customer` | `data.token` del login de la dueña o de Mariana, respectivamente |
| `onvo_perfume_id`, `paypal_perfume_id` | `data.id` al crear el perfume de esa pasarela |
| `onvo_variant_id`, `paypal_variant_id` | `data.id` al crear su presentación |
| Variables terminadas en `order_id` | `data.id` al crear **ese pedido**; mantener separados tarjeta aprobada, rechazada, SINPE y caso no admitido |
| Variables terminadas en `payment_id` | `data.id` al iniciar **ese pago**; no usar el ID externo del proveedor ni el token de la URL de retorno |
| Variables terminadas en `checkout_url` | `data.url_pago` para abrirla en el navegador; no es una URL de consulta de nuestra API |

Por ejemplo, donde el guion dice `/api/v1/cuenta/pedidos/{{paypal_card_order_id}}/pagos`, escribe `http://localhost:8000/api/v1/cuenta/pedidos/` seguido del UUID real del pedido y `/pagos`. Conserva el pedido y el pago aprobados de ONVO para su reembolso; no los sobrescribas con los otros escenarios. Los tokens quedan en tus pestañas e historial local: no copies ese historial a Git ni a capturas públicas.

Las guías de [ONVO](../../../docs/demostracion-onvo.md) y [PayPal](../../../docs/demostracion-paypal.md) contienen el recorrido completo, cuerpos y resultados esperados. Usar Postman sin cuenta no sustituye las cuentas sandbox del proveedor: el checkout sigue completándose en su navegador externo.

Para comprobar la primera petición sin variables, puedes pegar esto en **Tests** de `GET http://localhost:8000/api/v1` y pulsar **Send**:

```javascript
pm.test('API disponible', () => pm.response.to.have.status(200));
pm.test('Nombre de la aplicación', () => pm.expect(pm.response.json().nombre).to.eql('ArchivumParfums'));
pm.test('Versión de la API', () => pm.expect(pm.response.json().version).to.eql('v1'));
```

En la respuesta, selecciona **Body → Test Results**. Las tres comprobaciones se ejecutaron en Lightweight sin cuenta el 26/10/2026: [captura de los resultados](../../../docs/evidencia-investigacion-1/postman-sin-cuenta.png). Esa petición de salud no sustituye los escenarios completos de pagos.

## Opcional: importar con una sesión de Postman

Si prefieres colecciones y captura automática de variables, inicia sesión en Postman e importa la colección y el entorno correspondientes. Para Avance 1 son [la colección general](ArchivumParfums.postman_collection.json) y [su entorno](ArchivumParfums-local.postman_environment.json); selecciona **ArchivumParfums · demo local ficticia**. Las parejas de Investigación 1 están en la tabla de abajo.

Completa `base_url` en el entorno activo con la dirección local real, sin `/api/v1` ni barra final, y `demo_password` con la contraseña usada al sembrar. Los JSON versionados no contienen tokens válidos ni UUID de recursos creados durante el recorrido; los scripts los capturan desde las respuestas. Esta captura automática corresponde al modo importado, no a las pestañas manuales de Lightweight.

## Ejecutar Avance 1 en orden

Recorre los casos de la 01 a la 35 con **Send**: abre la petición importada o crea su pestaña manual con los datos del JSON. Revisa el estado, el cuerpo y los encabezados; si importaste la colección, revisa también los resultados de sus scripts. Las peticiones con errores intencionales deben mostrar el estado esperado, no 500.

| Peticiones | Comprobación |
| --- | --- |
| 01–04 | Salud, perfil y vitrinas públicas de ambas tiendas |
| 05–06 | Validación 422 y credenciales incorrectas 401 |
| 07–11 | Login de dos dueños y un colaborador, cuenta y perfil del dueño |
| 12–21 | Crear perfume y presentación, editar, rechazar duplicado y overflow, desactivar y reactivar; comprobar detalle público y precio |
| 22–24 | Búsqueda y paginación siguiendo los enlaces reales de la respuesta |
| 25–26 | Confirmar que existe el perfume de la otra tienda y ocultarlo al dueño ajeno con 404 |
| 27–30 | Verificar identidad del colaborador, rechazar sus operaciones con 403 y comprobar después que el perfume no cambió |
| 31–35 | Cerrar las tres sesiones y comprobar que los tokens reutilizados de colaborador y dueña reciben 401 |

En la colección importada, los scripts comprueban códigos HTTP y `X-Request-Id`, respuestas relevantes y errores RFC 9457; capturan los tokens, identificadores y enlaces que requieren los pasos siguientes. En Lightweight realiza esas comprobaciones y copias manualmente. No hay peticiones ocultas, migraciones ni reinicios de la base. Si falla un login o una creación, corrige esa petición antes de continuar.

Cada recorrido crea un perfume y una presentación y modifica esos datos. No elimina los registros al terminar. Repetirlo crea otros registros ficticios. Los tres tokens de ese recorrido se revocan al llegar al final; si lo interrumpes, puedes cerrar las sesiones con sus peticiones DELETE correspondientes. No publiques un entorno exportado con tokens capturados: el archivo versionado debe conservar vacíos los valores de ejecución.

## Alcance

Postman utiliza **la base configurada en el servidor HTTP**, no selecciona automáticamente la base de Pest. `composer test` ejecuta Unit, Feature y Architecture; no ejecuta esta colección. No se agrega Node.js como requisito del proyecto: para la prueba manual basta Postman y la API iniciada.

Este recorrido de Avance 1 cubre cuentas del personal, catálogo, vitrina, validaciones, permisos, aislamiento y revocación. Las nuevas colecciones de Investigación 1, descritas abajo, cubren los pagos. Para imágenes, consulta [el recorrido del README de la API](../../README.md#recorrido-básico-en-postman) y selecciona un archivo ficticio desde tu computadora. Servicios, horarios y citas se siguen en sus issues.

## Investigación 1: archivos y nombres del recorrido

| Pasarela | Colección | Entorno local |
| --- | --- | --- |
| ONVO | [24 peticiones](ArchivumParfums-ONVO.postman_collection.json) | [Entorno ONVO](ArchivumParfums-ONVO-local.postman_environment.json) |
| PayPal | [21 peticiones](ArchivumParfums-PayPal.postman_collection.json) | [Entorno PayPal](ArchivumParfums-PayPal-local.postman_environment.json) |

Cada recorrido es independiente de Avance 1 y de la otra pasarela. Sin cuenta, abre el JSON o su guía y copia cada petición en una pestaña manual. Con sesión, puedes importar los dos JSON de la fila correspondiente, seleccionar su entorno y configurar `base_url` y `demo_password`. Las guardas de las colecciones solo admiten localhost, `127.0.0.1` o `[::1]`, con puerto válido; en las pestañas manuales comprueba tú esa dirección antes de enviar.

Los nombres breves de los archivos te ayudan a localizar los pasos:

| Momento | ONVO | PayPal |
| --- | --- | --- |
| Preparación | Carpeta **01 · Preparación**; **04 · Elegir ONVO para la tienda** | Carpeta **A · Preparación y sesiones**; **06 · Dueña: activar PayPal** |
| Crear y pausar | **02 · Tarjeta aprobada** → **02 · Crear checkout (pausa)** y **03 · Repetir pago pendiente** | **B · Crear el pago y abrir PayPal** → **11 · Cliente: iniciar el depósito en USD** y **12 · Repetir el inicio y pausar** |
| Confirmar | **04 · Confirmar pago** y **05 · Ver depósito del pedido**, en **02 · Tarjeta aprobada** | **C · Confirmar el depósito** → **13 · Confirmar la captura en PayPal** y **16 · Verificar que no se acreditó dos veces** |
| Rechazo o SINPE | Carpetas **03 · Tarjeta rechazada** y **04 · SINPE Móvil** | **D · Verificar SINPE no admitido** → **18 · Verificar que PayPal no admite SINPE** |
| Reembolso o cierre | **05 · Reembolso** → **02 · Procesar reembolso** | **E · Cerrar sesiones**; el reembolso PayPal todavía no está implementado |

Las llaves ONVO y el Client ID/Secret PayPal se configuran **solo en el `.env` del servidor**, según las guías de [ONVO](../../../docs/demostracion-onvo.md) y [PayPal](../../../docs/demostracion-paypal.md). El entorno Postman contiene tokens de Archivum y referencias obtenidas por HTTP, todos vacíos al versionarse. La contraseña de demo es ficticia; no sirve para ingresar a ONVO o PayPal.

## Investigación 1: ejecutar con Send y pausar en el checkout

1. Ejecuta las peticiones de preparación: salud, login de dueña y cliente, selección de pasarela y creación del producto ficticio. La presentación cuesta ₡15.000 y el depósito predeterminado es ₡7.500.
2. Como cliente, crea el pedido y su pago. En Lightweight copia sus `data.id` por separado y conserva `data.url_pago`; con la colección importada, los scripts guardan esos valores en el entorno. Revisa cada respuesta antes de avanzar.
3. Repite el inicio pendiente con la petición de doble clic **antes de pagar**: debe conservar el mismo intento. Abre después `data.url_pago` en el navegador y completa el checkout sandbox. Las peticiones indican dónde pausar; no se completa el pago con solo enviar la petición de creación.
4. Regresa a Postman para consultar el pago y el pedido. Solo `confirmado` con los importes esperados aprueba el escenario; un estado pendiente o un 202 no se presenta como cobro confirmado.
5. En ONVO, continúa con los pedidos separados de tarjeta rechazada y SINPE. El reembolso utiliza el **primer pedido aprobado**, antes de encargarlo. Sus variables no se sobrescriben al probar los otros escenarios.
6. En PayPal, el tipo de cambio de **500 CRC/USD es didáctico**: el depósito se cobra como USD 15,00 y la API expone `monto_cobrado: 1500` centavos. La aprobación del comprador precede a la captura del servidor. Para acreditar tarjeta, selecciónala como fuente de fondos o usa invitado si la cuenta lo permite; pagar con saldo no acredita ese requisito.

Usa **Send, una petición a la vez**, tanto en Lightweight como en el modo importado. En Lightweight la pausa consiste en dejar de enviar peticiones y completar el navegador antes de continuar. Si importaste la colección y usas Runner, sus pausas llaman `pm.execution.setNextRequest(null)`; ese método no abre el navegador ni controla Send. Retoma manualmente desde la petición indicada después del checkout.

En la colección importada, si faltan variables, la guarda llama `pm.execution.skipRequest()`: la petición se omite, queda sin respuesta ni pruebas y **no cuenta como aprobada**. En Runner, omitir una petición puede continuar con la siguiente. En Lightweight no se ejecutan esas guardas de la colección: verifica tú token, UUID y cuerpo antes de pulsar Send. El comportamiento de los scripts está descrito en la [referencia oficial de Postman](https://learning.postman.com/docs/tests-and-scripts/write-scripts/postman-sandbox-reference/pm-execution/).

Ante **202**, conserva el UUID local para consultar **ese mismo pago**: no se cuenta como cobro confirmado ni se borra el intento para crear otro. En la colección importada, los scripts preservan su referencia y el test estricto detiene el Runner. En Lightweight copia el ID desde la respuesta y vuelve a consultar con GET; no cambies de pedido para resolver la incertidumbre. Para un ensayo nuevo, conserva antes las referencias del anterior; con sesión, usa otro entorno limpio. Crea los pedidos cerca del ensayo porque tienen vencimiento.

## Guion de cinco minutos

Prepara antes las cuentas sandbox, la API, los logins, el catálogo y las ventanas del navegador. Durante la exposición muestra ONVO aprobado/rechazado y SINPE, cambia la configuración a PayPal, completa aprobación y captura, enseña el CI y defiende una decisión del informe. Ensaya el reembolso ONVO por separado para poder explicarlo y ejecutarlo si lo solicitan. Los tiempos de los proveedores y la elegibilidad de tarjeta deben comprobarse con las cuentas efectivas; no se sustituyen por resultados simulados.
