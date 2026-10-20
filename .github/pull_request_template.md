## Resumen

<!-- Qué cambia y por qué, en dos o tres líneas. -->

## Issues

<!-- Un issue por línea: "Closes #N". -->

## Comprobación

- [ ] `composer lint`, `composer analyse` y `composer test` (Pest) pasan en mi máquina.
- [ ] Los commits son atómicos, siguen Conventional Commits y llevan `Refs: #N` en el pie.
- [ ] El código y los comentarios están en inglés; los mensajes al usuario salen de `lang/es`.
- [ ] Si cambió la API, `openapi/v1.json` está regenerado con `composer openapi` y `archivum-web` usa los tipos regenerados.
- [ ] Si cambió el esquema, la migración es reversible, toda tabla con `business_id` tiene su política de fila y `docs/data-model.md` está al día.
- [ ] Si agregué un endpoint del panel, su fila está en `tests/Feature/Tenancy/StaffIsolationTest.php` y la prueba con las dos tiendas de `TwoBusinesses` pasa.
- [ ] Si usé IA, la sección "Uso de IA" del README está al día.
