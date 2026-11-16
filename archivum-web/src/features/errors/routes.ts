import type { RouteRecordRaw } from 'vue-router'

/**
 * The "Sin permiso" and "No encontrado" views (#127). The views live in
 * src/shared because ProblemNotice also shows them in place of a view whose
 * request answered 403 or 404. The router adds these routes last: the second
 * one catches every path that no other route matches.
 */
export const errorRoutes: RouteRecordRaw[] = [
  {
    path: '/sin-permiso',
    name: 'forbidden',
    component: () => import('@/shared/ForbiddenView.vue'),
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/shared/NotFoundView.vue'),
  },
]
