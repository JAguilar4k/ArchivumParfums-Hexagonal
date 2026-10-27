# Plan de trabajo

Versión 2 · 7 de octubre de 2026. Las tareas viven en los issues de GitHub. Este archivo fija los módulos (un milestone por módulo), las etiquetas, la forma de trabajo y el orden, siguiendo el repositorio guía SodaYa-Hexagonal.

## Estado

Al 7 de octubre de 2026 no hay código ni despliegue. El enunciado pide repositorio desde la semana 1 y sistema desplegado desde la semana 3: M01 a M04 van primero y cuanto antes.

Orden de prioridad: núcleo del corte vigente → resto del núcleo → módulo propio (botellas, pedidos y proveedores) → extras. Si al 30 de noviembre el núcleo no está completo y en verde, se congelan los extras.

Actualización de Investigación 1 (26/10/2026): los 26 issues `investigacion-1` (#68–#93) están asignados a `JAguilar4k`. La implementación del corte y el informe se preparan en `feature/pagos`, según el enunciado específico. El #76 requiere #62 y #67 y la cadena de horarios/citas #51–#61; por acuerdo con la persona responsable, esas dependencias se mantienen con el equipo de citas. La [evidencia sandbox](evidencia-investigacion-1/README.md) ya registra ONVO con tarjeta aprobada/rechazada, SINPE y reembolso, y PayPal con tarjeta aprobada y captura. Postman se usa sin cuenta, mediante peticiones manuales; las colecciones separadas quedaron preparadas. No se crean issues nuevos ni se marca completa la entrega mientras falten #76 y la revisión humana del seguimiento.

El enunciado específico agrega la etiqueta `v-investigacion1`, el informe `docs/investigacion-1.md`, confirmación por consulta del servidor sin webhooks y reembolso ONVO para este corte. El expediente técnico permanece separado en `docs/payment-gateways.md`. #94–#97 conservan las mejoras y obligaciones posteriores de Avance 2. La etiqueta se crea sobre la entrega integrada, no sobre trabajo en revisión.

## Módulos

Formato del repo guía: `MNN · Título` y una descripción de una oración con el objetivo de negocio, sin fecha límite. La columna "Etiquetas de corte" indica cuáles llevarán sus issues.

| Milestone | Descripción | Etiquetas de corte |
|---|---|---|
| M01 · Proyecto base | Dejar `archivum-api-hexagonal` inicializado en Laravel 13 con PostgreSQL 18, sus dos roles, zona horaria de Costa Rica y mensajes en español. | avance-1 |
| M02 · Arquitectura hexagonal y API versionada | Definir la arquitectura hexagonal con DDD en `src/`, organizada por contexto y módulo, bajo `/api/v1` y con su lenguaje ubicuo. | avance-1 |
| M03 · Calidad e integración continua | Automatizar el control de calidad con Pint, Larastan y Pest en cada pull request y dejar el proyecto documentado e instalable. | avance-1 |
| M04 · Despliegue y operación en producción | Publicar el sistema en una dirección pública desde el primer avance y operarlo con despliegue automático, colas, tareas programadas y respaldos. | avance-1, entrega-final |
| M05 · Multi-inquilino y acceso del personal | Hacer que varias tiendas compartan la instalación con sus datos separados y que el personal ingrese con credenciales propias. | avance-1 |
| M06 · Catálogo de perfumes | Permitir que el dueño administre perfumes y presentaciones con sus notas, etiquetas y precio de venta calculado. | avance-1 |
| M07 · Vitrina pública y contrato de la API | Publicar el catálogo de cada tienda para cualquier visitante y fijar el contrato de la API. | avance-1 |
| M08 · Servicios y horarios de atención | Permitir que el dueño defina las citas que ofrece y cuándo atiende cada persona, incluidos los bloqueos. | avance-1 |
| M09 · Citas de prueba | Calcular la disponibilidad real y reservar, reprogramar y cancelar citas sin que dos personas tomen el mismo espacio. | avance-1 |
| M10 · Botellas para decants | Llevar los mililitros de cada botella abierta con su historial, sin vender nunca más de lo que queda. | investigacion-1 |
| M11 · Pedidos | Recibir pedidos de botellas bajo pedido y de decants, con el precio congelado y lo que se cobra calculado en el servidor. | investigacion-1 |
| M12 · Pagos | Cobrar depósitos y pagos totales con ONVO y PayPal en modo de prueba, confirmarlos por webhook y reembolsar cuando corresponde. | investigacion-1, avance-2, extra |
| M13 · Cuentas, roles y sesiones | Permitir que los clientes se registren y que cada rol haga solo lo que le corresponde, con sesiones que vencen y se renuevan. | avance-2 |
| M14 · Seguridad y privacidad | Defender la API de los ataques más comunes, cumplir la Ley 8968 sobre los datos de los clientes y atender la auditoría cruzada. | avance-2, investigacion-2, entrega-final |
| M15 · Recomendador con IA | Convertir lo que el cliente describe en recomendaciones del catálogo validadas, con respaldo cuando la IA falla. | avance-2 |
| M16 · Cliente web | Construir `archivum-web`, la SPA con la que visitantes, clientes y dueños usan el sistema consumiendo solo la API. | avance-2, entrega-final |
| M17 · Agenda del personal y reportes | Dar a cada persona su agenda y el control de sus citas, y al dueño lo que entró y lo que más se vendió. | avance-2, entrega-final |
| M18 · Paquetes de decants | Vender paquetes de decants prepagados que se canjean sin que el saldo quede negativo. | entrega-final |
| M19 · Lista de espera | Ofrecer una fila para las fechas sin espacio y apartar al siguiente las citas que se liberan. | entrega-final |
| M20 · Colas y procesos automáticos | Sacar el trabajo lento de la petición y vencer solos los pedidos, las ofertas y los paquetes que nadie pagó o confirmó. | entrega-final |
| M21 · Avisos al cliente | Recordar las citas y avisar de pedidos listos y ofertas por un canal que se elige por configuración. | entrega-final, extra |
| M22 · Agenda en vivo | Mostrar las citas nuevas, movidas o canceladas en el momento en que ocurren, sin recargar. | entrega-final |
| M23 · Proveedores y órdenes de compra | Importar el catálogo del proveedor y controlar lo pedido, lo pagado y lo recibido. | entrega-final |
| M24 · Observabilidad | Dar al equipo los medios para saber si el sistema está sano y dónde falla. | entrega-final |
| M25 · Google Calendar | Reflejar las citas en el calendario de quien atiende y bloquear los horarios que ya tiene ocupados. | extra |
| M26 · Reseñas | Recoger la opinión de los clientes y resumirla por perfume para el dueño. | extra |
| M27 · Servidor MCP | Exponer la disponibilidad y el catálogo a los asistentes de IA mediante herramientas de solo lectura. | extra |
| M28 · Caché del catálogo y precios dinámicos | Servir el catálogo con rapidez y aplicar descuentos en las franjas de baja ocupación. | extra |
| M29 · Factura electrónica | Emitir comprobantes electrónicos cuando Hacienda publique la nueva versión. | extra |

Extras dentro de otros módulos, como en el repo guía: propinas y comisiones por colaborador en M12, y WhatsApp Business Cloud en M21.

Dónde caen los entregables del enunciado que no son código de un módulo:

- Informe comparativo de pasarelas (Investigación 1): un issue de M12.
- Auditoría cruzada y atención de hallazgos (Investigación 2): issues de M14; cada hallazgo recibido se registra después como issue con la etiqueta `auditoria`.
- Sección "Uso de IA" del README y documentación del proyecto: issues de M03.

## Etiquetas

Se conservan las etiquetas por defecto de GitHub. Dependabot crea `dependencies` y `github_actions` por su cuenta.

| Etiqueta | Descripción |
|---|---|
| `avance-1` | Requisitos del Avance 1 (21/10) |
| `investigacion-1` | Requisitos de la Investigación 1 (28/10) |
| `avance-2` | Requisitos del Avance 2 (18/11) |
| `investigacion-2` | Requisitos de la Investigación 2 (25/11) |
| `entrega-final` | Requisitos de la entrega final (07/12) |
| `extra` | Extra declarado; no suma a la nota base |
| `auditoria` | Hallazgo de la auditoría cruzada |
| `arquitectura` | Estructura del código y regla de dependencias |
| `base-de-datos` | Esquema, integridad y objetos de PostgreSQL |
| `accessibility` | Barrier affecting people with disabilities |

## Issues

Con la forma del repo guía:

- **Título** en infinitivo, corto y en el lenguaje del negocio: "Reservar una cita de prueba", "Evitar dos citas en el mismo espacio", "Modelar el dominio de las citas".
- **Cuerpo** con la plantilla "Historia de usuario": "Como [rol] quiero [necesidad] para [beneficio]", criterios de aceptación verificables como casillas, y detalles técnicos con la ubicación del módulo (`Src\<Contexto>\<Módulo>\...`) y la línea "Requisitos:" con las reglas de la especificación (R1–R56), la capacidad del núcleo y el criterio de la rúbrica que cubre.
- **Granularidad:** un issue, un pull request. En un módulo con dominio el orden típico es modelar el dominio, crear el esquema con sus restricciones y políticas, implementar la persistencia y luego un issue por caso de uso o endpoint, con sus pruebas dentro de los criterios.
- **Etiquetas:** la del corte en que se entrega, más las de área que correspondan (`arquitectura`, `base-de-datos`, `documentation`).

## Forma de trabajo

- Tablero de GitHub Projects con todos los issues.
- Rama creada desde el issue con *Create a branch*: sale de `main` y lleva el número y el título (`23-reservar-una-cita-de-prueba`).
- Conventional Commits, uno por cambio lógico, con `Refs: #N` en el pie.
- Un pull request por issue, con `Closes #N`, CI en verde y revisión de otra persona del equipo; se fusiona con merge commit.
- Etiquetas de git `v-avance1`, `v-avance2` y `v-final` sobre `main` en cada entrega, como pide el enunciado.

## Cortes

| Corte | Fecha | Qué se presenta | Rúbrica |
|---|---|---|---|
| Revisión 1 | miércoles 14/10 | M01 a M04 encaminados, sistema desplegado, y validar con el profesor la sección 13 de la especificación | Sin nota |
| Avance 1 | miércoles 21/10, 13:00 | Issues `avance-1` de M01 a M09 | Funciona lo pedido 40, aislamiento por negocio 20, pruebas y documentación 25, trabajo en equipo 15 |
| Investigación 1 | miércoles 28/10 | Issues `investigacion-1` de M10 a M12, con el informe de pasarelas | 10 % |
| Revisión 2 | miércoles 11/11 | Avance hacia el Avance 2 | Sin nota |
| Avance 2 | miércoles 18/11, 13:00 | Issues `avance-2` de M12 a M17 | Seguridad y permisos 30, cobro por webhook 30, cliente que consume la API 20, IA 20 |
| Investigación 2 | miércoles 25/11 | Issues `investigacion-2` de M14 | 10 % |
| Entrega final | lunes 07/12, 13:00 | Todo el núcleo, módulo propio y hallazgos corregidos; extras si da el tiempo | Sistema completo 30, auditoría atendida 15, operación 20, calidad y pruebas 15, defensa individual 20 |

## Reparto sugerido si son tres

- Persona A: tiendas, aislamiento, disponibilidad, citas y lista de espera.
- Persona B: botellas, pedidos, pagos, webhooks y paquetes.
- Persona C: cliente web, IA, tiempo real y despliegue.

Todos revisan los pull requests de los demás y hacen al menos una tarea fuera de su área, para llegar preparados a la defensa individual.

## Cómo pedirle trabajo a Claude Code

> Trabajá el issue #N siguiendo CLAUDE.md: rama desde el issue, commits atómicos con `Refs: #N` y pull request con `Closes #N`.
