<script setup lang="ts">
import { nextTick, ref, useTemplateRef } from 'vue'

import { api } from '@/api/client'
import { callApi, type ApiProblem } from '@/api/problem'
import ProblemNotice from '@/shared/ProblemNotice.vue'
import type { Appointment } from './appointment'

/**
 * Cancels an appointment with `POST /cuenta/citas/{cita}/cancelacion` (#66), after the customer confirms it (WCAG
 * 3.3.6). The question takes the place of the button, so the focus goes to "No, mantener la cita", the choice that
 * changes nothing, and comes back to "Cancelar la cita" when the customer keeps it. Past the deadline of the store the
 * API answers 409, and its message says until when a change was possible. Emits the appointment as the API answers it;
 * the view moves the focus, because the button disappears.
 */
const props = defineProps<{ appointment: Appointment }>()
const emit = defineEmits<{ cancelled: [appointment: Appointment] }>()

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
    api.POST('/cuenta/citas/{cita}/cancelacion', { params: { path: { cita: props.appointment.id } }, body: {} }),
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
  <div class="cancel-appointment">
    <button v-if="!confirming" ref="startButton" type="button" class="button--secondary" @click="ask">
      Cancelar la cita
    </button>
    <div v-else class="cancel-order__confirm" role="group" aria-labelledby="pregunta-cancelar-cita">
      <p id="pregunta-cancelar-cita">¿Seguro que quiere cancelar esta cita? El espacio queda libre para otra persona.</p>
      <div class="form-actions">
        <button type="button" :aria-disabled="sending ? 'true' : undefined" @click="cancel">Sí, cancelar la cita</button>
        <button
          ref="keepButton"
          type="button"
          class="button--secondary"
          :aria-disabled="sending ? 'true' : undefined"
          @click="keep"
        >
          No, mantener la cita
        </button>
      </div>
    </div>
    <ProblemNotice v-if="problem" :problem="problem" />
  </div>
</template>
