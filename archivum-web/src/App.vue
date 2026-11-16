<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'

import SessionMenu from '@/features/session/SessionMenu.vue'
</script>

<template>
  <header class="app-header">
    <RouterLink to="/">ArchivumParfums</RouterLink>
    <SessionMenu />
  </header>
  <RouterView />
</template>
