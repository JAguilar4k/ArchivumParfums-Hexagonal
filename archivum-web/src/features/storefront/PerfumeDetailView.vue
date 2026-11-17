<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import { api } from '@/api/client'
import { callApi } from '@/api/problem'
import type { components } from '@/api/schema'
import { useCartStore } from '@/features/cart/store'
import ErrorState from '@/shared/ErrorState.vue'
import LoadingState from '@/shared/LoadingState.vue'
import MlAmount from '@/shared/MlAmount.vue'
import { formatColones } from '@/shared/money'
import { usePageTitle } from '@/shared/pageChanges'
import { useApiData } from '@/shared/useApiData'
import { concentrationLabel, tagLabel } from './labels'

/**
 * The detail of a perfume of the showcase (#128). A perfume that is inactive or belongs to another store answers 404,
 * so it shows "No encontrado". The tab and the heading take the name of the perfume, and a trail of links says where
 * the page is in the store. Each presentation and each decant goes to the cart of the store (#130), and a line that
 * screen readers announce says what was added.
 *
 * The API offers the decants of each open bottle. Two bottles of the same presentation offer the same decant at the
 * same price, so the page shows it once, from the first bottle that the API lists. Bottles of different presentations
 * offer the same size at different prices (R3), so then each decant names the presentation of its bottle, in its text
 * and in the name of its button.
 */
const route = useRoute()
const tienda = computed(() => String(route.params.tienda))
const perfumeId = computed(() => String(route.params.perfume))

const detail = useApiData(() =>
  callApi(() =>
    api.GET('/tiendas/{tienda}/perfumes/{perfume}', {
      params: { path: { tienda: tienda.value, perfume: perfumeId.value } },
    }),
  ),
)

const perfume = computed(() => detail.data.value?.data ?? null)
const name = computed(() => (perfume.value ? `${perfume.value.marca} ${perfume.value.nombre}` : null))

const layers = computed(() =>
  perfume.value
    ? [
        { name: 'Salida', notes: perfume.value.notas.salida },
        { name: 'Corazón', notes: perfume.value.notas.corazon },
        { name: 'Fondo', notes: perfume.value.notas.fondo },
      ].filter((layer) => layer.notes.length > 0)
    : [],
)

usePageTitle(() => name.value)

type Decant = components['schemas']['ShowcaseDecantResource']

/** One decant per size and presentation of its bottle, from the smallest. */
const decants = computed(() => {
  const offers = new Map<string, Decant>()

  for (const decant of perfume.value?.decants ?? []) {
    const key = `${decant.presentacion_ml}:${decant.ml}`

    if (!offers.has(key)) {
      offers.set(key, decant)
    }
  }

  return [...offers.values()].sort((a, b) => a.ml - b.ml || a.presentacion_ml - b.presentacion_ml)
})

/** Whether the decants come from bottles of more than one presentation, so each one must name it. */
const fromSeveralPresentations = computed(() => new Set(decants.value.map((decant) => decant.presentacion_ml)).size > 1)

/** A decant as a screen reader says it, with "mililitros" in words and its bottle when it tells two offers apart. */
function spokenDecant(decant: Decant): string {
  const bottle = fromSeveralPresentations.value ? ` de la botella de ${decant.presentacion_ml} mililitros` : ''

  return `el decant de ${decant.ml} mililitros${bottle}`
}

const cart = useCartStore()
const added = ref<{ what: string; ml: number; bottleMl: number | null } | null>(null)

function addBottle(variant: components['schemas']['ShowcaseVariantResource']): void {
  if (!perfume.value || !name.value) {
    return
  }

  cart.addBottle(tienda.value, {
    perfumeId: perfume.value.id,
    perfume: name.value,
    presentationId: variant.id,
    ml: variant.ml,
    price: variant.precio_venta,
  })
  added.value = { what: 'la botella', ml: variant.ml, bottleMl: null }
}

function addDecant(decant: Decant): void {
  if (!perfume.value || !name.value) {
    return
  }

  cart.addDecant(tienda.value, {
    perfumeId: perfume.value.id,
    perfume: name.value,
    bottleId: decant.botella_id,
    ml: decant.ml,
    price: decant.precio,
  })
  added.value = { what: 'el decant', ml: decant.ml, bottleMl: fromSeveralPresentations.value ? decant.presentacion_ml : null }
}

watch(
  [tienda, perfumeId],
  () => {
    added.value = null
    void detail.reload()
  },
  { immediate: true },
)
</script>

<template>
  <div class="page perfume-detail">
    <LoadingState v-if="detail.loading.value" message="Cargando el perfume…" />
    <ErrorState v-else-if="detail.problem.value" :problem="detail.problem.value" @retry="detail.reload" />
    <article v-else-if="perfume" class="page" aria-labelledby="perfume-titulo">
      <nav class="breadcrumbs" aria-label="Ubicación">
        <ol>
          <li><RouterLink :to="{ name: 'storefront', params: { tienda } }">Vitrina</RouterLink></li>
          <li><span aria-current="page">{{ name }}</span></li>
        </ol>
      </nav>

      <h1 id="perfume-titulo">{{ name }}</h1>
      <img v-if="perfume.foto_url" :src="perfume.foto_url" :alt="`Botella de ${name}`" class="perfume-detail__image" />

      <dl class="facts">
        <div v-if="perfume.concentracion">
          <dt>Concentración</dt>
          <dd>{{ concentrationLabel(perfume.concentracion) }}</dd>
        </div>
        <div v-if="perfume.genero">
          <dt>Género</dt>
          <dd>{{ perfume.genero }}</dd>
        </div>
        <div v-if="perfume.familia">
          <dt>Familia olfativa</dt>
          <dd>{{ perfume.familia }}</dd>
        </div>
      </dl>

      <div v-if="perfume.descripcion" class="prose">
        <p>{{ perfume.descripcion }}</p>
      </div>

      <section v-if="layers.length > 0" class="page-section" aria-labelledby="notes-title">
        <h2 id="notes-title">Notas</h2>
        <p>
          Las notas de salida se sienten al aplicarlo, las de corazón después de unos minutos y las de fondo son las que
          duran al final. Vea también <RouterLink to="/ayuda#notas">qué son las notas de un perfume</RouterLink>.
        </p>
        <dl class="facts">
          <div v-for="layer in layers" :key="layer.name">
            <dt>{{ layer.name }}</dt>
            <dd>{{ layer.notes.join(', ') }}</dd>
          </div>
        </dl>
      </section>

      <section class="page-section" aria-labelledby="tags-title">
        <h2 id="tags-title">Cuándo usarlo</h2>
        <dl class="facts">
          <div>
            <dt>Momento del día</dt>
            <dd>{{ perfume.etiquetas.momento.map(tagLabel).join(', ') || 'Cualquiera' }}</dd>
          </div>
          <div>
            <dt>Clima</dt>
            <dd>{{ perfume.etiquetas.clima.map(tagLabel).join(', ') || 'Cualquiera' }}</dd>
          </div>
          <div>
            <dt>Ocasión</dt>
            <dd>{{ perfume.etiquetas.ocasion.join(', ') || 'Cualquiera' }}</dd>
          </div>
        </dl>
      </section>

      <section class="page-section" aria-labelledby="variants-title">
        <h2 id="variants-title">Presentaciones</h2>
        <ul v-if="perfume.presentaciones.length > 0" class="variants">
          <li v-for="variant in perfume.presentaciones" :key="variant.id">
            <span>Botella de <MlAmount :amount="variant.ml" /> · {{ formatColones(variant.precio_venta) }}</span>
            <!-- The visible text starts the name, which says which presentation it adds (WCAG 2.5.3). -->
            <button
              type="button"
              :aria-label="`Agregar al carrito la botella de ${variant.ml} mililitros`"
              @click="addBottle(variant)"
            >
              Agregar al carrito
            </button>
          </li>
        </ul>
        <p v-else>Por ahora no hay botellas a la venta.</p>
      </section>

      <section class="page-section" aria-labelledby="decants-title">
        <h2 id="decants-title">Decants disponibles</h2>
        <p>
          Un decant es una cantidad pequeña del perfume, en un frasco aparte, para probarlo antes de comprar la botella.
        </p>
        <ul v-if="decants.length > 0" class="decants">
          <li v-for="decant in decants" :key="`${decant.presentacion_ml}-${decant.ml}`">
            <span>
              Decant de <MlAmount :amount="decant.ml" /><template v-if="fromSeveralPresentations">
                de la botella de <MlAmount :amount="decant.presentacion_ml"
              /></template>
              · {{ formatColones(decant.precio) }}
            </span>
            <!-- The visible text starts the name, which says which decant it adds (WCAG 2.5.3). -->
            <button type="button" :aria-label="`Agregar al carrito ${spokenDecant(decant)}`" @click="addDecant(decant)">
              Agregar al carrito
            </button>
          </li>
        </ul>
        <p v-else>Por ahora no hay decants disponibles.</p>
      </section>

      <!-- Always in the page, so screen readers announce each product added. -->
      <p class="results-summary perfume-detail__added" role="status">
        <template v-if="added">
          Se agregó al carrito {{ added.what }} de <MlAmount :amount="added.ml" /><template v-if="added.bottleMl">
            de la botella de <MlAmount :amount="added.bottleMl"
          /></template>.
          <RouterLink :to="{ name: 'cart', params: { tienda } }">Ver el carrito</RouterLink>
        </template>
      </p>
    </article>
  </div>
</template>
