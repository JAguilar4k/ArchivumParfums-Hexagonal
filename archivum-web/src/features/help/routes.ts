import type { RouteRecordRaw } from 'vue-router'

/**
 * The help of the client (#128): how to use each page and what the words of perfumery mean (WCAG 3.1.3, 3.1.4 and
 * 3.3.5). Every page links to it from its footer. It asks for no session.
 */
export const helpRoutes: RouteRecordRaw[] = [
  { path: '/ayuda', name: 'help', component: () => import('./HelpView.vue'), meta: { title: 'Ayuda y glosario' } },
]
