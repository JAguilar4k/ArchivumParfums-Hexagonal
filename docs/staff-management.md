# Administrar el personal de la tienda (#106)

`Src\Businesses\Members` deja que un dueño agregue, edite y desactive al personal de su tienda en `/api/v1/panel/personal`, sin compartir su cuenta. La tienda sale siempre de la sesión (R54).

## Cómo funciona

| Operación | Caso de uso | Notas |
| --- | --- | --- |
| `POST /panel/personal` | `RegisterMember`, que abre la cuenta con el puerto `StaffAccounts` y llama a `AddMember` | Responde 201 con `Location`. La cuenta y la membresía entran en la transacción de la petición. |
| `GET /panel/personal` | `ListMembers` | Incluye a las personas desactivadas. |
| `GET /panel/personal/{persona}` | `GetMember` | Una persona de otra tienda responde 404. |
| `PATCH /panel/personal/{persona}` | `UpdateMember` | Revoca los tokens al cambiar el rol o al desactivar. |

- **Desactivar.** `members.is_active` pasa a `false` y la fila se conserva. `fn_member_business` deja de encontrar a esa persona, así que `BusinessContext` no resuelve tienda y el panel responde 403. El middleware `RefuseDeactivatedMembers` responde en `POST /sesiones` igual que ante credenciales incorrectas: sin él, la cuenta entraría como cliente. `activo: true` la reactiva.
- **Tokens.** Un token conserva las abilities con que se emitió (#102). Por eso cambiar el rol o desactivar revoca todos los tokens de la persona.
- **Último dueño activo.** Quitarle el rol o desactivarlo responde 409 `conflicto`. `countActiveOwners` bloquea las filas de los dueños activos, de modo que dos dueños que se quitan entre sí a la vez no dejan la tienda sin dueño; lo prueba `LastOwnerConcurrencyTest` con dos conexiones.

## Decisiones que el issue dejó pendientes

Se tomó la opción más conservadora en cada caso. Falta que el equipo las confirme.

| Pendiente | Decisión tomada | Por qué |
| --- | --- | --- |
| Cómo recibe sus credenciales la persona agregada | El dueño fija una `contrasena` inicial | El sistema todavía no envía correos ni tiene recuperación de contraseña (#193). |
| Impedir quitar al último dueño activo | Sí, con 409 `conflicto` | Es lo que propone el issue. |
| Si una cuenta de cliente puede pasar a ser personal | No: cualquier correo ya registrado responde 422 en `correo` | Un tercero no debería cambiar los permisos ni la sesión de una cuenta ajena. |
| El borrado | No hay `DELETE`: responde 405 | El issue solo pide desactivar y el historial de citas y pedidos apunta a la persona. |
| Las citas futuras de quien se desactiva o deja de atender | Sin definir | Las citas todavía no están en `main`; lo debe resolver el módulo de citas (M09). |

## Lo que falta

La bitácora de altas, cambios de rol y desactivaciones depende de #113, que todavía no existe en el código. El horario de atención llega con #56.
