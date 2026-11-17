<script setup lang="ts">
/** Shown when a view loaded its data and there is nothing to list (#126). */
withDefaults(defineProps<{ message?: string }>(), { message: 'No hay nada para mostrar.' })
</script>

<template>
  <div class="empty-state">
    <p>{{ message }}</p>
    <slot />
  </div>
</template>
