<script setup lang="ts">
import { computed, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import { api } from '@/api/client'
import { callApi } from '@/api/problem'
import ErrorState from '@/shared/ErrorState.vue'
import LoadingState from '@/shared/LoadingState.vue'
import { formatColones } from '@/shared/money'
import { useApiData } from '@/shared/useApiData'
import { tagLabel } from './labels'

/**
 * The detail of a perfume of the showcase (#128). A perfume that is inactive or belongs to another store answers 404,
 * so it shows "No encontrado".
 */
const route = useRoute()
const tienda = computed(() => String(route.params.tienda))
const perfumeId = computed(() => String(route.params.perfume))

const detail = useApiData(() =>
  callApi(() =>
    api.GET('/tiendas/{tienda}/perfumes/{perfume}', {
      params: { path: { tienda: tienda.value, perfume: perfumeId.value } },
    }),
  ),
)

const perfume = computed(() => detail.data.value?.data ?? null)

const layers = computed(() =>
  perfume.value
    ? [
        { name: 'Salida', notes: perfume.value.notas.salida },
        { name: 'Corazón', notes: perfume.value.notas.corazon },
        { name: 'Fondo', notes: perfume.value.notas.fondo },
      ].filter((layer) => layer.notes.length > 0)
    : [],
)

watch([tienda, perfumeId], () => void detail.reload(), { immediate: true })
</script>

<template>
  <main class="perfume-detail">
    <LoadingState v-if="detail.loading.value" message="Cargando el perfume…" />
    <ErrorState v-else-if="detail.problem.value" :problem="detail.problem.value" @retry="detail.reload" />
    <article v-else-if="perfume">
      <RouterLink :to="{ name: 'storefront', params: { tienda } }">Volver a la vitrina</RouterLink>

      <img v-if="perfume.foto_url" :src="perfume.foto_url" :alt="`${perfume.marca} ${perfume.nombre}`" />
      <h2>{{ perfume.marca }} {{ perfume.nombre }}</h2>
      <p class="perfume-detail__kind">
        {{ [perfume.concentracion, perfume.genero, perfume.familia].filter(Boolean).join(' · ') }}
      </p>
      <p v-if="perfume.descripcion">{{ perfume.descripcion }}</p>

      <section v-if="layers.length > 0" aria-labelledby="notes-title">
        <h3 id="notes-title">Notas</h3>
        <dl>
          <template v-for="layer in layers" :key="layer.name">
            <dt>{{ layer.name }}</dt>
            <dd>{{ layer.notes.join(', ') }}</dd>
          </template>
        </dl>
      </section>

      <section aria-labelledby="tags-title">
        <h3 id="tags-title">Cuándo usarlo</h3>
        <dl>
          <dt>Momento</dt>
          <dd>{{ perfume.etiquetas.momento.map(tagLabel).join(', ') || 'Cualquiera' }}</dd>
          <dt>Clima</dt>
          <dd>{{ perfume.etiquetas.clima.map(tagLabel).join(', ') || 'Cualquiera' }}</dd>
          <dt>Ocasión</dt>
          <dd>{{ perfume.etiquetas.ocasion.join(', ') || 'Cualquiera' }}</dd>
        </dl>
      </section>

      <section aria-labelledby="variants-title">
        <h3 id="variants-title">Presentaciones</h3>
        <ul v-if="perfume.presentaciones.length > 0" class="variants">
          <li v-for="variant in perfume.presentaciones" :key="variant.ml">
            Botella de {{ variant.ml }} ml · {{ formatColones(variant.precio_venta) }}
          </li>
        </ul>
        <p v-else>Por ahora no hay botellas a la venta.</p>
      </section>

      <section aria-labelledby="decants-title">
        <h3 id="decants-title">Decants disponibles</h3>
        <ul v-if="perfume.decants.length > 0" class="decants">
          <li v-for="decant in perfume.decants" :key="`${decant.botella_id}-${decant.ml}`">
            Decant de {{ decant.ml }} ml · {{ formatColones(decant.precio) }}
          </li>
        </ul>
        <p v-else>Por ahora no hay decants disponibles.</p>
      </section>
    </article>
  </main>
</template>
