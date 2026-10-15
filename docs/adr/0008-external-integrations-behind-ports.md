# ADR 0008: Las integraciones externas quedan detrás de puertos

Estado: aceptada.

## Contexto

ArchivumParfums depende de servicios que no controla: dos pasarelas de pago (ONVO Pay en colones y PayPal en dólares), un proveedor de IA que todavía no está definido, Google Calendar y WhatsApp. Cada uno tiene su propio modelo de datos, sus errores y sus credenciales, y cualquiera puede cambiar de proveedor durante el curso. Las pruebas y la integración continua no pueden llamarlos.

## Decisión

Cada integración externa es un puerto del dominio con un adaptador por proveedor en la infraestructura del módulo, enlazado en su proveedor de servicios:

| Integración | Puerto | Adaptadores |
|---|---|---|
| Pagos | `PaymentGateway`, en `Src\Payments\Payments\Domain\Contracts` (#88) | `OnvoGateway` (#90) y `PayPalGateway` (#91) |
| Recomendador con IA | `AiRecommender`, en `Src\Recommendations\Recommender\Domain\Contracts` (#117) | `LaravelAiRecommender`, con `laravel/ai`, y uno falso para las pruebas |
| Google Calendar | El puerto de calendario de `Src\Scheduling\Calendar\Domain\Contracts` (#181) | El de Google y uno falso para las pruebas |
| WhatsApp y los demás avisos | `CustomerNotifier`, en `Src\Shared\Domain\Contracts` (#161) | Uno por canal; el de WhatsApp Business Cloud llega con #166 |

El puerto habla el idioma del negocio, con objetos de valor y DTO propios. Ningún tipo, nombre ni código de error del proveedor sale del adaptador: el adaptador es la capa anticorrupción. Las credenciales viven en `.env` y la configuración solo elige qué adaptador se usa.

## Alternativas descartadas

- **Llamar al SDK del proveedor desde el caso de uso.** Cambiar de proveedor obliga a tocar cada caso de uso que lo usa, y las pruebas necesitan la red.
- **Un servicio genérico de integraciones.** Junta en una clase proveedores que no tienen nada en común y no le dice al dominio qué puede pedir.
- **Usar directamente los modelos del proveedor en el dominio.** El estado de un pago dependería del vocabulario de cada pasarela.

## Consecuencias

- Cambiar de proveedor toca un solo adaptador y su enlace en el proveedor de servicios.
- Las pruebas usan adaptadores falsos: ni Pest ni la integración continua llaman a ONVO, PayPal, al proveedor de IA, a Google ni a Meta.
- Cada adaptador traduce los errores del proveedor a excepciones del dominio, y una falla del proveedor de IA cae al recomendador por reglas (R50).
- La prueba de arquitectura impide que `Domain` y `Application` dependan del framework, y con él de `laravel/ai` (#117).
