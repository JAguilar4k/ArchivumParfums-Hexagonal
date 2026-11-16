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

La sesión ([#126](https://github.com/JAguilar4k/ArchivumParfums-Hexagonal/issues/126)) registra una vez cómo leer su token con `setTokenProvider(() => token)`; mientras no lo haga, las peticiones salen sin token.

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
│   ├── main.ts           crea la aplicación con Pinia y Vue Router
│   ├── App.vue
│   ├── router/index.ts   junta las rutas de cada funcionalidad
│   ├── api/              schema.ts (generado), client.ts, el cliente tipado de la API,
│   │                     y problem.ts, que interpreta sus errores
│   ├── features/         una carpeta por funcionalidad
│   │   ├── home/         vista de inicio, sus rutas y sus pruebas
│   │   └── errors/       rutas de "Sin permiso" y "No encontrado"
│   └── shared/           lo que usan varias funcionalidades: config.ts, ProblemNotice,
│                         FieldError y las vistas "Sin permiso" y "No encontrado"
└── vite.config.ts
```

El código se organiza por funcionalidad. Cada una vive en `src/features/<funcionalidad>` con sus vistas, sus componentes, sus stores de Pinia, un `routes.ts` con sus rutas y una carpeta `__tests__` con sus pruebas. Lo que usan varias funcionalidades va en `src/shared`. Para agregar una funcionalidad:

1. Crear `src/features/<funcionalidad>/` con su vista y su `routes.ts`, como `src/features/home`.
2. Sumar sus rutas en `src/router/index.ts`, antes de `errorRoutes`, que atrapa toda ruta que no existe.
3. Escribir sus pruebas en `src/features/<funcionalidad>/__tests__/`.

## Idioma

El código, los nombres y los comentarios van en inglés; los textos de la interfaz, en español. Es la misma regla de la API ([CLAUDE.md](../CLAUDE.md), Idioma).
