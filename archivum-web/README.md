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

Antes de abrir un pull request pasan `npm run lint`, `npm run type-check`, `npm run test` y `npm run build`.

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
│   ├── features/         una carpeta por funcionalidad
│   │   └── home/         vista de inicio, sus rutas y sus pruebas
│   └── shared/           lo que usan varias funcionalidades, como config.ts
└── vite.config.ts
```

El código se organiza por funcionalidad. Cada una vive en `src/features/<funcionalidad>` con sus vistas, sus componentes, sus stores de Pinia, un `routes.ts` con sus rutas y una carpeta `__tests__` con sus pruebas. Lo que usan varias funcionalidades va en `src/shared`. Para agregar una funcionalidad:

1. Crear `src/features/<funcionalidad>/` con su vista y su `routes.ts`, como `src/features/home`.
2. Sumar sus rutas en `src/router/index.ts`.
3. Escribir sus pruebas en `src/features/<funcionalidad>/__tests__/`.

## Idioma

El código, los nombres y los comentarios van en inglés; los textos de la interfaz, en español. Es la misma regla de la API ([CLAUDE.md](../CLAUDE.md), Idioma).
