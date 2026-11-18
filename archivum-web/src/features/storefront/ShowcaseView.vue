<script setup lang="ts">
import { computed, inject, nextTick, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import { fieldAttributes, fieldMessages, focusFirstInvalidField, groupAttributes } from '@/api/problem'
import ErrorState from '@/shared/ErrorState.vue'
import FieldError from '@/shared/FieldError.vue'
import LoadingState from '@/shared/LoadingState.vue'
import { formatColones } from '@/shared/money'
import SideDrawer from '@/shared/SideDrawer.vue'
import { useApiData } from '@/shared/useApiData'
import {
  CLIMATES,
  formFromQuery,
  MOMENTS,
  queryFromForm,
  SHOWCASE_PAGE_SIZES,
  showcaseQuery,
  type FilterForm,
} from './filters'
import { tagLabel } from './labels'
import PerfumeCard from './PerfumeCard.vue'
import { STORE_FAMILIES } from './profile'
import { cachedShowcase, loadShowcase, prefetchShowcase } from './publicStore'

const route = useRoute()
const router = useRouter()
const slug = computed(() => String(route.params.tienda))
const families = inject(STORE_FAMILIES)
const filterOpen = ref(false)
const filterButton = useTemplateRef<HTMLButtonElement>('filterButton')
const filterForm = useTemplateRef<HTMLFormElement>('filterForm')
const title = useTemplateRef<HTMLElement>('title')
const listTitle = useTemplateRef<HTMLElement>('listTitle')
const focusListAfterLoad = ref(false)
const filterAnnouncement = ref('')
const form = ref(formFromQuery(route.query))
let debounce: ReturnType<typeof setTimeout> | undefined

const query = computed(() => showcaseQuery(route.query))
const showcase = useApiData(
  () => loadShowcase(slug.value, query.value),
  (page) => page.data.length === 0,
  { cached: () => cachedShowcase(slug.value, query.value), keepPrevious: true },
)

const meta = computed(() => showcase.data.value?.meta)
const problem = computed(() => showcase.problem.value)
const perPage = computed(() => String(query.value.por_pagina))
const options = computed(() => [...new Set([perPage.value, ...SHOWCASE_PAGE_SIZES.map(String)])].sort((a, b) => Number(a) - Number(b)))
const summary = computed(() => {
  const page = meta.value
  if (!page) return ''
  if (page.total === 0) return 'No hay perfumes que coincidan con la búsqueda. Pruebe con menos filtros.'
  return `Mostrando ${page.from ?? 0}–${page.to ?? 0} de ${page.total} perfumes`
})
const pageNumbers = computed(() => {
  if (!meta.value) return []
  const { current_page: current, last_page: last } = meta.value
  if (last <= 7) return Array.from({ length: last }, (_, index) => index + 1)

  const start = Math.max(2, Math.min(current - 1, last - 3))
  const numbers = [1, start, start + 1, start + 2, last]
  if (start > 2) numbers.splice(1, 0, -1)
  if (start + 2 < last - 1) numbers.splice(-1, 0, -2)
  return numbers
})

const filterTitles: Record<keyof FilterForm, string> = {
  texto: 'Búsqueda',
  familia: 'Familia',
  momento: 'Momento',
  clima: 'Clima',
  nota: 'Nota',
  ocasion: 'Ocasión',
  precio_maximo: 'Precio máximo',
}
const filterKeys = Object.keys(filterTitles) as (keyof FilterForm)[]
const active = computed(() => {
  const applied = formFromQuery(route.query)
  return filterKeys.flatMap((key) => {
    const value = String(applied[key]).trim()
    if (!value) return []
    const label = key === 'precio_maximo' && Number.isFinite(Number(value)) ? formatColones(Number(value)) : tagLabel(value)
    return [{ key, title: filterTitles[key], label }]
  })
})

const choiceGroups = computed(() => [
  { name: 'familia', title: 'Familia olfativa', any: 'Todas', options: families?.value ?? [] },
  { name: 'momento', title: 'Momento del día', any: 'Cualquiera', options: MOMENTS },
  { name: 'clima', title: 'Clima', any: 'Cualquiera', options: CLIMATES },
] as const)

const pairedFields = [
  { name: 'nota', label: 'Nota', hint: 'Por ejemplo: vainilla.' },
  { name: 'ocasion', label: 'Ocasión', hint: 'Por ejemplo: boda.' },
] as const

watch(
  () => [slug.value, route.query],
  async (_current, _previous, onCleanup) => {
    if (route.name !== 'storefront') return
    let current = true
    onCleanup(() => { current = false })
    clearTimeout(debounce)
    form.value = formFromQuery(route.query)
    await showcase.reload()
    if (!current || route.name !== 'storefront') return

    const validation = problem.value
    if (validation?.kind === 'validation' && filterKeys.some((key) => fieldMessages(validation, key).length)) {
      filterOpen.value = true
      await nextTick()
      if (filterForm.value) focusFirstInvalidField(filterForm.value, validation)
    }
    if (focusListAfterLoad.value) {
      focusListAfterLoad.value = false
      await nextTick()
      listTitle.value?.focus({ preventScroll: true })
    }
  },
  { immediate: true },
)

watch(meta, (page) => {
  if (page && page.current_page < page.last_page) preloadPage(page.current_page + 1)
})

onBeforeUnmount(() => clearTimeout(debounce))

function choiceOptions(group: (typeof choiceGroups.value)[number]): string[] {
  const current = form.value[group.name]
  return ['', ...new Set([...group.options, ...(current ? [current] : [])])]
}

function applyFilters(): void {
  clearTimeout(debounce)
  const filters = queryFromForm(form.value)
  const applied = queryFromForm(formFromQuery(route.query))
  if (filterKeys.every((key) => filters[key] === applied[key])) return

  if (route.query.por_pagina) filters.por_pagina = route.query.por_pagina
  void router.push({ name: 'storefront', params: { tienda: slug.value }, query: filters })
}

function editText(name: keyof FilterForm, event: Event): void {
  form.value[name] = (event.target as HTMLInputElement).value
  clearTimeout(debounce)
  debounce = setTimeout(applyFilters, 300)
}

function choose(name: 'familia' | 'momento' | 'clima', value: string): void {
  form.value[name] = value
  applyFilters()
}

async function removeFilter(name: keyof FilterForm): Promise<void> {
  const filters = formFromQuery(route.query)
  filters[name] = ''
  form.value = filters
  applyFilters()
  await nextTick()
  title.value?.focus()
  filterAnnouncement.value = `Se quitó el filtro ${filterTitles[name]}.`
}

async function clearFilters(keepFocus: boolean): Promise<void> {
  form.value = formFromQuery({})
  applyFilters()
  if (!keepFocus) {
    await nextTick()
    title.value?.focus()
  }
  filterAnnouncement.value = 'Se quitaron todos los filtros.'
}

function changePerPage(event: Event): void {
  const newSize = (event.target as HTMLSelectElement).value
  void router.push({
    name: 'storefront', params: { tienda: slug.value }, query: { ...route.query, pagina: undefined, por_pagina: newSize },
  })
}

function pageLink(page: number) {
  return { name: 'storefront', params: { tienda: slug.value }, query: { ...route.query, pagina: String(page) } }
}

function preloadPage(page: number): void {
  const params = { ...query.value, pagina: page > 1 ? page : undefined }
  void prefetchShowcase(slug.value, params)
}

function viewResults(): void {
  applyFilters()
  filterOpen.value = false
}
</script>

<template>
  <div class="showcase">
    <nav class="breadcrumbs" aria-label="Ubicación">
      <ol>
        <li><RouterLink :to="{ name: 'store-home', params: { tienda: slug } }">Inicio</RouterLink></li>
        <li><span aria-current="page">Vitrina</span></li>
      </ol>
    </nav>

    <h1 ref="title" class="showcase__title" tabindex="-1">Vitrina</h1>

    <div class="showcase__tools">
      <button
        ref="filterButton"
        type="button"
        class="button--secondary showcase__filter-button"
        aria-haspopup="dialog"
        aria-controls="showcase-filter-drawer"
        @click="filterOpen = true"
      >
        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 6h18M7 12h10m-7 6h4" />
        </svg>
        Filtrar ({{ active.length }})
      </button>
      <p class="results-summary showcase__summary" role="status">{{ summary }}</p>
      <div class="showcase__per-page">
        <label for="showcase-page-size">Por página</label>
        <select
          id="showcase-page-size"
          name="por_pagina"
          :value="perPage"
          v-bind="fieldAttributes(problem, 'por_pagina')"
          @change="changePerPage"
        >
          <option v-for="size in options" :key="size" :value="size">{{ size }}</option>
        </select>
        <FieldError :problem="problem" field="por_pagina" />
      </div>
    </div>

    <ul v-if="active.length" class="catalog-list showcase__chips" aria-label="Filtros aplicados">
      <li v-for="filter in active" :key="filter.key">
        <button
          type="button"
          class="showcase__chip"
          :aria-label="`Quitar filtro ${filter.title}: ${filter.label}`"
          @click="removeFilter(filter.key)"
        >
          {{ filter.title }}: {{ filter.label }} <span aria-hidden="true">×</span>
        </button>
      </li>
      <li><button type="button" class="showcase__clear" @click="clearFilters(false)">Quitar todos</button></li>
    </ul>
    <p class="visually-hidden" role="status">{{ filterAnnouncement }}</p>

    <section aria-labelledby="perfumes-titulo">
      <h2 id="perfumes-titulo" ref="listTitle" class="visually-hidden" tabindex="-1">Perfumes</h2>
      <div class="showcase__results" :aria-busy="showcase.loading.value">
        <LoadingState v-if="showcase.loading.value && !showcase.data.value" message="Cargando perfumes…" />
        <ErrorState v-if="problem && problem.kind !== 'validation'" :problem="problem" @retry="showcase.reload" />
        <ul v-if="showcase.data.value?.data.length" class="catalog-grid card-list">
          <PerfumeCard v-for="perfume in showcase.data.value.data" :key="perfume.id" :perfume="perfume" :slug="slug" />
        </ul>
      </div>

      <nav v-if="meta && meta.last_page > 1" class="showcase__pagination pagination" aria-label="Páginas de la vitrina">
        <RouterLink
          v-if="meta.current_page > 1"
          class="button button--secondary"
          :to="pageLink(meta.current_page - 1)"
          @click="focusListAfterLoad = true"
          @pointerenter="preloadPage(meta.current_page - 1)"
          @focusin="preloadPage(meta.current_page - 1)"
        >Anterior</RouterLink>
        <span v-else class="showcase__page-end">Anterior</span>

        <template v-for="page in pageNumbers" :key="page">
          <span v-if="page < 0" aria-hidden="true">…</span>
          <span v-else-if="page === meta.current_page" class="showcase__page-current" aria-current="page">{{ page }}</span>
          <RouterLink
            v-else
            class="button button--secondary showcase__page-link"
            :aria-label="`Página ${page}`"
            :to="pageLink(page)"
            @click="focusListAfterLoad = true"
            @pointerenter="preloadPage(page)"
            @focusin="preloadPage(page)"
          >{{ page }}</RouterLink>
        </template>

        <RouterLink
          v-if="meta.current_page < meta.last_page"
          class="button button--secondary"
          :to="pageLink(meta.current_page + 1)"
          @click="focusListAfterLoad = true"
          @pointerenter="preloadPage(meta.current_page + 1)"
          @focusin="preloadPage(meta.current_page + 1)"
        >Siguiente</RouterLink>
        <span v-else class="showcase__page-end">Siguiente</span>
      </nav>
    </section>

    <SideDrawer
      id="showcase-filter-drawer"
      v-model:open="filterOpen"
      v-bind="filterButton ? { trigger: filterButton } : {}"
      heading-id="showcase-filter-title"
      title="Filtrar"
      side="left"
      close-label="Cerrar el panel de filtros"
      class="showcase-filter-drawer"
    >
      <p class="showcase-filter__intro">La lista cambia al elegir cada filtro.</p>
      <form ref="filterForm" class="showcase-filter" aria-labelledby="showcase-filter-title" novalidate @submit.prevent="applyFilters">
        <div class="form-field">
          <label for="texto">Marca, nombre o nota</label>
          <p id="pista-texto" class="field-hint">Por ejemplo: Maison Noctis. Para filtrar una nota, use el campo «Nota».</p>
          <input
            id="texto"
            name="texto"
            type="search"
            :value="form.texto"
            v-bind="fieldAttributes(problem, 'texto', 'pista-texto')"
            @input="editText('texto', $event)"
          />
          <FieldError :problem="problem" field="texto" />
        </div>

        <fieldset
          v-for="group in choiceGroups"
          :key="group.name"
          class="choice-group showcase-filter__group"
          v-bind="groupAttributes(problem, group.name)"
        >
          <legend>{{ group.title }}</legend>
          <div class="showcase-filter__choices">
            <label v-for="choice in choiceOptions(group)" :key="choice" class="showcase-filter__choice">
              <input
                type="radio"
                :name="group.name"
                :value="choice"
                :checked="form[group.name] === choice"
                @change="choose(group.name, choice)"
              />
              <span>{{ choice ? tagLabel(choice) : group.any }}</span>
            </label>
          </div>
          <FieldError :problem="problem" :field="group.name" />
        </fieldset>

        <div class="showcase-filter__pair">
          <div v-for="field in pairedFields" :key="field.name" class="form-field">
            <label :for="field.name">{{ field.label }}</label>
            <p :id="`pista-${field.name}`" class="field-hint">{{ field.hint }}</p>
            <input
              :id="field.name"
              :name="field.name"
              :value="form[field.name]"
              v-bind="fieldAttributes(problem, field.name, `pista-${field.name}`)"
              @input="editText(field.name, $event)"
            />
            <FieldError :problem="problem" :field="field.name" />
          </div>
        </div>

        <div class="form-field">
          <label for="precio_maximo">Precio máximo en colones</label>
          <p id="pista-precio_maximo" class="field-hint">Solo números, sin puntos ni espacios.</p>
          <input
            id="precio_maximo"
            name="precio_maximo"
            type="number"
            min="0"
            step="1"
            inputmode="numeric"
            :value="form.precio_maximo"
            v-bind="fieldAttributes(problem, 'precio_maximo', 'pista-precio_maximo')"
            @input="editText('precio_maximo', $event)"
          />
          <FieldError :problem="problem" field="precio_maximo" />
        </div>
      </form>

      <template #footer>
        <div class="showcase-filter__actions">
          <button type="button" class="button--secondary" @click="clearFilters(true)">Limpiar</button>
          <button type="button" @click="viewResults">{{ meta ? `Ver ${meta.total} perfumes` : 'Ver perfumes' }}</button>
        </div>
      </template>
    </SideDrawer>
  </div>
</template>

<style scoped>
.showcase {
  display: grid;
  gap: 1rem;
}

.showcase__title {
  margin-block: .25rem .5rem;
  text-align: center;
  font-size: clamp(2rem, 4.25vw, 2.875rem);
}

.showcase__tools {
  display: grid;
  grid-template-columns: max-content minmax(0, 1fr) max-content;
  align-items: center;
  gap: .75rem 1rem;
  padding-block: .75rem;
  border-block: 1px solid var(--color-divider);
}

.showcase__filter-button { border-color: var(--color-text); color: var(--color-text); }
.showcase__summary { justify-self: center; color: var(--color-text-muted); }

.showcase__per-page {
  display: grid;
  grid-template-columns: auto minmax(4.75rem, auto);
  align-items: center;
  gap: .375rem .5rem;
  font-weight: 600;
}

.showcase__per-page .field-error { grid-column: 1 / -1; }

.showcase__chips { display: flex; flex-wrap: wrap; gap: .5rem; }
.showcase__chips li { min-width: 0; }

.showcase__chip {
  border: 1px solid var(--color-accent);
  background: var(--color-selected);
  color: var(--color-link);
  overflow-wrap: anywhere;
}

.showcase__clear {
  padding-inline: .5rem;
  border-color: transparent;
  background: transparent;
  color: var(--color-link);
  text-decoration: underline;
}

.showcase__chip:hover,
.showcase__clear:hover { text-decoration: underline; }

.showcase__results[aria-busy='true'] :deep(.perfume-art img),
.showcase__results[aria-busy='true'] :deep(.perfume-art svg) { opacity: .55; }

.showcase__results .catalog-grid {
  grid-template-columns: repeat(4, minmax(0, 1fr));
  margin-block: .5rem 2.5rem;
}

.showcase__pagination { justify-content: center; flex-wrap: wrap; }

.showcase__pagination .button {
  border-color: var(--color-text);
  color: var(--color-text);
}

.showcase__pagination .showcase__page-link { padding-inline: .625rem; border-width: 1px; }

.showcase__page-end,
.showcase__page-current {
  display: inline-grid;
  place-items: center;
  min-height: var(--target-size);
  min-width: var(--target-size);
  padding-inline: .75rem;
  border: 1px solid var(--color-divider);
  border-radius: 999px;
  color: var(--color-text-muted);
}

.showcase__page-current {
  border-color: var(--color-accent);
  background: var(--color-accent);
  color: var(--color-on-accent);
  font-weight: 700;
}

.showcase-filter-drawer { --drawer-width: 24rem; }
.showcase-filter { display: grid; gap: 1rem; padding: 0 1.25rem 1rem; }
.showcase-filter__intro { padding: 1rem 1.25rem; color: var(--color-text-muted); font-size: .9375rem; }
.showcase-filter__group { min-inline-size: 0; margin: 0; padding: 0; border: 0; }
.showcase-filter__group legend { margin-block-end: .5rem; }

.showcase-filter__pair,
.showcase-filter__choices,
.showcase-filter__actions {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: .5rem;
}

.showcase-filter__choice { position: relative; min-width: 0; }

.showcase-filter__choice input {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  margin: 0;
  opacity: 0;
  cursor: pointer;
}

.showcase-filter__choice span {
  display: grid;
  place-items: center;
  min-height: var(--target-size);
  padding: .25rem .5rem;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  background: var(--color-surface);
  color: var(--color-text);
  text-align: center;
  overflow-wrap: anywhere;
}

.showcase-filter__choice input:checked + span {
  border-color: var(--color-accent);
  background: var(--color-accent);
  color: var(--color-on-accent);
  font-weight: 700;
}

.showcase-filter__choice input:focus-visible + span {
  outline: 3px solid var(--color-focus);
  outline-offset: 2px;
}

.showcase-filter__pair .form-field { min-width: 0; }
.showcase-filter__actions button { padding-inline: .375rem; font-size: .9375rem; }

@media (max-width: 57rem) {
  .showcase__results .catalog-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}

@media (max-width: 40rem) {
  .showcase__tools { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .showcase__filter-button { justify-self: start; padding-inline: .75rem; }
  .showcase__per-page { justify-self: end; grid-template-columns: minmax(0, auto) minmax(3.75rem, auto); }
  .showcase__summary { grid-column: 1 / -1; grid-row: 1; text-align: center; font-size: .9375rem; }
  .showcase__results .catalog-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
