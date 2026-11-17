import type { Role } from './store'

declare module 'vue-router' {
  interface RouteMeta {
    /**
     * Roles that may open the route (#126). A route without it is public. A
     * child route inherits the roles of its parent.
     */
    roles?: readonly Role[]
  }
}

export {}
