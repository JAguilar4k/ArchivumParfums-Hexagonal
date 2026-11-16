import type { RouteRecordRaw } from 'vue-router'

/** The recommender of a store, under the same header as its showcase (#128). It asks for no session. */
export const recommendationRoutes: RouteRecordRaw[] = [
  {
    path: '/tiendas/:tienda/recomendaciones',
    component: () => import('@/features/storefront/StoreLayout.vue'),
    children: [
      {
        path: '',
        name: 'recommendations',
        component: () => import('./RecommenderView.vue'),
        meta: { title: 'Pedir una recomendación' },
      },
    ],
  },
]
