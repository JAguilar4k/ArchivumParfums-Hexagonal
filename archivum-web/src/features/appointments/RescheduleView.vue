<script setup lang="ts">
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import { api } from '@/api/client'
import { callApi, fieldAttributes, focusFirstInvalidField, type ApiProblem } from '@/api/problem'
import ErrorState from '@/shared/ErrorState.vue'
import FieldError from '@/shared/FieldError.vue'
import LoadingState from '@/shared/LoadingState.vue'
import ProblemNotice from '@/shared/ProblemNotice.vue'
import { useApiData } from '@/shared/useApiData'
import { appointmentTitle, canChange } from './appointment'
import { leaveNotice } from './notice'
import SlotList from './SlotList.vue'
import { calendarDayOf, formatDay, formatHours, todayAtOffsetOf } from './time'
import { useSlots } from './useSlots'

/**
 * "Cambiar la hora de la cita" (#129): another free slot of the same service and the same person, from the
 * availability of the store of the appointment, and `PATCH /cuenta/citas/{cita}` (#65). The page opens with the free
 * slots of the day of the appointment. A 409 explains why: the slot was taken meanwhile, and the list loads again, or
 * the deadline of the store passed. The API decides both; the page does not repeat its rules.
 */
const route = useRoute()
const router = useRouter()
const appointmentId = computed(() => String(route.params.cita))

const detail = useApiData(() =>
  callApi(() => api.GET('/cuenta/citas/{cita}', { params: { path: { cita: appointmentId.value } } })),
)
const appointment = computed(() => detail.data.value?.data ?? null)
const title = computed(() => (appointment.value ? appointmentTitle(appointment.value) : ''))

const day = ref('')
const start = ref<string | null>(null)
const slots = useSlots()
const problem = ref<ApiProblem | null>(null)
const sending = ref(false)
const formElement = useTemplateRef<HTMLFormElement>('formElement')
const dayField = useTemplateRef<HTMLInputElement>('dayField')

const today = computed(() => (appointment.value ? todayAtOffsetOf(appointment.value.inicia_en) : ''))

async function showSlots(): Promise<void> {
  if (!appointment.value) {
    return
  }

  if (!day.value) {
    problem.value = {
      kind: 'validation',
      status: null,
      type: null,
      message: 'Revise los datos marcados.',
      fieldErrors: { fecha: ['Elija el día de la cita.'] },
      requestId: null,
      retryAfterSeconds: null,
    }
    await nextTick()
    dayField.value?.focus()

    return
  }

  problem.value = null
  start.value = null
  await slots.load({
    tienda: appointment.value.tienda.slug,
    servicio: appointment.value.servicio.id,
    fecha: day.value,
    persona: appointment.value.persona.id,
  })
}

async function reschedule(): Promise<void> {
  if (sending.value || !appointment.value) {
    return
  }

  if (!start.value) {
    problem.value = {
      kind: 'validation',
      status: null,
      type: null,
      message: 'Revise los datos marcados.',
      fieldErrors:
        slots.slots.value.length > 0
          ? { inicia_en: ['Elija una hora de la lista.'] }
          : { fecha: ['Elija el día y pulse «Ver los horarios libres» para escoger la hora.'] },
      requestId: null,
      retryAfterSeconds: null,
    }
    await nextTick()

    if (formElement.value) {
      focusFirstInvalidField(formElement.value, problem.value)
    }

    return
  }

  sending.value = true
  problem.value = null

  const outcome = await callApi(() =>
    api.PATCH('/cuenta/citas/{cita}', {
      params: { path: { cita: appointmentId.value } },
      body: { inicia_en: start.value ?? '' },
    }),
  )

  sending.value = false

  if (outcome.problem) {
    if (outcome.problem.kind === 'conflict' && slots.day.value) {
      // The slot may have been taken meanwhile: the list of the same day loads again.
      await showSlots()
    }

    problem.value = outcome.problem
    await nextTick()

    if (outcome.problem.kind === 'validation' && formElement.value) {
      focusFirstInvalidField(formElement.value, outcome.problem)
    }

    return
  }

  leaveNotice(appointmentId.value, 'Se cambió la hora de su cita.')
  await router.push({ name: 'appointment', params: { cita: appointmentId.value } })
}

// Another day makes the slots of the list useless.
watch(day, (current) => {
  if (slots.day.value && current !== slots.day.value) {
    slots.clear()
    start.value = null
  }
})

watch(
  appointmentId,
  async () => {
    slots.clear()
    start.value = null
    problem.value = null
    await detail.reload()

    if (appointment.value && canChange(appointment.value)) {
      day.value = calendarDayOf(appointment.value.inicia_en)
      await showSlots()
    }
  },
  { immediate: true },
)
</script>

<template>
  <div class="page reschedule">
    <LoadingState v-if="detail.loading.value" message="Cargando la cita…" />
    <ErrorState v-else-if="detail.problem.value" :problem="detail.problem.value" @retry="detail.reload" />
    <template v-else-if="appointment">
      <nav class="breadcrumbs" aria-label="Ubicación">
        <ol>
          <li><RouterLink :to="{ name: 'appointments' }">Mis citas</RouterLink></li>
          <li>
            <RouterLink :to="{ name: 'appointment', params: { cita: appointment.id } }">{{ title }}</RouterLink>
          </li>
          <li><span aria-current="page">Cambiar la hora</span></li>
        </ol>
      </nav>
      <h1>Cambiar la hora de la cita</h1>

      <template v-if="canChange(appointment)">
        <div class="prose">
          <p>
            Su cita es el {{ formatDay(appointment.inicia_en) }}, de
            {{ formatHours(appointment.inicia_en, appointment.termina_en) }}, con {{ appointment.persona.nombre }}.
            Elija otro día y otra hora con la misma persona. Las horas son las de la tienda.
          </p>
        </div>

        <form ref="formElement" class="form" novalidate @submit.prevent="reschedule">
          <div class="form-field">
            <label for="fecha">Día de la cita</label>
            <p id="pista-fecha" class="field-hint">Elija el día y pulse «Ver los horarios libres».</p>
            <input
              id="fecha"
              ref="dayField"
              v-model="day"
              type="date"
              name="fecha"
              :min="today"
              v-bind="fieldAttributes(problem, 'fecha', 'pista-fecha')"
              @keydown.enter.prevent="showSlots"
            />
            <FieldError :problem="problem" field="fecha" />
          </div>
          <div class="form-actions">
            <button
              type="button"
              class="button--secondary"
              :aria-disabled="slots.loading.value ? 'true' : undefined"
              @click="showSlots"
            >
              Ver los horarios libres
            </button>
          </div>
          <!-- Always in the page, so screen readers announce how many slots there are. -->
          <p class="results-summary" role="status">
            {{ slots.loading.value ? 'Buscando horarios libres…' : slots.summary.value }}
          </p>
          <ProblemNotice v-if="slots.problem.value" :problem="slots.problem.value" />

          <SlotList
            v-if="slots.slots.value.length > 0"
            v-model:start="start"
            :slots="slots.slots.value"
            :day="slots.day.value"
            :problem="problem"
            :with-people="false"
          />

          <ProblemNotice v-if="problem && problem.kind !== 'validation'" :problem="problem" />
          <div class="form-actions">
            <!-- aria-disabled and not disabled: the button keeps the focus while the change is sent. -->
            <button type="submit" :aria-disabled="sending ? 'true' : undefined">
              {{ sending ? 'Cambiando la hora…' : 'Cambiar a esta hora' }}
            </button>
            <RouterLink :to="{ name: 'appointment', params: { cita: appointment.id } }" class="button button--secondary">
              Volver sin cambiar
            </RouterLink>
          </div>
        </form>
      </template>
      <template v-else>
        <p class="status-message">Esta cita ya no se puede cambiar, porque ya pasó o ya no está activa.</p>
        <RouterLink :to="{ name: 'appointment', params: { cita: appointment.id } }" class="action-link">
          Volver a la cita
        </RouterLink>
      </template>
    </template>
  </div>
</template>
