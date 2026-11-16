import { createRouter, createWebHistory } from 'vue-router'

import { errorRoutes } from '@/features/errors/routes'
import { homeRoutes } from '@/features/home/routes'
import { sessionRoutes } from '@/features/session/routes'

// Each feature exports its own routes from src/features/<feature>/routes.ts.
// errorRoutes goes last, because it catches every path that nothing else matches.
const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [...homeRoutes, ...sessionRoutes, ...errorRoutes],
  // A new page starts at the top, and back returns to where it was. A change of the query alone (a search, a page
  // of results) keeps the place: the view moves the focus if it needs to.
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    }

    return to.path === from.path ? false : { top: 0 }
  },
})

export default router
