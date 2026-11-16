<script setup lang="ts">
import { RouterLink, useRouter } from 'vue-router'

import { useSessionStore } from './store'

const session = useSessionStore()
const router = useRouter()

async function signOut(): Promise<void> {
  await session.signOut()
  await router.push({ name: 'home' })
}
</script>

<template>
  <nav class="session-menu" aria-label="Sesión">
    <template v-if="session.isSignedIn">
      <span class="session-menu__name">{{ session.account?.nombre }}</span>
      <button type="button" class="button--secondary" @click="signOut">Cerrar sesión</button>
    </template>
    <RouterLink v-else :to="{ name: 'sign-in' }">Iniciar sesión</RouterLink>
  </nav>
</template>
