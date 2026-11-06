# Límites de tasa (#108)

La API frena la adivinación de contraseñas y el abuso del recomendador con limitadores con nombre, definidos en `Src\Shared\Infrastructure\RateLimitsServiceProvider` y aplicados con el middleware `throttle`. Los valores salen de `config/archivum.php`, y cada uno se puede cambiar con su variable de `.env.example`.

| Limitador | Ruta | Clave | Valor por defecto |
| --- | --- | --- | --- |
| `sesiones` | `POST /api/v1/sesiones` | IP | 10 por minuto |
| `sesiones` | `POST /api/v1/sesiones` | Correo, como hash | 20 cada 15 minutos |
| `cuentas` | `POST /api/v1/cuentas` | IP | 10 por hora |
| `recomendaciones` | `POST /api/v1/tiendas/{tienda}/recomendaciones` | IP, para el visitante | 10 por minuto |
| `recomendaciones` | `POST /api/v1/tiendas/{tienda}/recomendaciones` | Cuenta, con sesión | 20 por minuto |
| `webhooks` | `POST /api/v1/webhooks/onvo` y `/paypal` | IP | 300 por minuto |

El issue dejó los valores sin definir: estos son una propuesta que el equipo debe confirmar.

## Comportamiento

- Pasar un límite responde 429 `demasiadas-solicitudes` en formato RFC 9457, con el encabezado `Retry-After`. `POST /sesiones` responde 429 aunque la contraseña sea correcta.
- El límite por correo frena la adivinación desde varias IP. Su ventana es corta y su cantidad alta para que un tercero no pueda dejar fuera con facilidad a otra persona. El correo entra a la caché solo como hash.
- Un webhook rechazado por el límite lo reenvía la pasarela o lo recupera la conciliación (#96).
- La IP sale de `Request::ip()`. Detrás de un proxy, `TrustProxies` (#109) debe confiar en el proxy de la plataforma; si no, todas las peticiones contarían como una sola IP.

## Lo que falta

`POST /tiendas/{tienda}/recomendaciones` (#118) y `POST /webhooks/onvo` y `/paypal` (#94) todavía no existen en el código. Sus limitadores ya están definidos y probados con rutas de prueba. Cada uno de esos issues debe agregar a su ruta `throttle:recomendaciones` o `throttle:webhooks`; `RateLimitsTest` los deja como `todo`.
