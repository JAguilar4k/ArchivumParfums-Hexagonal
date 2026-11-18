<script setup lang="ts">
defineProps<{ name: 'decant' | 'calendar' | 'payment' | 'sparkle' | 'message' }>()
</script>

<template>
  <svg aria-hidden="true" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path v-if="name === 'decant'" d="M10 2h4v4h-4zM8 6h8l1 3v11a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2V9z" />
    <path v-else-if="name === 'calendar'" d="M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2zM3 10h18M8 3v4M16 3v4" />
    <path v-else-if="name === 'payment'" d="M5 6h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2zM3 10h18M7 15h4" />
    <path v-else-if="name === 'sparkle'" d="m12 3 2.3 6.7L21 12l-6.7 2.3L12 21l-2.3-6.7L3 12l6.7-2.3z" />
    <path v-else d="M4 20l1.3-3.9A8 8 0 1 1 8 19z" />
  </svg>
</template>
