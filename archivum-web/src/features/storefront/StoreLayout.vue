<script setup lang="ts">
import { computed, provide, watch } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'

import { api } from '@/api/client'
import { callApi } from '@/api/problem'
import { useCartStore } from '@/features/cart/store'
import ErrorState from '@/shared/ErrorState.vue'
import LoadingState from '@/shared/LoadingState.vue'
import { useApiData } from '@/shared/useApiData'
import { STORE_PROFILE } from './profile'

/**
 * The public pages of a store (#128): its profile on top and, below, the showcase, the detail of a perfume, the
 * recommender, the cart (#130) or the booking of an appointment (#129). A store that does not exist shows "No
 * encontrado" instead. The name of the store is not a heading: the heading of level 1 is the name of each page, as in
 * the title of the tab.
 */
const route = useRoute()
const tienda = computed(() => String(route.params.tienda))

const profile = useApiData(() =>
  callApi(() => api.GET('/tiendas/{tienda}', { params: { path: { tienda: tienda.value } } })),
)

// The pages under the layout read the profile from here instead of asking for it again.
provide(STORE_PROFILE, computed(() => profile.data.value?.data ?? null))

const cart = useCartStore()

watch(
  tienda,
  (slug) => {
    cart.load(slug)
    void profile.reload()
  },
  { immediate: true },
)
</script>

<template>
  <LoadingState v-if="profile.loading.value" message="Cargando la tienda…" />
  <ErrorState v-else-if="profile.problem.value" :problem="profile.problem.value" @retry="profile.reload" />
  <div v-else-if="profile.data.value" class="store">
    <header class="store-header">
      <!-- The name of the store follows the logo, so the logo adds nothing for a screen reader. -->
      <img v-if="profile.data.value.data.logo_url" :src="profile.data.value.data.logo_url" alt="" class="store-header__logo" />
      <div class="store-header__text">
        <p class="store-header__name">{{ profile.data.value.data.nombre }}</p>
        <p v-if="profile.data.value.data.lugar_cita" class="store-header__place">
          Lugar de las citas de prueba: {{ profile.data.value.data.lugar_cita }}
        </p>
      </div>
      <nav class="store-nav" aria-label="Tienda">
        <ul>
          <li><RouterLink :to="{ name: 'storefront', params: { tienda } }">Vitrina</RouterLink></li>
          <li><RouterLink :to="{ name: 'recommendations', params: { tienda } }">Pedir una recomendación</RouterLink></li>
          <li><RouterLink :to="{ name: 'book-appointment', params: { tienda } }">Reservar una cita de prueba</RouterLink></li>
          <li><RouterLink :to="{ name: 'cart', params: { tienda } }">Carrito ({{ cart.count(tienda) }})</RouterLink></li>
        </ul>
      </nav>
    </header>
    <RouterView />
  </div>
</template>
