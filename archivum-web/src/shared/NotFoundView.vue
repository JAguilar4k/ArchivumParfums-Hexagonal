<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<template>
  <section class="problem-view" aria-labelledby="not-found-title">
    <h1 id="not-found-title">No encontrado</h1>
    <p>Lo que busca no existe o ya no está disponible.</p>
    <RouterLink to="/">Volver al inicio</RouterLink>
  </section>
</template>
