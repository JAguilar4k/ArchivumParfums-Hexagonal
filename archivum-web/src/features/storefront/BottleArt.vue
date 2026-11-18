<script setup lang="ts">
import { computed } from 'vue'

import { bottlePalette } from './bottlePalettes'

const props = defineProps<{ id: string; family?: string | null; hero?: boolean }>()

const shapes = [
  { cap: [48, 10, 24, 26, 3], neck: [54, 36, 12, 8, 1], body: [30, 44, 60, 104, 10], label: [42, 84, 36, 26, 2], shine: [38, 54, 6, 70, 3] },
  { cap: [49, 12, 22, 24, 11], neck: [54, 36, 12, 10, 1], body: [22, 46, 76, 100, 36], label: [40, 84, 40, 24, 2], shine: [34, 62, 6, 50, 3] },
  { cap: [42, 8, 36, 28, 2], neck: [53, 36, 14, 8, 1], body: [26, 44, 68, 102, 4], label: [38, 80, 44, 30, 1], shine: [34, 52, 6, 80, 2] },
] as const

const shape = computed(() => {
  let index = 0
  for (const character of props.id) {
    index = (index * 31 + character.charCodeAt(0)) % shapes.length
  }
  return shapes[index] ?? shapes[0]
})
const palette = computed(() => bottlePalette(props.family))
const ink = computed(() => props.hero ? '#e7c98f' : palette.value.ink)

function roundedRect([x, y, width, height, radius]: readonly [number, number, number, number, number]): string {
  const right = x + width
  const bottom = y + height
  return `M${x + radius} ${y}H${right - radius}Q${right} ${y} ${right} ${y + radius}`
    + `V${bottom - radius}Q${right} ${bottom} ${right - radius} ${bottom}`
    + `H${x + radius}Q${x} ${bottom} ${x} ${bottom - radius}V${y + radius}Q${x} ${y} ${x + radius} ${y}Z`
}
</script>

<template>
  <svg class="bottle-art" aria-hidden="true" width="120" height="160" viewBox="0 0 120 160">
    <path :d="roundedRect(shape.cap)" :fill="ink" />
    <path :d="roundedRect(shape.neck)" :fill="ink" opacity="0.8" />
    <path :d="roundedRect(shape.body)" :fill="hero ? palette.ink : palette.glass" :stroke="ink" stroke-width="2" />
    <path :d="roundedRect(shape.label)" :fill="hero ? '#1f0a14' : '#fffaf4'" :stroke="ink" />
    <path :d="roundedRect(shape.shine)" fill="#fff" opacity="0.35" />
  </svg>
</template>
