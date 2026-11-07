import { createRouter, createWebHistory } from 'vue-router'

import { homeRoutes } from '@/features/home/routes'

// Each feature exports its own routes from src/features/<feature>/routes.ts.
const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [...homeRoutes],
})

export default router
