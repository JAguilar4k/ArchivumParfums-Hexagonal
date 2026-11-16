<script setup lang="ts">
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import { api } from '@/api/client'
import { callApi, fieldAttributes } from '@/api/problem'
import ErrorState from '@/shared/ErrorState.vue'
import FieldError from '@/shared/FieldError.vue'
import LoadingState from '@/shared/LoadingState.vue'
import MlAmount from '@/shared/MlAmount.vue'
import { formatColones } from '@/shared/money'
import { useApiData } from '@/shared/useApiData'
import { CLIMATES, formFromQuery, MOMENTS, queryFromForm, showcaseQuery } from './filters'
import { concentrationLabel, tagLabel } from './labels'

/**
 * The showcase of a store (#128), page by page. The filters live in the URL, so a search can be shared and the
 * back button returns to it. A line that screen readers announce says how many perfumes match, and after a change of
 * page the focus goes to the title of the list, because the link that was used may no longer exist.
 */
const route = useRoute()
const router = useRouter()
const tienda = computed(() => String(route.params.tienda))

const form = ref(formFromQuery(route.query))
const listTitle = useTemplateRef<HTMLElement>('listTitle')
const focusListAfterLoad = ref(false)

const showcase = useApiData(
  () =>
    callApi(() =>
      api.GET('/tiendas/{tienda}/perfumes', {
        params: { path: { tienda: tienda.value }, query: showcaseQuery(route.query) },
      }),
    ),
  (page) => page.data.length === 0,
)

const meta = computed(() => showcase.data.value?.meta ?? null)

const summary = computed(() => {
  if (showcase.loading.value || showcase.problem.value) {
    return ''
  }

  if (showcase.empty.value) {
    return 'No hay perfumes que coincidan con la búsqueda. Pruebe con menos filtros.'
  }

  if (!meta.value) {
    return ''
  }

  const found = meta.value.total === 1 ? 'Se encontró 1 perfume.' : `Se encontraron ${meta.value.total} perfumes.`

  return meta.value.last_page > 1 ? `${found} Página ${meta.value.current_page} de ${meta.value.last_page}.` : found
})

watch(
  () => [tienda.value, route.query],
  async () => {
    form.value = formFromQuery(route.query)
    await showcase.reload()

    if (focusListAfterLoad.value) {
      focusListAfterLoad.value = false
      await nextTick()
      listTitle.value?.focus()
    }
  },
  { immediate: true },
)

function search(): void {
  void router.push({ name: 'storefront', params: { tienda: tienda.value }, query: queryFromForm(form.value) })
}

function clear(): void {
  void router.push({ name: 'storefront', params: { tienda: tienda.value } })
}

function pageLink(page: number) {
  return { name: 'storefront', params: { tienda: tienda.value }, query: { ...route.query, pagina: String(page) } }
}
</script>

<template>
  <div class="page showcase">
    <h1>Vitrina</h1>

    <section class="page-section" aria-labelledby="buscar-titulo">
      <h2 id="buscar-titulo">Buscar perfumes</h2>
      <p class="form-intro">
        Llene solo los campos que le interesen y pulse «Buscar». Si no conoce alguna palabra, la
        <RouterLink to="/ayuda#glosario">explicación de las palabras de perfumería</RouterLink> le puede ayudar.
      </p>

      <form class="filters" role="search" aria-labelledby="buscar-titulo" novalidate @submit.prevent="search">
        <div class="filters__fields">
          <div class="form-field">
            <label for="texto">Marca o nombre</label>
            <p id="pista-texto" class="field-hint">Por ejemplo: Maison Noctis.</p>
            <input
              id="texto"
              v-model="form.texto"
              name="texto"
              type="search"
              v-bind="fieldAttributes(showcase.problem.value, 'texto', 'pista-texto')"
            />
            <FieldError :problem="showcase.problem.value" field="texto" />
          </div>

          <div class="form-field">
            <label for="familia">Familia olfativa</label>
            <p id="pista-familia" class="field-hint">El tipo de aroma, por ejemplo: Floral o Amaderada.</p>
            <input
              id="familia"
              v-model="form.familia"
              name="familia"
              v-bind="fieldAttributes(showcase.problem.value, 'familia', 'pista-familia')"
            />
            <FieldError :problem="showcase.problem.value" field="familia" />
          </div>

          <div class="form-field">
            <label for="nota">Nota</label>
            <p id="pista-nota" class="field-hint">Un aroma que lleva el perfume, por ejemplo: vainilla.</p>
            <input
              id="nota"
              v-model="form.nota"
              name="nota"
              v-bind="fieldAttributes(showcase.problem.value, 'nota', 'pista-nota')"
            />
            <FieldError :problem="showcase.problem.value" field="nota" />
          </div>

          <div class="form-field">
            <label for="momento">Momento del día</label>
            <select
              id="momento"
              v-model="form.momento"
              name="momento"
              v-bind="fieldAttributes(showcase.problem.value, 'momento')"
            >
              <option value="">Cualquiera</option>
              <option v-for="moment in MOMENTS" :key="moment" :value="moment">{{ tagLabel(moment) }}</option>
            </select>
            <FieldError :problem="showcase.problem.value" field="momento" />
          </div>

          <div class="form-field">
            <label for="clima">Clima</label>
            <select id="clima" v-model="form.clima" name="clima" v-bind="fieldAttributes(showcase.problem.value, 'clima')">
              <option value="">Cualquiera</option>
              <option v-for="climate in CLIMATES" :key="climate" :value="climate">{{ tagLabel(climate) }}</option>
            </select>
            <FieldError :problem="showcase.problem.value" field="clima" />
          </div>

          <div class="form-field">
            <label for="ocasion">Ocasión</label>
            <p id="pista-ocasion" class="field-hint">Por ejemplo: cena, boda u oficina.</p>
            <input
              id="ocasion"
              v-model="form.ocasion"
              name="ocasion"
              v-bind="fieldAttributes(showcase.problem.value, 'ocasion', 'pista-ocasion')"
            />
            <FieldError :problem="showcase.problem.value" field="ocasion" />
          </div>

          <div class="form-field">
            <label for="precio_maximo">Precio máximo en colones</label>
            <p id="pista-precio_maximo" class="field-hint">Solo números, sin puntos ni espacios. Por ejemplo: 150000.</p>
            <input
              id="precio_maximo"
              v-model="form.precio_maximo"
              name="precio_maximo"
              type="number"
              min="0"
              step="1"
              inputmode="numeric"
              v-bind="fieldAttributes(showcase.problem.value, 'precio_maximo', 'pista-precio_maximo')"
            />
            <FieldError :problem="showcase.problem.value" field="precio_maximo" />
          </div>
        </div>

        <div class="form-actions">
          <button type="submit">Buscar</button>
          <button type="button" class="button--secondary" @click="clear">Limpiar filtros</button>
        </div>
      </form>
    </section>

    <section class="page-section" aria-labelledby="perfumes-titulo">
      <h2 id="perfumes-titulo" ref="listTitle" tabindex="-1">Perfumes</h2>
      <!-- Always in the page, so screen readers announce each new count. -->
      <p class="results-summary" role="status">{{ summary }}</p>

      <LoadingState v-if="showcase.loading.value" message="Cargando perfumes…" />
      <ErrorState v-else-if="showcase.problem.value" :problem="showcase.problem.value" @retry="showcase.reload" />
      <template v-else-if="showcase.data.value && !showcase.empty.value">
        <ul class="card-list">
          <li v-for="perfume in showcase.data.value.data" :key="perfume.id" class="card perfume-card">
            <!-- The name of the perfume follows as a heading, so the photo adds nothing for a screen reader. -->
            <img v-if="perfume.foto_url" :src="perfume.foto_url" alt="" class="card__image" />
            <h3>
              <RouterLink :to="{ name: 'perfume', params: { tienda, perfume: perfume.id } }" class="action-link">
                {{ perfume.marca }} {{ perfume.nombre }}
              </RouterLink>
            </h3>
            <p v-if="perfume.concentracion || perfume.familia" class="perfume-card__kind">
              {{ [concentrationLabel(perfume.concentracion), perfume.familia].filter(Boolean).join(' · ') }}
            </p>
            <ul class="perfume-card__variants">
              <li v-for="variant in perfume.presentaciones" :key="variant.ml">
                Botella de <MlAmount :amount="variant.ml" /> · {{ formatColones(variant.precio_venta) }}
              </li>
            </ul>
          </li>
        </ul>

        <nav v-if="meta && meta.last_page > 1" class="pagination" aria-label="Páginas de la vitrina">
          <RouterLink
            v-if="meta.current_page > 1"
            :to="pageLink(meta.current_page - 1)"
            class="action-link"
            @click="focusListAfterLoad = true"
          >
            Página anterior
          </RouterLink>
          <span>Página {{ meta.current_page }} de {{ meta.last_page }}</span>
          <RouterLink
            v-if="meta.current_page < meta.last_page"
            :to="pageLink(meta.current_page + 1)"
            class="action-link"
            @click="focusListAfterLoad = true"
          >
            Página siguiente
          </RouterLink>
        </nav>
      </template>
    </section>
  </div>
</template>
