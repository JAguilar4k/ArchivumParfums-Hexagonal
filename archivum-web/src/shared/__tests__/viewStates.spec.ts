import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'

import ExampleListView from './ExampleListView.vue'

function page(names: string[]) {
  return {
    data: names.map((nombre, index) => ({ id: String(index), marca: 'Marca', nombre })),
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, last_page: 1, per_page: 15, total: names.length },
  }
}

function answer(...responses: Array<() => Response>) {
  const fetch = vi.fn(async (request: Request) => {
    void request
    const next = responses.shift()

    if (!next) {
      throw new Error('Unexpected request')
    }

    return next()
  })
  vi.stubGlobal('fetch', fetch)

  return fetch
}

function mountView() {
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/', component: { render: () => null } }] })

  return mount(ExampleListView, { global: { plugins: [router] } })
}

const ok = (names: string[]) => () =>
  new Response(JSON.stringify(page(names)), { status: 200, headers: { 'Content-Type': 'application/json' } })

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('the three states of a view', () => {
  it('shows the loading state while the data has not arrived, then the data', async () => {
    let release: () => void = () => {}
    const fetch = vi.fn(
      (request: Request) =>
        new Promise<Response>((resolve) => {
          void request
          release = () => resolve(ok(['Oud Nocturno'])())
        }),
    )
    vi.stubGlobal('fetch', fetch)

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.get('[role="status"]').text()).toBe('Cargando…')

    release()
    await flushPromises()

    expect(wrapper.find('[role="status"]').exists()).toBe(false)
    expect(wrapper.get('li').text()).toBe('Oud Nocturno')
  })

  it('shows the empty state when there is nothing to list', async () => {
    answer(ok([]))

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.text()).toContain('La tienda todavía no publica perfumes.')
    expect(wrapper.find('li').exists()).toBe(false)
  })

  it('shows the error state through the interpreter and loads again on retry', async () => {
    answer(
      () => new Response('<html>Bad Gateway</html>', { status: 502, headers: { 'Content-Type': 'text/html' } }),
      ok(['Oud Nocturno']),
    )

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Ocurrió un error inesperado.')
    expect(wrapper.text()).not.toContain('Bad Gateway')

    await wrapper.get('button').trigger('click')
    await flushPromises()

    expect(wrapper.get('li').text()).toBe('Oud Nocturno')
  })

  it('shows the "No encontrado" view without a retry when the store does not exist', async () => {
    answer(
      () =>
        new Response(
          JSON.stringify({
            type: 'https://archivum.test/problemas/no-encontrado',
            title: 'Recurso no encontrado',
            status: 404,
            detail: 'No existe la tienda solicitada.',
            instance: 'urn:uuid:0193b0d4-0000-7000-8000-000000000040',
          }),
          { status: 404, headers: { 'Content-Type': 'application/problem+json' } },
        ),
    )

    const wrapper = mountView()
    await flushPromises()

    expect(wrapper.get('h1').text()).toBe('No encontrado')
    expect(wrapper.find('button').exists()).toBe(false)
  })
})
