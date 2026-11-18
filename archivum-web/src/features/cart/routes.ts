import type { RouteRecordRaw } from 'vue-router'

/** The cart of a store, under the same header as its showcase (#130). Creating the order asks for a session. */
export const cartRoute: RouteRecordRaw = {
  path: 'carrito',
  name: 'cart',
  component: () => import('./CartView.vue'),
  meta: { title: 'Carrito' },
}
