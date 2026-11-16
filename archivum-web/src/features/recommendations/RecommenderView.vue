<script setup lang="ts">
import { computed, nextTick, ref, shallowRef, useTemplateRef } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import { api } from '@/api/client'
import { callApi, fieldAttributes, focusFirstInvalidField, type ApiProblem } from '@/api/problem'
import type { components } from '@/api/schema'
import EmptyState from '@/shared/EmptyState.vue'
import FieldError from '@/shared/FieldError.vue'
import LoadingState from '@/shared/LoadingState.vue'
import ProblemNotice from '@/shared/ProblemNotice.vue'
import { formatColones } from '@/shared/money'

type Recommendation = components['schemas']['RecommendationResource']

/**
 * The recommender of a store (#128): the visitor describes what they like and the API answers perfumes of the
 * showcase with a reason, from the AI or from the rules when the AI fails (#120). The reason is plain text.
 */
const route = useRoute()
const tienda = computed(() => String(route.params.tienda))

const form = useTemplateRef<HTMLFormElement>('form')
const texto = ref('')
const sending = ref(false)
const problem = ref<ApiProblem | null>(null)
const recommendation = shallowRef<Recommendation | null>(null)

const originLabel = computed(() =>
  recommendation.value?.origen === 'ia' ? 'Recomendado con IA' : 'Recomendado por reglas',
)

async function recommend(): Promise<void> {
  // The answer can take up to 10 seconds: a second click must not send the text again.
  if (sending.value || !texto.value.trim()) {
    return
  }

  sending.value = true
  problem.value = null

  const outcome = await callApi(() =>
    api.POST('/tiendas/{tienda}/recomendaciones', {
      params: { path: { tienda: tienda.value } },
      body: { texto: texto.value },
    }),
  )

  sending.value = false
  recommendation.value = outcome.data?.data ?? null
  problem.value = outcome.problem

  if (outcome.problem) {
    await nextTick()

    if (form.value) {
      focusFirstInvalidField(form.value, outcome.problem)
    }
  }
}
</script>

<template>
  <main class="recommender">
    <h2>Pedir una recomendación</h2>

    <form ref="form" @submit.prevent="recommend">
      <label for="texto-recomendacion">Describa lo que le gusta</label>
      <textarea
        id="texto-recomendacion"
        v-model="texto"
        name="texto"
        rows="4"
        placeholder="Por ejemplo: algo fresco y cítrico para el día, que no cueste más de ₡60 000."
        aria-describedby="aviso-ia"
        v-bind="fieldAttributes(problem, 'texto')"
      ></textarea>
      <p id="aviso-ia" class="recommender__notice">
        Lo que escriba se envía a un proveedor externo de inteligencia artificial. No incluya datos personales como su
        nombre, correo o teléfono.
      </p>
      <FieldError :problem="problem" field="texto" />

      <button type="submit" :disabled="sending || !texto.trim()">
        {{ sending ? 'Buscando…' : 'Recomendar' }}
      </button>
    </form>

    <LoadingState v-if="sending" message="Buscando perfumes para usted. Puede tardar unos segundos…" />
    <ProblemNotice v-else-if="problem" :problem="problem" />
    <template v-else-if="recommendation">
      <EmptyState
        v-if="recommendation.perfumes.length === 0"
        message="No encontramos perfumes para esa descripción. Pruebe con otras palabras."
      />
      <section v-else aria-labelledby="results-title">
        <h3 id="results-title">Le recomendamos</h3>
        <p class="recommender__origin">{{ originLabel }}</p>
        <ul class="recommendations">
          <li v-for="perfume in recommendation.perfumes" :key="perfume.id">
            <h4>
              <RouterLink :to="{ name: 'perfume', params: { tienda, perfume: perfume.id } }">
                {{ perfume.marca }} {{ perfume.nombre }}
              </RouterLink>
            </h4>
            <p class="recommendation__reason">{{ perfume.razon }}</p>
            <ul>
              <li v-for="variant in perfume.presentaciones" :key="variant.ml">
                {{ variant.ml }} ml · {{ formatColones(variant.precio_venta) }}
              </li>
            </ul>
          </li>
        </ul>
      </section>
    </template>
  </main>
</template>
