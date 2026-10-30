# Endurecer las respuestas en producción (#109)

La API en producción responde solo por HTTPS, con encabezados de seguridad y sin detalles internos.

## Encabezados

`SetSecurityHeaders` corre en el grupo global, así que también cubre los errores y las rutas que no existen.

| Encabezado | Valor |
| --- | --- |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains`, solo en producción |
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `DENY` |
| `Referrer-Policy` | `no-referrer` |
| `Content-Security-Policy` | `default-src 'none'; frame-ancestors 'none'` |

También quita `X-Powered-By`. La documentación en `/docs/api` tiene su propia política, porque carga su visor desde unpkg.

## HTTPS

En producción, `RedirectToHttps` responde 308 a una petición por HTTP. El 308 conserva el método y el cuerpo, y el dominio sale de `APP_URL`, nunca de la petición. `/up` queda fuera para que el chequeo de salud de la plataforma no dependa del proxy, y `/docs/*` también, porque en producción esa ruta responde 403 sea cual sea el esquema.

`config/trustedproxy.php` toma de `ARCHIVUM_TRUSTED_PROXIES` las direcciones en las que se confía. Vacía no confía en nadie: un cliente no puede fingir HTTPS con su propio `X-Forwarded-Proto`. Al desplegar (#21) hay que poner ahí el proxy de la plataforma; sin eso, toda petición se redirige.

## CORS

`config/cors.php` toma el origen permitido de `config/archivum.php`, que lo lee de `ARCHIVUM_WEB_ORIGIN`. Otro origen no recibe `Access-Control-Allow-Origin`.

## Errores

Con `APP_DEBUG=false`, un error inesperado responde 500 `error-interno` sin traza, archivo, consulta SQL ni mensaje de la excepción. El registro sí lo guarda con su `X-Request-Id`.

## Lo que falta

- **Producción.** La dirección pública (#21), el dominio de archivum-web (#131) y los valores reales de `ARCHIVUM_TRUSTED_PROXIES` y `ARCHIVUM_WEB_ORIGIN` todavía no existen. Las pruebas simulan la configuración de producción, pero nada de esto se ha probado en un despliegue real.
- **Credenciales de `/docs/api`.** Pedirlas en producción corresponde a #180; mientras tanto, esa documentación responde 403 en producción.
