# Privacidad de los datos personales

Este documento lista cada dato personal que guarda ArchivumParfums, para qué se usa, dónde vive y quién lo ve (Ley 8968). Un campo personal nuevo no entra al esquema sin agregarse aquí.

## Datos que se guardan

| Dato | Para qué se usa | Dónde vive | Quién lo ve |
| --- | --- | --- | --- |
| Nombre | Identificar a la persona en sus pedidos y citas, y en el panel | `users.name` | La propia persona y el dueño de la tienda donde opera |
| Correo | Ingresar y recibir avisos | `users.email`, único en toda la instalación | La propia persona; el dueño ve el del personal de su tienda |
| Contraseña | Verificar el ingreso | `users.password`, solo el hash bcrypt | Nadie: ninguna respuesta la incluye |
| Teléfono, por tienda | Coordinar la entrega por WhatsApp | `customers.phone`, cifrado con `APP_KEY` | Hoy nadie: ningún endpoint lo devuelve. Ver "Pendiente" |
| Fecha y versión del aviso aceptado | Demostrar el consentimiento | `users.privacy_accepted_at` y `users.privacy_notice_version` | El equipo que opera la base; ningún endpoint lo devuelve |
| Tokens de sesión y dispositivo | Mantener la sesión | `personal_access_tokens`, solo el hash del token | La propia persona recibe el token una vez, al ingresar |

## Datos que no se guardan

- La IP no se guarda en la base. Los límites de tasa (#108) la usan como clave temporal en la caché, y el correo entra allí solo como hash.
- No se guardan datos de tarjetas: el cobro ocurre en la pasarela.

## Cómo se protegen

- **Teléfono cifrado.** `CustomerModel` usa el cast `encrypted`, así que una consulta directa a la base no muestra el número. La clave es `APP_KEY`, y `APP_PREVIOUS_KEYS` permite rotarla sin perder lo ya cifrado. Si hiciera falta buscar por teléfono, se agrega un hash aparte, nunca el número en claro.
- **Aviso de privacidad.** `POST /api/v1/cuentas` exige `acepta_privacidad: true`. La versión aceptada sale de `ARCHIVUM_PRIVACY_NOTICE_VERSION`, nunca del cuerpo. El texto del aviso lo muestra archivum-web.
- **Registros sin datos sensibles.** `RedactSensitiveData` reemplaza contraseñas, tokens, correos y teléfonos en cada entrada de los canales `single` y `daily`, también si vienen en el mensaje de una consulta fallida.
- **Datos ficticios.** Los seeders solo crean correos del dominio reservado `.test` y teléfonos de prueba; una prueba falla si eso cambia.

## Lo que cubren otros issues

- Al proveedor de IA nunca viajan datos personales (R51): #121.
- Exportar y corregir los datos: #111. Eliminar la cuenta: #112.
- Pulse todavía no está instalado: #180 debe probar que tampoco guarda estos datos. `RedactSensitiveData` no lo cubre.

## Pendiente de definir

- **Texto del aviso.** Falta redactar el responsable, los fines y los destinatarios que pide la Ley 8968.
- **Quién ve el teléfono.** Se propone que solo el dueño de la tienda, para coordinar la entrega por WhatsApp. Mientras no se confirme, ningún endpoint lo devuelve.
