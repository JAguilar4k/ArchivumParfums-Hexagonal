import type { RouteRecordRaw } from 'vue-router'

import { bookingRoute } from '@/features/appointments/routes'
import { cartRoute } from '@/features/cart/routes'
import { recommendationRoute } from '@/features/recommendations/routes'
import { defaultBusinessSlug } from '@/shared/config'
import { importPerfume, importShowcase } from './storeViews'

const legacyFilters = ['texto', 'familia', 'nota', 'momento', 'clima', 'ocasion', 'precio_maximo', 'pagina', 'por_pagina']

/**
 * The public pages of a store, with its slug in the path (#128). None asks for a session. The root opens the store
 * of VITE_DEFAULT_BUSINESS_SLUG.
 */
export const storefrontRoutes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'home',
    redirect: (to) => ({ name: 'store-home', params: { tienda: defaultBusinessSlug() }, query: to.query, hash: to.hash }),
  },
  {
    path: '/tiendas/:tienda',
    component: () => import('./StoreLayout.vue'),
    children: [
      {
        path: '',
        name: 'store-home',
        component: () => import('./StoreHomeView.vue'),
        meta: { title: 'Una fragancia también guarda memoria' },
        beforeEnter: (to) => {
          if (legacyFilters.some((field) => field in to.query)) {
            return { name: 'storefront', params: to.params, query: to.query, hash: to.hash, replace: true }
          }
        },
      },
      { path: 'vitrina', name: 'storefront', component: importShowcase, meta: { title: 'Vitrina' } },
      {
        path: 'perfumes/:perfume',
        name: 'perfume',
        component: importPerfume,
        // The view replaces it with the name of the perfume once it arrives.
        meta: { title: 'Perfume' },
      },
      recommendationRoute,
      cartRoute,
      bookingRoute,
    ],
  },
]
