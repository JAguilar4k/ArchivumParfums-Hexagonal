<script setup lang="ts">
import { computed, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import { api } from '@/api/client'
import { callApi } from '@/api/problem'
import ErrorState from '@/shared/ErrorState.vue'
import LoadingState from '@/shared/LoadingState.vue'
import { useApiData } from '@/shared/useApiData'
import CancelOrderButton from './CancelOrderButton.vue'
import { canCancel, canPay, type Order } from './order'
import OrderSummary from './OrderSummary.vue'
import PayButton from './PayButton.vue'

/**
 * An order of the customer (#130), from `GET /cuenta/pedidos/{pedido}`: the view that opens after creating it. An
 * order of another account answers 404 and shows "No encontrado".
 */
const route = useRoute()
const orderId = computed(() => String(route.params.pedido))

const detail = useApiData(() =>
  callApi(() => api.GET('/cuenta/pedidos/{pedido}', { params: { path: { pedido: orderId.value } } })),
)

const order = computed(() => detail.data.value?.data ?? null)

function replace(changed: Order): void {
  detail.data.value = { data: changed }
}

watch(orderId, () => void detail.reload(), { immediate: true })
</script>

<template>
  <main class="order-view">
    <LoadingState v-if="detail.loading.value" message="Cargando el pedido…" />
    <ErrorState v-else-if="detail.problem.value" :problem="detail.problem.value" @retry="detail.reload" />
    <template v-else-if="order">
      <OrderSummary :order="order" />
      <p v-if="order.estado === 'confirmado' && order.por_cobrar_ahora === 0" class="order-view__free">
        No hay nada que cobrar ahora: el pedido quedó confirmado.
      </p>
      <PayButton v-if="canPay(order)" :order="order" />
      <CancelOrderButton v-if="canCancel(order)" :order="order" @cancelled="replace" />
      <RouterLink :to="{ name: 'orders' }">Ver mis pedidos</RouterLink>
    </template>
  </main>
</template>
