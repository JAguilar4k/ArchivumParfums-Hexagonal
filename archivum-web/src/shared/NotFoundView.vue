<script setup lang="ts">
import { RouterLink } from 'vue-router'

import { usePageTitle } from './pageChanges'

// The view can appear inside another page, such as the detail of a perfume that does not exist: the tab says it too.
usePageTitle(() => 'No encontrado')
</script>

<template>
  <section class="problem-view" aria-labelledby="not-found-title">
    <h1 id="not-found-title">No encontrado</h1>
    <p>Lo que busca no existe o ya no está disponible.</p>
    <RouterLink to="/">Volver al inicio</RouterLink>
  </section>
</template>
