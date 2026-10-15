# ADR 0011: Las rutas del cliente resuelven la tienda con funciones `SECURITY DEFINER`

Estado: aceptada. Llega con #32; el primer recurso del cliente que la usa son las citas (#59 y #64).

## Contexto

En el panel la tienda sale de la sesión del personal, y en las rutas públicas, del slug de la URL ([ADR 0005](0005-tenancy-through-a-port-and-row-level-security.md)). Las rutas `/api/v1/cuenta/...` no tienen ninguna de las dos: una misma cuenta puede ser cliente de varias tiendas, y sus citas o pedidos están repartidos entre ellas.

Las tablas de una tienda tienen la seguridad a nivel de fila forzada: sin `app.business_id`, ni `archivum_app` ni `archivum_owner` ven filas. Para saber qué tienda fijar hay que leer `customers`, o la tabla del recurso, antes de fijar ninguna.

## Decisión

Dos funciones `SECURITY DEFINER` responden esa pregunta sin entregar filas:

- `fn_customer_businesses(user_id)` devuelve solo las tiendas donde la cuenta tiene fila en `customers`.
- `fn_customer_resource_business(tipo, id, user_id)` devuelve la tienda de un recurso propio por su identificador, o `NULL` si es de otro cliente o no existe. Acepta una lista cerrada de tipos; cada esquema con recursos del cliente agrega el suyo.

Las dos tienen `search_path` vacío, son propiedad de `archivum_owner` y solo `archivum_app` tiene `EXECUTE`. Las tablas que leen agregan una política de solo lectura para `archivum_owner`, que es quien las ejecuta.

El tercer adaptador de `BusinessContext`, `CustomerBusinessContext`, las usa a través del puerto `CustomerBusinesses`:

- En una ruta de un recurso, el middleware `business:account` pide la tienda del recurso que nombra la ruta y fija esa tienda en la transacción. Un recurso de otro cliente responde 404 `no-encontrado`, igual que uno que no existe.
- En un listado, `eachBusiness()` recorre las tiendas de la cuenta y abre una transacción por tienda, cada una con su `app.business_id`. Cada pasada ve solo las filas de esa tienda.

`fn_member_business(user_id)` resuelve el mismo problema al ingresar: devuelve solo la tienda y el rol de una persona del personal.

## Alternativas descartadas

- **Tomar la tienda de la URL, como en `/api/v1/tiendas/{tienda}/cuenta/citas`.** El cliente tendría que saber en qué tienda está cada recurso, y un listado de todas sus citas necesitaría una petición por tienda.
- **Una política que deje ver a cada cliente sus propias filas en todas las tiendas.** Mezcla dos criterios en cada política, cada tabla tendría que saber de quién es cada fila, y el aislamiento entre tiendas dejaría de depender de una sola expresión.
- **Leer `customers` con un rol con `BYPASSRLS`.** Ese rol vería todas las filas de todas las tiendas; las funciones solo devuelven identificadores de tiendas.
- **Fijar varias tiendas a la vez en `app.business_id`.** Las políticas compararían contra una lista, y un error en el código podría unir datos de tiendas distintas en una misma consulta.

## Consecuencias

- Cada tipo de recurso del cliente se agrega en tres lugares: la rama de `fn_customer_resource_business`, el enum `CustomerResourceType` y el parámetro de ruta en `CustomerBusinessContext::RESOURCES`. `BusinessRoutesTest` falla si una ruta de la cuenta que nombra un recurso no usa `business:account`.
- Un listado del cliente hace una consulta por tienda. Las tiendas de un cliente son pocas, así que el costo es bajo.
- Las funciones `SECURITY DEFINER` son la única puerta para leer sin tienda, y sus pruebas revisan su dueño, su `search_path` y quién puede ejecutarlas.
- El detalle de las políticas está en [database.md](../database.md#seguridad-a-nivel-de-fila).
