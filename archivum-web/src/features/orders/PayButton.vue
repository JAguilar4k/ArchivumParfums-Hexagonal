<script setup lang="ts">
import { nextTick, ref, useTemplateRef } from 'vue'
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
 * API saved, never an amount of the browser. The button is busy from the first click (aria-disabled, so it keeps the
 * focus), so a double click sends one request; the API also answers a repeated request with the payment that is still
 * pending (#92). A hint says beforehand that the payment opens the page of the gateway (WCAG 3.2.5). A gateway that
 * charges in dollars (PayPal) first shows the amount that the API reports, and the focus goes to "Continuar al pago",
 * which reads that amount. `describedBy` names the order in a list of orders.
 */
const props = defineProps<{ order: Order; describedBy?: string }>()

const router = useRouter()
const paying = ref(false)
const problem = ref<ApiProblem | null>(null)
const foreignCharge = ref<Payment | null>(null)
const continueButton = useTemplateRef<HTMLButtonElement>('continueButton')

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
  savePendingPayment({ orderId: props.order.id, paidBefore: props.order.pagado, paymentId: payment.id })

  if (payment.estado === 'fallido' || payment.estado === 'vencido') {
    await router.push({ name: 'payment-processing' })

    return
  }

  if (payment.url_pago && payment.moneda_cobrada !== 'CRC') {
    foreignCharge.value = payment
    await nextTick()
    continueButton.value?.focus()

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
    <button
      type="button"
      :aria-disabled="paying ? 'true' : undefined"
      :aria-describedby="[describedBy, `pista-pago-${order.id}`].filter(Boolean).join(' ')"
      @click="pay"
    >
      Pagar {{ formatColones(amountToPay(order)) }}
    </button>
    <p :id="`pista-pago-${order.id}`" class="field-hint">Se abre la página segura de la pasarela de pago.</p>
    <div v-if="foreignCharge" class="pay-order__foreign">
      <p :id="`cobro-${order.id}`">
        Esta pasarela cobra en dólares: se te cobrarán
        <strong>{{ formatCharge(foreignCharge.monto_cobrado, foreignCharge.moneda_cobrada) }}</strong>
        por {{ formatColones(foreignCharge.monto) }}.
      </p>
      <button
        ref="continueButton"
        type="button"
        :aria-describedby="`cobro-${order.id}`"
        @click="goToGateway(foreignCharge)"
      >
        Continuar al pago
      </button>
    </div>
    <ProblemNotice v-if="problem" :problem="problem" />
  </div>
</template>
