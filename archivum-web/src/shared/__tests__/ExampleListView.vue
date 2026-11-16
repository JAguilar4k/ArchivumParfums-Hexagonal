<script setup lang="ts">
import { onMounted } from 'vue'

import { api } from '@/api/client'
import { callApi } from '@/api/problem'
import EmptyState from '../EmptyState.vue'
import ErrorState from '../ErrorState.vue'
import LoadingState from '../LoadingState.vue'
import { useApiData } from '../useApiData'

/** A view like the ones of the features, to test the three states. */
const perfumes = useApiData(
  () => callApi(() => api.GET('/tiendas/{tienda}/perfumes', { params: { path: { tienda: 'archivum' } } })),
  (page) => page.data.length === 0,
)

onMounted(perfumes.reload)
</script>

<template>
  <LoadingState v-if="perfumes.loading.value" />
  <ErrorState v-else-if="perfumes.problem.value" :problem="perfumes.problem.value" @retry="perfumes.reload" />
  <EmptyState v-else-if="perfumes.empty.value" message="La tienda todavía no publica perfumes." />
  <ul v-else-if="perfumes.data.value">
    <li v-for="perfume in perfumes.data.value.data" :key="perfume.id">{{ perfume.nombre }}</li>
  </ul>
</template>
