import type { RouteRecordRaw } from 'vue-router'

import { defaultBusinessSlug } from '@/shared/config'

/**
 * The public pages of a store, with its slug in the path (#128). None asks for a session. The root opens the store
 * of VITE_DEFAULT_BUSINESS_SLUG.
 */
export const storefrontRoutes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'home',
    redirect: () => ({ name: 'storefront', params: { tienda: defaultBusinessSlug() } }),
  },
  {
    path: '/tiendas/:tienda',
    component: () => import('./StoreLayout.vue'),
    children: [
      { path: '', name: 'storefront', component: () => import('./ShowcaseView.vue'), meta: { title: 'Vitrina' } },
      {
        path: 'perfumes/:perfume',
        name: 'perfume',
        component: () => import('./PerfumeDetailView.vue'),
        // The view replaces it with the name of the perfume once it arrives.
        meta: { title: 'Perfume' },
      },
    ],
  },
]
