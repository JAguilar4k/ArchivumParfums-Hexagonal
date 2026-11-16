import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router'

import type { Role } from './store'
import './meta'

/** What the guard needs to know about the session. */
export interface SessionState {
  isSignedIn: boolean
  role: Role | null
}

/**
 * Decides where a navigation goes (#126): a visitor who opens a route with
 * roles goes to the sign-in, and comes back to it after signing in; a signed-in
 * account whose role is not listed sees "Sin permiso". The guards only help the
 * navigation: the API decides the permissions (#102, #104).
 */
export function sessionGuard(to: RouteLocationNormalized, session: SessionState): true | RouteLocationRaw {
  if (to.name === 'sign-in' && session.isSignedIn) {
    return { name: 'home' }
  }

  const roles = to.meta.roles

  if (!roles) {
    return true
  }

  if (!session.isSignedIn) {
    return { name: 'sign-in', query: { redirect: to.fullPath } }
  }

  if (!session.role || !roles.includes(session.role)) {
    return { name: 'forbidden' }
  }

  return true
}

/**
 * The route to return to after signing in: only a path of this application,
 * never another site (`//otro.sitio` or `https://...`).
 */
export function redirectTarget(redirect: unknown): string {
  return typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//')
    ? redirect
    : '/'
}
