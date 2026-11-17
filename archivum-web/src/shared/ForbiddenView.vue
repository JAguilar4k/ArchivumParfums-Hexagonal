<script setup lang="ts">
import { RouterLink } from 'vue-router'

import { usePageTitle } from './pageChanges'

// The view can appear inside another page, such as the detail of a perfume that does not exist: the tab says it too.
usePageTitle(() => 'Sin permiso')
</script>

<template>
  <section class="problem-view" aria-labelledby="forbidden-title">
    <h1 id="forbidden-title">Sin permiso</h1>
    <p>Su cuenta no tiene permiso para ver esta página o hacer esta acción.</p>
    <RouterLink to="/">Volver al inicio</RouterLink>
  </section>
</template>
