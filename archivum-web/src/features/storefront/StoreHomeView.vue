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
            Prueba una fragancia en un decant antes de comprar la botella, o huélela en la tienda con una cita de prueba.
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
      <div class="home-benefits__inner">
        <ul class="catalog-list home-benefits__grid">
          <li>
            <CatalogIcon name="decant" />
            <span>
              <strong>Decants para probar</strong>
              <span class="home-benefits__description">Pruébalos unos días</span>
            </span>
          </li>
          <li>
            <CatalogIcon name="calendar" />
            <span>
              <strong>Cita de prueba</strong>
              <span class="home-benefits__description">En la tienda</span>
            </span>
          </li>
          <li>
            <CatalogIcon name="payment" />
            <span>
              <strong>Pago en línea</strong>
              <span class="home-benefits__description">Con ONVO o PayPal</span>
            </span>
          </li>
          <li>
            <CatalogIcon name="sparkle" />
            <span>
              <strong>Recomendación personal</strong>
              <span class="home-benefits__description">Cuéntanos qué te gusta</span>
            </span>
          </li>
        </ul>
      </div>
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
      <div class="catalog-heading-row"><h2 id="moments-title">Elige según el momento</h2></div>
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

    <section class="catalog-shell home-section home-promos" aria-label="Prueba y elige una fragancia">
      <article class="home-promo home-promo--appointment">
        <p class="home-eyebrow">Cita de prueba</p>
        <h2>Huela antes de decidir</h2>
        <p>Elige perfumes y pruébalos en la piel con una persona de la tienda.</p>
        <p v-if="profile?.lugar_cita">{{ profile.lugar_cita }}</p>
        <RouterLink class="button home-button-sand" :to="{ name: 'book-appointment', params: { tienda: slug } }">
          Ver horarios disponibles
        </RouterLink>
      </article>
      <article class="home-promo home-promo--recommender">
        <p class="home-eyebrow">Recomendador</p>
        <h2>¿No sabe cuál elegir?</h2>
        <p>Cuéntanos con tus palabras qué te gusta y recibe sugerencias del catálogo de la tienda.</p>
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

/* --- Revamp, fase 2: portada comercial --- */

.home-hero {
  position: relative;
  overflow: hidden;
  border-block-end: 1px solid var(--color-divider);
}

.home-hero::after {
  content: '';
  position: absolute;
  inset-block-start: -35%;
  inset-inline-end: -14%;
  width: 38rem;
  height: 38rem;
  border-radius: 50%;
  background: radial-gradient(circle, rgb(243 220 178 / 26%) 0%, rgb(243 220 178 / 0%) 66%);
  pointer-events: none;
}

.home-hero__inner {
  position: relative;
  z-index: 1;
}

.home-hero__title {
  font-family: Inter, system-ui, sans-serif;
  font-size: clamp(2rem, 4.5vw, 3.35rem);
  font-weight: 600;
  line-height: 1.06;
  letter-spacing: -0.03em;
}

.home-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 0.625rem;
  font-size: 0.75rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
}

.home-eyebrow::before {
  content: '';
  width: 2.25rem;
  border-top: 2px solid currentColor;
  opacity: 0.55;
}

.home-hero__description {
  font-size: 1.0625rem;
}

.home-hero__circle {
  box-shadow: inset 0 0 0 1px rgb(243 220 178 / 25%), 0 32px 64px -44px rgb(0 0 0 / 65%);
}

.home-benefits {
  border-block-start: 1px solid var(--color-divider);
}

.home-benefits__grid li + li::before {
  content: '';
  position: absolute;
  inset-block: 0.35rem;
  inset-inline-start: 0;
  border-inline-start: 1px solid var(--color-divider);
}

.home-moment,
.home-promo {
  border-radius: 1.25rem;
  transition: transform 180ms ease, box-shadow 180ms ease;
}

.home-moment:hover,
.home-promo:hover {
  transform: translateY(-3px);
  box-shadow: 0 24px 44px -34px rgb(0 0 0 / 55%);
}

.home-moment strong {
  letter-spacing: -0.01em;
}

.home-promo h2 {
  font-family: var(--font-display);
  letter-spacing: -0.01em;
}

.home-collection,
.home-section {
  padding-block: clamp(1.75rem, 4vw, 3rem);
}

/* Franja de beneficios: fondo a sangre, contenido centrado y columnas uniformes. El relleno horizontal lo pone
   .catalog-shell, así que aquí se anula para no descentrar la franja respecto del resto de la página. */
.home-benefits__grid {
  align-items: stretch;
  gap: 0;
  padding: 1.25rem 0;
}

.home-benefits li {
  position: relative;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: start;
  gap: 0 0.75rem;
  min-height: 4.5rem;
  padding-block: 0.25rem;
  padding-inline: clamp(0.75rem, 1.6vw, 1.5rem);
}

.home-benefits li:first-child {
  padding-inline-start: 0;
}

.home-benefits li:last-child {
  padding-inline-end: 0;
}

.home-benefits li svg {
  margin-block-start: 0.15rem;
}

.home-benefits span {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.home-benefits br {
  display: none;
}

.home-benefits strong {
  line-height: 1.25;
}

@media (max-width: 55rem) {
  .home-benefits__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem 0;
  }

  .home-benefits li,
  .home-benefits li:first-child,
  .home-benefits li:last-child {
    padding-inline: 0 1rem;
  }

  .home-benefits li + li::before {
    display: none;
  }

  .home-benefits li:nth-child(even) {
    padding-inline: 1rem 0;
  }

  .home-benefits li:nth-child(even)::before {
    display: block;
  }
}

@media (max-width: 40rem) {
  .home-eyebrow {
    letter-spacing: 0.16em;
  }
}
</style>
