<script setup lang="ts">
import { computed, inject, nextTick, onMounted, reactive, ref, useTemplateRef, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import { api } from '@/api/client'
import { callApi, fieldAttributes, focusFirstInvalidField, groupAttributes, type ApiProblem } from '@/api/problem'
import { useSessionStore } from '@/features/session/store'
import { STORE_PROFILE } from '@/features/storefront/profile'
import EmptyState from '@/shared/EmptyState.vue'
import ErrorState from '@/shared/ErrorState.vue'
import FieldError from '@/shared/FieldError.vue'
import LoadingState from '@/shared/LoadingState.vue'
import ProblemNotice from '@/shared/ProblemNotice.vue'
import { formatColones } from '@/shared/money'
import { useApiData } from '@/shared/useApiData'
import { namedItems, type Named, type Service } from './appointment'
import { leaveNotice } from './notice'
import PerfumePicker from './PerfumePicker.vue'
import SlotList from './SlotList.vue'
import { todayIn } from './time'
import { useSlots } from './useSlots'

/**
 * "Reservar una cita de prueba" (#129). The customer chooses the service (`GET /tiendas/{tienda}/servicios`), the day,
 * one of the free slots that the API gives for it (`GET /tiendas/{tienda}/disponibilidad`), the person if more than
 * one can attend, and the perfumes to try, and books with `POST /tiendas/{tienda}/citas`. The browser never works out
 * the availability: a 409 says that the slot was taken, asks again for the slots of the same day and keeps the rest of
 * the choice. A visitor signs in first and comes back with the same choice, which travels in the address.
 */
const route = useRoute()
const router = useRouter()
const session = useSessionStore()
const profile = inject(STORE_PROFILE, null)

const tienda = computed(() => String(route.params.tienda))
const services = useApiData(
  () => callApi(() => api.GET('/tiendas/{tienda}/servicios', { params: { path: { tienda: tienda.value } } })),
  (result) => result.data.length === 0,
)
const serviceList = computed<Service[]>(() => services.data.value?.data ?? [])

const form = reactive({ servicio: '', fecha: '', inicio: null as string | null, persona: '' })
const perfumes = ref<Named[]>([])
const slots = useSlots()
const problem = ref<ApiProblem | null>(null)
const sending = ref(false)
const formElement = useTemplateRef<HTMLFormElement>('formElement')
const dayField = useTemplateRef<HTMLInputElement>('dayField')

const today = computed(() => todayIn(profile?.value?.zona_horaria))
const service = computed(() => serviceList.value.find((item) => item.id === form.servicio) ?? null)
const chosenSlot = computed(() => slots.slots.value.find((slot) => slot.inicia_en === form.inicio) ?? null)
const people = computed(() => (chosenSlot.value ? namedItems(chosenSlot.value.personas) : []))
const isCustomer = computed(() => session.role === 'cliente')

/** The choice in the address, so that the sign-in returns to it. */
const choicePath = computed(
  () =>
    router.resolve({
      name: 'book-appointment',
      params: { tienda: tienda.value },
      query: {
        ...(form.servicio ? { servicio: form.servicio } : {}),
        ...(form.fecha ? { fecha: form.fecha } : {}),
        ...(form.inicio ? { inicio: form.inicio } : {}),
        ...(form.persona ? { persona: form.persona } : {}),
        ...(perfumes.value.length > 0 ? { perfumes: perfumes.value.map((perfume) => perfume.id).join(',') } : {}),
      },
    }).fullPath,
)

function describe(item: Service): string {
  const price = item.precio === 0 ? 'sin costo' : formatColones(item.precio)

  return `${item.nombre}: ${item.duracion_minutos} minutos, ${price}`
}

/** A problem of the form found before asking the API, shown and announced like a 422. */
function localProblem(fieldErrors: Record<string, string[]>): ApiProblem {
  return {
    kind: 'validation',
    status: null,
    type: null,
    message: 'Revise los datos marcados.',
    fieldErrors,
    requestId: null,
    retryAfterSeconds: null,
  }
}

async function showProblem(found: ApiProblem): Promise<void> {
  problem.value = found
  await nextTick()

  if (formElement.value) {
    focusFirstInvalidField(formElement.value, found)
  }
}

async function showSlots(): Promise<void> {
  const errors: Record<string, string[]> = {}

  if (!form.servicio) {
    errors.servicio_id = ['Elija un servicio.']
  }

  if (!form.fecha) {
    errors.fecha = ['Elija el día de la cita.']
  }

  if (Object.keys(errors).length > 0) {
    await showProblem(localProblem(errors))

    return
  }

  problem.value = null
  form.inicio = null
  await slots.load({ tienda: tienda.value, servicio: form.servicio, fecha: form.fecha })
}

async function book(): Promise<void> {
  if (sending.value) {
    return
  }

  if (!form.servicio || !form.inicio) {
    const errors: Record<string, string[]> = {}

    if (!form.servicio) {
      errors.servicio_id = ['Elija un servicio.']
    }

    if (!form.inicio && slots.slots.value.length > 0) {
      errors.inicia_en = ['Elija una hora de la lista.']
    } else if (!form.inicio) {
      errors.fecha = ['Elija el día y pulse «Ver los horarios libres» para escoger la hora.']
    }

    await showProblem(localProblem(errors))

    return
  }

  sending.value = true
  problem.value = null

  const outcome = await callApi(() =>
    api.POST('/tiendas/{tienda}/citas', {
      params: { path: { tienda: tienda.value } },
      body: {
        servicio_id: form.servicio,
        inicia_en: form.inicio ?? '',
        persona_id: form.persona || null,
        perfumes: perfumes.value.map((perfume) => perfume.id),
      },
    }),
  )

  sending.value = false

  if (outcome.problem) {
    if (outcome.problem.kind === 'conflict') {
      await slotTaken(outcome.problem)
    } else if (outcome.problem.kind === 'validation') {
      await showProblem(outcome.problem)
    } else {
      problem.value = outcome.problem
    }

    return
  }

  leaveNotice(outcome.data.data.id, 'Se reservó su cita de prueba.')
  await router.push({ name: 'appointment', params: { cita: outcome.data.data.id } })
}

/**
 * Someone else took the slot first (R14): the message of the API stays, the slots of the same day load again and the
 * focus goes to the first of them, or to the day when none is left. The service, the day and the perfumes stay.
 */
async function slotTaken(found: ApiProblem): Promise<void> {
  form.inicio = null
  await slots.load({ tienda: tienda.value, servicio: form.servicio, fecha: form.fecha })
  problem.value = found
  await nextTick()

  const firstSlot = formElement.value?.querySelector<HTMLInputElement>('input[name="inicia_en"]')
  const target = firstSlot ?? dayField.value
  target?.focus()
}

/** The perfumes of the address, with the names that the showcase gives; one that is gone is left out. */
async function perfumesOf(ids: string[]): Promise<Named[]> {
  const found = await Promise.all(
    ids.map(async (id) => {
      const outcome = await callApi(() =>
        api.GET('/tiendas/{tienda}/perfumes/{perfume}', { params: { path: { tienda: tienda.value, perfume: id } } }),
      )

      return outcome.data ? { id, nombre: outcome.data.data.nombre } : null
    }),
  )

  return found.filter((perfume): perfume is Named => perfume !== null)
}

/** Takes up the choice that the address brings back from the sign-in, or from the page of a perfume. */
async function restoreChoice(): Promise<void> {
  const query = route.query
  const text = (value: unknown): string => (typeof value === 'string' ? value : '')
  const asked = text(query.servicio)
  const onlyService = serviceList.value.length === 1 ? serviceList.value[0]?.id : undefined

  form.servicio = serviceList.value.some((item) => item.id === asked) ? asked : (onlyService ?? '')
  form.fecha = /^\d{4}-\d{2}-\d{2}$/.test(text(query.fecha)) ? text(query.fecha) : ''

  const ids = text(query.perfumes).split(',').filter(Boolean).slice(0, 20)
  perfumes.value = ids.length > 0 ? await perfumesOf(ids) : []

  if (form.servicio && form.fecha) {
    await slots.load({ tienda: tienda.value, servicio: form.servicio, fecha: form.fecha })

    const start = text(query.inicio)
    form.inicio = slots.slots.value.some((slot) => slot.inicia_en === start) ? start : null
    form.persona = people.value.some((person) => person.id === text(query.persona)) ? text(query.persona) : ''
  }
}

// Another service or another day makes the slots of the list useless: they belong to the previous choice.
watch(
  () => form.servicio,
  (current, previous) => {
    if (previous !== '' && current !== previous) {
      slots.clear()
      form.inicio = null
    }
  },
)
watch(
  () => form.fecha,
  (current) => {
    if (slots.day.value && current !== slots.day.value) {
      slots.clear()
      form.inicio = null
    }
  },
)
// A person who cannot attend the chosen slot stops being the choice. While no slot is chosen, as after a 409, the
// person stays, and it comes back with the next slot that the person attends.
watch(people, (current) => {
  if (form.persona && chosenSlot.value && !current.some((person) => person.id === form.persona)) {
    form.persona = ''
  }
})

onMounted(async () => {
  await services.reload()

  if (services.data.value) {
    await restoreChoice()
  }
})
</script>

<template>
  <div class="page booking">
    <h1>Reservar una cita de prueba</h1>
    <div class="prose">
      <p>
        En la cita prueba en la piel los perfumes que quiera, con la ayuda de una persona de la tienda. Las horas son las
        de la tienda. Puede leer antes <RouterLink to="/ayuda#ayuda-citas">cómo funcionan las citas de prueba</RouterLink>.
      </p>
    </div>

    <LoadingState v-if="services.loading.value" message="Cargando los servicios…" />
    <ErrorState v-else-if="services.problem.value" :problem="services.problem.value" @retry="services.reload" />
    <EmptyState v-else-if="services.empty.value" message="Esta tienda todavía no ofrece citas de prueba.">
      <RouterLink :to="{ name: 'storefront', params: { tienda } }" class="action-link">Ver la vitrina</RouterLink>
    </EmptyState>
    <form v-else-if="services.data.value" ref="formElement" class="form booking__form" novalidate @submit.prevent="book">
      <fieldset v-if="serviceList.length > 1" class="choice-group" v-bind="groupAttributes(problem, 'servicio_id')">
        <legend>Servicio</legend>
        <div class="choice-group__options">
          <label v-for="item in serviceList" :key="item.id" class="choice">
            <input v-model="form.servicio" type="radio" name="servicio_id" :value="item.id" />
            <span class="choice__text">
              {{ describe(item) }}
              <span class="choice__detail">Hasta {{ item.maximo_perfumes }} perfumes</span>
            </span>
          </label>
        </div>
        <FieldError :problem="problem" field="servicio_id" />
      </fieldset>
      <p v-else-if="service" class="booking__service">Servicio: {{ describe(service) }}.</p>

      <div class="form-field">
        <label for="fecha">Día de la cita</label>
        <p id="pista-fecha" class="field-hint">Elija el día y pulse «Ver los horarios libres».</p>
        <input
          id="fecha"
          ref="dayField"
          v-model="form.fecha"
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
      <p class="results-summary" role="status">{{ slots.loading.value ? 'Buscando horarios libres…' : slots.summary.value }}</p>
      <ProblemNotice v-if="slots.problem.value" :problem="slots.problem.value" />
      <ProblemNotice v-if="problem?.kind === 'conflict'" :problem="problem" />

      <SlotList
        v-if="slots.slots.value.length > 0"
        v-model:start="form.inicio"
        :slots="slots.slots.value"
        :day="slots.day.value"
        :problem="problem"
      />

      <div v-if="people.length > 1" class="form-field">
        <label for="persona">Persona que le atiende</label>
        <p id="pista-persona" class="field-hint">Si no elige, le atiende quien tenga menos citas ese día.</p>
        <select id="persona" v-model="form.persona" name="persona_id" v-bind="fieldAttributes(problem, 'persona_id', 'pista-persona')">
          <option value="">Cualquiera de las personas disponibles</option>
          <option v-for="person in people" :key="person.id" :value="person.id">{{ person.nombre }}</option>
        </select>
        <FieldError :problem="problem" field="persona_id" />
      </div>

      <PerfumePicker
        v-if="service && service.maximo_perfumes > 0"
        v-model:chosen="perfumes"
        :tienda="tienda"
        :max="service.maximo_perfumes"
        :problem="problem"
      />

      <ProblemNotice v-if="problem && problem.kind !== 'validation' && problem.kind !== 'conflict'" :problem="problem" />
      <div class="form-actions">
        <!-- aria-disabled and not disabled: the button keeps the focus while the appointment is booked. -->
        <button v-if="isCustomer" type="submit" :aria-disabled="sending ? 'true' : undefined">
          {{ sending ? 'Reservando la cita…' : 'Reservar la cita' }}
        </button>
        <RouterLink
          v-else-if="!session.isSignedIn"
          :to="{ name: 'sign-in', query: { redirect: choicePath } }"
          class="button"
        >
          Inicie sesión para reservar
        </RouterLink>
        <p v-else class="booking__staff">Las citas de prueba se reservan con una cuenta de cliente.</p>
      </div>
    </form>
  </div>
</template>
