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

    <section class="page-section prose" aria-labelledby="ayuda-pedidos">
      <h2 id="ayuda-pedidos" tabindex="-1">Pedidos y pagos</h2>
      <p>
        Agregue al carrito las botellas y los decants que quiera desde la página de cada perfume. En el carrito puede
        cambiar la cantidad de botellas o quitar productos. Para crear el pedido necesita iniciar sesión.
      </p>
      <p>
        Al crear el pedido, la tienda calcula el subtotal y lo que se cobra ahora: los decants completos y una parte de
        las botellas. Tiene un tiempo para pagarlo, y la página del pedido dice hasta cuándo. Si no lo paga a tiempo, el
        pedido vence y sus decants vuelven a quedar disponibles para otras personas.
      </p>
      <p>
        «Pagar» abre la página segura de la pasarela de pago. Al volver, la tienda muestra el pago cuando la pasarela lo
        confirma, lo que puede tardar unos segundos. Con PayPal el cobro es en dólares, y la página le muestra el monto
        antes de continuar.
      </p>
      <p>
        Puede cancelar un pedido mientras no se hayan encargado sus botellas ni preparado sus decants. Si ya pagó algo, se
        le devolverá todo lo pagado.
      </p>

      <h3 id="estados-del-pedido" tabindex="-1">Estados de un pedido</h3>
      <dl class="glossary">
        <dt>Pendiente de pago</dt>
        <dd>El pedido espera el pago de lo que se cobra ahora.</dd>
        <dt>Confirmado</dt>
        <dd>La tienda recibió el pago y prepara el pedido.</dd>
        <dt>Encargado al proveedor</dt>
        <dd>La tienda pidió las botellas a su proveedor.</dd>
        <dt>Listo para entregar</dt>
        <dd>El pedido está listo. Si queda saldo, se paga antes de la entrega.</dd>
        <dt>Entregado</dt>
        <dd>El pedido ya se entregó.</dd>
        <dt>Cancelado</dt>
        <dd>El pedido se canceló. Si había un pago, se devuelve.</dd>
        <dt>Vencido sin pago</dt>
        <dd>Pasó el tiempo para pagar y el pago no llegó.</dd>
      </dl>
    </section>

    <section class="page-section" aria-labelledby="glosario">
      <h2 id="glosario" tabindex="-1">Glosario</h2>
      <dl class="glossary">
        <dt id="bajo-pedido" tabindex="-1">Bajo pedido</dt>
        <dd>
          La tienda no guarda botellas selladas: encarga a su proveedor cada botella que usted pide, y la entrega cuando
          llega. Puede tardar unas semanas.
        </dd>

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

        <dt id="pasarela-de-pago" tabindex="-1">Pasarela de pago</dt>
        <dd>El servicio externo, como ONVO o PayPal, en cuya página segura se paga con tarjeta.</dd>

        <dt id="por-cobrar-ahora" tabindex="-1">Por cobrar ahora</dt>
        <dd>La parte del pedido que se paga al crearlo: los decants completos y una parte de las botellas.</dd>

        <dt id="presentacion" tabindex="-1">Presentación</dt>
        <dd>Un tamaño de botella sellada de un perfume, con su precio.</dd>

        <dt id="saldo" tabindex="-1">Saldo</dt>
        <dd>
          La parte de las botellas que queda por pagar después del primer pago. Se paga cuando el pedido está listo para
          entregar.
        </dd>

        <dt id="ml" tabindex="-1"><abbr title="mililitros">ml</abbr></dt>
        <dd>Mililitros, la medida de la cantidad de perfume. Mil mililitros son un litro.</dd>

        <dt id="colones" tabindex="-1">₡ (colones)</dt>
        <dd>El símbolo del colón, la moneda de Costa Rica. Los precios están en colones, sin decimales.</dd>

        <dt id="inteligencia-artificial" tabindex="-1">Inteligencia artificial</dt>
        <dd>
          Un programa que interpreta lo que usted escribe para sugerirle perfumes. Antes de mostrar sus sugerencias, la
          tienda comprueba que esos perfumes existan, estén a la venta y no pasen de su presupuesto.
        </dd>
      </dl>
    </section>
  </div>
</template>
