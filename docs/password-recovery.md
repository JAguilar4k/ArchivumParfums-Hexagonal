# Cambiar y recuperar la contraseña (#193)

Cualquier cuenta puede cambiar su contraseña con la sesión abierta o recuperarla por correo. Las rutas son propuestas: la sección 8 de la especificación no las lista.

| Ruta | Respuesta | Efecto |
| --- | --- | --- |
| `PUT /api/v1/cuenta/contrasena` | 204; 422 en `contrasena_actual` o `contrasena_nueva`; 401 sin sesión | Fija la contraseña y cierra las demás sesiones. Conserva el token de la petición. |
| `POST /api/v1/recuperaciones-de-contrasena` | 202, siempre con el mismo cuerpo | Pone en cola la búsqueda de la cuenta, el token y el correo. |
| `POST /api/v1/recuperaciones-de-contrasena/confirmacion` | 204; 422 en `token` o `contrasena_nueva` | Fija la contraseña, gasta el token y cierra todas las sesiones. |

## Cómo no se delata una cuenta

`POST /recuperaciones-de-contrasena` no consulta nada dentro de la petición: encola `SendPasswordResetLink` para cualquier correo. La búsqueda, el hash del token y el envío corren en el worker, así que ni el cuerpo ni el tiempo de respuesta dicen si la cuenta existe. Esto va más allá de lo que pide el issue, que solo pone en cola el correo, porque crear el token también tarda.

Un correo ya atendido hace menos de un minuto no recibe otro, y la respuesta es la misma.

## El enlace

- Se arma con `ARCHIVUM_WEB_ORIGIN`, nunca con el `Host` de la petición: `<origen>/recuperar-contrasena?token=<token>`.
- Lleva solo el token. archivum-web le pide el correo a la persona, para que su dirección no quede en URLs ni en registros de servidores.
- El token es de un solo uso. La tabla `password_reset_tokens` guarda solo su hash y no tiene `business_id`, porque una cuenta no pertenece a una tienda.
- **En desarrollo**, el mailer `log` escribe el correo en el registro, enlace incluido. En producción hay que configurar un mailer real.

## Límites de tasa

Las tres rutas usan los limitadores de #108: `contrasena-cambio` por cuenta, y `contrasena-recuperacion` y `contrasena-confirmacion` por IP y por correo. Están definidos en `RateLimitsServiceProvider`, y sus valores, en `archivum.rate_limits` de `config/archivum.php`. La tabla con cada ruta, clave y valor está en [rate-limits.md](rate-limits.md).

## Decisiones pendientes de confirmar

- **Duración del enlace.** 60 minutos, el valor por defecto del broker, configurable con `ARCHIVUM_RESET_LINK_MINUTES`.
- **Aviso por correo tras un cambio.** No se envía: el issue lo deja pendiente.
- **Persona desactivada.** Puede recuperar la contraseña, pero sigue sin poder ingresar hasta que un dueño la reactive (#106).
