<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import type { components } from '@/api/schema'
import { openCartDrawer } from '@/features/cart/drawer'
import { useCartStore } from '@/features/cart/store'
import { storeWhatsApp } from '@/shared/config'
import ErrorState from '@/shared/ErrorState.vue'
import LoadingState from '@/shared/LoadingState.vue'
import MlAmount from '@/shared/MlAmount.vue'
import { formatColones } from '@/shared/money'
import { usePageTitle } from '@/shared/pageChanges'
import { useApiData } from '@/shared/useApiData'
import CatalogIcon from './CatalogIcon.vue'
import { showcaseQuery } from './filters'
import { concentrationLabel, tagLabel } from './labels'
import PerfumeArt from './PerfumeArt.vue'
import PerfumeCard from './PerfumeCard.vue'
import { cachedPerfume, cachedShowcase, loadPerfume, loadShowcase, prefetchShowcase } from './publicStore'

type Bottle = components['schemas']['ShowcaseVariantResource']
type Decant = components['schemas']['ShowcaseDecantResource']

const route = useRoute()
const slug = computed(() => String(route.params.tienda))
const perfumeId = computed(() => String(route.params.perfume))
const cart = useCartStore()

const detail = useApiData(
  () => loadPerfume(slug.value, perfumeId.value),
  undefined,
  { cached: () => cachedPerfume(slug.value, perfumeId.value) },
)
const perfume = computed(() => detail.data.value?.data ?? null)
const fullName = computed(() => perfume.value ? `${perfume.value.marca} ${perfume.value.nombre}` : '')
usePageTitle(() => perfume.value?.nombre)

const layers = computed(() => perfume.value ? [
  { name: 'Salida', detail: 'Al aplicarlo', notes: perfume.value.notas.salida },
  { name: 'Corazón', detail: 'A los pocos minutos', notes: perfume.value.notas.corazon },
  { name: 'Fondo', detail: 'Lo que dura al final', notes: perfume.value.notas.fondo },
] : [])

const attributes = computed(() => perfume.value ? [
  concentrationLabel(perfume.value.concentracion), perfume.value.familia, perfume.value.genero,
].filter(Boolean).join(' · ') : '')

const bottles = computed(() => (perfume.value?.presentaciones ?? []).slice().sort((a, b) => a.ml - b.ml))

// The API can list the same decant for more than one opened bottle of a presentation.
const decants = computed(() => {
  const unique = new Map<string, Decant>()
  for (const decant of perfume.value?.decants ?? []) {
    const key = decantKey(decant)
    if (!unique.has(key)) unique.set(key, decant)
  }
  return [...unique.values()].sort((a, b) => a.ml - b.ml || a.presentacion_ml - b.presentacion_ml)
})
const sourceSizes = computed(() => new Set(decants.value.map((item) => item.presentacion_ml)).size)

const offers = computed(() => [
  ...bottles.value.map((data) => ({
    kind: 'bottle' as const,
    key: bottleKey(data),
    data,
    price: data.precio_venta,
    label: `la botella de ${data.ml} mililitros`,
    message: `botella de ${data.ml} mililitros`,
  })),
  ...decants.value.map((data) => {
    const bottle = sourceSizes.value > 1 ? ` de la botella de ${data.presentacion_ml} mililitros` : ''
    return {
      kind: 'decant' as const,
      key: decantKey(data),
      data,
      price: data.precio,
      label: `el decant de ${data.ml} mililitros${bottle}`,
      message: `decant de ${data.ml} mililitros${bottle}`,
    }
  }),
])
const choice = ref('')
const selected = computed(() => offers.value.find((item) => item.key === choice.value) ?? offers.value[0])
const quantity = ref(1)

const family = computed(() => perfume.value?.familia)
const relatedQuery = computed(() => showcaseQuery({ familia: family.value ?? null }))
const related = useApiData(
  () => loadShowcase(slug.value, relatedQuery.value),
  undefined,
  { cached: () => cachedShowcase(slug.value, relatedQuery.value) },
)
const relatedPerfumes = computed(() => family.value
  ? (related.data.value?.data ?? []).filter((item) => item.id !== perfumeId.value).slice(0, 4)
  : [],
)
const whatsapp = storeWhatsApp()
const compact = typeof window.matchMedia === 'function' ? window.matchMedia('(max-width: 43rem)') : null
const notesOpen = ref(!compact?.matches)
function updateNotes(event: MediaQueryListEvent): void {
  notesOpen.value = !event.matches
}

onMounted(() => compact?.addEventListener('change', updateNotes))
onBeforeUnmount(() => compact?.removeEventListener('change', updateNotes))

watch([slug, perfumeId], () => {
  if (route.name !== 'perfume') return
  choice.value = ''
  quantity.value = 1
  void detail.reload()
}, { immediate: true })

watch([slug, family], () => {
  if (route.name === 'perfume' && family.value) void related.reload()
}, { immediate: true })

function bottleKey(offer: Bottle): string {
  return `bottle:${offer.id}`
}

function decantKey(offer: Decant): string {
  return `decant:${offer.presentacion_ml}:${offer.ml}`
}

function select(key: string): void {
  choice.value = key
  quantity.value = 1
}

function decrease(): void {
  if (quantity.value > 1) quantity.value -= 1
}

function add(event: MouseEvent): void {
  const product = perfume.value
  const offer = selected.value
  if (!product || !offer) return

  const line = {
    perfumeId: product.id,
    perfume: fullName.value,
    ml: offer.data.ml,
    price: offer.price,
    family: product.familia,
    photo: product.foto_variantes?.[0]?.url ?? product.foto_url,
  }
  if (offer.kind === 'bottle') {
    cart.addBottle(slug.value, { ...line, presentationId: offer.data.id }, quantity.value)
  } else {
    cart.addDecant(slug.value, { ...line, bottleId: offer.data.botella_id })
  }
  openCartDrawer({
    slug: slug.value,
    message: `Se agregó ${product.nombre}, ${offer.message}.`,
    trigger: event.currentTarget as HTMLElement,
  })
}

function preloadShowcase(): void {
  void prefetchShowcase(slug.value, showcaseQuery({}))
}
</script>

<template>
  <div class="perfume-detail">
    <LoadingState v-if="detail.loading.value && !perfume" message="Cargando el perfume…" />
    <ErrorState v-else-if="detail.problem.value && !perfume" :problem="detail.problem.value" @retry="detail.reload" />
    <article v-else-if="perfume" aria-labelledby="perfume-titulo">
      <nav class="breadcrumbs detail-trail" aria-label="Ubicación">
        <ol>
          <li><RouterLink :to="{ name: 'store-home', params: { tienda: slug } }">Inicio</RouterLink></li>
          <li>
            <RouterLink :to="{ name: 'storefront', params: { tienda: slug } }" @pointerenter="preloadShowcase" @focusin="preloadShowcase">
              Vitrina
            </RouterLink>
          </li>
          <li><span aria-current="page">{{ fullName }}</span></li>
        </ol>
      </nav>

      <div class="detail-columns">
        <figure class="detail-figure">
          <PerfumeArt
            :id="perfume.id"
            :family="perfume.familia"
            :photo="perfume.foto_url"
            :variants="perfume.foto_variantes"
            sizes="auto, (max-width: 43rem) 100vw, (max-width: 80rem) 50vw, 38rem"
            :alt="`Botella de ${fullName}`"
          >
            <span v-if="perfume.etiquetas.momento[0]" class="perfume-badge">{{ tagLabel(perfume.etiquetas.momento[0]) }}</span>
          </PerfumeArt>
        </figure>

        <div class="detail-info">
          <div class="detail-heading">
            <p class="perfume-brand">{{ perfume.marca }}</p>
            <h1 id="perfume-titulo" tabindex="-1">{{ perfume.nombre }}</h1>
            <p v-if="attributes" class="detail-meta">{{ attributes }}</p>
          </div>

          <p v-if="selected" class="detail-price">
            <strong>{{ formatColones(selected.price) }}</strong>
            <span v-if="selected.kind === 'bottle'">Botella de <MlAmount :amount="selected.data.ml" /></span>
            <span v-else>
              Decant de <MlAmount :amount="selected.data.ml" /><template v-if="sourceSizes > 1">
                de la botella de <MlAmount :amount="selected.data.presentacion_ml" />
              </template>
            </span>
          </p>

          <fieldset v-if="offers.length" class="choice-group detail-offers">
            <legend>Presentación</legend>
            <ul v-if="bottles.length" class="catalog-list detail-offers__row">
              <li v-for="variant in bottles" :key="variant.id">
                <label class="detail-offer">
                  <input
                    type="radio"
                    name="presentacion"
                    :value="bottleKey(variant)"
                    :checked="selected?.key === bottleKey(variant)"
                    @change="select(bottleKey(variant))"
                  />
                  <span class="detail-offer__label">
                    <strong>Botella de <MlAmount :amount="variant.ml" /></strong>
                    <span>{{ formatColones(variant.precio_venta) }}</span>
                  </span>
                </label>
              </li>
            </ul>
            <ul v-if="decants.length" class="catalog-list detail-offers__row detail-offers__row--decants decants">
              <li v-for="decant in decants" :key="decantKey(decant)">
                <label class="detail-offer">
                  <input
                    type="radio"
                    name="presentacion"
                    :value="decantKey(decant)"
                    :checked="selected?.key === decantKey(decant)"
                    @change="select(decantKey(decant))"
                  />
                  <span class="detail-offer__label">
                    <strong>
                      Decant de <MlAmount :amount="decant.ml" /><template v-if="sourceSizes > 1">
                        de la botella de <MlAmount :amount="decant.presentacion_ml" />
                      </template>
                    </strong>
                    <span>{{ formatColones(decant.precio) }}</span>
                  </span>
                </label>
              </li>
            </ul>
          </fieldset>
          <p v-else>No hay presentaciones disponibles por el momento.</p>

          <div v-if="selected" class="detail-buy">
            <div v-if="selected.kind === 'bottle'" class="detail-quantity" role="group" aria-label="Cantidad de botellas">
              <button type="button" :aria-disabled="quantity <= 1" aria-label="Reducir la cantidad" @click="decrease">−</button>
              <output aria-label="Cantidad">{{ quantity }}</output>
              <button type="button" aria-label="Aumentar la cantidad" @click="quantity += 1">+</button>
            </div>
            <button type="button" class="detail-buy__add" data-add-perfume :aria-label="`Agregar al carrito ${selected.label}`" @click="add">
              Agregar al carrito
            </button>
          </div>

          <RouterLink
            :to="{ name: 'book-appointment', params: { tienda: slug }, query: { perfumes: perfume.id } }"
            class="button button--secondary detail-appointment"
          >
            Reservar una cita para olerlo en la tienda
          </RouterLink>

          <ul class="catalog-list detail-benefits" aria-label="Opciones para probar y comprar">
            <li><CatalogIcon name="decant" /> <span>Pruébelo en un decant</span></li>
            <li><CatalogIcon name="payment" /> <span>Pago en línea con ONVO o PayPal</span></li>
            <li v-if="whatsapp">
              <CatalogIcon name="message" />
              <a :href="'https://wa.me/' + whatsapp" class="action-link" rel="noopener">Dudas por WhatsApp</a>
            </li>
            <li v-else><CatalogIcon name="calendar" /> <span>Cita de prueba en la tienda</span></li>
          </ul>

          <p v-if="perfume.descripcion" class="detail-description">{{ perfume.descripcion }}</p>

          <div class="detail-disclosures">
            <details :key="perfume.id" :open="notesOpen">
              <summary>Notas</summary>
              <dl class="detail-notes">
                <div v-for="layer in layers" :key="layer.name">
                  <dt>{{ layer.name }}</dt>
                  <dd>
                    <span>{{ layer.detail }}</span>
                    <span>{{ layer.notes.join(', ') || 'Sin notas publicadas' }}</span>
                  </dd>
                </div>
              </dl>
              <RouterLink to="/ayuda#notas" class="action-link">Qué son las notas de un perfume</RouterLink>
            </details>
            <details>
              <summary>Cuándo usarlo</summary>
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
            </details>
            <details>
              <summary>Qué es un decant</summary>
              <p>Es una cantidad pequeña del perfume, en un frasco aparte, para probarlo antes de comprar la botella.</p>
              <RouterLink to="/ayuda#decant" class="action-link">Más sobre los decants</RouterLink>
            </details>
          </div>
        </div>
      </div>

      <section v-if="relatedPerfumes.length" class="detail-related" aria-labelledby="related-title">
        <div class="catalog-heading-row">
          <h2 id="related-title">Complete su selección</h2>
          <RouterLink
            class="action-link"
            :to="{ name: 'storefront', params: { tienda: slug }, query: { familia: family } }"
            @pointerenter="prefetchShowcase(slug, relatedQuery)"
            @focusin="prefetchShowcase(slug, relatedQuery)"
          >Ver más de esta familia</RouterLink>
        </div>
        <ul class="catalog-grid">
          <PerfumeCard v-for="item in relatedPerfumes" :key="item.id" :perfume="item" :slug="slug" />
        </ul>
      </section>
    </article>
  </div>
</template>

<style scoped>
.detail-trail { margin-block-end: .625rem; }

.detail-columns {
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
  align-items: start;
  gap: clamp(1.25rem, 3.75vw, 3rem);
}

.detail-figure {
  --bottle-width: 17rem;
  position: sticky;
  inset-block-start: var(--sticky-header-offset, 9rem);
  min-width: 0;
  margin: 0;
}

.detail-figure .perfume-art { aspect-ratio: 21 / 22; }

.detail-info {
  display: grid;
  min-width: 0;
  gap: 1.25rem;
  padding-block-start: .75rem;
  overflow-wrap: anywhere;
}

.detail-heading { display: grid; gap: .25rem; }
.detail-heading .perfume-brand { margin-block-end: .5rem; font-size: .8125rem; }
.detail-heading h1 { font-size: clamp(2rem, 4.25vw, 3.375rem); line-height: 1.15; }
.detail-meta { color: var(--color-text-muted); }

.detail-price {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
  gap: .125rem .75rem;
}

.detail-price strong { font-size: clamp(1.5rem, 3vw, 2.125rem); }
.detail-price span { color: var(--color-text-muted); }

.detail-offers {
  min-inline-size: 0;
  display: grid;
  gap: .625rem;
  padding: 0;
  border: 0;
}

.detail-offers legend {
  margin-block-end: .5rem;
  font-size: .875rem;
  letter-spacing: .12em;
  text-transform: uppercase;
}

.detail-offers__row {
  --columns: 2;
  display: grid;
  grid-template-columns: repeat(var(--columns), minmax(0, 1fr));
  grid-auto-rows: 1fr;
  gap: .625rem;
}

.detail-offers__row--decants { --columns: 3; }

.detail-offer {
  position: relative;
  display: block;
  height: 100%;
  cursor: pointer;
}

.detail-offer input {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  margin: 0;
  opacity: 0;
  cursor: inherit;
}

.detail-offer__label {
  display: grid;
  align-content: center;
  gap: .125rem;
  min-height: 5rem;
  height: 100%;
  padding: .625rem clamp(.5rem, 1.25vw, 1rem);
  border: 2px solid var(--color-option-border);
  border-radius: .75rem;
  background: var(--color-surface);
}

.detail-offer__label span { color: var(--color-text-muted); font-size: .9375rem; }
.detail-offer input:checked + .detail-offer__label { border-color: var(--color-accent); background: var(--color-selected); }

.detail-offer input:focus-visible + .detail-offer__label {
  outline: 3px solid var(--color-focus);
  outline-offset: 2px;
}

.detail-buy { display: flex; flex-wrap: wrap; gap: .5rem .75rem; }
.detail-buy__add { flex: 1; min-height: 3.25rem; letter-spacing: .06em; text-transform: uppercase; text-align: center; }

.detail-quantity {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  background: var(--color-surface);
  font-weight: 700;
}

.detail-quantity button {
  padding-inline: .375rem;
  border-color: transparent;
  background: transparent;
  color: var(--color-text);
}

.detail-quantity output { min-width: 2ch; padding-inline: .125rem; text-align: center; }
.detail-appointment { min-height: 3.5rem; border-color: var(--color-text); color: var(--color-text); text-align: center; }

.detail-benefits {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: .5rem;
  padding-block: 1rem;
  border-block: 1px solid var(--color-divider);
}

.detail-benefits li { display: grid; justify-items: center; align-content: start; gap: .375rem; text-align: center; font-size: .875rem; }
.detail-benefits svg { width: 1.5rem; height: 1.5rem; color: var(--color-accent); }

.detail-description { color: var(--color-text-muted); }

.detail-disclosures details { border-block-end: 1px solid var(--color-divider); }
.detail-disclosures summary {
  display: list-item;
  min-height: var(--target-size);
  padding-block: .75rem;
  font-weight: 700;
  font-size: .875rem;
  letter-spacing: .08em;
  text-transform: uppercase;
  cursor: pointer;
}

.detail-disclosures details > p { margin-block-end: .5rem; }

.detail-notes {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: .75rem;
  margin-block-end: .5rem;
}

.detail-notes > div {
  padding: .875rem;
  border: 1px solid var(--color-divider);
  border-radius: .75rem;
  background: var(--color-surface);
}

.detail-notes dt { font-weight: 700; }
.detail-notes dd { margin: 0; font-size: .9375rem; }
.detail-notes dd span { display: block; }
.detail-notes dd span:first-child { color: var(--color-text-muted); font-size: .8125rem; }
.detail-related { margin-block: clamp(2rem, 5vw, 4.25rem) 1rem; }

@media (max-width: 55rem) {
  .detail-columns { gap: 1.5rem; }
  .detail-offers__row--decants { --columns: 2; }
}

@media (max-width: 43rem) {
  .detail-columns { grid-template-columns: minmax(0, 1fr); gap: .75rem; }
  .detail-figure { position: static; --bottle-width: 9rem; }
  .detail-figure .perfume-art { aspect-ratio: 4 / 3; }
  .detail-info { padding-block-start: .25rem; gap: .875rem; }
  .detail-offer__label { min-height: 4rem; font-size: .875rem; }
  .detail-offer__label span { font-size: .875rem; }
  .detail-buy {
    position: fixed;
    inset: auto 0 0;
    z-index: 5;
    padding: .625rem var(--page-gutter) max(.625rem, env(safe-area-inset-bottom));
    border-block-start: 1px solid var(--color-divider);
    background: var(--color-surface);
  }
  .detail-buy__add { min-height: 3rem; padding-inline: .5rem; font-size: .875rem; }
  .detail-quantity button { min-width: var(--target-size); }
  .detail-notes { grid-template-columns: repeat(auto-fit, minmax(min(100%, 7.5rem), 1fr)); }
  .perfume-detail:has(.detail-buy) { margin-block-end: 5rem; }
}
</style>
