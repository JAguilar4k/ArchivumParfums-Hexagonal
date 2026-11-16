import { createRouter, createWebHistory } from 'vue-router'

import { errorRoutes } from '@/features/errors/routes'
import { homeRoutes } from '@/features/home/routes'

// Each feature exports its own routes from src/features/<feature>/routes.ts.
// errorRoutes goes last, because it catches every path that nothing else matches.
const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [...homeRoutes, ...errorRoutes],
})

export default router
