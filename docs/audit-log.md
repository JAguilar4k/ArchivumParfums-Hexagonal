# Bitácora de cambios sensibles (#113)

`audit_logs` guarda quién hizo cada cambio sensible, para aclarar cualquier diferencia de dinero o de mililitros. Cada entrada lleva la tienda, la cuenta o el sistema (`actor_user_id` nulo), la fecha, la acción, el recurso, los valores antes y después y el `X-Request-Id`.

## Qué se audita

| Operación | Acción | Dónde se registra |
| --- | --- | --- |
| Alta de personal desde el panel | `member.added` | `RegisterMember` |
| Cambio de rol | `member.role_changed` | `UpdateMember` |
| Desactivar o reactivar a una persona | `member.deactivated`, `member.activated` | `UpdateMember` |
| Ajuste de una botella | `bottle.adjusted` | `AdjustDecantBottle` |
| Precio manual de una presentación | `variant.manual_price_changed` | `AddPerfumeVariant`, `EditPerfumeVariant` |
| Reembolso solicitado | `refund.requested`, una entrada por cada reembolso | `RefundPayment` |

Un `PATCH` que desactiva y además cambia el rol deja una sola entrada, `member.deactivated`, con los dos valores antes y después. Cambiar solo el nombre o si atiende citas no deja entrada.

El reembolso se registra en `RefundPayment`, el caso de uso que crea cada fila de `refunds` con su identificador y deja su solicitud a la pasarela en la cola (#97); un pedido cancelado puede repartir el importe entre varios pagos, y cada parte deja su entrada. Queda con el cliente como autor si canceló, con el dueño si marcó una línea no disponible, y con el sistema cuando no hay sesión.

## Las garantías

- **Misma transacción.** El puerto `AuditTrail` escribe en la transacción del cambio: si la entrada falla, el cambio no se guarda. `AuditTrailTest` lo prueba en cada operación. Cada caso de uso abre su propia transacción con `TransactionRunner`: no basta con la del middleware `business`, porque Laravel convierte la excepción en respuesta dentro de la cadena de middlewares y esa transacción termina confirmando.
- **Inalterable.** La migración revoca `UPDATE`, `DELETE` y `TRUNCATE` al rol de la aplicación, y el trigger `trg_audit_logs_immutable` los rechaza desde cualquier rol, también el dueño de la tabla.
- **Aislada.** RLS forzado con la misma expresión que las demás tablas: una tienda no ve las entradas de otra.
- **Sin secretos.** `PostgresAuditTrail` quita `password`, `contrasena`, `token`, `phone` y `telefono` a cualquier profundidad.

## Por qué no tiene llaves foráneas

`audit_logs` no apunta a `businesses` ni a `users`. Una entrada debe sobrevivir a la cuenta que nombra (#112), y una llave foránea haría que un `TRUNCATE ... CASCADE` o un borrado en cascada chocara con el trigger. Es una excepción a la convención de `docs/database.md`, y la integridad la da que solo `AuditTrail` escribe aquí.

## Lo que falta

- **Pago manual.** `POST /panel/pedidos/{pedido}/pagos-manuales` llega con #98, que todavía no está en esta rama. Ya existe `AuditAction::ManualPaymentRecorded`: al integrar, `RecordManualPayment` debe llamar a `AuditTrail` dentro de su transacción, y hay que completar el `todo` de `AuditTrailTest`.
- **Alta por consola.** El dueño que se crea al dar de alta una tienda con `CreateBusinessConsoleCommand` no deja entrada, porque esa tienda todavía no tiene operación que aclarar.

## Pendiente de definir

- Si el dueño consulta la bitácora desde el panel: la sección 8 no tiene ruta y este issue no la crea.
- Cuánto tiempo se conserva. Hoy se conserva sin límite; borrar entradas exigiría retirar el trigger con una migración.
