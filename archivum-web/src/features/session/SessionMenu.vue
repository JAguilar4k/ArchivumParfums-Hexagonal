<script setup lang="ts">
import { ref, useTemplateRef, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import StoreIcon from '@/shared/StoreIcon.vue'

import { useSessionStore } from './store'

const session = useSessionStore()
const route = useRoute()
const router = useRouter()
const open = ref(false)
const signingOut = ref(false)
const toggle = useTemplateRef<HTMLButtonElement>('toggle')

watch(() => route.fullPath, () => { open.value = false })

function closeMenu(): void {
  open.value = false
  toggle.value?.focus()
}

function leaveMenu(event: FocusEvent): void {
  const menu = event.currentTarget as HTMLElement
  if (!(event.relatedTarget instanceof Node) || !menu.contains(event.relatedTarget)) {
    open.value = false
  }
}

async function signOut(): Promise<void> {
  if (signingOut.value) return

  signingOut.value = true
  try {
    await session.signOut()
    await router.push({ name: 'home' })
  } finally {
    signingOut.value = false
    open.value = false
  }
}
</script>

<template>
  <nav class="session-menu" aria-label="Sesión" @focusout="leaveMenu" @keydown.esc.stop="closeMenu">
    <template v-if="session.isSignedIn">
      <button
        ref="toggle"
        type="button"
        class="icon-button"
        aria-label="Menú de mi cuenta"
        :aria-expanded="open"
        aria-controls="session-menu-links"
        @click="open = !open"
      ><StoreIcon name="account" /></button>
      <div v-show="open" id="session-menu-links" class="session-menu__panel">
        <p class="session-menu__name">{{ session.account?.nombre }}</p>
        <RouterLink v-if="session.role === 'cliente'" :to="{ name: 'orders' }">Mis pedidos</RouterLink>
        <RouterLink v-if="session.role === 'cliente'" :to="{ name: 'appointments' }">Mis citas</RouterLink>
        <button type="button" class="button--secondary" :aria-disabled="signingOut || undefined" @click="signOut">
          Cerrar sesión
        </button>
      </div>
    </template>
    <RouterLink v-else :to="{ name: 'sign-in' }" class="icon-button" aria-label="Iniciar sesión">
      <StoreIcon name="account" />
    </RouterLink>
  </nav>
</template>

<style scoped>
.session-menu { position: relative; }

.session-menu__panel {
  position: absolute;
  z-index: 1;
  inset-block-start: calc(100% + 0.25rem);
  inset-inline-end: 0;
  display: grid;
  min-width: 11rem;
  max-width: min(18rem, 65vw);
  padding: 0.75rem 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-surface);
  box-shadow: 0 0.5rem 1.25rem #0003;
}

.session-menu__name {
  margin-block-end: 0.25rem;
  font-weight: 700;
  overflow-wrap: anywhere;
}

.session-menu__panel a {
  display: flex;
  align-items: center;
  min-height: var(--target-size);
}
</style>
