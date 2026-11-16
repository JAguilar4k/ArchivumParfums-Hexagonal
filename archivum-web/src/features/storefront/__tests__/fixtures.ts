import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import type { components } from '@/api/schema'
import App from '@/App.vue'
import { errorRoutes } from '@/features/errors/routes'
import { helpRoutes } from '@/features/help/routes'
import { recommendationRoutes } from '@/features/recommendations/routes'
import { sessionRoutes } from '@/features/session/routes'
import { installPageChanges } from '@/shared/pageChanges'
import { storefrontRoutes } from '../routes'

export const STORE = 'archivumparfums'

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
    presentaciones: prices.map(([ml, precio_venta]) => ({ ml, precio_venta })),
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

/** Opens a path of the client with every public route and the titles and focus of each page, like main.ts does. */
export async function open(path: string) {
  const pinia = createPinia()
  setActivePinia(pinia)

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [...storefrontRoutes, ...recommendationRoutes, ...sessionRoutes, ...helpRoutes, ...errorRoutes],
  })
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
