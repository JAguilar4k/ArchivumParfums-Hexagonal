import { describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import App from '@/App.vue'
import { sessionRoutes } from '@/features/session/routes'
import { homeRoutes } from '../routes'

describe('home routes', () => {
  it('render the home view at /', async () => {
    const router = createRouter({ history: createMemoryHistory(), routes: [...homeRoutes, ...sessionRoutes] })
    await router.push('/')

    const wrapper = mount(App, { global: { plugins: [createPinia(), router] } })
    await flushPromises()

    expect(wrapper.get('h1').text()).toBe('ArchivumParfums')
  })
})
