# ADR 0007: Las reglas de integridad también viven en PostgreSQL

Estado: aceptada.

## Contexto

El dominio valida cada dato, pero no todo llega por el dominio: un script, una importación del catálogo o un error de programación pueden escribir directo en la base.

## Decisión

PostgreSQL 18 es el único motor y hace cumplir la integridad: restricciones `CHECK` para los rangos del negocio, llaves únicas y foráneas que incluyen la tienda, y el esquema en tercera forma normal. Las pruebas corren contra PostgreSQL, no contra SQLite. El dominio decide las reglas del negocio; la base garantiza integridad, aislamiento y consultas por conjuntos.

## Alternativas descartadas

- **Validar solo en el código.** Un camino que no pase por el dominio deja datos imposibles.
- **Lógica de negocio en procedimientos almacenados.** Duplica los casos de uso y los saca de las pruebas unitarias.
- **SQLite para las pruebas.** Es más rápido, pero no entiende `uuidv7()`, las restricciones de exclusión ni las políticas de fila que el proyecto usa.

## Consecuencias

- Los límites se declaran dos veces: como constante en el objeto de valor y como `CHECK` en la migración.
- Las convenciones de la base están en [database.md](../database.md).
