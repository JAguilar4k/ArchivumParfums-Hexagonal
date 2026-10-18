# archivum-api-hexagonal

API REST de ArchivumParfums. Laravel 13, PHP 8.4 y PostgreSQL 18. La puesta en marcha del proyecto está en el [README principal](../README.md#puesta-en-marcha).

## Acceso local

Desde esta carpeta, con las dependencias y el entorno preparados, iniciar el servidor:

```bash
php artisan serve
```

Con el puerto predeterminado `8000`, las direcciones son:

| Dirección | Respuesta |
| --- | --- |
| [http://localhost:8000/](http://localhost:8000/) | Redirección temporal HTTP 302 a `/api/v1`. |
| [http://localhost:8000/api/v1](http://localhost:8000/api/v1) | Índice JSON de la API: `{"nombre":"ArchivumParfums","version":"v1"}`. |
| [http://localhost:8000/up](http://localhost:8000/up) | Comprobación de salud HTTP 200. |
| [http://localhost:8000/api](http://localhost:8000/api) | HTTP 404: las rutas de la API llevan versión. |

Si se inicia el servidor con otro puerto, se sustituye `8000` en estas direcciones.

## Pruebas

`tests/Feature/ApiRoutingTest.php` cubre la redirección inicial, el índice de la API, el rechazo de las rutas sin versión y la ruta de salud fuera del prefijo.

```bash
php artisan test --compact tests/Feature/ApiRoutingTest.php
```

## Uso de IA

Codex generó la redirección inicial, la prueba HTTP de regresión y esta documentación en el trabajo del issue #232. La ejecución de las pruebas automatizadas y la revisión humana del equipo están pendientes; cada integrante debe entender y poder modificar lo entregado antes de aprobar el pull request.
