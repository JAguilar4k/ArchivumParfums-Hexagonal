import type { Router } from 'vue-router'

import { setUnauthenticatedHandler } from '@/api/client'
import { sessionGuard } from './guards'
import { useSessionStore } from './store'

/**
 * Connects the session with the router (#126). It runs once in main.ts, after
 * Pinia and before the router, so the guard also applies to the first route.
 *
 * - The session of the tab comes back after a reload.
 * - A 401 to a request with a token ends the session and opens the sign-in,
 *   which returns to the route that was open.
 * - Every navigation goes through the guard by role.
 */
export function installSession(router: Router): void {
  const session = useSessionStore()
  session.restore()

  setUnauthenticatedHandler(() => {
    session.clear()

    const current = router.currentRoute.value

    if (current.name !== 'sign-in') {
      void router.push({ name: 'sign-in', query: { redirect: current.fullPath } })
    }
  })

  router.beforeEach((to) => sessionGuard(to, session))
}
