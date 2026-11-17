<script setup lang="ts">
/** Shown while a view waits for its data (#126). */
withDefaults(defineProps<{ message?: string }>(), { message: 'Cargando…' })
</script>

<template>
  <p class="loading-state" role="status" aria-live="polite">{{ message }}</p>
</template>
