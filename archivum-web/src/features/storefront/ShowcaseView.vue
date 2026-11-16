<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import { api } from '@/api/client'
import { callApi, fieldAttributes } from '@/api/problem'
import EmptyState from '@/shared/EmptyState.vue'
import ErrorState from '@/shared/ErrorState.vue'
import FieldError from '@/shared/FieldError.vue'
import LoadingState from '@/shared/LoadingState.vue'
import { formatColones } from '@/shared/money'
import { useApiData } from '@/shared/useApiData'
import { CLIMATES, formFromQuery, MOMENTS, queryFromForm, showcaseQuery } from './filters'
import { tagLabel } from './labels'

/**
 * The showcase of a store (#128), page by page. The filters live in the URL, so a search can be shared and the
 * back button returns to it.
 */
const route = useRoute()
const router = useRouter()
const tienda = computed(() => String(route.params.tienda))

const form = ref(formFromQuery(route.query))

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

watch(
  () => [tienda.value, route.query],
  () => {
    form.value = formFromQuery(route.query)
    void showcase.reload()
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
  <main class="showcase">
    <h2>Vitrina</h2>

    <form class="filters" role="search" @submit.prevent="search">
      <label for="texto">Buscar</label>
      <input id="texto" v-model="form.texto" name="texto" type="search" v-bind="fieldAttributes(showcase.problem.value, 'texto')" />
      <FieldError :problem="showcase.problem.value" field="texto" />

      <label for="familia">Familia</label>
      <input id="familia" v-model="form.familia" name="familia" v-bind="fieldAttributes(showcase.problem.value, 'familia')" />
      <FieldError :problem="showcase.problem.value" field="familia" />

      <label for="nota">Nota</label>
      <input id="nota" v-model="form.nota" name="nota" v-bind="fieldAttributes(showcase.problem.value, 'nota')" />
      <FieldError :problem="showcase.problem.value" field="nota" />

      <label for="momento">Momento</label>
      <select id="momento" v-model="form.momento" name="momento" v-bind="fieldAttributes(showcase.problem.value, 'momento')">
        <option value="">Cualquiera</option>
        <option v-for="moment in MOMENTS" :key="moment" :value="moment">{{ tagLabel(moment) }}</option>
      </select>
      <FieldError :problem="showcase.problem.value" field="momento" />

      <label for="clima">Clima</label>
      <select id="clima" v-model="form.clima" name="clima" v-bind="fieldAttributes(showcase.problem.value, 'clima')">
        <option value="">Cualquiera</option>
        <option v-for="climate in CLIMATES" :key="climate" :value="climate">{{ tagLabel(climate) }}</option>
      </select>
      <FieldError :problem="showcase.problem.value" field="clima" />

      <label for="ocasion">Ocasión</label>
      <input id="ocasion" v-model="form.ocasion" name="ocasion" v-bind="fieldAttributes(showcase.problem.value, 'ocasion')" />
      <FieldError :problem="showcase.problem.value" field="ocasion" />

      <label for="precio_maximo">Precio máximo (₡)</label>
      <input
        id="precio_maximo"
        v-model="form.precio_maximo"
        name="precio_maximo"
        type="number"
        min="0"
        step="1"
        inputmode="numeric"
        v-bind="fieldAttributes(showcase.problem.value, 'precio_maximo')"
      />
      <FieldError :problem="showcase.problem.value" field="precio_maximo" />

      <button type="submit">Buscar</button>
      <button type="button" @click="clear">Limpiar filtros</button>
    </form>

    <LoadingState v-if="showcase.loading.value" message="Cargando perfumes…" />
    <ErrorState v-else-if="showcase.problem.value" :problem="showcase.problem.value" @retry="showcase.reload" />
    <EmptyState v-else-if="showcase.empty.value" message="No hay perfumes que coincidan con la búsqueda." />
    <template v-else-if="showcase.data.value">
      <ul class="perfumes">
        <li v-for="perfume in showcase.data.value.data" :key="perfume.id" class="perfume-card">
          <img v-if="perfume.foto_url" :src="perfume.foto_url" :alt="`${perfume.marca} ${perfume.nombre}`" />
          <h3>
            <RouterLink :to="{ name: 'perfume', params: { tienda, perfume: perfume.id } }">
              {{ perfume.marca }} {{ perfume.nombre }}
            </RouterLink>
          </h3>
          <p v-if="perfume.concentracion || perfume.familia" class="perfume-card__kind">
            {{ [perfume.concentracion, perfume.familia].filter(Boolean).join(' · ') }}
          </p>
          <ul class="perfume-card__variants">
            <li v-for="variant in perfume.presentaciones" :key="variant.ml">
              {{ variant.ml }} ml · {{ formatColones(variant.precio_venta) }}
            </li>
          </ul>
        </li>
      </ul>

      <nav v-if="meta && meta.last_page > 1" class="pagination" aria-label="Páginas">
        <RouterLink v-if="meta.current_page > 1" :to="pageLink(meta.current_page - 1)">Anterior</RouterLink>
        <span>Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <RouterLink v-if="meta.current_page < meta.last_page" :to="pageLink(meta.current_page + 1)">Siguiente</RouterLink>
      </nav>
    </template>
  </main>
</template>
