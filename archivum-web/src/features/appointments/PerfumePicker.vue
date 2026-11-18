<script setup lang="ts">
import { computed, nextTick, onMounted, ref, shallowRef, useTemplateRef } from 'vue'

import { groupAttributes, type ApiProblem } from '@/api/problem'
import type { components } from '@/api/schema'
import { cachedShowcase, loadShowcase } from '@/features/storefront/publicStore'
import FieldError from '@/shared/FieldError.vue'
import ProblemNotice from '@/shared/ProblemNotice.vue'
import type { Named } from './appointment'

type ShowcasePerfume = components['schemas']['ShowcasePerfume']

/**
 * The perfumes to try in the appointment (#129), up to the maximum of the service. The list comes from the showcase of
 * the store (`GET /tiendas/{tienda}/perfumes`), searched by brand or name. A button adds or removes each perfume and
 * keeps its place, so the focus stays on it; the status line says what changed. Its messages are those of `perfumes`.
 */
const props = defineProps<{ tienda: string; max: number; problem: ApiProblem | null }>()
const chosen = defineModel<Named[]>('chosen', { required: true })

const SHOWN = 8

const text = ref('')
const results = shallowRef<ShowcasePerfume[]>([])
const searching = ref(false)
const searchProblem = ref<ApiProblem | null>(null)
const notice = ref('')
const chosenCount = useTemplateRef<HTMLElement>('chosenCount')

const full = computed(() => chosen.value.length >= props.max)

function isChosen(id: string): boolean {
  return chosen.value.some((perfume) => perfume.id === id)
}

async function search(): Promise<void> {
  if (searching.value) {
    return
  }

  searching.value = true
  searchProblem.value = null

  const texto = text.value.trim()
  const query = { ...(texto ? { texto } : {}), por_pagina: SHOWN }
  const cached = cachedShowcase(props.tienda, query)

  if (cached) {
    showResults(cached.data, cached.meta.total, texto)
  }

  const outcome = await loadShowcase(props.tienda, query)

  searching.value = false

  if (outcome.problem) {
    searchProblem.value = outcome.problem
    results.value = []
    notice.value = ''

    return
  }

  showResults(outcome.data.data, outcome.data.meta.total, texto)
}

function showResults(perfumes: ShowcasePerfume[], found: number, texto: string): void {
  results.value = perfumes
  notice.value = resultsSummary(perfumes.length, found, texto)
}

function resultsSummary(shown: number, found: number, texto: string): string {
  if (found === 0) {
    return texto ? `No hay perfumes que coincidan con «${texto}».` : 'La tienda no tiene perfumes en la vitrina.'
  }

  if (shown < found) {
    return `Se muestran ${shown} de ${found} perfumes. Busque por marca o nombre para encontrar otro.`
  }

  return found === 1 ? 'Se encontró un perfume.' : `Se encontraron ${found} perfumes.`
}

/** How many the customer chose, from the new list: the model takes the change only after the parent passes it. */
function count(list: Named[]): string {
  return `Lleva ${list.length} de ${props.max}.`
}

function choose(list: Named[], message: string): void {
  chosen.value = list
  notice.value = `${message} ${count(list)}`
}

function toggle(perfume: ShowcasePerfume): void {
  if (isChosen(perfume.id)) {
    choose(
      chosen.value.filter((item) => item.id !== perfume.id),
      `Se quitó ${perfume.nombre} de la cita.`,
    )

    return
  }

  if (full.value) {
    const perfumes = props.max === 1 ? 'un perfume' : `${props.max} perfumes`
    notice.value = `Ya eligió ${perfumes}, el máximo de este servicio. Quite uno para agregar otro.`

    return
  }

  choose([...chosen.value, { id: perfume.id, nombre: perfume.nombre }], `Se agregó ${perfume.nombre} a la cita.`)
}

async function remove(perfume: Named): Promise<void> {
  choose(
    chosen.value.filter((item) => item.id !== perfume.id),
    `Se quitó ${perfume.nombre} de la cita.`,
  )
  // The button that was used disappears with its perfume.
  await nextTick()
  chosenCount.value?.focus()
}

onMounted(search)
</script>

<template>
  <fieldset class="choice-group perfume-picker" v-bind="groupAttributes(problem, 'perfumes', 'pista-perfumes')">
    <legend>Perfumes que quiere probar</legend>
    <p id="pista-perfumes" class="field-hint">
      Puede elegir hasta {{ max }}, para que la tienda los tenga listos. Si no elige ninguno, le ayudan a escoger en la
      cita.
    </p>

    <p ref="chosenCount" class="perfume-picker__count" tabindex="-1">Elegidos: {{ chosen.length }} de {{ max }}</p>
    <ul v-if="chosen.length > 0" class="perfume-picker__chosen">
      <li v-for="perfume in chosen" :key="perfume.id">
        <span>{{ perfume.nombre }}</span>
        <!-- The visible text starts the name, which says which perfume it removes (WCAG 2.5.3). -->
        <button type="button" class="button--secondary" :aria-label="`Quitar ${perfume.nombre}`" @click="remove(perfume)">
          Quitar
        </button>
      </li>
    </ul>
    <FieldError :problem="problem" field="perfumes" />

    <div class="form-field">
      <label for="buscar-perfume">Buscar un perfume por marca o nombre</label>
      <input id="buscar-perfume" v-model="text" type="search" name="buscar-perfume" @keydown.enter.prevent="search" />
    </div>
    <div class="form-actions">
      <button type="button" class="button--secondary" :aria-disabled="searching ? 'true' : undefined" @click="search">
        Buscar perfumes
      </button>
    </div>
    <!-- Always in the page, so screen readers announce the results and each change. -->
    <p class="results-summary" role="status">{{ notice }}</p>
    <ProblemNotice v-if="searchProblem" :problem="searchProblem" />
    <ul v-if="results.length > 0" class="perfume-picker__results">
      <li v-for="perfume in results" :key="perfume.id">
        <span>
          {{ perfume.nombre }}
          <span class="perfume-picker__brand">{{ perfume.marca }}</span>
        </span>
        <button
          type="button"
          :class="{ 'button--secondary': isChosen(perfume.id) }"
          :aria-label="isChosen(perfume.id) ? `Quitar ${perfume.nombre} de la cita` : `Agregar ${perfume.nombre} a la cita`"
          :aria-disabled="!isChosen(perfume.id) && full ? 'true' : undefined"
          @click="toggle(perfume)"
        >
          {{ isChosen(perfume.id) ? 'Quitar' : 'Agregar' }}
        </button>
      </li>
    </ul>
  </fieldset>
</template>
