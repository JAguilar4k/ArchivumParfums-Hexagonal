import type { RouteRecordRaw } from 'vue-router'

export const sessionRoutes: RouteRecordRaw[] = [
  {
    path: '/iniciar-sesion',
    name: 'sign-in',
    component: () => import('./SignInView.vue'),
    meta: { title: 'Iniciar sesión' },
  },
]
