<script setup lang="ts">
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import { api } from '@/api/client'
import { callApi } from '@/api/problem'
import { defaultBusinessSlug } from '@/shared/config'
import EmptyState from '@/shared/EmptyState.vue'
import ErrorState from '@/shared/ErrorState.vue'
import LoadingState from '@/shared/LoadingState.vue'
import { useApiData } from '@/shared/useApiData'
import { appointmentLinkText, namedItems, statusLabel } from './appointment'
import { formatHours } from './time'

/**
 * "Mis citas" (#129), from `GET /cuenta/citas`: the upcoming appointments, the closest first, or the past ones, the
 * most recent first, each on its own page so that the two never mix. Each appointment shows its store, its state in
 * Spanish, its hours in the clock of the store and what it tries, and opens its own page, where it can be moved or
 * cancelled. The page of results lives in the URL (`?pagina=`).
 */
const props = defineProps<{ period: 'proximas' | 'pasadas' }>()

const route = useRoute()
const page = computed(() => {
  const value = Number(route.query.pagina)

  return Number.isInteger(value) && value > 1 ? value : 1
})

const appointments = useApiData(
  () => callApi(() => api.GET('/cuenta/citas', { params: { query: { periodo: props.period, pagina: page.value } } })),
  (result) => result.data.length === 0,
)

const meta = computed(() => appointments.data.value?.meta ?? null)
const title = computed(() => (props.period === 'proximas' ? 'Mis citas' : 'Mis citas pasadas'))
const routeName = computed(() => (props.period === 'proximas' ? 'appointments' : 'past-appointments'))
const heading = useTemplateRef<HTMLElement>('heading')
const notice = ref('')

// The two lists share this view, so a change of list loads again too: the router moves the focus and names the page
// then. A new page of results keeps the route, so the focus and the announcement are the view's.
watch(
  [() => props.period, page],
  async ([period, current], previous) => {
    if (period !== previous[0]) {
      notice.value = ''
      await appointments.reload()

      return
    }

    notice.value = meta.value ? `Página ${current} de ${meta.value.last_page}.` : ''
    await appointments.reload()
    await nextTick()
    heading.value?.focus()
  },
  { immediate: true },
)
</script>

<template>
  <div class="page appointments">
    <h1 ref="heading" tabindex="-1">{{ title }}</h1>
    <nav class="sub-nav" aria-label="Tus citas">
      <ul>
        <li><RouterLink :to="{ name: 'appointments' }">Próximas</RouterLink></li>
        <li><RouterLink :to="{ name: 'past-appointments' }">Pasadas</RouterLink></li>
      </ul>
    </nav>
    <!-- Always in the page, so screen readers announce a new page of results. -->
    <p class="results-summary" role="status">{{ notice }}</p>

    <LoadingState v-if="appointments.loading.value" message="Cargando tus citas…" />
    <ErrorState v-else-if="appointments.problem.value" :problem="appointments.problem.value" @retry="appointments.reload" />
    <EmptyState
      v-else-if="appointments.empty.value"
      :message="period === 'proximas' ? 'No tienes citas próximas.' : 'No tienes citas pasadas.'"
    >
      <RouterLink
        v-if="period === 'proximas'"
        :to="{ name: 'book-appointment', params: { tienda: defaultBusinessSlug() } }"
        class="action-link"
      >
        Reservar una cita de prueba
      </RouterLink>
    </EmptyState>
    <template v-else-if="appointments.data.value">
      <ul class="card-list appointment-list">
        <li v-for="appointment in appointments.data.value.data" :key="appointment.id" class="card">
          <h2>
            <RouterLink :to="{ name: 'appointment', params: { cita: appointment.id } }" class="action-link">
              {{ appointmentLinkText(appointment) }}
            </RouterLink>
          </h2>
          <dl class="facts">
            <div>
              <dt>Tienda</dt>
              <dd>{{ appointment.tienda.nombre }}</dd>
            </div>
            <div>
              <dt>Estado</dt>
              <dd class="appointment-list__status">{{ statusLabel(appointment.estado) }}</dd>
            </div>
            <div>
              <dt>Hora</dt>
              <dd>De {{ formatHours(appointment.inicia_en, appointment.termina_en) }}</dd>
            </div>
            <div>
              <dt>Servicio</dt>
              <dd>{{ appointment.servicio.nombre }}</dd>
            </div>
            <div>
              <dt>Te atiende</dt>
              <dd>{{ appointment.persona.nombre }}</dd>
            </div>
            <div v-if="appointment.lugar_cita">
              <dt>Lugar</dt>
              <dd>{{ appointment.lugar_cita }}</dd>
            </div>
            <div>
              <dt>Perfumes para probar</dt>
              <dd>{{ namedItems(appointment.perfumes).map((perfume) => perfume.nombre).join(', ') || 'Los elige en la cita' }}</dd>
            </div>
          </dl>
        </li>
      </ul>
      <nav v-if="meta && meta.last_page > 1" class="pagination" :aria-label="`Páginas de ${title.toLowerCase()}`">
        <RouterLink
          v-if="meta.current_page > 1"
          :to="{ name: routeName, query: { pagina: meta.current_page - 1 } }"
          class="action-link"
        >
          Página anterior
        </RouterLink>
        <span>Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <RouterLink
          v-if="meta.current_page < meta.last_page"
          :to="{ name: routeName, query: { pagina: meta.current_page + 1 } }"
          class="action-link"
        >
          Página siguiente
        </RouterLink>
      </nav>
    </template>
  </div>
</template>
