# Dominio del catálogo de perfumes

Este documento propone el diseño de las reglas del catálogo que vivirán en el dominio (`Src\Catalog\Perfumes\Domain`), para que ningún camino de entrada, ni el panel ni la importación de R6, pueda crear un perfume o una presentación inválidos. Es la base del issue #36 (M06 · Catálogo de perfumes, R5, núcleo 1). La implementación del puerto con Eloquent llega con #38 y el cálculo del precio de venta con #43.

Las convenciones de capas están en [architecture.md](architecture.md), las de nombres en [ubiquitous-language.md](ubiquitous-language.md) y las tablas en las que se guarda este agregado en [data-model.md](data-model.md) y [database.md](database.md).

> **Estado: propuesta documental parcial.** Este documento solo describe el diseño. **Todavía no existen** las clases, las constantes, `Colones`, `lang/es/catalog.php`, el puerto ni las pruebas unitarias que se nombran aquí, y R5 sigue en `tests/Architecture/pending-rules.php`. Por eso **#36 aún no está cumplido**: sus criterios de aceptación (agregado, objetos de valor, ciclo de activación, `create()`/`reconstitute()`, puerto y pruebas de Pest sin framework) se completan en un pull request aparte. Donde el texto dice «rechaza», «lanza» o «verifica», se lee como «rechazará», «lanzará» o «verificará».

> **Propuestas pendientes de validación.** Lo que el issue dejó «pendiente de definir» (largos, rangos de precio y de ml, listas y campos obligatorios) no está decidido. Los números de este documento, marcados con 🧪, son **ejemplos de partida**, no reglas del producto, y no deben imponerse como invariantes hasta que el equipo o la perfumería los confirme. Se reúnen en la sección [Propuestas pendientes de validación](#propuestas-pendientes-de-validación).

## Diagrama

```mermaid
classDiagram
    class Perfume {
        <<aggregate root>>
        PerfumeId id
        BusinessId businessId
        Brand brand
        PerfumeName name
        PerfumeDetails details
        PerfumeNote[] notes
        PerfumeTag[] tags
        bool isActive
        create()
        reconstitute()
        revise()
        deactivate()
        activate()
        addVariant()
    }
    class PerfumeVariant {
        PerfumeVariantId id
        SizeMl size
        Colones salePrice
        Colones manualPrice
        bool isActive
        deactivate()
        activate()
    }
    Perfume "1" *-- "0..*" PerfumeVariant : contiene
    Perfume "1" *-- "0..*" PerfumeNote : tiene
    Perfume "1" *-- "0..*" PerfumeTag : se etiqueta con
```

`Perfume` es la raíz del agregado. Las presentaciones, las notas y las etiquetas no existen fuera de él: se leen y se guardan siempre a través del perfume, y la tienda se referencia por `BusinessId`.

## Agregado

### Perfume

| Dato | Tipo | Obligatorio | Regla |
| --- | --- | --- | --- |
| `id` | `PerfumeId` | Sí | UUID v7; la aplicación lo genera con `PerfumeRepository::nextId()` |
| `businessId` | `BusinessId` | Sí | Tienda dueña del perfume; no cambia |
| `brand` | `Brand` | Sí | No vacía, hasta 120 caracteres 🧪 |
| `name` | `PerfumeName` | Sí | No vacío, hasta 120 caracteres 🧪 |
| `details` | `PerfumeDetails` | No | Concentración, género, familia olfativa y descripción; cada uno puede faltar |
| `notes` | lista de `PerfumeNote` | No | Notas de salida, corazón y fondo |
| `tags` | lista de `PerfumeTag` | No | Momento del día, clima y ocasión |
| `isActive` | `bool` | Sí | `true` al crear |
| `variants` | lista de `PerfumeVariant` | No | Sin dos presentaciones con los mismos ml |

Un perfume nace **activo y sin presentaciones**. Las presentaciones se agregan después con `addVariant()`, porque la importación de R6 crea el perfume y su presentación en dos pasos y el panel permite crear un perfume antes de decidir sus tamaños.

### PerfumeVariant

| Dato | Tipo | Obligatorio | Regla |
| --- | --- | --- | --- |
| `id` | `PerfumeVariantId` | Sí | UUID v7 |
| `size` | `SizeMl` | Sí | Entero positivo; el tope y las fracciones están pendientes (ejemplo 🧪: hasta 1000 ml, sin fracciones); no cambia |
| `salePrice` | `Colones` | No | Precio calculado (#43); puede faltar mientras no haya costo ni precio manual |
| `manualPrice` | `Colones` | No | Precio fijado por el dueño (R1); si existe, manda sobre el calculado |
| `isActive` | `bool` | Sí | `true` al crear |

Cuando existe, el precio —calculado o manual— es un entero no negativo en colones. Su mínimo, su máximo y si se admite ₡0 están **pendientes de decisión** (ejemplo 🧪: de ₡1 000 a ₡5 000 000, sin ₡0); mientras no se confirmen, no se rechaza ningún precio por rango.

## Ciclo de vida

| Operación | Quién | Efecto |
| --- | --- | --- |
| `Perfume::create()` | Panel, importación | Perfume activo, sin presentaciones |
| `Perfume::reconstitute()` | Repositorio | Rearma el agregado desde la base, sin aplicar valores por defecto |
| `revise()` | Panel | Cambia marca, nombre, detalles, notas y etiquetas |
| `addVariant()` | Panel, importación | Agrega una presentación activa; lanza `DuplicateVariantSizeException` si ya hay una con esos ml |
| `deactivate()` / `activate()` | Panel | Oculta o vuelve a mostrar el perfume. **No elimina**: conserva sus presentaciones y su historia |
| `deactivateVariant()` / `activateVariant()` | Panel | Igual, para una presentación |

`Perfume` tiene constructor privado, como `Business` y `Member`. Desactivar un perfume no desactiva sus presentaciones: cada una conserva su estado, y la vitrina (#48) muestra una presentación solo si ella y su perfume están activos.

## Objetos de valor

Todos son inmutables y rechazan los datos inválidos al construirse, con `InvalidValueException` y una clave de `lang/es`. Los textos se recortan antes de validar.

| Objeto | Qué guarda | Rechaza | Clave |
| --- | --- | --- | --- |
| `PerfumeId`, `PerfumeVariantId` | UUID v7 | Cualquier otro formato | `shared.invalid_identifier` |
| `Brand` | Marca | Vacía o de más de 120 caracteres 🧪 | `catalog.brand_invalid` |
| `PerfumeName` | Nombre | Vacío o de más de 120 caracteres 🧪 | `catalog.name_invalid` |
| `PerfumeDetails` | Concentración (60), género (30), familia olfativa (60) y descripción (1000) 🧪 | Un texto más largo que su máximo; un texto en blanco se guarda como ausente | `catalog.concentration_too_long`, `catalog.gender_too_long`, `catalog.family_too_long`, `catalog.description_too_long` |
| `SizeMl` | Mililitros enteros | Menos de 1; el tope y las fracciones como 7,5 ml están pendientes (ejemplo 🧪: más de 1000) | `catalog.size_ml_invalid` |
| `PerfumeNote` | Capa (`top`, `heart`, `base`) y texto | Texto vacío o de más de 80 caracteres 🧪; capa inexistente | `catalog.note_invalid`, `catalog.note_layer_invalid` |
| `PerfumeTag` | Tipo y valor | Ver la tabla siguiente | `catalog.time_of_day_invalid`, `catalog.climate_invalid`, `catalog.occasion_invalid`, `catalog.tag_kind_invalid` |
| `Colones` (`Src\Shared`) | Monto entero | Negativo o con fracción | `shared.colones_negative`, `shared.colones_not_integer` |

### Etiquetas

| Tipo (`kind`) | Valores válidos | Texto libre |
| --- | --- | --- |
| `time_of_day` | `day` (día), `afternoon` (tarde), `night` (noche) | No |
| `climate` | `warm` (cálido), `temperate` (templado), `cold` (frío) | No |
| `occasion` | Cualquier texto no vacío de hasta 60 caracteres 🧪 | Sí |

Los valores se guardan en inglés, igual que en el `CHECK` de `perfume_tags` (#37); la API los traduce. «Tarde» es `afternoon` y «noche» es `night`, nunca `evening`.

### Colones

`Colones` vive en `Src\Shared\Domain\ValueObjects` porque lo reutilizan pedidos y pagos. Guarda un entero no negativo; `Colones::fromNumber()` acepta un entero, un decimal sin fracción (`45000.0`) o una cadena numérica (`"45000"`), y rechaza `45000.5` y `"abc"`. Nunca hay punto flotante en el dominio.

## Invariantes del agregado

| # | Invariante | Dónde se verifica | Excepción |
| --- | --- | --- | --- |
| 1 | Marca y nombre no están vacíos | `Brand`, `PerfumeName` | `InvalidValueException` |
| 2 | Los ml son positivos (el tope y las fracciones están [pendientes](#propuestas-pendientes-de-validación)) | `SizeMl` | `InvalidValueException` |
| 3 | Los precios son colones enteros no negativos (el mínimo, el máximo y si vale ₡0 están [pendientes](#propuestas-pendientes-de-validación)) | `Colones` | `InvalidValueException` |
| 4 | El momento del día es día, tarde o noche; el clima es cálido, templado o frío | `PerfumeTag` | `InvalidValueException` |
| 5 | No hay dos presentaciones con los mismos ml | `Perfume::addVariant()` | `DuplicateVariantSizeException` |
| 6 | No hay dos notas iguales en la misma capa ni dos etiquetas iguales del mismo tipo | `Perfume::create()`, `revise()` | `InvalidPerfumeException` |
| 7 | Solo se cambia o desactiva una presentación que el perfume contiene | `Perfume::deactivateVariant()` y afines | `InvalidPerfumeException` |
| 8 | Desactivar no elimina | `Perfume`, `PerfumeVariant` | — |

La base respalda parte de estas invariantes (#37), pero **no todas ni con los mismos rangos**:

| Invariante | Qué exige hoy PostgreSQL |
| --- | --- |
| 2 | `perfume_variants_size_ml_check`: `size_ml > 0`, dentro del tipo `smallint`. No hay tope de negocio ni lista de tamaños |
| 3 | `perfume_variants_sale_price_check` y `perfume_variants_sale_price_override_check`: `>= 0`. No hay mínimo ni máximo de negocio, y ₡0 pasa |
| 4 | `CHECK` de `perfume_tags` para momento del día y clima |
| 5 | Llave única `(business_id, perfume_id, size_ml)` |
| 6 | Llaves únicas de `perfume_notes` y `perfume_tags` |

Los rangos de precio y de ml que se proponen más abajo **no** se replican en SQL, y no se amplían las restricciones de la base hasta que haya una decisión de negocio. Mientras no se confirmen, el dominio no rechaza más de lo que ya rechaza la base.

## Excepciones

Las violaciones lanzan excepciones de dominio con una clave de traducción; el texto sale de `lang/es/catalog.php`. El dominio no conoce el idioma ni el framework.

| Excepción | Cuándo | Clave |
| --- | --- | --- |
| `InvalidPerfumeException` | Nota o etiqueta repetida; presentación que el perfume no contiene | `catalog.duplicate_note`, `catalog.duplicate_tag`, `catalog.variant_not_found` |
| `DuplicateVariantSizeException` | Dos presentaciones con los mismos ml | `catalog.duplicate_variant_size` |
| `PerfumeNotFoundException` | El perfume no existe en la tienda | `catalog.perfume_not_found` |
| `InvalidValueException` (compartida) | Un objeto de valor rechaza su dato | Las de la tabla de objetos de valor |

## Puerto

`PerfumeRepository`, en `Domain/Contracts`, igual que `BusinessRepository`:

| Método | Qué hace |
| --- | --- |
| `nextId(): PerfumeId` | Genera la identidad de un perfume nuevo |
| `nextVariantId(): PerfumeVariantId` | Genera la identidad de una presentación nueva |
| `save(Perfume): void` | Guarda el perfume completo con sus presentaciones, notas y etiquetas |
| `find(PerfumeId): ?Perfume` | Busca un perfume de la tienda en curso |
| `findForUpdate(PerfumeId): ?Perfume` | Lo busca y lo bloquea hasta que termine la transacción |

El repositorio filtra por la tienda que indica `BusinessContext`: el puerto no recibe `BusinessId`. La implementación con Eloquent es el issue #38.

## Propuestas pendientes de validación

Los puntos que #36 dejó «pendientes de definir» **siguen pendientes**. La columna del medio es solo una propuesta de partida para poder discutir y, más adelante, construir; **no es una regla del producto** y no se impone como invariante hasta que alguien la confirme. Cuando se confirme una, se enlaza aquí la decisión aprobada (issue, ADR o especificación) y se vuelve constante de su objeto de valor.

| Pendiente de #36 | Propuesta 🧪 (sin confirmar) | Respaldo actual |
| --- | --- | --- |
| Largos máximos | Marca 120, nombre 120, concentración 60, género 30, familia 60, descripción 1000, nota 80, ocasión 60 | Son los `varchar` de #37: la base no guarda más. Los largos más cortos que esos serían decisión de negocio |
| Precio mínimo y máximo, y si vale ₡0 | Ejemplo: ₡1 000 a ₡5 000 000 y ₡0 no se admite | Solo `>= 0` en la base. **Sin decisión** |
| ml permitidos (rango o lista) y fracciones | Ejemplo: de 1 a 1000, sin lista cerrada y sin fracciones como 7,5 ml | Solo `size_ml > 0` en un `smallint`, que de hecho no admite fracciones. R4 propone 2, 5 y 10 ml como **supuesto** configurable. **Sin decisión** |
| Valores de concentración y de género | Ejemplo: texto libre; `EDP`, `EDT`, `Extrait` y `masculino`, `femenino`, `unisex` como sugerencias | La base no restringe estas columnas. **Sin decisión** |
| Familia y ocasión: ¿lista cerrada o texto libre? | Ejemplo: texto libre | La base no restringe la familia; la ocasión es texto libre en el `CHECK` de #37. **Sin decisión** |
| Obligatorios al crear desde el panel | Ejemplo: solo marca y nombre | La importación de R6 solo trae marca, nombre, ml y costo. **Sin decisión** para el panel |
| Obligatorios de una presentación | Ejemplo: solo los ml; ambos precios pueden faltar | `sale_price` admite nulo hasta #43. **Sin decisión** |

Las decisiones de negocio se consultan antes de implementar, como pide `CLAUDE.md`. Si la implementación necesita avanzar antes de la respuesta, empieza solo con lo que ya respaldan la especificación y la base: marca y nombre no vacíos, ml positivos, precios enteros no negativos, momentos y clima de las listas de R5.

## Pruebas (pendientes)

Estas pruebas **todavía no existen**; se escriben junto con la implementación de #36. Serían pruebas unitarias de Pest en `tests/Unit/Catalog/Perfumes/Domain` y `tests/Unit/Shared/Domain`, sin arrancar el framework. La primera que nombre la regla se titularía `R5: …` y quitaría a R5 de `tests/Architecture/pending-rules.php`.

| Prueba | Qué verifica |
| --- | --- |
| Objetos de valor | Recortan; rechazan vacíos, largos, ml no positivos, momentos y climas fuera de lista |
| `Colones` | Rechaza negativos y fracciones; acepta cadenas numéricas enteras |
| `Perfume` | `create()` y `reconstitute()`; dos presentaciones con los mismos ml; notas y etiquetas repetidas |
| Estado | Desactivar y reactivar un perfume y una presentación sin perder las presentaciones |
| Precio | Ausencia de precio y, una vez confirmado, el rango permitido |

Los casos críticos de PostgreSQL (llaves únicas, `CHECK`, aislamiento por tienda) los cubren las pruebas de la base de #37, no estas.

## Lo que este issue no incluye

- La persistencia con Eloquent, los repositorios y las fábricas: #38.
- Los casos de uso y la API del panel: #39.
- El cálculo del precio de venta a partir del costo, el margen y el redondeo: #43. Aquí `salePrice` solo se guarda y se valida.
- La importación del proveedor: #173 y #174.
- La llave única del perfume dentro de la tienda (`brand`, `name` y quizá `concentration`): se decide en #39 y #174.
