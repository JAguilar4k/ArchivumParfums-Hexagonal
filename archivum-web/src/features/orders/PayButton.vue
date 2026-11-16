<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'

import { api } from '@/api/client'
import { callApi, type ApiProblem } from '@/api/problem'
import ProblemNotice from '@/shared/ProblemNotice.vue'
import { formatCharge, formatColones } from '@/shared/money'
import { leaveTo } from '@/shared/navigation'
import { amountToPay, type Order, type Payment } from './order'
import { savePendingPayment } from './pendingPayment'

/**
 * Starts the online payment of an order (#130) with `POST /cuenta/pedidos/{pedido}/pagos`, which charges what the
 * API saved, never an amount of the browser. The button is disabled from the first click, so a double click sends
 * one request; the API also answers a repeated request with the payment that is still pending (#92). A gateway that
 * charges in dollars (PayPal) first shows the amount that the API reports.
 */
const props = defineProps<{ order: Order }>()

const router = useRouter()
const paying = ref(false)
const problem = ref<ApiProblem | null>(null)
const foreignCharge = ref<Payment | null>(null)

async function pay(): Promise<void> {
  if (paying.value) {
    return
  }

  paying.value = true
  problem.value = null

  const outcome = await callApi(() =>
    api.POST('/cuenta/pedidos/{pedido}/pagos', { params: { path: { pedido: props.order.id } } }),
  )

  if (outcome.problem) {
    problem.value = outcome.problem
    paying.value = false

    return
  }

  const payment = outcome.data.data
  savePendingPayment({ orderId: props.order.id, paidBefore: props.order.pagado })

  if (payment.url_pago && payment.moneda_cobrada !== 'CRC') {
    foreignCharge.value = payment

    return
  }

  await goToGateway(payment)
}

/** Opens the checkout of the gateway, or the "procesando" view when the API gave no checkout to open. */
async function goToGateway(payment: Payment): Promise<void> {
  if (!payment.url_pago || !leaveTo(payment.url_pago)) {
    await router.push({ name: 'payment-processing' })
  }
}
</script>

<template>
  <div class="pay-order">
    <button type="button" :disabled="paying" @click="pay">Pagar {{ formatColones(amountToPay(order)) }}</button>
    <div v-if="foreignCharge" class="pay-order__foreign" role="status">
      <p>
        Esta pasarela cobra en dólares: se le cobrarán
        <strong>{{ formatCharge(foreignCharge.monto_cobrado, foreignCharge.moneda_cobrada) }}</strong>
        por {{ formatColones(foreignCharge.monto) }}.
      </p>
      <button type="button" @click="goToGateway(foreignCharge)">Continuar al pago</button>
    </div>
    <ProblemNotice v-if="problem" :problem="problem" />
  </div>
</template>
