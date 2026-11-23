<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

import { api } from '@/api/client'
import { callApi, type ApiProblem } from '@/api/problem'
import { statusLabel, type Order } from './order'
import OrderSummary from './OrderSummary.vue'
import { clearPendingPayment, POLL_DURATION_MS, POLL_INTERVAL_MS, readPendingPayment } from './pendingPayment'

/**
 * "Procesando" (#130): the gateway returns here, to the address of PAYMENTS_RETURN_URL in the API. The view asks
 * `GET /cuenta/pedidos/{pedido}` to see whether the API credited the order. It also reads the payment because a
 * rejected attempt can leave its order waiting for another payment. After 2 min it says the payment is still in
 * process. The verified notice (#94) or reconciliation (#96) handles settlement on the server.
 * Every state is said in one line that is always in the page, so screen readers announce each change, and a problem
 * is said there too, without a second heading of level 1.
 */
type State = 'waiting' | 'confirmed' | 'closed' | 'declined' | 'expired' | 'timeout' | 'missing' | 'failed'

const pending = readPendingPayment()
const state = ref<State>(pending ? 'waiting' : 'missing')
const order = ref<Order | null>(null)
const problem = ref<ApiProblem | null>(null)
let timer: ReturnType<typeof setTimeout> | null = null
let elapsed = 0
let stopped = false

const STATE_CLASSES: Record<State, string> = {
  waiting: 'payment-processing__waiting',
  confirmed: 'payment-processing__done',
  closed: 'payment-processing__closed',
  declined: 'payment-processing__declined',
  expired: 'payment-processing__expired',
  timeout: 'payment-processing__timeout',
  missing: 'payment-processing__missing',
  failed: 'payment-processing__failed',
}

const statusText = computed(() => {
  switch (state.value) {
    case 'waiting':
      return 'Procesando tu pago…'
    case 'confirmed':
      return 'Pago confirmado.'
    case 'closed':
      return order.value ? `El pedido está «${statusLabel(order.value.estado)}», así que el pago no se aplicó.` : ''
    case 'declined':
      return 'El pago fue rechazado. Consulte el pedido para volver a intentarlo.'
    case 'expired':
      return 'El intento de pago venció. Consulte el pedido para volver a intentarlo.'
    case 'timeout':
      return 'Tu pago sigue en proceso. Verás su estado en «Mis pedidos» cuando la pasarela lo confirme.'
    case 'missing':
      return 'No hay un pago en curso en esta pestaña. Revisa el estado de tus pedidos en «Mis pedidos».'
    case 'failed':
      return failureText(problem.value)
  }

  return ''
})

/** The problem of the order in one sentence: "No encontrado" and "Sin permiso" have no page of their own here. */
function failureText(found: ApiProblem | null): string {
  if (found?.kind === 'not-found') {
    return 'No encontramos el pedido de este pago. Revisa tus pedidos en «Mis pedidos».'
  }

  if (found?.kind === 'forbidden') {
    return 'Tu cuenta no tiene permiso para ver el pedido de este pago.'
  }

  return found?.message ?? ''
}

async function check(): Promise<void> {
  if (!pending) {
    return
  }

  const outcome = await callApi(() =>
    api.GET('/cuenta/pedidos/{pedido}', { params: { path: { pedido: pending.orderId } } }),
  )

  if (stopped) {
    return
  }

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

  const paymentId = pending.paymentId

  if (paymentId) {
    const payment = await callApi(() => api.GET('/cuenta/pagos/{pago}', { params: { path: { pago: paymentId } } }))

    if (stopped) {
      return
    }

    const status = payment.data?.data.estado

    if (status === 'fallido' || status === 'vencido') {
      state.value = status === 'fallido' ? 'declined' : 'expired'
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
  stopped = true

  if (timer) {
    clearTimeout(timer)
  }
})
</script>

<template>
  <div class="page payment-processing">
    <h1>Pago del pedido</h1>
    <p class="payment-processing__status" :class="STATE_CLASSES[state]" role="status">{{ statusText }}</p>
    <p v-if="state === 'waiting'" class="payment-processing__hint">
      Esta página se actualiza sola cuando la pasarela confirme el pago a la tienda. Puede tardar hasta 2 minutos.
    </p>
    <OrderSummary v-if="state === 'confirmed' && order" :order="order" />
    <RouterLink :to="{ name: 'orders' }" class="action-link">Ver mis pedidos</RouterLink>
  </div>
</template>
