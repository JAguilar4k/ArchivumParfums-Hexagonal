# ADR 0006: Mensajes al usuario desde archivos de traducción

Estado: aceptada.

## Contexto

El código se escribe en inglés y quien usa el sistema lee español. Un texto escrito dentro de una clase mezcla los dos idiomas y obliga a tocar código para corregir una redacción.

## Decisión

Ningún mensaje al usuario se escribe en el código. Las excepciones del dominio llevan una clave de traducción y sus parámetros; la infraestructura resuelve el texto desde `lang/es` al armar la respuesta.

## Alternativas descartadas

- **Mensajes en español dentro de las excepciones.** El dominio quedaría con textos de presentación.
- **Llamar al traductor de Laravel desde el dominio.** Rompe la regla de dependencias.

## Consecuencias

- El dominio no conoce el idioma de quien lo usa.
- Una prueba compara las claves de `lang/es` con las de `lang/en` para que no falte ninguna.
