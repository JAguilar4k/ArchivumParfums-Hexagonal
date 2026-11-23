<script setup lang="ts">
/**
 * The privacy notice that a new customer accepts (#194). It says what docs/privacy.md lists for an account (#110):
 * which data is kept, what for and who sees it. The API records the date and the version that it accepts
 * (ARCHIVUM_PRIVACY_NOTICE_VERSION); a change of this text goes with a new version there.
 */
defineProps<{ id: string }>()
</script>

<template>
  <section :id="id" class="privacy-notice prose" aria-labelledby="privacy-notice-title">
    <h2 id="privacy-notice-title">Aviso de privacidad</h2>
    <p>Para crear tu cuenta, ArchivumParfums guarda estos datos, conforme a la Ley 8968:</p>
    <ul>
      <li>
        <strong>Tu nombre</strong>, para identificarte en tus pedidos y citas. Lo ves tú y la tienda donde haces
        pedidos o agenda citas.
      </li>
      <li><strong>Tu correo</strong>, para ingresar y recibir avisos. Solo tú lo ves.</li>
      <li><strong>Tu contraseña</strong>, guardada de forma que nadie la puede leer.</li>
      <li><strong>La fecha y la versión de este aviso</strong>, para demostrar que lo aceptó.</li>
    </ul>
    <p>
      No guardamos datos de tarjetas: el pago ocurre en la pasarela. Lo que escribas en el recomendador se envía a un
      proveedor externo de inteligencia artificial, sin tu cuenta.
    </p>
  </section>
</template>
