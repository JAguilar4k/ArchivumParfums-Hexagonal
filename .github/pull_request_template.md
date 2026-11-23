## Resumen

<!-- Qué cambia y por qué, en dos o tres líneas. -->

## Issues

<!-- Un issue por línea: "Closes #N". -->

## Comprobación

- [ ] `composer lint`, `composer analyse` y `composer test` (Pest) pasan en mi máquina.
- [ ] Los commits son atómicos, siguen Conventional Commits y llevan `Refs: #N` en el pie.
- [ ] El código y los comentarios están en inglés; los mensajes al usuario salen de `lang/es`.
- [ ] Si cambió la API, `openapi/v1.json` está regenerado con `composer openapi` y el check `Web` compila el cliente con el contrato propuesto. Si cambia el contrato publicado, enlaza el seguimiento en [Web](https://github.com/JAguilar4k/ArchivumParfums-Web/issues).
- [ ] Si cambió el esquema, el submódulo apunta a una revisión de [Base de datos](https://github.com/JAguilar4k/ArchivumParfums-Database) con migraciones reversibles, aislamiento por fila y modelo actualizado.
- [ ] Si agregué un endpoint del panel, su fila está en `tests/Feature/Tenancy/StaffIsolationTest.php` y la prueba con las dos tiendas de `TwoBusinesses` pasa.
- [ ] Si usé IA, la sección "Uso de IA" del README está al día.
