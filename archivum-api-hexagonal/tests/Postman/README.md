# Pruebas HTTP con Postman

Este cliente de pruebas recorre la API desde fuera, a través de su adaptador HTTP. Vive en `tests/`, separado de `src/`: no introduce dependencias de Postman en Domain, Application ni Infrastructure. Los datos iniciales pertenecen a los seeders de `database/seeders`; la colección los utiliza mediante la API y crea datos ficticios adicionales.

## Preparar e importar

1. Completa la [instalación de la API](../../README.md#instalación), incluidas las migraciones y la demo. Usa una base local de demostración con sus datos iniciales; el recorrido presupone las dos tiendas, cuentas, UUID de demo y perfumes de los seeders. No ejecutarlo contra producción.
2. Inicia el servidor y conserva abierta su terminal. Su dirección y puerto pueden cambiar; no hay que editar las peticiones para cambiarlos. Configura `APP_URL` en `.env` con la dirección real de la API y reinicia después de `php artisan config:clear`.
3. En Postman, importa [la colección](ArchivumParfums.postman_collection.json) y [el entorno](ArchivumParfums-local.postman_environment.json). Selecciona **ArchivumParfums · demo local ficticia** como entorno activo.
4. Completa `base_url` en el entorno: se versiona vacía y debe ser la dirección que puedes abrir desde Postman, **sin `/api/v1` ni barra final**. Por ejemplo, si ejecutas `php artisan serve --host=localhost --port=8080 --no-reload`, utiliza `http://localhost:8080` tanto en `base_url` como en `APP_URL`. Para otro servidor, utiliza su dirección real y el esquema HTTP o HTTPS correspondiente.
5. Ajusta `demo_password` a `ARCHIVUM_DEMO_PASSWORD` **usada al sembrar**. El ejemplo es `demo-archivum`, una contraseña ficticia de desarrollo, distinta de las contraseñas SQL. Cambiar esa variable después de sembrar no cambia por sí solo las cuentas existentes.

Los JSON no contienen tokens válidos, UUID de recursos creados durante el recorrido ni rutas de archivos de una computadora. Los identificadores y tokens se capturan desde las respuestas en el entorno activo.

## Ejecutar en orden

Abre cada petición y pulsa **Send**, de la 01 a la 35. Revisa el estado, el cuerpo, los encabezados y los resultados de los scripts antes de seguir. No basta con que la respuesta sea JSON. Las peticiones con errores intencionales deben mostrar el estado esperado, no 500.

| Peticiones | Comprobación |
| --- | --- |
| 01–04 | Salud, perfil y vitrinas públicas de ambas tiendas |
| 05–06 | Validación 422 y credenciales incorrectas 401 |
| 07–11 | Login de dos dueños y un colaborador, cuenta y perfil del dueño |
| 12–21 | Crear perfume y presentación, editar, rechazar duplicado y overflow, desactivar y reactivar; comprobar detalle público y precio |
| 22–24 | Búsqueda y paginación siguiendo los enlaces reales de la respuesta |
| 25–26 | Confirmar que existe el perfume de la otra tienda y ocultarlo al dueño ajeno con 404 |
| 27–30 | Verificar identidad del colaborador, rechazar sus operaciones con 403 y comprobar después que el perfume no cambió |
| 31–35 | Cerrar las tres sesiones y comprobar que los tokens reutilizados de colaborador y dueña reciben 401 |

Los scripts comprueban códigos HTTP y `X-Request-Id`, respuestas relevantes y errores RFC 9457; capturan los tokens, identificadores y enlaces que requieren los pasos siguientes. No hacen peticiones ocultas, migraciones ni reinicios de la base. Si falla un login o una creación, corrige esa petición y vuelve a enviarla antes de continuar; los pasos siguientes dependen de su respuesta.

Cada recorrido crea un perfume y una presentación y modifica esos datos. No elimina los registros al terminar. Repetirlo crea otros registros ficticios. Los tres tokens de ese recorrido se revocan al llegar al final; si lo interrumpes, puedes cerrar las sesiones con sus peticiones DELETE correspondientes. No publiques un entorno exportado con tokens capturados: el archivo versionado debe conservar vacíos los valores de ejecución.

## Alcance

Postman utiliza **la base configurada en el servidor HTTP**, no selecciona automáticamente la base de Pest. `composer test` ejecuta Unit, Feature y Architecture; no ejecuta esta colección. No se agrega Node.js como requisito del proyecto: para la prueba manual basta Postman y la API iniciada.

Este recorrido cubre cuentas del personal, catálogo, vitrina, validaciones, permisos, aislamiento y revocación. No incluye subida de imágenes ni demuestra servicios, horarios, disponibilidad, reservas o pagos pendientes de integrar. Para imágenes, consulta [el recorrido del README de la API](../../README.md#recorrido-básico-en-postman) y selecciona un archivo ficticio desde tu computadora.
