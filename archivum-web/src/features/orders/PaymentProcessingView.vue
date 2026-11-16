<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import { api } from '@/api/client'
import { callApi, type ApiProblem } from '@/api/problem'
import ProblemNotice from '@/shared/ProblemNotice.vue'
import { statusLabel, type Order } from './order'
import OrderSummary from './OrderSummary.vue'
import { clearPendingPayment, POLL_DURATION_MS, POLL_INTERVAL_MS, readPendingPayment } from './pendingPayment'

/**
 * "Procesando" (#130): the gateway returns here, to the address of PAYMENTS_RETURN_URL in the API. The view asks
 * `GET /cuenta/pedidos/{pedido}` until the API credits the payment, which happens only with a verified notice of the
 * gateway (#94) or the reconciliation (#96). It never reads the parameters of the address: a "success" in the URL
 * changes nothing. After 2 min it says that the payment is still in process and that "Mis pedidos" will show it.
 */
type State = 'waiting' | 'confirmed' | 'closed' | 'timeout' | 'missing' | 'failed'

const pending = readPendingPayment()
const state = ref<State>(pending ? 'waiting' : 'missing')
const order = ref<Order | null>(null)
const problem = ref<ApiProblem | null>(null)
let timer: ReturnType<typeof setTimeout> | null = null
let elapsed = 0

async function check(): Promise<void> {
  if (!pending) {
    return
  }

  const outcome = await callApi(() =>
    api.GET('/cuenta/pedidos/{pedido}', { params: { path: { pedido: pending.orderId } } }),
  )

  if (outcome.problem) {
    // The order is gone or not this account's: asking again changes nothing. Any other failure may pass.
    if (['not-found', 'forbidden', 'unauthenticated'].includes(outcome.problem.kind)) {
      problem.value = outcome.problem
      state.value = 'failed'

      return
    }
  } else {
    order.value = outcome.data.data

    if (order.value.pagado > pending.paidBefore) {
      state.value = 'confirmed'
      clearPendingPayment()

      return
    }

    if (order.value.estado === 'cancelado' || order.value.estado === 'vencido') {
      state.value = 'closed'
      clearPendingPayment()

      return
    }
  }

  if (elapsed >= POLL_DURATION_MS) {
    state.value = 'timeout'

    return
  }

  elapsed += POLL_INTERVAL_MS
  timer = setTimeout(() => void check(), POLL_INTERVAL_MS)
}

onMounted(() => void check())

onUnmounted(() => {
  if (timer) {
    clearTimeout(timer)
  }
})
</script>

<template>
  <main class="payment-processing">
    <h1>Pago del pedido</h1>
    <div v-if="state === 'waiting'" class="payment-processing__waiting" role="status">
      <p>Procesando su pago…</p>
      <p>Esta página se actualiza sola cuando la pasarela confirme el pago a la tienda.</p>
    </div>
    <template v-else-if="state === 'confirmed' && order">
      <p class="payment-processing__done" role="status">Pago confirmado.</p>
      <OrderSummary :order="order" />
    </template>
    <p v-else-if="state === 'closed' && order" class="payment-processing__closed" role="status">
      El pedido está «{{ statusLabel(order.estado) }}», así que el pago no se aplicó.
    </p>
    <p v-else-if="state === 'timeout'" class="payment-processing__timeout" role="status">
      Su pago sigue en proceso. Verá su estado en «Mis pedidos» cuando la pasarela lo confirme.
    </p>
    <p v-else-if="state === 'missing'" class="payment-processing__missing">
      No hay un pago en curso en esta pestaña. Revise el estado de sus pedidos en «Mis pedidos».
    </p>
    <ProblemNotice v-else-if="problem" :problem="problem" />
    <RouterLink :to="{ name: 'orders' }">Ver mis pedidos</RouterLink>
  </main>
</template>
