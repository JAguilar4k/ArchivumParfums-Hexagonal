import { describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import App from '@/App.vue'
import { homeRoutes } from '@/features/home/routes'
import { sessionRoutes } from '@/features/session/routes'
import { errorRoutes } from '../routes'

async function visit(path: string) {
  const router = createRouter({ history: createMemoryHistory(), routes: [...homeRoutes, ...sessionRoutes, ...errorRoutes] })
  await router.push(path)

  const wrapper = mount(App, { global: { plugins: [createPinia(), router] } })
  await flushPromises()

  return { wrapper, router }
}

describe('error routes', () => {
  it('show the "Sin permiso" view at /sin-permiso', async () => {
    const { wrapper } = await visit('/sin-permiso')

    expect(wrapper.get('h1').text()).toBe('Sin permiso')
  })

  it('show the "No encontrado" view for a path that no route matches, keeping the address', async () => {
    const { wrapper, router } = await visit('/no/existe')

    expect(wrapper.get('h1').text()).toBe('No encontrado')
    expect(router.currentRoute.value.fullPath).toBe('/no/existe')
  })

  it('leave the home view at /', async () => {
    const { wrapper } = await visit('/')

    expect(wrapper.get('h1').text()).toBe('ArchivumParfums')
  })
})
