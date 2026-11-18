<script setup lang="ts">
import { computed, inject, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import EmptyState from '@/shared/EmptyState.vue'
import ErrorState from '@/shared/ErrorState.vue'
import LoadingState from '@/shared/LoadingState.vue'
import { useApiData } from '@/shared/useApiData'
import BottleArt from './BottleArt.vue'
import CatalogIcon from './CatalogIcon.vue'
import { SHOWCASE_PAGE_SIZE, type Moment } from './filters'
import PerfumeCard from './PerfumeCard.vue'
import { STORE_PROFILE } from './profile'
import { cachedShowcase, loadShowcase, prefetchShowcase } from './publicStore'

const route = useRoute()
const slug = computed(() => String(route.params.tienda))
const profile = inject(STORE_PROFILE)
const query = { por_pagina: SHOWCASE_PAGE_SIZE }
const collection = useApiData(
  () => loadShowcase(slug.value, query),
  (page) => page.data.length === 0,
  { cached: () => cachedShowcase(slug.value, query) },
)

const moments: { value: Moment; name: string; description: string }[] = [
  { value: 'dia', name: 'Para el día', description: 'Cítricas, acuáticas y florales ligeras' },
  { value: 'tarde', name: 'Para la tarde', description: 'Aromáticas y amaderadas suaves' },
  { value: 'noche', name: 'Para la noche', description: 'Orientales, oud y cuero' },
]

function storefront(moment?: Moment) {
  return { name: 'storefront', params: { tienda: slug.value }, query: moment ? { momento: moment } : {} }
}

function preload(moment?: Moment): void {
  void prefetchShowcase(slug.value, { ...query, momento: moment })
}

watch(slug, () => {
  if (route.name === 'store-home') void collection.reload()
}, { immediate: true })
</script>

<template>
  <div class="store-home">
    <h1 id="home-title" class="visually-hidden">Inicio</h1>
    <section class="home-hero" aria-labelledby="home-hero-title">
      <div class="catalog-shell home-hero__inner">
        <div class="home-hero__copy">
          <p class="home-eyebrow">Perfumería de nicho</p>
          <h2 id="home-hero-title" class="home-hero__title">Una fragancia también guarda memoria</h2>
          <p class="home-hero__description">
            Pruebe una fragancia en un decant antes de comprar la botella, o huélala en la tienda con una cita de prueba.
          </p>
          <div class="home-hero__actions">
            <RouterLink class="button home-button-sand" :to="storefront()" @pointerenter="preload()" @focusin="preload()">
              Explorar la colección
            </RouterLink>
            <RouterLink class="button home-button-outline" :to="{ name: 'book-appointment', params: { tienda: slug } }">
              Reservar una cita de prueba
            </RouterLink>
          </div>
        </div>
        <div class="home-hero__art" aria-hidden="true">
          <div class="home-hero__circle">
            <BottleArt id="a" family="Floral" hero />
            <BottleArt id="b" family="Amaderada" hero />
            <BottleArt id="c" family="Acuática" hero />
          </div>
        </div>
      </div>
    </section>

    <section class="home-benefits" aria-label="Cómo probar y comprar">
      <ul class="catalog-shell catalog-list home-benefits__grid">
        <li><CatalogIcon name="decant" /><span><strong>Decants para probar</strong><br /> Pruébelo unos días</span></li>
        <li><CatalogIcon name="calendar" /><span><strong>Cita de prueba</strong><br /> En la tienda</span></li>
        <li><CatalogIcon name="payment" /><span><strong>Pago en línea</strong><br /> Con ONVO o PayPal</span></li>
        <li><CatalogIcon name="sparkle" /><span><strong>Recomendación personal</strong><br /> Describa lo que le gusta</span></li>
      </ul>
    </section>

    <section class="catalog-shell home-collection" aria-labelledby="collection-title">
      <div class="catalog-heading-row">
        <h2 id="collection-title">La colección</h2>
        <RouterLink class="action-link" :to="storefront()" @pointerenter="preload()" @focusin="preload()">Ver toda la vitrina</RouterLink>
      </div>
      <LoadingState v-if="collection.loading.value && !collection.data.value" message="Cargando la colección…" />
      <ErrorState v-else-if="collection.problem.value" :problem="collection.problem.value" @retry="collection.reload" />
      <EmptyState v-else-if="collection.empty.value" message="La tienda todavía no publica perfumes." />
      <ul v-else-if="collection.data.value" class="catalog-grid">
        <PerfumeCard v-for="perfume in collection.data.value.data.slice(0, 8)" :key="perfume.id" :perfume="perfume" :slug="slug" />
      </ul>
    </section>

    <section class="catalog-shell home-section" aria-labelledby="moments-title">
      <div class="catalog-heading-row"><h2 id="moments-title">Elija según el momento</h2></div>
      <ul class="catalog-list home-moments">
        <li v-for="moment in moments" :key="moment.value">
          <RouterLink
            :class="`home-moment home-moment--${moment.value}`"
            :to="storefront(moment.value)"
            @pointerenter="preload(moment.value)"
            @focusin="preload(moment.value)"
          >
            <strong>{{ moment.name }}</strong>
            <span>{{ moment.description }}</span>
          </RouterLink>
        </li>
      </ul>
    </section>

    <section class="catalog-shell home-section home-promos" aria-label="Pruebe y elija una fragancia">
      <article class="home-promo home-promo--appointment">
        <p class="home-eyebrow">Cita de prueba</p>
        <h2>Huela antes de decidir</h2>
        <p>Elija perfumes y pruébelos en la piel con una persona de la tienda.</p>
        <p v-if="profile?.lugar_cita">{{ profile.lugar_cita }}</p>
        <RouterLink class="button home-button-sand" :to="{ name: 'book-appointment', params: { tienda: slug } }">
          Ver horarios disponibles
        </RouterLink>
      </article>
      <article class="home-promo home-promo--recommender">
        <p class="home-eyebrow">Recomendador</p>
        <h2>¿No sabe cuál elegir?</h2>
        <p>Describa con sus palabras lo que le gusta y reciba sugerencias del catálogo de la tienda.</p>
        <RouterLink class="button home-button-dark" :to="{ name: 'recommendations', params: { tienda: slug } }">
          Pedir una recomendación
        </RouterLink>
      </article>
    </section>
  </div>
</template>

<style scoped>
.home-hero {
  --color-focus: #a5cdff;
  background: var(--color-hero);
  color: var(--color-light);
}

.home-hero__inner {
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
  align-items: center;
  gap: 2rem 3rem;
  padding-block: clamp(2.5rem, 6vw, 4.5rem);
}

.home-eyebrow {
  color: var(--color-sand);
  font-size: .8125rem;
  font-weight: 700;
  letter-spacing: .14em;
  text-transform: uppercase;
}

.home-hero__title {
  max-width: 13ch;
  margin-block: 1.25rem;
  font-size: clamp(2.5rem, 5.5vw, 4.25rem);
  line-height: 1.08;
  letter-spacing: -.01em;
  text-wrap: wrap;
}

.home-hero__description {
  max-width: 34rem;
  font-size: 1.1875rem;
}

.home-hero__actions {
  display: flex;
  flex-wrap: wrap;
  gap: .75rem;
  margin-block-start: 2rem;
}

.home-hero__actions .button {
  min-height: 3.25rem;
  padding-inline: clamp(.875rem, 1.5vw, 1.25rem);
  font-size: .9375rem;
  letter-spacing: .06em;
}

.home-button-sand {
  border-color: var(--color-sand);
  background: var(--color-sand);
  color: var(--color-hero);
}

.home-button-outline {
  border-color: var(--color-light);
  background: transparent;
  color: var(--color-light);
}

.home-button-dark {
  border-color: var(--color-dark);
  background: var(--color-dark);
  color: var(--color-light);
}

.home-button-sand:hover,
.home-button-outline:hover,
.home-button-dark:hover {
  border-color: var(--color-sand);
  background: var(--color-light);
  color: var(--color-hero);
  text-decoration: underline;
}

.home-hero__art {
  display: grid;
  place-items: center;
}

.home-hero__circle {
  display: flex;
  align-items: flex-end;
  justify-content: center;
  gap: 2%;
  width: min(26rem, 100%);
  aspect-ratio: 1;
  padding-block-end: 14%;
  border-radius: 50%;
  background: var(--color-hero-circle);
}

.home-hero__circle .bottle-art { width: 25%; height: auto; }
.home-hero__circle .bottle-art:nth-child(2) { width: 36%; }
.home-hero__circle .bottle-art:nth-child(3) { width: 27%; }

.home-benefits {
  background: var(--color-surface);
  border-block-end: 1px solid var(--color-divider);
}

.home-benefits__grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 1rem;
  padding: 1.25rem var(--page-gutter);
}

.home-benefits li { display: flex; align-items: center; gap: .75rem; }
.home-benefits svg { flex: none; color: var(--color-accent); }
.home-benefits span { min-width: 0; color: var(--color-text-muted); font-size: .9375rem; overflow-wrap: anywhere; }
.home-benefits strong { color: var(--color-text); font-size: 1.0625rem; }

.home-collection { padding-block: clamp(2rem, 5vw, 3.5rem) 2rem; }
.home-section { padding-block: 2rem; }

.home-moments,
.home-promos {
  display: grid;
  gap: 1.25rem;
}

.home-moments { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.home-promos { grid-template-columns: repeat(2, minmax(0, 1fr)); padding-block-end: 4rem; }

.home-moment,
.home-promo {
  display: flex;
  flex-direction: column;
  height: 100%;
  border-radius: 1rem;
}

.home-moment {
  justify-content: flex-end;
  min-height: clamp(8rem, 23vw, 18rem);
  padding: 1.5rem;
  background: #f2ecd2;
  color: var(--color-dark);
  text-decoration: none;
}

.home-moment strong { font: 600 1.75rem/1.25 var(--font-display, Georgia, serif); }
.home-moment:hover strong { text-decoration: underline; }
.home-moment--tarde { background: #efdfc8; }
.home-moment--noche { background: var(--color-hero); color: var(--color-light); }

.home-promo {
  gap: .75rem;
  padding: clamp(1.25rem, 3vw, 2rem);
}

.home-promo h2 { font-size: 1.875rem; }
.home-promo .button { align-self: flex-start; min-height: 3rem; margin-block-start: auto; }
.home-promo--appointment { --color-focus: #a5cdff; background: var(--color-wine); color: var(--color-light); }
.home-promo--recommender { background: #efe5d8; color: var(--color-dark); }
.home-promo--recommender .home-eyebrow { color: #653d00; }

@media (max-width: 55rem) {
  .home-hero__inner, .home-benefits__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 40rem) {
  .home-hero__inner, .home-promos, .home-moments { grid-template-columns: minmax(0, 1fr); }
  .home-hero__art { justify-items: start; }
  .home-hero__circle { width: min(100%, 18rem); padding-block-end: 12%; }
  .home-benefits li { align-items: flex-start; gap: .5rem; }
  .home-benefits li svg { width: 1.25rem; height: 1.25rem; }
}
</style>
