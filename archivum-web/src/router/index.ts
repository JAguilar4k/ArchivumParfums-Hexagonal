import { createRouter, createWebHistory } from 'vue-router'

import { routes } from './routes'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  // A new page starts at the top, and back returns to where it was. A link to a part of a page (/ayuda#decant) goes
  // to that part. A change of the query alone (a search, a page of results) keeps the place: the view moves the focus
  // if it needs to.
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    }

    if (to.hash) {
      return { el: to.hash }
    }

    return to.path === from.path ? false : { top: 0 }
  },
})

export default router
