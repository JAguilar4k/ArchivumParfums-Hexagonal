<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

import type { components } from '@/api/schema'
import { openCartDrawer } from '@/features/cart/drawer'
import { useCartStore } from '@/features/cart/store'
import MlAmount from '@/shared/MlAmount.vue'
import { formatColones } from '@/shared/money'
import { concentrationLabel, tagLabel } from './labels'
import PerfumeArt from './PerfumeArt.vue'
import { prefetchPerfume } from './publicStore'

const props = withDefaults(defineProps<{
  perfume: components['schemas']['ShowcasePerfume']
  slug: string
  heading?: 'h2' | 'h3'
}>(), { heading: 'h3' })

const cart = useCartStore()
const fullName = computed(() => `${props.perfume.marca} ${props.perfume.nombre}`)
const smallest = computed(() => [...props.perfume.presentaciones].sort((a, b) => a.ml - b.ml)[0])
const moment = computed(() => props.perfume.etiquetas.momento[0])

function preload(): void {
  void prefetchPerfume(props.slug, props.perfume.id)
}

function add(event: MouseEvent): void {
  const offer = smallest.value
  if (!offer) return

  cart.addBottle(props.slug, {
    perfumeId: props.perfume.id,
    perfume: fullName.value,
    presentationId: offer.id,
    ml: offer.ml,
    price: offer.precio_venta,
    family: props.perfume.familia,
    photo: props.perfume.foto_url,
  })
  openCartDrawer({
    slug: props.slug,
    message: `Se agregó ${props.perfume.nombre}, botella de ${offer.ml} mililitros.`,
    trigger: event.currentTarget as HTMLElement,
  })
}
</script>

<template>
  <li class="perfume-card" @pointerenter="preload" @focusin="preload">
    <PerfumeArt :id="perfume.id" :family="perfume.familia" :photo="perfume.foto_url">
      <span v-if="moment" class="perfume-badge">{{ tagLabel(moment) }}</span>
    </PerfumeArt>
    <component :is="heading" class="perfume-card__heading">
      <span class="perfume-brand" aria-hidden="true">{{ perfume.marca }}</span>
      <RouterLink
        :to="{ name: 'perfume', params: { tienda: slug, perfume: perfume.id } }"
        :aria-label="fullName"
        class="perfume-card__link perfume-card__name"
      >{{ perfume.nombre }}</RouterLink>
    </component>
    <template v-if="smallest">
      <p class="perfume-card__size">
        Botella de <MlAmount :amount="smallest.ml" /><template v-if="perfume.concentracion"> · {{ concentrationLabel(perfume.concentracion) }}</template>
      </p>
      <p class="perfume-card__price">{{ formatColones(smallest.precio_venta) }}</p>
      <button
        class="button--secondary perfume-card__add"
        type="button"
        :aria-label="`Agregar al carrito: ${perfume.nombre}, botella de ${smallest.ml} ml`"
        @click="add"
      >
        Agregar<span class="perfume-card__add-suffix"> al carrito</span>
      </button>
    </template>
  </li>
</template>

<style src="./catalog.css"></style>
