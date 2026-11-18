import type { RouteRecordRaw } from 'vue-router'

/** The recommender of a store, under the same header as its showcase (#128). It asks for no session. */
export const recommendationRoute: RouteRecordRaw = {
  path: 'recomendaciones',
  name: 'recommendations',
  component: () => import('./RecommenderView.vue'),
  meta: { title: 'Pedir una recomendación' },
}
