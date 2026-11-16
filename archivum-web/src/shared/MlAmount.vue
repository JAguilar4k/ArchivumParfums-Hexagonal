<script setup lang="ts">
/** An amount of perfume, with "ml" marked as the abbreviation of mililitros (WCAG 3.1.4). */
defineProps<{ amount: number }>()
</script>

<template>
  <span class="ml-amount">{{ amount }}&nbsp;<abbr title="mililitros">ml</abbr></span>
</template>
