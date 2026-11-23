<script setup lang="ts">
import { nextTick, ref, useTemplateRef } from 'vue'

import { api } from '@/api/client'
import { callApi, type ApiProblem } from '@/api/problem'
import ProblemNotice from '@/shared/ProblemNotice.vue'
import type { Order } from './order'

/**
 * Cancels an order with `POST /cuenta/pedidos/{pedido}/cancelacion` (#83), after the customer confirms it (WCAG
 * 3.3.6). The question takes the place of the button, so the focus goes to "No, mantener el pedido", the choice that
 * changes nothing, and comes back to "Cancelar el pedido" when the customer keeps it. When the API refuses it, its 409
 * is shown. Emits the order as the API answers it; the view moves the focus, because the button disappears.
 * `describedBy` names the order in a list of orders.
 */
const props = defineProps<{ order: Order; describedBy?: string }>()
const emit = defineEmits<{ cancelled: [order: Order] }>()

const confirming = ref(false)
const sending = ref(false)
const problem = ref<ApiProblem | null>(null)
const startButton = useTemplateRef<HTMLButtonElement>('startButton')
const keepButton = useTemplateRef<HTMLButtonElement>('keepButton')

async function ask(): Promise<void> {
  confirming.value = true
  problem.value = null
  await nextTick()
  keepButton.value?.focus()
}

async function keep(): Promise<void> {
  if (sending.value) {
    return
  }

  confirming.value = false
  await nextTick()
  startButton.value?.focus()
}

async function cancel(): Promise<void> {
  if (sending.value) {
    return
  }

  sending.value = true
  problem.value = null

  const outcome = await callApi(() =>
    api.POST('/cuenta/pedidos/{pedido}/cancelacion', { params: { path: { pedido: props.order.id } } }),
  )

  sending.value = false
  confirming.value = false

  if (outcome.problem) {
    problem.value = outcome.problem
    await nextTick()
    startButton.value?.focus()

    return
  }

  emit('cancelled', outcome.data.data)
}
</script>

<template>
  <div class="cancel-order">
    <button
      v-if="!confirming"
      ref="startButton"
      type="button"
      class="button--secondary"
      :aria-describedby="describedBy"
      @click="ask"
    >
      Cancelar el pedido
    </button>
    <div v-else class="cancel-order__confirm" role="group" :aria-labelledby="`pregunta-cancelar-${order.id}`">
      <p :id="`pregunta-cancelar-${order.id}`">
        ¿Seguro que quieres cancelar este pedido? Si ya pagaste algo, se te devolverá todo lo pagado.
      </p>
      <div class="form-actions">
        <button type="button" :aria-disabled="sending ? 'true' : undefined" @click="cancel">Sí, cancelar el pedido</button>
        <button
          ref="keepButton"
          type="button"
          class="button--secondary"
          :aria-disabled="sending ? 'true' : undefined"
          @click="keep"
        >
          No, mantener el pedido
        </button>
      </div>
    </div>
    <ProblemNotice v-if="problem" :problem="problem" />
  </div>
</template>
