<script setup lang="ts">
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import { api } from '@/api/client'
import { callApi } from '@/api/problem'
import ErrorState from '@/shared/ErrorState.vue'
import LoadingState from '@/shared/LoadingState.vue'
import { usePageTitle } from '@/shared/pageChanges'
import { useApiData } from '@/shared/useApiData'
import CancelOrderButton from './CancelOrderButton.vue'
import { canCancel, canPay, orderTitle, type Order } from './order'
import OrderSummary from './OrderSummary.vue'
import PayButton from './PayButton.vue'

/**
 * An order of the customer (#130), from `GET /cuenta/pedidos/{pedido}`: the view that opens after creating it. An
 * order of another account answers 404 and shows "No encontrado". After a cancellation the focus goes to the title,
 * because the button that was used disappears, and a line that screen readers announce says what happened.
 */
const route = useRoute()
const orderId = computed(() => String(route.params.pedido))

const detail = useApiData(() =>
  callApi(() => api.GET('/cuenta/pedidos/{pedido}', { params: { path: { pedido: orderId.value } } })),
)

const order = computed(() => detail.data.value?.data ?? null)
const title = computed(() => (order.value ? orderTitle(order.value) : null))
const heading = useTemplateRef<HTMLElement>('heading')
const notice = ref('')

usePageTitle(() => title.value)

async function cancelled(changed: Order): Promise<void> {
  detail.data.value = { data: changed }
  notice.value = 'Se canceló el pedido.'
  await nextTick()
  heading.value?.focus()
}

watch(
  orderId,
  () => {
    notice.value = ''
    void detail.reload()
  },
  { immediate: true },
)
</script>

<template>
  <div class="page order-view">
    <LoadingState v-if="detail.loading.value" message="Cargando el pedido…" />
    <ErrorState v-else-if="detail.problem.value" :problem="detail.problem.value" @retry="detail.reload" />
    <template v-else-if="order">
      <nav class="breadcrumbs" aria-label="Ubicación">
        <ol>
          <li><RouterLink :to="{ name: 'orders' }">Mis pedidos</RouterLink></li>
          <li><span aria-current="page">{{ title }}</span></li>
        </ol>
      </nav>
      <h1 ref="heading" tabindex="-1">{{ title }}</h1>
    </template>
    <!-- Always in the page, so screen readers announce a cancellation. -->
    <p class="results-summary status-message" role="status">{{ notice }}</p>
    <template v-if="!detail.loading.value && !detail.problem.value && order">
      <OrderSummary :order="order" />
      <p v-if="order.estado === 'confirmado' && order.por_cobrar_ahora === 0" class="order-view__free">
        No hay nada que cobrar ahora: el pedido quedó confirmado.
      </p>
      <PayButton v-if="canPay(order)" :order="order" />
      <CancelOrderButton v-if="canCancel(order)" :order="order" @cancelled="cancelled" />
      <RouterLink :to="{ name: 'orders' }" class="action-link">Ver todos mis pedidos</RouterLink>
    </template>
  </div>
</template>
