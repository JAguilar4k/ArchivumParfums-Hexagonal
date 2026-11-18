<script setup lang="ts">
withDefaults(defineProps<{
  name: 'menu' | 'search' | 'moon' | 'sun' | 'account' | 'bag' | 'chat' | 'close' | 'minus' | 'plus'
  size?: number
}>(), { size: 22 })
</script>

<template>
  <svg
    aria-hidden="true"
    focusable="false"
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.75"
    stroke-linecap="round"
    stroke-linejoin="round"
  >
    <path v-if="name === 'menu'" d="M4 7h16M4 12h16M4 17h16" />
    <g v-else-if="name === 'search'"><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></g>
    <path v-else-if="name === 'moon'" d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z" />
    <g v-else-if="name === 'sun'">
      <circle cx="12" cy="12" r="4" />
      <path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M19 5l-1.5 1.5m-11 11L5 19" />
    </g>
    <g v-else-if="name === 'account'"><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></g>
    <path v-else-if="name === 'bag'" d="M6 7h12l-1 13H7L6 7Zm3 0a3 3 0 0 1 6 0" />
    <path v-else-if="name === 'chat'" d="m4 20 1.3-3.9A8 8 0 1 1 8 19Z" />
    <path v-else-if="name === 'close'" d="m6 6 12 12M18 6 6 18" />
    <path v-else-if="name === 'minus'" d="M6 12h12" />
    <path v-else d="M6 12h12m-6-6v12" />
  </svg>
</template>
