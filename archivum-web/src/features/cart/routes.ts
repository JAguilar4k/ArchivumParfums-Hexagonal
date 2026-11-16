import type { RouteRecordRaw } from 'vue-router'

/** The cart of a store, under the same header as its showcase (#130). Creating the order asks for a session. */
export const cartRoutes: RouteRecordRaw[] = [
  {
    path: '/tiendas/:tienda/carrito',
    component: () => import('@/features/storefront/StoreLayout.vue'),
    children: [{ path: '', name: 'cart', component: () => import('./CartView.vue'), meta: { title: 'Carrito' } }],
  },
]
