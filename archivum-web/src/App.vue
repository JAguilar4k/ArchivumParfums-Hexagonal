<script setup lang="ts">
import { computed, ref, useTemplateRef, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'

import CartDrawer from '@/features/cart/CartDrawer.vue'
import { openCartDrawer } from '@/features/cart/drawer'
import { useCartStore } from '@/features/cart/store'
import SessionMenu from '@/features/session/SessionMenu.vue'
import { provideStoreFrame } from '@/features/storefront/profile'
import { prefetchShowcase } from '@/features/storefront/publicStore'
import SideDrawer from '@/shared/SideDrawer.vue'
import StoreCategories from '@/shared/StoreCategories.vue'
import StoreIcon from '@/shared/StoreIcon.vue'
import { storeWhatsApp } from '@/shared/config'
import { announcement, focusMainContent, MAIN_CONTENT_ID } from '@/shared/pageChanges'
import { useTheme } from '@/shared/useTheme'

const route = useRoute()
const router = useRouter()
const cart = useCartStore()
const { tienda, profile, families } = provideStoreFrame()
const { dark, toggleTheme } = useTheme()
const store = computed(() => profile.data.value?.data)
const count = computed(() => cart.count(tienda.value))
const shopLink = computed(() => ({ name: 'storefront', params: { tienda: tienda.value } }))
const phone = storeWhatsApp()
const whatsapp = phone ? `https://wa.me/${encodeURIComponent(phone)}` : ''

const menuOpen = ref(false)
const searchOpen = ref(false)
const search = ref('')
const menuTrigger = useTemplateRef<HTMLButtonElement>('menuTrigger')
const searchTrigger = useTemplateRef<HTMLButtonElement>('searchTrigger')

watch(() => route.fullPath, () => {
  menuOpen.value = false
  searchOpen.value = false
})

function openSearch(): void {
  search.value = route.name === 'storefront' && typeof route.query.texto === 'string' ? route.query.texto : ''
  searchOpen.value = true
}

function showCart(event: MouseEvent): void {
  openCartDrawer({ slug: tienda.value, trigger: event.currentTarget as HTMLElement })
}

function preloadShowcase(): void {
  void prefetchShowcase(tienda.value, { por_pagina: 12 })
}

async function searchShowcase(): Promise<void> {
  const text = search.value.trim()
  searchOpen.value = false
  await router.push({ ...shopLink.value, query: text ? { texto: text } : {} })
}

function skipToContent(event: MouseEvent): void {
  event.preventDefault()
  focusMainContent({ preventScroll: false })
}
</script>

<template>
  <a class="skip-link" :href="`#${MAIN_CONTENT_ID}`" @click="skipToContent">Saltar al contenido</a>
  <aside aria-label="Aviso de la tienda">
    <p class="app-announcement">
      Decants para probar antes de comprar<span v-if="store?.lugar_cita"> · Citas de prueba en {{ store.lugar_cita }}</span>
    </p>
  </aside>
  <header class="app-header">
    <div class="frame-inner app-header__row">
      <div class="app-header__actions">
        <button ref="menuTrigger" type="button" class="icon-button" aria-label="Abrir el menú" aria-haspopup="dialog" @click="menuOpen = true">
          <StoreIcon name="menu" />
        </button>
        <button ref="searchTrigger" type="button" class="icon-button" aria-label="Buscar perfumes" aria-haspopup="dialog" @click="openSearch">
          <StoreIcon name="search" />
        </button>
      </div>
      <RouterLink
        :to="shopLink"
        class="app-header__brand"
        @pointerenter="preloadShowcase"
        @focusin="preloadShowcase"
      >
        <img v-if="store?.logo_url" :src="store.logo_url" alt="" width="40" height="40" decoding="async" class="app-header__logo" />
        <span v-else class="app-header__monogram" aria-hidden="true">AP</span>
        <span class="app-header__name">{{ store?.nombre }}</span>
        <span v-if="!store?.nombre" class="visually-hidden">Vitrina</span>
      </RouterLink>
      <div class="app-header__actions app-header__actions--right">
        <button
          type="button"
          class="icon-button"
          :aria-label="dark ? 'Activar el tema claro' : 'Activar el tema oscuro'"
          :aria-pressed="dark"
          @click="toggleTheme"
        ><StoreIcon :name="dark ? 'sun' : 'moon'" :size="20" /></button>
        <SessionMenu />
        <button
          type="button"
          class="icon-button app-header__bag"
          :aria-label="`Carrito, ${count} productos`"
          aria-haspopup="dialog"
          @click="showCart"
        >
          <StoreIcon name="bag" />
          <span v-if="count" class="app-header__count" aria-hidden="true">{{ count }}</span>
        </button>
      </div>
    </div>
    <StoreCategories :tienda="tienda" :families="families" class="app-header__categories frame-inner" />
  </header>
  <main :id="MAIN_CONTENT_ID" tabindex="-1">
    <RouterView />
  </main>
  <footer class="app-footer">
    <div class="frame-inner app-footer__columns">
      <div>
        <p v-if="store?.nombre" class="app-footer__name">{{ store.nombre }}</p>
        <p class="app-footer__place">Perfumería de nicho<span v-if="store?.lugar_cita">. {{ store.lugar_cita }}</span></p>
      </div>
      <nav aria-label="Tienda, pie de página">
        <p class="app-footer__label">Tienda</p>
        <ul>
          <li><RouterLink :to="shopLink" @pointerenter="preloadShowcase" @focusin="preloadShowcase">Vitrina</RouterLink></li>
          <li><RouterLink :to="{ name: 'book-appointment', params: { tienda } }">Reservar una cita</RouterLink></li>
          <li><RouterLink :to="{ name: 'recommendations', params: { tienda } }">Recomendador</RouterLink></li>
        </ul>
      </nav>
      <nav aria-label="Cuenta, pie de página">
        <p class="app-footer__label">Cuenta</p>
        <ul>
          <li><RouterLink :to="{ name: 'sign-in' }">Iniciar sesión</RouterLink></li>
          <li><RouterLink :to="{ name: 'register' }">Crear una cuenta</RouterLink></li>
          <li><RouterLink :to="{ name: 'orders' }">Mis pedidos</RouterLink></li>
          <li><RouterLink :to="{ name: 'appointments' }">Mis citas</RouterLink></li>
        </ul>
      </nav>
      <nav aria-label="Ayuda, pie de página">
        <p class="app-footer__label">Ayuda</p>
        <ul>
          <li><RouterLink :to="{ name: 'help' }">Ayuda y glosario</RouterLink></li>
          <li v-if="whatsapp"><a :href="whatsapp" target="_blank" rel="noopener">Escribir por WhatsApp</a></li>
        </ul>
      </nav>
    </div>
    <div class="frame-inner app-footer__end">
      <p>© 2026 <span v-if="store?.nombre">{{ store.nombre }} · </span>Proyecto académico: los pagos funcionan en modo de prueba.</p>
      <ul aria-label="Formas de pago" class="app-footer__payments"><li>ONVO</li><li>PayPal</li></ul>
    </div>
    <a
      v-if="whatsapp"
      class="app-whatsapp"
      :href="whatsapp"
      target="_blank"
      rel="noopener"
      aria-label="Escribir a la tienda por WhatsApp"
    ><StoreIcon name="chat" :size="26" /></a>
  </footer>
  <p class="visually-hidden" role="status" aria-live="polite" aria-atomic="true">{{ announcement }}</p>

  <SideDrawer
    v-model:open="menuOpen"
    :trigger="menuTrigger"
    title="Menú"
    heading-id="store-menu-title"
    close-label="Cerrar el menú"
    side="left"
  >
    <StoreCategories :tienda="tienda" :families="families" class="menu-categories" @navigate="menuOpen = false" />
  </SideDrawer>
  <SideDrawer
    v-model:open="searchOpen"
    :trigger="searchTrigger"
    title="Buscar perfumes"
    heading-id="store-search-title"
    close-label="Cerrar la búsqueda"
    initial-focus="#store-search"
    side="left"
  >
    <form class="form store-search" role="search" @submit.prevent="searchShowcase">
      <div class="form-field">
        <label for="store-search">Marca, nombre o nota</label>
        <input id="store-search" v-model="search" name="texto" type="search" />
      </div>
      <div class="form-actions"><button type="submit">Buscar</button></div>
    </form>
  </SideDrawer>
  <CartDrawer />
</template>
