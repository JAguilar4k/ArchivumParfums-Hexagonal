import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import App from '@/App.vue'
import { fakeApi } from '@/api/__tests__/fakeApi'
import { routes } from '@/router/routes'

const home = { path: '/', name: 'home', component: { render: () => null } }

async function visit(path: string) {
  fakeApi({})
  const router = createRouter({ history: createMemoryHistory(), routes: [home, ...routes.filter((route) => route.name !== 'home')] })
  await router.push(path)

  const wrapper = mount(App, { global: { plugins: [createPinia(), router] } })
  await flushPromises()

  return { wrapper, router }
}

afterEach(() => vi.unstubAllGlobals())

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

  it('leave the other routes alone', async () => {
    const { router } = await visit('/')

    expect(router.currentRoute.value.name).toBe('home')
  })
})
