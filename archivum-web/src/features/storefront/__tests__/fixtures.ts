import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'

import { setUnauthenticatedHandler } from '@/api/client'
import type { components } from '@/api/schema'
import App from '@/App.vue'
import { installSession } from '@/features/session/install'
import { STORAGE_KEY, useSessionStore, type Role } from '@/features/session/store'
import { accountOf } from '@/features/session/__tests__/accounts'
import { routes } from '@/router/routes'
import { installPageChanges } from '@/shared/pageChanges'

export const STORE = 'archivumparfums'

enableAutoUnmount(afterEach)

export const profile: components['schemas']['PublicBusiness'] = {
  nombre: 'ArchivumParfums',
  slug: STORE,
  logo_url: 'https://archivum.test/logo.png',
  lugar_cita: 'Escazú, San Rafael, 200 m norte del parque central',
  zona_horaria: 'America/Costa_Rica',
}

export function showcasePerfume(id: string, nombre: string, prices: Array<[number, number]>) {
  return {
    id,
    marca: 'Maison Archivum',
    nombre,
    concentracion: 'Eau de Parfum',
    familia: 'Amaderada',
    foto_url: null,
    etiquetas: { momento: ['noche'], clima: ['frio'], ocasion: ['cena'] },
    presentaciones: prices.map(([ml, precio_venta]) => ({ id: `${id}-${ml}`, ml, precio_venta })),
  }
}

export function page(data: unknown[], currentPage = 1, lastPage = 1) {
  return {
    data,
    links: { first: null, last: null, prev: null, next: null },
    meta: {
      current_page: currentPage,
      from: 1,
      last_page: lastPage,
      links: [],
      path: null,
      per_page: 12,
      to: data.length,
      total: data.length,
    },
  }
}

/**
 * Opens a path of the client with every route, the session guard and the titles and focus of each page, like main.ts
 * does. With a role, the tab already has a session of an account with that role.
 */
export async function open(path: string, signedInAs?: Role) {
  if (signedInAs) {
    const expiresAt = new Date(Date.now() + 60 * 60 * 1000).toISOString()
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ token: '1|prueba', expiresAt, account: accountOf(signedInAs) }))
  }

  const pinia = createPinia()
  setActivePinia(pinia)

  const router = createRouter({ history: createMemoryHistory(), routes })
  installSession(router)
  installPageChanges(router)
  await router.push(path)

  const wrapper = mount(App, { global: { plugins: [pinia, router] }, attachTo: document.body })
  await flushPromises()

  return { wrapper, router }
}

/** The same text without the narrow spaces of the thousands separator, to compare prices. */
export function plain(text: string): string {
  return text.replace(/\s/g, ' ')
}

/** Undoes what `open` and fakeApi left: the session, the storage, fetch and the page. */
export function cleanUp(): void {
  useSessionStore().clear()
  setUnauthenticatedHandler(() => {})
  localStorage.clear()
  sessionStorage.clear()
  vi.unstubAllGlobals()
  document.body.innerHTML = ''
}
