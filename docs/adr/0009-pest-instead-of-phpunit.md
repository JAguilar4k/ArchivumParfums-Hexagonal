# ADR 0009: Pest en lugar de PHPUnit

Estado: aceptada.

## Contexto

El repo guía escribe sus pruebas con PHPUnit. El enunciado del curso exige Pest, y en lo que se califica el enunciado manda sobre el repo guía.

## Decisión

Todas las pruebas se escriben con Pest 5, que corre sobre PHPUnit. Se conservan las carpetas y el alcance del repo guía: `tests/Unit` para el dominio y los casos de uso sin framework, `tests/Feature` para los endpoints y los adaptadores contra PostgreSQL con el rol de la aplicación, y `tests/Architecture` para la regla de dependencias, escrita con `arch()` de Pest. `composer test` las ejecuta, en local y en la integración continua.

## Alternativas descartadas

- **PHPUnit, como el repo guía.** Incumple el enunciado.
- **Mezclar Pest y PHPUnit.** Dos estilos para lo mismo, y cada integrante tendría que leer los dos.

## Consecuencias

- Las pruebas son funciones (`it()`, `arch()`) en lugar de clases. Los traits se aplican con `pest()->use()`, como `RefreshDatabaseAsOwner`.
- Larastan ve `$this` de una prueba de Pest como `TestCall` y no conoce los métodos de `TestCase`. Por eso las pruebas usan las funciones de `Pest\Laravel` (`get()`, `postJson()`) en lugar de `$this->get()`.
- Las pruebas del repo guía no se copian tal cual: se traducen a Pest con la misma intención.
