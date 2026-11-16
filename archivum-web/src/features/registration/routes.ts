import type { RouteRecordRaw } from 'vue-router'

/** "Crear cuenta" (#194): public, and a signed-in account that opens it goes home. */
export const registrationRoutes: RouteRecordRaw[] = [
  {
    path: '/crear-cuenta',
    name: 'register',
    component: () => import('./RegisterView.vue'),
  },
]
