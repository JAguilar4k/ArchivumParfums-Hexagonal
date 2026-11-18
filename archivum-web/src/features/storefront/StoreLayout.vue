<script setup lang="ts">
import { inject } from 'vue'
import { RouterView } from 'vue-router'

import ErrorState from '@/shared/ErrorState.vue'
import LoadingState from '@/shared/LoadingState.vue'
import { STORE_FRAME } from './profile'

const { tienda, profile } = inject(STORE_FRAME)!
</script>

<template>
  <LoadingState v-if="profile.loading.value" message="Cargando la tienda…" />
  <ErrorState v-else-if="profile.problem.value" :problem="profile.problem.value" @retry="profile.reload" />
  <RouterView v-else-if="profile.data.value" :key="tienda" />
</template>
