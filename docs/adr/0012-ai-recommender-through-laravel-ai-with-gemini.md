# ADR 0012: El recomendador consulta a Gemini a través de `laravel/ai`

Estado: aceptada. Llega con #117.

## Contexto

El núcleo 10 pide una funcionalidad de IA con datos estructurados: el cliente describe con sus palabras el perfume que busca y la IA devuelve JSON con sus preferencias y hasta 5 perfumes del catálogo de la tienda, cada uno con una razón corta (R48). La salida se valida antes de usarse (R49); si el proveedor falla, tarda más de 10 s o su salida no valida, responde un recomendador por reglas (R50); al proveedor solo viajan el texto del cliente y datos del catálogo (R51). La IA no conversa (sección 12 de la especificación).

`CLAUDE.md` fija `laravel/ai`, el SDK oficial, con salida estructurada, y deja el proveedor por definir; el [ADR 0008](0008-external-integrations-behind-ports.md) pone la integración detrás del puerto `AiRecommender`. El curso no usa dinero real, el equipo trabaja desde Costa Rica y ninguna prueba puede llamar a un servicio real.

## Decisión

El dominio del módulo `Recommendations/Recommender` solo conoce el puerto `AiRecommender`, con `recommend(texto, catálogo)`, y objetos de valor propios: `CatalogPerfume` y `CatalogVariant` para el catálogo que recibe, y `Recommendation`, `CustomerPreferences`, `SuggestedPerfume` y `RecommendationOrigin` para lo que devuelve. Ningún tipo de `laravel/ai` ni de Gemini sale del adaptador.

El adaptador `LaravelAiRecommender` le pregunta al agente `RecommendationAgent`, que implementa `Agent` y `HasStructuredOutput` del SDK:

- Las instrucciones llevan las reglas y el catálogo de la tienda en JSON; el texto del cliente viaja aparte, como el mensaje, y las instrucciones lo tratan como una descripción, nunca como órdenes.
- El esquema declarado pide las preferencias (notas, momento del día, clima, ocasión y presupuesto en colones) y una lista de hasta 5 perfumes, cada uno con su identificador y su razón. Momento y clima usan los valores con que el catálogo etiqueta sus perfumes.
- El agente no tiene herramientas, no guarda conversación ni toca la base: no se publican las migraciones de conversaciones del SDK.

El proveedor es **Gemini** (Gemini Developer API, con una llave de Google AI Studio) y el modelo, `gemini-3.5-flash-lite`. Los dos viven en `config/ai.php`, y la llave, solo en `GEMINI_API_KEY` de `.env`. El límite de 10 s es fijo y vive en `config/archivum.php` (`ai_timeout_seconds`): el adaptador lo pasa al SDK como tiempo máximo de la solicitud HTTP.

El adaptador convierte toda excepción del SDK o del cliente HTTP en `AiUnavailableException`, con una de tres claves: `ai_failed` (error o falta de conexión), `ai_timed_out` (pasó el límite) y `ai_invalid_output` (la respuesta no tiene la forma ni los tipos del esquema, o trae más de 5 perfumes). Los valores que el esquema admite, como un identificador que no está en el catálogo o un presupuesto de cero, pasan tal cual: compararlos con el catálogo es la validación de #119, y el respaldo por reglas llega con #120.

Las pruebas del adaptador simulan las respuestas de Gemini con `Http::fake`: salida correcta, errores, tiempo agotado y salidas que no cumplen el esquema. Las demás pruebas usan `FakeAiRecommender`, de `tests/Support`, y `tests/TestCase.php` mantiene `Http::preventStrayRequests()`.

## Por qué Gemini

- Su plan gratuito cubre los modelos Flash y Flash-Lite sin tarjeta, y Costa Rica está entre las regiones donde se ofrece la API.
- `laravel/ai` trae su controlador, con salida estructurada por esquema.
- Flash-Lite es el más rápido de esos modelos, y la respuesta tiene que llegar antes de 10 s.

## Alternativas descartadas

- **OpenAI o Anthropic.** No tienen plan gratuito para su API: piden una tarjeta y cobran por uso. Siguen disponibles con el mismo adaptador si el equipo los necesita.
- **Un modelo local con Ollama.** Es gratis, pero el servidor del despliegue (#21, por definir) tendría que correr el modelo, y sin GPU difícilmente responde en 10 s.
- **Modelos gratuitos de OpenRouter.** Sus límites cambian y la salida estructurada depende del modelo que haya disponible.
- **HTTP directo a Gemini, sin SDK, como el repo guía hace con Stripe.** `CLAUDE.md` fija `laravel/ai`; con el SDK, cambiar a otro de sus proveedores es configuración y no código.
- **El texto del cliente dentro de las instrucciones.** Mezclaría la parte confiable del mensaje con la que escribe el cliente y facilitaría que un texto malicioso cambiara las reglas.

## Consecuencias

- Cambiar a otro proveedor del SDK es agregar su entrada en `config/ai.php` y su llave en `.env`; cambiar de SDK es escribir otro adaptador y su enlace en `RecommenderServiceProvider`. La prueba de arquitectura `ExternalIntegrationsTest` falla si `laravel/ai` se usa fuera de `Infrastructure/Ai`.
- En el plan gratuito, Google usa el contenido para mejorar sus productos. Por eso solo viajan el texto del cliente y el catálogo (R51, #121), y los datos de demostración son ficticios.
- Los límites del plan gratuito se ven en AI Studio. Si se agotan, Gemini responde 429 y la recomendación sale por reglas (#120).
- `laravel/ai` repite una vez la solicitud cuando el proveedor responde 429 o 5xx con un `Retry-After` de hasta 10 s. En ese caso la espera puede pasar del límite, pero el adaptador descarta igual una respuesta que llega tarde.
- Sin `GEMINI_API_KEY`, cada solicitud falla y responde el recomendador por reglas. Para la demostración hace falta una llave de AI Studio.
- Si Google retira `gemini-3.5-flash-lite`, se cambia el modelo en `config/ai.php`.
