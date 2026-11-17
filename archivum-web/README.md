# archivum-web

Cliente web de ArchivumParfums: una SPA en Vue 3 con TypeScript que consume solo la API de [`archivum-api-hexagonal`](../archivum-api-hexagonal/). No tiene acceso a la base de datos ni reglas de negocio propias: precios, montos y disponibilidad siempre vienen de la API.

## Requisitos

| Herramienta | Versión |
|---|---|
| Node.js | 26 LTS (26.11 o superior dentro de la 26, fijada en `engines` de `package.json`) |
| npm | 11, el que trae Node.js 26 |
| API | `archivum-api-hexagonal` en ejecución, según [su README](../archivum-api-hexagonal/README.md) |

`node -v` y `npm -v` muestran lo instalado. Con otra versión de Node.js, `npm install` avisa con `EBADENGINE`.

## Instalación

Los comandos sirven en PowerShell, en Git Bash y en una terminal de Linux o macOS.

1. Entrar a la carpeta del cliente, desde la raíz del repositorio:

   ```bash
   cd archivum-web
   ```

2. Instalar las dependencias exactas de `package-lock.json`:

   ```bash
   npm ci
   ```

3. Copiar el ejemplo de variables de entorno:

   ```bash
   cp .env.example .env              # Git Bash, Linux o macOS
   Copy-Item .env.example .env       # PowerShell
   ```

   Si la API corre en otra dirección o puerto, se cambia `VITE_API_URL` en `.env`.

4. Levantar el servidor de desarrollo, con la API ya en ejecución:

   ```bash
   npm run dev
   ```

   Vite muestra la dirección local de la SPA, por defecto `http://localhost:5173`.

## Variables de entorno

Vite solo expone al navegador las variables que empiezan con `VITE_`. Todas se declaran en `.env.example`, con su tipo en `env.d.ts`, y se leen desde `src/shared/config.ts`; ningún archivo del código tiene una dirección escrita. `.env` nunca se versiona.

| Variable | Ejemplo | Para qué sirve |
|---|---|---|
| `VITE_API_URL` | `http://localhost:8000/api/v1` | Dirección base de la API, con el prefijo `/api/v1`. |
| `VITE_DEFAULT_BUSINESS_SLUG` | `archivumparfums` | Tienda que abre la raíz del cliente (`/`); `archivumparfums` es la tienda piloto de los seeders. |

Las variables se leen al compilar: después de cambiar `.env`, se reinicia `npm run dev` o se vuelve a ejecutar `npm run build`.

## Comandos

| Comando | Qué hace |
|---|---|
| `npm run dev` | Levanta la SPA con recarga en caliente. |
| `npm run build` | Revisa los tipos y genera la versión de producción en `dist/`. |
| `npm run preview` | Sirve localmente la versión de `dist/`. |
| `npm run type-check` | Revisa los tipos con `vue-tsc` en modo estricto. |
| `npm run lint` | Revisa el código con ESLint y las reglas de Vue y TypeScript, sin modificarlo. |
| `npm run lint:fix` | Corrige con ESLint lo que se puede corregir solo. |
| `npm run test` | Ejecuta las pruebas unitarias con Vitest una vez. |
| `npm run test:watch` | Ejecuta las pruebas cada vez que cambia un archivo. |
| `npm run api:types` | Regenera `src/api/schema.ts` desde `../archivum-api-hexagonal/openapi/v1.json`. |
| `npm run api:check` | Falla si `src/api/schema.ts` no coincide con `openapi/v1.json`. |

Antes de abrir un pull request pasan `npm run lint`, `npm run type-check`, `npm run test`, `npm run build` y `npm run api:check`.

## Integración continua

El job `Web` de [`ci.yml`](../.github/workflows/ci.yml) valida el cliente en cada pull request y en cada push a `main`, junto al job `Quality` de la API. Con la versión de Node.js de `engines` y la caché de npm, ejecuta `npm ci`, `npm run api:check`, `npm run lint`, `npm run type-check`, `npm run test`, `npm run build` y `npm audit --audit-level=high`: falla si los tipos no coinciden con `openapi/v1.json` o si hay una vulnerabilidad alta o crítica. Corre en todo pull request, aunque no toque `archivum-web/`, para que el check siempre se informe.

Dependabot revisa las dependencias de npm cada lunes, con el mismo prefijo `build` y el mismo grupo de versiones menores y parches que Composer ([`dependabot.yml`](../.github/dependabot.yml)).

## Tipos de la API

Ningún tipo de la API se escribe a mano. `openapi-typescript` genera `src/api/schema.ts` desde el contrato `openapi/v1.json` de la API, que genera Scramble, y `openapi-fetch` da el cliente tipado de `src/api/client.ts`. Una llamada que el contrato no permite (una ruta que no existe, un cuerpo sin un campo obligatorio o un atributo que la respuesta no tiene) hace fallar `npm run type-check`; las pruebas de `src/api/__tests__/contract.spec.ts` lo comprueban.

`src/api/schema.ts` se versiona y no se edita. El pull request que cambia un endpoint regenera los dos archivos y los incluye:

```bash
cd archivum-api-hexagonal && composer openapi      # openapi/v1.json
cd ../archivum-web && npm run api:types            # src/api/schema.ts
```

`npm run api:check` regenera los tipos en memoria y falla si difieren de los versionados.

El cliente `api` toma la dirección de `VITE_API_URL`, envía `Accept: application/json` y, cuando hay sesión, su token como `Authorization: Bearer`. Cada llamada devuelve `data` si la respuesta fue exitosa o `error` con el problema RFC 9457 que documenta el contrato (`Problem` o, en una validación fallida, `ValidationProblem` con `errores` por campo):

```ts
import { api } from '@/api/client'

const { data, error } = await api.GET('/tiendas/{tienda}/perfumes', {
  params: { path: { tienda: 'archivum' }, query: { nota: 'vainilla' } },
})
```

La sesión ([#126](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/issues/126)) registra una vez cómo leer su token con `setTokenProvider(() => token)` y qué hacer ante un 401 con `setUnauthenticatedHandler`; mientras no lo haga, las peticiones salen sin token.

## Sesión y permisos de navegación

El store `session` de Pinia (`src/features/session/store.ts`, [#126](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/issues/126)) guarda el token, su `vence_en` y la cuenta con su rol y su tienda:

- **Iniciar sesión:** `POST /sesiones` y luego `GET /cuenta`. Unas credenciales incorrectas responden 401 `credenciales-invalidas` y la vista "Iniciar sesión" (`/iniciar-sesion`) muestra su mensaje; un 422 marca los campos.
- **Dónde vive el token:** en `sessionStorage` (se pierde al cerrar la pestaña), nunca en la URL ni en los registros. La contraseña no se guarda en ningún lado.
- **Renovación:** `POST /sesiones/actual/renovacion` cinco minutos antes de `vence_en`; si no hay respuesta, se reintenta cada 30 segundos mientras el token siga vigente.
- **Un 401 con token** (vencido o revocado, que ya no se puede renovar) borra la sesión y lleva al inicio de sesión, que después regresa a la ruta que estaba abierta. Un 401 sin token, como una contraseña equivocada, no termina ninguna sesión.
- **Cerrar sesión:** `DELETE /sesiones/actual`, y se borran el token y la cuenta del navegador.

`installSession(router)` en `main.ts` conecta la sesión con el router. Cada ruta declara en `meta.roles` quién puede abrirla, y una ruta hija hereda los roles de su padre:

```ts
{ path: '/cuenta/citas', component: AppointmentsView, meta: { roles: ['cliente'] } }
{ path: '/panel', meta: { roles: ['dueno'] }, children: [...] }
{ path: '/panel/agenda', component: AgendaView, meta: { roles: ['dueno', 'colaborador'] } }
```

Una ruta sin `meta.roles` es pública. El visitante que abre una ruta con roles va al inicio de sesión; una cuenta cuyo rol no está en la lista ve "Sin permiso". Los guardias solo ayudan a navegar: los permisos los decide la API (#102, #104).

### Crear cuenta

La vista "Crear cuenta" (`/crear-cuenta`, `src/features/registration`, [#194](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/issues/194)) registra una cuenta de cliente con `POST /cuentas`: envía `nombre`, `correo`, `contrasena` y `acepta_privacidad`, nunca un rol ni una tienda.

- **Aviso de privacidad.** `PrivacyNotice.vue` dice qué datos guarda la cuenta, para qué y quién los ve, como los lista `docs/privacy.md` (#110). La casilla de aceptación empieza sin marcar y, sin marcarla, el formulario no se envía. La API registra la fecha y la versión aceptada (`ARCHIVUM_PRIVACY_NOTICE_VERSION`): si el texto cambia, esa versión también.
- **Errores.** La política de contraseñas la decide la API: un 422 marca cada campo con su mensaje, conserva lo escrito salvo la contraseña y lleva el foco al primer campo con error. Mientras la solicitud está en curso el botón lleva `aria-disabled`: conserva el foco y un segundo clic no manda otro registro.
- **Después del 201** se inicia sesión como en "Iniciar sesión" y se regresa a la ruta que el visitante quería abrir (`?redirect=`), o a la vitrina. Si la cuenta se creó pero el inicio de sesión falla, se abre "Iniciar sesión" con el correo ya escrito y el aviso "Se creó su cuenta": el correo pasa en memoria, nunca por la URL ni por el almacenamiento.
- **Accesibilidad (AAA).** Cada campo tiene una pista antes del control, ligada con `aria-describedby`: para qué sirven el nombre y el correo, y un consejo para la contraseña que no repite la política de la API (#194): si la contraseña no la cumple, el 422 dice junto al campo qué le falta. "Mostrar la contraseña" deja revisarla. Si se envía sin marcar la casilla, el foco va a la casilla, que lee su mensaje, y el mensaje se va al marcarla. El aviso de privacidad va enmarcado dentro del formulario, y "Ayuda y glosario" explica la cuenta en "Su cuenta".
- La contraseña solo vive en el formulario y en las dos solicitudes. "Iniciar sesión" enlaza a "Crear cuenta" con la misma ruta de regreso, y una cuenta con sesión que abre cualquiera de las dos vuelve al inicio.

## Vitrina y recomendaciones

Las páginas públicas de una tienda ([#128](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/issues/128)) llevan su slug en la ruta y no piden sesión. La raíz (`/`) abre la tienda de `VITE_DEFAULT_BUSINESS_SLUG`.

| Ruta | Vista | API |
|---|---|---|
| `/tiendas/:tienda` | Vitrina, paginada y con filtros | `GET /tiendas/{tienda}/perfumes` |
| `/tiendas/:tienda/perfumes/:perfume` | Detalle: notas por capa, etiquetas, presentaciones y decants | `GET /tiendas/{tienda}/perfumes/{perfume}` |
| `/tiendas/:tienda/recomendaciones` | Recomendador | `POST /tiendas/{tienda}/recomendaciones` |

Las tres comparten el encabezado de la tienda (`StoreLayout`, con `GET /tiendas/{tienda}`): nombre, logo, lugar de las citas y los enlaces a las páginas de la tienda. El nombre de la tienda no es un encabezado: el `h1` de cada página es su propio nombre, el mismo de la pestaña ("Vitrina", "Pedir una recomendación" o el nombre del perfume). Una tienda que no existe, o un perfume inactivo o de otra tienda, muestra "No encontrado", también en la pestaña.

- **Filtros en la URL.** Texto, familia, nota, momento, clima, ocasión y precio máximo viven en el query (`?nota=vainilla&momento=noche`), así que una búsqueda se puede compartir y el botón de volver la recupera. Una búsqueda nueva empieza en la primera página; un filtro que la API rechaza con 422 se marca en su campo.
- **Precios.** Se muestran tal como los manda la API, en colones enteros con separador de miles (`formatColones` de `src/shared/money.ts`). El navegador no calcula ningún precio.
- **Vitrina.** Cada filtro lleva una pista con un ejemplo, unida al campo con `aria-describedby`. Una línea con `role="status"` anuncia cuántos perfumes se encontraron, y después de cambiar de página el foco pasa al título de la lista ("Perfumes"), porque el enlace usado puede desaparecer. Los enlaces de las páginas dicen "Página anterior" y "Página siguiente".
- **Detalle.** Una ruta de enlaces (`nav` "Ubicación": Vitrina › perfume) dice dónde está la página. La concentración nunca va abreviada sola ("Eau de Parfum (EDP)") y cada cantidad marca "ml" como abreviatura de mililitros (`MlAmount`).
- **Recomendador.** Junto al campo de texto da un ejemplo y avisa que lo escrito se envía a un proveedor externo de inteligencia artificial y que no conviene incluir datos personales; la API igual reemplaza correos y teléfonos antes de enviarlo (R51). Un texto vacío no se envía: el campo se marca y toma el foco. Mientras espera la respuesta, que puede tardar hasta 10 segundos, el botón queda ocupado (`aria-disabled`) y una línea con `role="status"` anuncia la espera y después cuántos perfumes recomienda. Cada perfume muestra su razón como texto (nunca como HTML), sus precios y un enlace al detalle, con "Recomendado con inteligencia artificial" o "Recomendado con las reglas de la tienda" según `origen`. Un 429 dice en cuánto tiempo volver a intentar.
- **Ayuda y glosario** (`/ayuda`, en `src/features/help`). Explica cómo buscar, cómo pedir una recomendación, cómo funcionan los pedidos y los pagos (#130) y las citas de prueba con sus estados (#129), y define las palabras de perfumería y de los pedidos: bajo pedido, concentración, decant, familia olfativa, notas, pasarela de pago, por cobrar ahora, presentación, prueba de olores, saldo, ml, ₡ e inteligencia artificial. Cada término tiene un id, así que una página puede enlazarlo (`/ayuda#notas`) y el foco llega a ese término. El pie de todas las páginas enlaza la ayuda.

## Carrito, pedidos y pagos

El cliente arma un pedido y lo paga en línea ([#130](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/issues/130)). La API calcula todos los montos y confirma el pago; el navegador solo envía qué se pide y muestra lo que la API responde.

| Ruta | Vista | API |
|---|---|---|
| `/tiendas/:tienda/carrito` | Carrito de la tienda | `POST /tiendas/{tienda}/pedidos` |
| `/cuenta/pedidos` | "Mis pedidos" | `GET /cuenta/pedidos` |
| `/cuenta/pedidos/:pedido` | Un pedido, con "Pagar" y "Cancelar el pedido" | `GET /cuenta/pedidos/{pedido}`, `POST .../pagos`, `POST .../cancelacion` |
| `/pagos/procesando` | "Procesando": la pasarela regresa aquí | `GET /cuenta/pedidos/{pedido}` |

Las rutas de `/cuenta` y `/pagos` piden una sesión con el rol `cliente`.

- **Carrito.** El detalle de un perfume agrega cada presentación (botella bajo pedido) y cada decant. El carrito vive en el store `cart` de Pinia y en `localStorage` bajo `archivum.carrito.<tienda>`, así que sobrevive a una recarga. No calcula un total: muestra el precio de la vitrina de cada línea solo como referencia.
- **Crear el pedido.** El cuerpo lleva solo identificadores y cantidades (`presentacion_id` y `cantidad`, o `botella_id` y `ml`), nunca un precio. Un 422 marca cada línea por su posición (`lineas.0.presentacion_id`). Un 409 por ml agotados no dice qué línea falló, así que el carrito vuelve a leer el detalle de cada perfume y marca los decants que la vitrina ya no ofrece. Un visitante ve "Inicie sesión para crear el pedido", que regresa al carrito.
- **El pedido** muestra el subtotal, lo que se cobra ahora, lo pagado y el saldo tal como los calcula la API; un pedido cancelado o vencido ya no debe nada, así que no muestra el saldo. Si no hay nada que cobrar, queda "Confirmado" sin pasar por la pasarela.
- **Pagar.** `POST /cuenta/pedidos/{pedido}/pagos` cobra lo que guardó la API: el depósito o el total mientras el pedido espera su pago, y el saldo cuando está listo. El botón queda ocupado (`aria-disabled`) desde el primer clic, así que un doble clic envía una sola solicitud, y la API además devuelve el pago pendiente si llega otra (#92). Una pista avisa antes que se abre la página de la pasarela. Con PayPal, antes de ir a la pasarela se muestra el monto en dólares de `monto_cobrado`, y el foco pasa a "Continuar al pago", que lee ese monto. Solo se sigue una dirección `http` o `https`.
- **Procesando.** La pasarela regresa a `PAYMENTS_RETURN_URL` de la API (por defecto `http://localhost:5173/pagos/procesando`), que no nombra el pedido: antes de salir, el cliente guarda en `sessionStorage` cuál pedido pagó y cuánto llevaba pagado. La vista consulta el pedido cada 3 s durante 2 min hasta que la API acredita el pago, lo que solo pasa con un aviso verificado de la pasarela o con la conciliación (#94, #96). Nunca lee los parámetros de la dirección: un `?estado=exito` no cambia nada. Pasados los 2 min, dice que el pago sigue en proceso y que su estado se verá en "Mis pedidos". Un pago cancelado en la pasarela vuelve a `PAYMENTS_CANCEL_URL`, "Mis pedidos".
- **Mis pedidos** muestra el estado en español, lo pagado y el saldo (salvo en un pedido cancelado o vencido), y ofrece pagar o cancelar cuando la API lo admite. Mientras un pedido espera su pago, también muestra lo que se cobra ahora, que es el monto de su "Pagar". La API decide: si rechaza la cancelación, se muestra su 409.
- **Accesibilidad (AAA).** Cada pedido se nombra por su fecha y hora ("Pedido del 16 nov 2026, 10:42"), así que dos enlaces nunca dicen lo mismo, y su página tiene una ruta de enlaces (Mis pedidos › pedido). En una lista, "Pagar" y "Cancelar el pedido" leen el pedido al que pertenecen. "Cancelar el pedido" pregunta antes y lleva el foco a "No, mantener el pedido". Cuando un botón desaparece (quitar una línea del carrito o cancelar un pedido), el foco pasa al título de la página y una línea con `role="status"` dice qué pasó. "Procesando" dice cada estado en una sola línea con `role="status"`. El pedido muestra en negrita hasta cuándo se puede pagar (R25) y enlaza la ayuda sobre los montos y los estados.

## Citas de prueba

El cliente reserva, cambia de hora y cancela sus citas de prueba de olores ([#129](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/issues/129)). La API decide qué horas están libres, quién atiende y hasta cuándo se puede cambiar una cita; el navegador solo muestra lo que responde.

| Ruta | Vista | API |
|---|---|---|
| `/tiendas/:tienda/citas/nueva` | Reservar una cita de prueba | `GET /tiendas/{tienda}/servicios`, `GET /tiendas/{tienda}/disponibilidad`, `POST /tiendas/{tienda}/citas` |
| `/cuenta/citas` y `/cuenta/citas/pasadas` | "Mis citas", próximas y pasadas | `GET /cuenta/citas?periodo=proximas` o `pasadas` |
| `/cuenta/citas/:cita` | Una cita, con "Cambiar la hora" y "Cancelar la cita" | `GET /cuenta/citas/{cita}`, `POST .../cancelacion` |
| `/cuenta/citas/:cita/reprogramar` | Cambiar la hora de la cita | `GET /tiendas/{tienda}/disponibilidad`, `PATCH /cuenta/citas/{cita}` |

Las rutas de `/cuenta/citas` piden una sesión con el rol `cliente`. Reservar es una página de la tienda, abierta a todos: el visitante elige antes de iniciar sesión, y el personal de una tienda lee que las citas se reservan con una cuenta de cliente.

- **Reservar.** Con un solo servicio, la página lo nombra con su duración y su precio; con varios, se elige uno. Después del día, "Ver los horarios libres" pide `GET /tiendas/{tienda}/disponibilidad` y muestra cada hora con quién puede atenderla: el navegador nunca calcula un horario. Si una hora la atienden varias personas, se puede elegir una; si no, la API asigna a quien tenga menos citas ese día. Los perfumes se buscan en la vitrina y se eligen hasta el máximo del servicio (`maximo_perfumes`).
- **Horario tomado.** Un 409 deja el mensaje de la API ("Ese horario ya no está disponible."), vuelve a pedir los horarios del mismo día y lleva el foco a la primera hora que queda. El servicio, el día, la persona y los perfumes se conservan. Un 422 marca su campo, también el grupo de los perfumes.
- **Visitante.** "Inicie sesión para reservar" guarda la elección en la ruta de regreso (`servicio`, `fecha`, `inicio`, `persona` y `perfumes`); al volver, la página la recupera y pide otra vez los horarios de ese día. El detalle de un perfume ofrece "Reservar una cita para probar" con ese perfume ya elegido.
- **Hora de la tienda.** Las horas se leen tal como las escribe la API, con el desfase de la tienda: `2026-11-21T09:00:00-06:00` es "9:00 a. m." en cualquier zona del navegador (`src/features/appointments/time.ts`). El primer día que se puede elegir es hoy en la zona del perfil de la tienda (`zona_horaria`).
- **Mis citas** separa las próximas de las pasadas y escribe el estado en español ("No asistió" para `no_asistio`). Una cita confirmada o apartada que todavía no empieza ofrece "Cambiar la hora" y "Cancelar la cita". El plazo para hacerlo lo decide la API: el cliente no repite esa regla y muestra el mensaje de su 409.
- **Cambiar la hora** abre con los horarios libres del día de la cita, de la misma persona y en la misma tienda (`tienda.slug` de la cita), y se puede pedir otro día. Envía solo la hora nueva (`inicia_en`); un 409 vuelve a pedir los horarios, como al reservar.
- **Accesibilidad (AAA).** Cada cita se nombra por su día y su hora ("Cita del sábado, 21 de noviembre de 2026, 9:00 a. m."), y su página tiene una ruta de enlaces (Mis citas › cita). En "Mis citas", todo estado distinto de confirmada se suma al enlace, así que una hora reservada otra vez después de cancelarla no repite su texto. Las horas son un grupo de opciones con su leyenda, y cada opción entera, con su texto, es el objetivo de 44 px. Cuántos horarios hay y cuántos perfumes se eligieron se anuncia con `role="status"`; al quitar un perfume, el foco pasa al conteo. "Cancelar la cita" pregunta antes y lleva el foco a "No, mantener la cita"; después de cancelar, el foco pasa al título y un aviso dice que se canceló. "Ayuda y glosario" explica las citas y cada estado.

## Estados de una vista

Toda vista que pide datos muestra tres estados con los componentes de `src/shared`: `LoadingState` mientras espera, `ErrorState` si falla (con el aviso de `ProblemNotice` y, si tiene sentido, el botón "Intentar de nuevo") y `EmptyState` si no hay nada que mostrar. `useApiData` lleva esos estados:

```vue
<script setup lang="ts">
import { onMounted } from 'vue'

import { api } from '@/api/client'
import { callApi } from '@/api/problem'
import EmptyState from '@/shared/EmptyState.vue'
import ErrorState from '@/shared/ErrorState.vue'
import LoadingState from '@/shared/LoadingState.vue'
import { useApiData } from '@/shared/useApiData'

const perfumes = useApiData(
  () => callApi(() => api.GET('/tiendas/{tienda}/perfumes', { params: { path: { tienda: 'archivum' } } })),
  (page) => page.data.length === 0,
)

onMounted(perfumes.reload)
</script>

<template>
  <LoadingState v-if="perfumes.loading.value" />
  <ErrorState v-else-if="perfumes.problem.value" :problem="perfumes.problem.value" @retry="perfumes.reload" />
  <EmptyState v-else-if="perfumes.empty.value" message="La tienda todavía no publica perfumes." />
  <ul v-else-if="perfumes.data.value">...</ul>
</template>
```

`src/shared/__tests__/ExampleListView.vue` es el ejemplo completo, con sus pruebas.

## Accesibilidad

El cliente apunta a WCAG 2.2 en el nivel AAA ([#126](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/issues/126)). La base vale para todas las vistas:

- **Estilos** (`src/styles/base.css`): texto con contraste de al menos 7:1 y bordes de los controles con 3:1; interlineado de 1.5, párrafos de hasta 70 caracteres y texto alineado a la izquierda; botones, campos y enlaces sueltos de al menos 44 × 44 px; anillo de foco de 3 px; sin animaciones cuando el sistema pide menos movimiento, y los colores del sistema en el modo de contraste alto. Las medidas van en `rem`, así que la página crece con el texto hasta el 200 % y se reacomoda desde 320 px de ancho.
- **Marco de la página** (`App.vue`): el enlace "Saltar al contenido", la cabecera con la sesión y un solo `main` (`#contenido`). Las vistas no declaran otro `main`.
- **Título y foco al navegar** (`src/shared/pageChanges.ts`): cada ruta declara `meta.title`. Al cambiar de página, la pestaña toma ese título ("Iniciar sesión · ArchivumParfums"), el foco pasa al contenido principal y un anunciador con `role="status"` lee el nombre de la página. Un cambio solo de la consulta (una búsqueda o una página de resultados) deja el foco donde estaba. `usePageTitle()` precisa el título cuando llegan los datos, como el nombre de un perfume.
- **Botones que esperan una respuesta**: llevan `aria-disabled="true"` en lugar de `disabled`, para que el foco no se pierda, y la función ignora el segundo clic.
- **Avisos**: los errores usan `role="alert"`; los demás mensajes, una región `.status-message` o `.results-summary` con `role="status"` que existe desde el principio, para que el lector de pantalla la lea al cambiar.
- **Ayuda en los campos** ([#128](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/issues/128)): una pista con `field-hint` y un id, que `fieldAttributes(problem, campo, 'pista-campo')` une al control junto con su error. En un grupo de opciones (`fieldset`), `groupAttributes(problem, campo, 'pista-campo')` une la pista y el error al grupo, sin `aria-invalid`, que un `fieldset` no admite (#129).
- **Palabras difíciles y abreviaturas** (#128): la página "Ayuda y glosario" las explica y el pie de cada página la enlaza. Las abreviaturas se escriben completas la primera vez o con `<abbr title="…">`.
- **Enlaces a una parte de una página** (#128): con `#id` en la ruta, el foco va a ese elemento, que lleva `tabindex="-1"`, en lugar del inicio del contenido.

Clases de apoyo: `page` (columna de una vista), `page-section`, `form`, `form-field`, `field-hint`, `form-actions`, `checkbox-field` (casilla con su texto, toda clicable), `choice-group` y `choice` (grupo de opciones de radio, cada una clicable con su texto y su detalle), `sub-nav` (enlaces entre páginas hermanas, como "Próximas" y "Pasadas"), `action-link` (enlace fuera de una oración, con la altura de un botón), `button` y `button--secondary`, `prose` (bloques de texto, con dos líneas y media entre párrafos), `results-summary`, `card-list` y `card`, `breadcrumbs`, `facts` (pares de nombre y valor), `glossary` y `visually-hidden`.

Para revisar una vista a mano: recorrerla solo con el teclado (Tab, Mayús+Tab, Enter y Espacio), ampliarla al 200 % y verla a 320 px de ancho.

## Errores de la API

Ninguna vista muestra el JSON de un error. `src/api/problem.ts` ([#127](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/issues/127)) interpreta cada respuesta fallida y devuelve un `ApiProblem` con su tipo (`kind`), el texto en español que se muestra, los mensajes de cada campo y el identificador de la solicitud:

| Respuesta | `kind` | Qué se muestra |
|---|---|---|
| 422 `datos-invalidos` | `validation` | El `detail` y, junto a cada control, los mensajes de su campo en `errores`; el foco va al primer campo con error. |
| 401 | `unauthenticated` | Lo atiende la sesión (#126). |
| 403 | `forbidden` | La vista "Sin permiso". |
| 404 | `not-found` | La vista "No encontrado". |
| 409 `conflicto` y cualquier otro problema | `conflict`, `other` | El `detail`, o el `title` si no trae `detail`. |
| 429 | `rate-limited` | En cuánto tiempo volver a intentar, según `Retry-After`. |
| 500 `error-interno` o una respuesta que no es `application/problem+json` | `internal` | Un mensaje genérico con el identificador de la solicitud para soporte. |
| Sin respuesta (falla de red) | `network` | Un mensaje genérico con el identificador de la solicitud para soporte. |

El cliente envía su propio `X-Request-Id` (un UUID nuevo en cada solicitud), así que conoce el identificador aunque no llegue respuesta: una solicitud sin respuesta lanza `NetworkError` con él. Con respuesta, el identificador sale de `instance` (`urn:uuid:...`) o del encabezado `X-Request-Id`, que la API expone por CORS junto con `Retry-After`.

`callApi` hace la llamada y nunca lanza: devuelve los datos o el problema. `ProblemNotice` muestra el problema en un aviso con `role="alert"`, o la vista que corresponde a un 403 o un 404:

```vue
<script setup lang="ts">
import { ref } from 'vue'

import { api } from '@/api/client'
import { callApi, type ApiProblem } from '@/api/problem'
import ProblemNotice from '@/shared/ProblemNotice.vue'

const problem = ref<ApiProblem | null>(null)

async function load() {
  const result = await callApi(() =>
    api.GET('/tiendas/{tienda}', { params: { path: { tienda: 'archivum' } } }),
  )
  problem.value = result.problem
}
</script>

<template>
  <ProblemNotice v-if="problem" :problem="problem" />
</template>
```

En un formulario, cada control lleva `name` igual a su campo de la API y `v-bind="fieldAttributes(problem, 'correo')"` (`aria-invalid` y `aria-describedby`), y `<FieldError :problem="problem" field="correo" />` muestra sus mensajes. Después de un 422, `focusFirstInvalidField(form, problem)` lleva el foco al primer control con error. `src/shared/__tests__/ExampleForm.vue` es un ejemplo completo.

## Estructura

```
archivum-web/
├── .env.example          variables de entorno del cliente
├── env.d.ts              tipos de las variables de entorno
├── index.html
├── src/
│   ├── main.ts           crea la aplicación con Pinia, la sesión, los estilos y Vue Router
│   ├── App.vue           marco de la página: "Saltar al contenido", cabecera, main, pie y anunciador
│   ├── styles/base.css   estilos base para WCAG 2.2 AAA
│   ├── router/           routes.ts junta las rutas de cada funcionalidad e index.ts crea el router
│   ├── api/              schema.ts (generado), client.ts, el cliente tipado de la API,
│   │                     y problem.ts, que interpreta sus errores
│   ├── features/         una carpeta por funcionalidad
│   │   ├── storefront/   perfil de la tienda, vitrina con filtros y detalle de un perfume
│   │   ├── recommendations/  recomendador con IA o por reglas
│   │   ├── cart/         carrito de cada tienda y creación del pedido
│   │   ├── orders/       pedidos del cliente, pago, cancelación y "procesando"
│   │   ├── appointments/ citas de prueba: reservar, "Mis citas", cambiar la hora y cancelar
│   │   ├── help/         "Ayuda y glosario"
│   │   ├── session/      store de la sesión, guardias por rol, "Iniciar sesión" y menú
│   │   ├── registration/ "Crear cuenta" con el aviso de privacidad
│   │   └── errors/       rutas de "Sin permiso" y "No encontrado"
│   └── shared/           lo que usan varias funcionalidades: config.ts, money.ts,
│                         navigation.ts, ProblemNotice,
│                         FieldError, las vistas "Sin permiso" y "No encontrado",
│                         LoadingState, ErrorState, EmptyState, useApiData,
│                         MlAmount (cantidad en ml) y pageChanges.ts (título y
│                         foco al navegar)
└── vite.config.ts
```

El código se organiza por funcionalidad. Cada una vive en `src/features/<funcionalidad>` con sus vistas, sus componentes, sus stores de Pinia, un `routes.ts` con sus rutas y una carpeta `__tests__` con sus pruebas. Lo que usan varias funcionalidades va en `src/shared`. Para agregar una funcionalidad:

1. Crear `src/features/<funcionalidad>/` con su vista y su `routes.ts`, como `src/features/storefront`.
2. Sumar sus rutas en `src/router/routes.ts`, antes de `errorRoutes`, que atrapa toda ruta que no existe.
3. Escribir sus pruebas en `src/features/<funcionalidad>/__tests__/`.

## Idioma

El código, los nombres y los comentarios van en inglés; los textos de la interfaz, en español. Es la misma regla de la API ([CLAUDE.md](../CLAUDE.md), Idioma).
