<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'

import SessionMenu from '@/features/session/SessionMenu.vue'
import { announcement, focusMainContent, MAIN_CONTENT_ID } from '@/shared/pageChanges'

/**
 * The frame of every page (#126): a link to skip to the content, the header with the session, the main content, a
 * footer with the help (#128) and an announcer that names the page after each navigation.
 */
function skipToContent(event: MouseEvent): void {
  event.preventDefault()
  focusMainContent({ preventScroll: false })
}
</script>

<template>
  <a class="skip-link" :href="`#${MAIN_CONTENT_ID}`" @click="skipToContent">Saltar al contenido</a>
  <header class="app-header">
    <RouterLink to="/" class="app-header__brand">ArchivumParfums</RouterLink>
    <SessionMenu />
  </header>
  <main :id="MAIN_CONTENT_ID" tabindex="-1">
    <RouterView />
  </main>
  <footer class="app-footer">
    <RouterLink to="/ayuda" class="action-link">Ayuda y glosario</RouterLink>
  </footer>
  <p class="visually-hidden" role="status" aria-live="polite" aria-atomic="true">{{ announcement }}</p>
</template>
