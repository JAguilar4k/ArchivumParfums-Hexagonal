<script setup lang="ts">
import { computed } from 'vue'

import BottleArt from './BottleArt.vue'
import { bottlePalette } from './bottlePalettes'

const props = defineProps<{ id: string; family?: string | null; photo?: string | null; alt?: string }>()
const palette = computed(() => bottlePalette(props.family))
</script>

<template>
  <div class="perfume-art" :style="{ background: palette.background }">
    <img v-if="photo" :src="photo" :alt="alt ?? ''" width="600" height="660" loading="lazy" decoding="async" />
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
