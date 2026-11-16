<script setup lang="ts">
import { computed, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import { api } from '@/api/client'
import { callApi } from '@/api/problem'
import EmptyState from '@/shared/EmptyState.vue'
import ErrorState from '@/shared/ErrorState.vue'
import LoadingState from '@/shared/LoadingState.vue'
import { formatColones } from '@/shared/money'
import { useApiData } from '@/shared/useApiData'
import CancelOrderButton from './CancelOrderButton.vue'
import { canCancel, canPay, statusLabel } from './order'
import PayButton from './PayButton.vue'

/**
 * "Mis pedidos" (#130), from `GET /cuenta/pedidos`: each order with its status in Spanish, what is paid and the
 * balance, and the payment or the cancellation when the API admits them. The page lives in the URL (`?pagina=`).
 */
const route = useRoute()
const page = computed(() => {
  const value = Number(route.query.pagina)

  return Number.isInteger(value) && value > 1 ? value : 1
})

const orders = useApiData(
  () => callApi(() => api.GET('/cuenta/pedidos', { params: { query: { pagina: page.value } } })),
  (result) => result.data.length === 0,
)

const meta = computed(() => orders.data.value?.meta ?? null)
const date = new Intl.DateTimeFormat('es-CR', { dateStyle: 'medium' })

watch(page, () => void orders.reload(), { immediate: true })
</script>

<template>
  <main class="orders-view">
    <h1>Mis pedidos</h1>
    <LoadingState v-if="orders.loading.value" message="Cargando sus pedidos…" />
    <ErrorState v-else-if="orders.problem.value" :problem="orders.problem.value" @retry="orders.reload" />
    <EmptyState v-else-if="orders.empty.value" message="Todavía no tiene pedidos." />
    <template v-else-if="orders.data.value">
      <ul class="orders">
        <li v-for="order in orders.data.value.data" :key="order.id" class="orders__item">
          <RouterLink :to="{ name: 'order', params: { pedido: order.id } }">
            Pedido del {{ date.format(new Date(order.creado_en)) }} en {{ order.tienda.nombre ?? 'la tienda' }}
          </RouterLink>
          <p>
            <span class="orders__status">{{ statusLabel(order.estado) }}</span> · Pagado
            {{ formatColones(order.pagado) }} · Saldo {{ formatColones(order.saldo) }}
          </p>
          <PayButton v-if="canPay(order)" :order="order" />
          <CancelOrderButton v-if="canCancel(order)" :order="order" @cancelled="orders.reload" />
        </li>
      </ul>
      <nav v-if="meta && meta.last_page > 1" class="pagination" aria-label="Páginas">
        <RouterLink v-if="meta.current_page > 1" :to="{ name: 'orders', query: { pagina: meta.current_page - 1 } }">
          Anterior
        </RouterLink>
        <span>Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <RouterLink
          v-if="meta.current_page < meta.last_page"
          :to="{ name: 'orders', query: { pagina: meta.current_page + 1 } }"
        >
          Siguiente
        </RouterLink>
      </nav>
    </template>
  </main>
</template>
