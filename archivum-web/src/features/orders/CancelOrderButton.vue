<script setup lang="ts">
import { ref } from 'vue'

import { api } from '@/api/client'
import { callApi, type ApiProblem } from '@/api/problem'
import ProblemNotice from '@/shared/ProblemNotice.vue'
import type { Order } from './order'

/**
 * Cancels an order with `POST /cuenta/pedidos/{pedido}/cancelacion` (#83), after the customer confirms it. When the
 * API refuses it, its 409 is shown. Emits the order as the API answers it.
 */
const props = defineProps<{ order: Order }>()
const emit = defineEmits<{ cancelled: [order: Order] }>()

const confirming = ref(false)
const sending = ref(false)
const problem = ref<ApiProblem | null>(null)

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

    return
  }

  emit('cancelled', outcome.data.data)
}
</script>

<template>
  <div class="cancel-order">
    <button v-if="!confirming" type="button" @click="confirming = true">Cancelar el pedido</button>
    <template v-else>
      <p>¿Seguro que quiere cancelar este pedido?</p>
      <button type="button" :disabled="sending" @click="cancel">Sí, cancelar</button>
      <button type="button" :disabled="sending" @click="confirming = false">No</button>
    </template>
    <ProblemNotice v-if="problem" :problem="problem" />
  </div>
</template>
