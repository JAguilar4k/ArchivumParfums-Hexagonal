<script setup lang="ts">
import { computed, watch } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'

import { api } from '@/api/client'
import { callApi } from '@/api/problem'
import ErrorState from '@/shared/ErrorState.vue'
import LoadingState from '@/shared/LoadingState.vue'
import { useApiData } from '@/shared/useApiData'

/**
 * The public pages of a store (#128): its profile on top and, below, the showcase, the detail of a perfume or the
 * recommender. A store that does not exist shows "No encontrado" instead.
 */
const route = useRoute()
const tienda = computed(() => String(route.params.tienda))

const profile = useApiData(() =>
  callApi(() => api.GET('/tiendas/{tienda}', { params: { path: { tienda: tienda.value } } })),
)

watch(tienda, () => void profile.reload(), { immediate: true })
</script>

<template>
  <LoadingState v-if="profile.loading.value" message="Cargando la tienda…" />
  <ErrorState v-else-if="profile.problem.value" :problem="profile.problem.value" @retry="profile.reload" />
  <div v-else-if="profile.data.value" class="store">
    <header class="store-header">
      <img
        v-if="profile.data.value.data.logo_url"
        :src="profile.data.value.data.logo_url"
        :alt="`Logo de ${profile.data.value.data.nombre}`"
        class="store-header__logo"
      />
      <h1>{{ profile.data.value.data.nombre }}</h1>
      <p v-if="profile.data.value.data.lugar_cita" class="store-header__place">
        Lugar de las citas de prueba: {{ profile.data.value.data.lugar_cita }}
      </p>
      <nav aria-label="Tienda">
        <RouterLink :to="{ name: 'storefront', params: { tienda } }">Vitrina</RouterLink>
        <RouterLink :to="{ name: 'recommendations', params: { tienda } }">Pedir una recomendación</RouterLink>
      </nav>
    </header>
    <RouterView />
  </div>
</template>
