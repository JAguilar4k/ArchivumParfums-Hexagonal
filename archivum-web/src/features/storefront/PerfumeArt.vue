<script setup lang="ts">
import { computed } from 'vue'

import type { components } from '@/api/schema'

import BottleArt from './BottleArt.vue'
import { bottlePalette } from './bottlePalettes'

const props = defineProps<{
  id: string
  family?: string | null
  photo?: string | null
  variants?: components['schemas']['ShowcasePerfume']['foto_variantes']
  sizes?: string
  alt?: string
}>()
const palette = computed(() => bottlePalette(props.family))
const srcset = computed(() => props.variants?.map(({ ancho, url }) => `${url} ${ancho}w`).join(', ') || undefined)
</script>

<template>
  <div class="perfume-art" :style="{ background: palette.background }">
    <img
      v-if="photo"
      :src="photo"
      :srcset="srcset"
      :sizes="srcset ? sizes : undefined"
      :alt="alt ?? ''"
      width="600"
      height="660"
      loading="lazy"
      decoding="async"
    />
    <BottleArt v-else :id="id" :family="family" />
    <slot />
  </div>
</template>

<style scoped>
.perfume-art {
  position: relative;
  display: grid;
  place-items: center;
  aspect-ratio: 10 / 11;
  overflow: hidden;
  border-radius: 1rem;
}

.perfume-art img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.perfume-art .bottle-art {
  width: min(56%, var(--bottle-width, 7rem));
  height: auto;
}
</style>
