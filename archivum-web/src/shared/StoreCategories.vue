<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import { prefetchShowcase } from '@/features/storefront/publicStore'

const props = defineProps<{ tienda: string; families: string[] }>()
const emit = defineEmits<{ navigate: [] }>()
const route = useRoute()
const showcaseLinks = computed<{ title: string; family?: string }[]>(() => [
  { title: 'Vitrina' },
  ...props.families.slice(0, 5).map((family) => ({ title: family, family })),
])

function preload(family?: string): void {
  void prefetchShowcase(props.tienda, { por_pagina: 12, ...(family ? { familia: family } : {}) })
}
</script>

<template>
  <nav class="store-categories" aria-label="Categorías">
    <ul>
      <li v-for="link in showcaseLinks" :key="link.family ? `family:${link.family}` : 'showcase'">
        <RouterLink
          v-slot="{ href, navigate }"
          custom
          :to="{ name: 'storefront', params: { tienda }, query: link.family ? { familia: link.family } : {} }"
        >
          <a
            :href="href"
            :aria-current="route.name === 'storefront' && route.query.familia === link.family ? 'page' : undefined"
            @pointerenter="preload(link.family)"
            @focusin="preload(link.family)"
            @click="navigate($event); emit('navigate')"
          >{{ link.title }}</a>
        </RouterLink>
      </li>
      <li><RouterLink :to="{ name: 'help', hash: '#decant' }" @click="emit('navigate')">Decants</RouterLink></li>
      <li>
        <RouterLink :to="{ name: 'book-appointment', params: { tienda } }" @click="emit('navigate')">Cita de prueba</RouterLink>
      </li>
      <li>
        <RouterLink :to="{ name: 'recommendations', params: { tienda } }" @click="emit('navigate')">Recomendador</RouterLink>
      </li>
    </ul>
  </nav>
</template>
