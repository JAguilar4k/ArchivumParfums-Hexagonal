<script setup lang="ts">
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import { api } from '@/api/client'
import { callApi } from '@/api/problem'
import ErrorState from '@/shared/ErrorState.vue'
import LoadingState from '@/shared/LoadingState.vue'
import { usePageTitle } from '@/shared/pageChanges'
import { useApiData } from '@/shared/useApiData'
import { appointmentTitle, canChange, namedItems, statusLabel, type Appointment } from './appointment'
import CancelAppointmentButton from './CancelAppointmentButton.vue'
import { takeNotice } from './notice'
import { formatDay, formatHours } from './time'

/**
 * An appointment of the customer (#129), from `GET /cuenta/citas/{cita}`: the page that opens after booking it. It
 * shows the day and the hours in the clock of the store, who attends, the place and the perfumes to try, and, while the
 * appointment is ahead, the way to move it to another hour or to cancel it. An appointment of another account answers
 * 404 and shows "No encontrado". After a cancellation the focus goes to the title, because the button that was used
 * disappears, and a line that screen readers announce says what happened.
 */
const route = useRoute()
const appointmentId = computed(() => String(route.params.cita))

const detail = useApiData(() =>
  callApi(() => api.GET('/cuenta/citas/{cita}', { params: { path: { cita: appointmentId.value } } })),
)

const appointment = computed(() => detail.data.value?.data ?? null)
const title = computed(() => (appointment.value ? appointmentTitle(appointment.value) : null))
const perfumes = computed(() => (appointment.value ? namedItems(appointment.value.perfumes) : []))
const heading = useTemplateRef<HTMLElement>('heading')
const notice = ref('')

usePageTitle(() => title.value)

async function cancelled(changed: Appointment): Promise<void> {
  detail.data.value = { data: changed }
  notice.value = 'Se canceló la cita.'
  await nextTick()
  heading.value?.focus()
}

watch(
  appointmentId,
  async (id) => {
    notice.value = ''
    // The booking or the new hour leave their message for this page.
    const message = takeNotice(id)
    await detail.reload()
    notice.value = message
  },
  { immediate: true },
)
</script>

<template>
  <div class="page appointment-view">
    <LoadingState v-if="detail.loading.value" message="Cargando la cita…" />
    <ErrorState v-else-if="detail.problem.value" :problem="detail.problem.value" @retry="detail.reload" />
    <template v-else-if="appointment">
      <nav class="breadcrumbs" aria-label="Ubicación">
        <ol>
          <li><RouterLink :to="{ name: 'appointments' }">Mis citas</RouterLink></li>
          <li><span aria-current="page">{{ title }}</span></li>
        </ol>
      </nav>
      <h1 ref="heading" tabindex="-1">{{ title }}</h1>
    </template>
    <!-- Always in the page, so screen readers announce a booking, a new hour or a cancellation. -->
    <p class="results-summary status-message" role="status">{{ notice }}</p>
    <template v-if="!detail.loading.value && !detail.problem.value && appointment">
      <dl class="facts">
        <div>
          <dt>Estado</dt>
          <dd class="appointment-view__status">{{ statusLabel(appointment.estado) }}</dd>
        </div>
        <div>
          <dt>Día</dt>
          <dd>{{ formatDay(appointment.inicia_en) }}</dd>
        </div>
        <div>
          <dt>Hora</dt>
          <dd>De {{ formatHours(appointment.inicia_en, appointment.termina_en) }}, hora de la tienda</dd>
        </div>
        <div>
          <dt>Tienda</dt>
          <dd>{{ appointment.tienda.nombre }}</dd>
        </div>
        <div>
          <dt>Lugar</dt>
          <dd>{{ appointment.lugar_cita ?? 'La tienda le confirmará el lugar.' }}</dd>
        </div>
        <div>
          <dt>Servicio</dt>
          <dd>{{ appointment.servicio.nombre }}</dd>
        </div>
        <div>
          <dt>Le atiende</dt>
          <dd>{{ appointment.persona.nombre }}</dd>
        </div>
        <div>
          <dt>Perfumes para probar</dt>
          <dd>{{ perfumes.map((perfume) => perfume.nombre).join(', ') || 'Los elige en la cita' }}</dd>
        </div>
      </dl>
      <div v-if="canChange(appointment)" class="appointment-view__actions">
        <p class="field-hint">
          La tienda acepta cambios hasta unas horas antes de la cita. Si ya no se puede, se lo explica al intentarlo.
        </p>
        <RouterLink :to="{ name: 'reschedule-appointment', params: { cita: appointment.id } }" class="button">
          Cambiar la hora
        </RouterLink>
        <CancelAppointmentButton :appointment="appointment" @cancelled="cancelled" />
      </div>
      <RouterLink :to="{ name: 'appointments' }" class="action-link">Ver todas mis citas</RouterLink>
    </template>
  </div>
</template>
