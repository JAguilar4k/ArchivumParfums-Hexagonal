# ADR 0002: Módulos agrupados por contexto delimitado

Estado: aceptada.

## Contexto

Con una sola carpeta por capa, los archivos de perfumes, citas, pedidos y pagos quedarían mezclados. Para cambiar una funcionalidad habría que recorrer todo el proyecto.

## Decisión

La estructura es `src/<Contexto>/<Módulo>/<Capa>`. Un módulo corresponde a un agregado, no a una tabla. Dentro de cada capa los archivos se agrupan por tipo: `Entities`, `ValueObjects`, `Contracts`, `Exceptions`, `UseCases`, `DTOs`, `Http` y `Persistence`. Lo común a un contexto vive en su módulo `Shared`; lo común a todos, en `src/Shared`.

## Alternativas descartadas

- **Capas en la raíz (`src/Domain/Catalog`).** Separa lo que cambia junto.
- **Un módulo por entidad o por tabla.** Multiplica carpetas sin reglas propias.
- **Cuarta capa `Presentation`.** En una API el controlador es un adaptador de entrada, igual que el repositorio lo es de salida.

## Consecuencias

- Todo lo de un agregado está en una carpeta: alta cohesión.
- El dominio de un módulo solo conoce su módulo y los núcleos compartidos; una prueba lo verifica.
- Cada módulo tiene su proveedor de servicios y su archivo de rutas.
