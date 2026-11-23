<script setup lang="ts">
import { computed, nextTick, ref, shallowRef, useTemplateRef } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import { api } from '@/api/client'
import { callApi, fieldAttributes, focusFirstInvalidField, type ApiProblem } from '@/api/problem'
import type { components } from '@/api/schema'
import FieldError from '@/shared/FieldError.vue'
import MlAmount from '@/shared/MlAmount.vue'
import ProblemNotice from '@/shared/ProblemNotice.vue'
import { formatColones } from '@/shared/money'

type Recommendation = components['schemas']['RecommendationResource']

/**
 * The recommender of a store (#128): the visitor describes what they like and the API answers perfumes of the
 * showcase with a reason, from the AI or from the rules when the AI fails (#120). The reason is plain text.
 * A line that screen readers announce says when the search starts and how it ended, because the answer can take
 * some seconds and appears below the form.
 */
const route = useRoute()
const tienda = computed(() => String(route.params.tienda))

const EMPTY_TEXT = 'Escriba qué le gusta para recomendarle perfumes.'

const form = useTemplateRef<HTMLFormElement>('form')
const texto = ref('')
const sending = ref(false)
const problem = ref<ApiProblem | null>(null)
const recommendation = shallowRef<Recommendation | null>(null)

const originLabel = computed(() =>
  recommendation.value?.origen === 'ia'
    ? 'Recomendado con inteligencia artificial.'
    : 'Recomendado con las reglas de la tienda, sin inteligencia artificial.',
)

const status = computed(() => {
  if (sending.value) {
    return 'Buscando perfumes para ti. Puede tardar unos segundos…'
  }

  if (!recommendation.value) {
    return ''
  }

  const count = recommendation.value.perfumes.length

  if (count === 0) {
    return 'No encontramos perfumes para esa descripción. Prueba con otras palabras.'
  }

  return count === 1 ? 'Te recomendamos 1 perfume.' : `Te recomendamos ${count} perfumes.`
})

async function recommend(): Promise<void> {
  // The answer can take up to 10 seconds: a second click must not send the text again.
  if (sending.value) {
    return
  }

  if (!texto.value.trim()) {
    await showProblem(localProblem(EMPTY_TEXT))

    return
  }

  sending.value = true
  problem.value = null
  recommendation.value = null

  const outcome = await callApi(() =>
    api.POST('/tiendas/{tienda}/recomendaciones', {
      params: { path: { tienda: tienda.value } },
      body: { texto: texto.value },
    }),
  )

  sending.value = false
  recommendation.value = outcome.data?.data ?? null

  if (outcome.problem) {
    await showProblem(outcome.problem)
  }
}

async function showProblem(found: ApiProblem): Promise<void> {
  problem.value = found
  await nextTick()

  if (form.value) {
    focusFirstInvalidField(form.value, found)
  }
}

/** A problem of the form that the page finds before asking the API, shown like a 422. */
function localProblem(message: string): ApiProblem {
  return {
    kind: 'validation',
    status: null,
    type: null,
    message,
    fieldErrors: { texto: [message] },
    requestId: null,
    retryAfterSeconds: null,
  }
}
</script>

<template>
  <div class="page recommender">
    <h1>Pedir una recomendación</h1>
    <p class="form-intro">
      Cuéntanos con tus palabras el perfume que buscas. Te sugerimos hasta cinco perfumes de esta tienda y te decimos por
      qué.
    </p>

    <form ref="form" class="form" novalidate @submit.prevent="recommend">
      <div class="form-field">
        <label for="texto-recomendacion">Cuéntanos qué te gusta</label>
        <p id="pista-recomendacion" class="field-hint">
          Puedes hablar de notas, momento del día, clima, ocasión y cuánto quieres gastar, en hasta 1000 caracteres. Por
          ejemplo: algo fresco y cítrico para el día, que no cueste más de ₡60 000.
        </p>
        <textarea
          id="texto-recomendacion"
          v-model="texto"
          name="texto"
          rows="4"
          v-bind="fieldAttributes(problem, 'texto', 'pista-recomendacion aviso-ia')"
        ></textarea>
        <FieldError :problem="problem" field="texto" />
        <p id="aviso-ia" class="field-hint recommender__notice">
          Lo que escribas se envía a un proveedor externo de inteligencia artificial. No incluyas datos personales como tu
          nombre, correo o teléfono.
        </p>
      </div>

      <div class="form-actions">
        <!-- aria-disabled and not disabled: the button keeps the focus while the request is on its way. -->
        <button type="submit" :aria-disabled="sending ? 'true' : undefined">
          {{ sending ? 'Buscando…' : 'Recomendar' }}
        </button>
      </div>
    </form>

    <!-- Always in the page, so screen readers announce the wait and the answer. -->
    <p :class="sending ? 'loading-state' : 'results-summary'" role="status">{{ status }}</p>

    <!-- A field with a problem already says it next to the field. -->
    <ProblemNotice v-if="problem && problem.kind !== 'validation'" :problem="problem" />
    <section
      v-else-if="recommendation && recommendation.perfumes.length > 0"
      class="page-section"
      aria-labelledby="results-title"
    >
      <h2 id="results-title">Le recomendamos</h2>
      <p class="recommender__origin">{{ originLabel }}</p>
      <ul class="card-list recommendations">
        <li v-for="perfume in recommendation.perfumes" :key="perfume.id" class="card">
          <h3>
            <RouterLink :to="{ name: 'perfume', params: { tienda, perfume: perfume.id } }" class="action-link">
              {{ perfume.marca }} {{ perfume.nombre }}
            </RouterLink>
          </h3>
          <p class="recommendation__reason">{{ perfume.razon }}</p>
          <ul class="perfume-card__variants">
            <li v-for="variant in perfume.presentaciones" :key="variant.ml">
              Botella de <MlAmount :amount="variant.ml" /> · {{ formatColones(variant.precio_venta) }}
            </li>
          </ul>
        </li>
      </ul>
    </section>
  </div>
</template>
