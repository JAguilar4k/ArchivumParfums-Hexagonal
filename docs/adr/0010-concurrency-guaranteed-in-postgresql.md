# ADR 0010: La concurrencia se garantiza en PostgreSQL, no solo en PHP

Estado: aceptada.

## Contexto

Tres reglas del negocio se rompen si dos peticiones llegan a la vez:

- Dos citas activas de la misma persona nunca se traslapan (R14).
- Nunca se reservan ni descuentan más ml de los que tiene una botella (R9).
- El saldo de un paquete de decants nunca baja de 0 (R34).

Una validación en PHP lee, decide y después escribe. Entre la lectura y la escritura otra petición puede hacer lo mismo, y las dos pasan la validación.

## Decisión

PostgreSQL es quien garantiza estas tres reglas, en la misma sentencia que escribe:

| Regla | Garantía | Quien llega segundo |
|---|---|---|
| Citas sin traslape | `EXCLUDE USING gist (business_id WITH =, member_id WITH =, tstzrange(starts_at, blocked_until) WITH &&) WHERE (status IN ('held', 'confirmed'))` sobre `appointments`, con `btree_gist` | La inserción falla con el SQLSTATE `23P01` |
| Ml de las botellas | `UPDATE` condicional que solo reserva o descuenta si alcanzan los ml, y `CHECK` que impide restantes o reservados negativos y reservados mayores que restantes | El `UPDATE` no cambia ninguna fila |
| Saldo de los paquetes | `UPDATE` condicional que solo descuenta si queda saldo, y `CHECK` sobre los decants restantes | El `UPDATE` no cambia ninguna fila |

El dominio sigue validando antes, para dar un buen mensaje en el caso común. El repositorio traduce la violación o las cero filas afectadas a una excepción del dominio, que la API responde como 409 `conflicto` con su mensaje de `lang/es`.

## Alternativas descartadas

- **Validar solo en PHP.** Deja pasar las dos peticiones simultáneas.
- **Bloqueo pesimista (`SELECT ... FOR UPDATE`) en cada caso de uso.** Funciona, pero depende de que nadie lo olvide y no cubre la escritura que no pase por ese caso de uso. Se reserva para los trabajos que deben ejecutarse una sola vez, como los recordatorios.
- **Aislamiento `SERIALIZABLE` con reintentos.** Garantiza lo mismo para toda la transacción, pero cada caso de uso tendría que reintentar y es más difícil de explicar.
- **Un candado en la caché o en la cola.** No protege de otra conexión que escriba directo en la base.

## Consecuencias

- Cada camino crítico tiene su prueba: la doble reserva de una cita, los últimos ml de una botella y el canje simultáneo de un paquete.
- Esas pruebas no funcionan dentro de la transacción de una prueba: usan dos conexiones o dos procesos en paralelo.
- La restricción de exclusión necesita la extensión `btree_gist`, descrita en [database.md](../database.md).
