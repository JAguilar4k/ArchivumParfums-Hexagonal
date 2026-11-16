import type { RouteRecordRaw } from 'vue-router'

/**
 * The orders of the customer (#130). They ask for a session with the role `cliente`; the API also checks that each
 * order belongs to the account. `/pagos/procesando` is the address of PAYMENTS_RETURN_URL in the API.
 */
export const orderRoutes: RouteRecordRaw[] = [
  {
    path: '/cuenta/pedidos',
    name: 'orders',
    component: () => import('./OrdersView.vue'),
    meta: { roles: ['cliente'], title: 'Mis pedidos' },
  },
  {
    path: '/cuenta/pedidos/:pedido',
    name: 'order',
    component: () => import('./OrderView.vue'),
    // The view replaces it with the date of the order once it arrives.
    meta: { roles: ['cliente'], title: 'Pedido' },
  },
  {
    path: '/pagos/procesando',
    name: 'payment-processing',
    component: () => import('./PaymentProcessingView.vue'),
    meta: { roles: ['cliente'], title: 'Pago del pedido' },
  },
]
