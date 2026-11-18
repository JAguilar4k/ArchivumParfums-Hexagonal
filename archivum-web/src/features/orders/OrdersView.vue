<script setup lang="ts">
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import { api } from '@/api/client'
import { callApi } from '@/api/problem'
import EmptyState from '@/shared/EmptyState.vue'
import ErrorState from '@/shared/ErrorState.vue'
import LoadingState from '@/shared/LoadingState.vue'
import { defaultBusinessSlug } from '@/shared/config'
import { formatColones } from '@/shared/money'
import { useApiData } from '@/shared/useApiData'
import CancelOrderButton from './CancelOrderButton.vue'
import { canCancel, canPay, isClosed, orderTitle, statusLabel } from './order'
import PayButton from './PayButton.vue'

/**
 * "Mis pedidos" (#130), from `GET /cuenta/pedidos`: each order with its status in Spanish, what is paid and the
 * balance, and the payment or the cancellation when the API admits them. The page lives in the URL (`?pagina=`).
 * After a cancellation or a change of page the list loads again, so the focus goes to the title and a line that
 * screen readers announce says what happened.
 */
const route = useRoute()
const tienda = defaultBusinessSlug()
const page = computed(() => {
  const value = Number(route.query.pagina)

  return Number.isInteger(value) && value > 1 ? value : 1
})

const orders = useApiData(
  () => callApi(() => api.GET('/cuenta/pedidos', { params: { query: { pagina: page.value } } })),
  (result) => result.data.length === 0,
)

const meta = computed(() => orders.data.value?.meta ?? null)
const heading = useTemplateRef<HTMLElement>('heading')
const notice = ref('')

async function reloadAndFocus(message: string): Promise<void> {
  notice.value = message
  await orders.reload()
  await nextTick()
  heading.value?.focus()
}

watch(page, (current, previous) => {
  if (previous === undefined) {
    void orders.reload()

    return
  }

  void reloadAndFocus(meta.value ? `Página ${current} de ${meta.value.last_page}.` : '')
}, { immediate: true })
</script>

<template>
  <div class="page orders-view">
    <h1 ref="heading" tabindex="-1">Mis pedidos</h1>
    <!-- Always in the page, so screen readers announce a cancellation or a new page. -->
    <p class="results-summary" role="status">{{ notice }}</p>

    <LoadingState v-if="orders.loading.value" message="Cargando sus pedidos…" />
    <ErrorState v-else-if="orders.problem.value" :problem="orders.problem.value" @retry="orders.reload" />
    <EmptyState v-else-if="orders.empty.value" message="Todavía no tiene pedidos.">
      <RouterLink :to="{ name: 'storefront', params: { tienda } }" class="action-link">Ver la vitrina</RouterLink>
    </EmptyState>
    <template v-else-if="orders.data.value">
      <ul class="card-list orders">
        <li v-for="order in orders.data.value.data" :key="order.id" class="card orders__item">
          <h2 :id="`pedido-${order.id}`">
            <RouterLink :to="{ name: 'order', params: { pedido: order.id } }" class="action-link">
              {{ orderTitle(order) }}
            </RouterLink>
          </h2>
          <dl class="facts">
            <div>
              <dt>Tienda</dt>
              <dd>{{ order.tienda.nombre ?? 'La tienda' }}</dd>
            </div>
            <div>
              <dt>Estado</dt>
              <dd class="orders__status">{{ statusLabel(order.estado) }}</dd>
            </div>
            <!-- While the order waits for its payment, "Pagar" charges this amount and not the balance. -->
            <div v-if="order.estado === 'pendiente_de_pago'">
              <dt>Por cobrar ahora</dt>
              <dd>{{ formatColones(order.por_cobrar_ahora) }}</dd>
            </div>
            <div>
              <dt>Pagado</dt>
              <dd>{{ formatColones(order.pagado) }}</dd>
            </div>
            <div v-if="!isClosed(order)">
              <dt>Saldo</dt>
              <dd>{{ formatColones(order.saldo) }}</dd>
            </div>
          </dl>
          <PayButton v-if="canPay(order)" :order="order" :described-by="`pedido-${order.id}`" />
          <CancelOrderButton
            v-if="canCancel(order)"
            :order="order"
            :described-by="`pedido-${order.id}`"
            @cancelled="reloadAndFocus('Se canceló el pedido.')"
          />
        </li>
      </ul>
      <nav v-if="meta && meta.last_page > 1" class="pagination" aria-label="Páginas de sus pedidos">
        <RouterLink
          v-if="meta.current_page > 1"
          :to="{ name: 'orders', query: { pagina: meta.current_page - 1 } }"
          class="action-link"
        >
          Página anterior
        </RouterLink>
        <span>Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <RouterLink
          v-if="meta.current_page < meta.last_page"
          :to="{ name: 'orders', query: { pagina: meta.current_page + 1 } }"
          class="action-link"
        >
          Página siguiente
        </RouterLink>
      </nav>
    </template>
  </div>
</template>
