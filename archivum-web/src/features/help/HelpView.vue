<script setup lang="ts">
/**
 * Help and glossary (#128): plain explanations of the pages and of the words of perfumery that they use. Each term
 * of the glossary has an id, so a page can link to it (`/ayuda#decant`).
 */
</script>

<template>
  <div class="page help">
    <h1>Ayuda y glosario</h1>
    <p class="form-intro">
      Aquí se explica cómo usar la tienda y qué significan las palabras de perfumería que aparecen en sus páginas.
    </p>

    <section class="page-section prose" aria-labelledby="ayuda-buscar">
      <h2 id="ayuda-buscar">Buscar un perfume</h2>
      <p>
        En la vitrina puede buscar por marca o nombre, familia olfativa, nota, momento del día, clima, ocasión o precio
        máximo. Llene solo los campos que le interesen y pulse «Buscar». «Limpiar filtros» muestra otra vez todos los
        perfumes.
      </p>
      <p>
        Cada perfume tiene su propia página, con sus notas, cuándo usarlo y los precios de sus botellas y decants.
      </p>
    </section>

    <section class="page-section prose" aria-labelledby="ayuda-recomendacion">
      <h2 id="ayuda-recomendacion">Pedir una recomendación</h2>
      <p>
        En «Pedir una recomendación» describa con sus palabras lo que le gusta: notas, momento del día, clima, ocasión
        o cuánto quiere gastar. La página le sugiere hasta cinco perfumes de la tienda y le dice por qué.
      </p>
      <p>
        El texto se envía a un proveedor externo de inteligencia artificial. No escriba su nombre, correo, teléfono ni
        otros datos personales. Si ese servicio no responde, la tienda recomienda con sus propias reglas y la página lo
        indica.
      </p>
    </section>

    <section class="page-section" aria-labelledby="glosario">
      <h2 id="glosario" tabindex="-1">Glosario</h2>
      <dl class="glossary">
        <dt id="concentracion" tabindex="-1">Concentración</dt>
        <dd>
          Cuánta esencia tiene un perfume. De menos a más: Eau de Cologne (EDC), Eau de Toilette (EDT), Eau de Parfum
          (EDP) y Parfum. Con más concentración, el aroma suele durar más.
        </dd>

        <dt id="decant" tabindex="-1">Decant</dt>
        <dd>
          Una cantidad pequeña de un perfume, por ejemplo de 5 mililitros, que la tienda pasa de una botella abierta a un
          frasco aparte. Sirve para probar un perfume antes de comprar la botella.
        </dd>

        <dt id="familia-olfativa" tabindex="-1">Familia olfativa</dt>
        <dd>El grupo de aromas al que pertenece un perfume, por ejemplo floral, amaderada, cítrica u oriental.</dd>

        <dt id="notas" tabindex="-1">Notas</dt>
        <dd>
          Los aromas que forman un perfume. Las notas de salida se sienten al aplicarlo, las de corazón después de unos
          minutos y las de fondo son las que duran al final.
        </dd>

        <dt id="presentacion" tabindex="-1">Presentación</dt>
        <dd>Un tamaño de botella sellada de un perfume, con su precio.</dd>

        <dt id="ml" tabindex="-1"><abbr title="mililitros">ml</abbr></dt>
        <dd>Mililitros, la medida de la cantidad de perfume. Mil mililitros son un litro.</dd>

        <dt id="colones" tabindex="-1">₡</dt>
        <dd>El símbolo de los colones costarricenses. Los precios están en colones, sin decimales.</dd>

        <dt id="inteligencia-artificial" tabindex="-1">Inteligencia artificial</dt>
        <dd>
          Un programa que interpreta lo que usted escribe para sugerirle perfumes. Antes de mostrar sus sugerencias, la
          tienda comprueba que esos perfumes existan, estén a la venta y no pasen de su presupuesto.
        </dd>
      </dl>
    </section>
  </div>
</template>
