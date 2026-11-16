import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'

import { fakeApi, json, problem } from '@/api/__tests__/fakeApi'
import { open, page, plain, profile, showcasePerfume, STORE } from './fixtures'

afterEach(() => {
  vi.unstubAllGlobals()
  document.body.innerHTML = ''
})

const oud = showcasePerfume('p-1', 'Oud Nocturno', [[50, 85000], [100, 125000]])

describe('showcase', () => {
  it('opens the store of VITE_DEFAULT_BUSINESS_SLUG at the root', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: () => json(200, page([oud])),
    })

    const { router } = await open('/')

    expect(router.currentRoute.value.fullPath).toBe(`/tiendas/${STORE}`)
  })

  it('shows the profile of the store and its perfumes with the prices of the API', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: () => json(200, page([oud])),
    })

    const { wrapper } = await open(`/tiendas/${STORE}`)

    expect(wrapper.get('.store-header h1').text()).toBe('ArchivumParfums')
    expect(wrapper.get('.store-header__logo').attributes('alt')).toBe('Logo de ArchivumParfums')
    expect(wrapper.get('.store-header__place').text()).toContain('Escazú, San Rafael')

    const card = wrapper.get('.perfume-card')
    expect(card.get('h3 a').text()).toBe('Maison Archivum Oud Nocturno')
    expect(card.get('h3 a').attributes('href')).toBe(`/tiendas/${STORE}/perfumes/p-1`)
    expect(plain(card.text())).toContain('50 ml · ₡85 000')
    expect(plain(card.text())).toContain('100 ml · ₡125 000')
  })

  it('sends the filters of the URL to the API and fills the form with them', async () => {
    const { requests } = fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: () => json(200, page([oud])),
    })

    const { wrapper } = await open(`/tiendas/${STORE}?nota=vainilla&momento=noche&precio_maximo=90000`)

    const sent = new URL(requests.find((request) => request.url.includes('/perfumes'))?.url ?? '')
    expect(Object.fromEntries(sent.searchParams)).toEqual({ nota: 'vainilla', momento: 'noche', precio_maximo: '90000' })
    expect((wrapper.get('#nota').element as HTMLInputElement).value).toBe('vainilla')
    expect((wrapper.get('#momento').element as HTMLSelectElement).value).toBe('noche')
    expect((wrapper.get('#precio_maximo').element as HTMLInputElement).value).toBe('90000')
  })

  it('puts a new search in the URL, so it can be shared, and loads it', async () => {
    const { requests } = fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: () => json(200, page([oud])),
    })
    const { wrapper, router } = await open(`/tiendas/${STORE}?pagina=3`)

    await wrapper.get('#texto').setValue('oud')
    await wrapper.get('#clima').setValue('frio')
    await wrapper.get('form.filters').trigger('submit')
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({ texto: 'oud', clima: 'frio' })
    expect(requests[requests.length - 1]?.url).toContain('texto=oud')
    expect(requests[requests.length - 1]?.url).not.toContain('pagina')
  })

  it('links the next page keeping the filters', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: () => json(200, page([oud], 1, 3)),
    })

    const { wrapper } = await open(`/tiendas/${STORE}?nota=vainilla`)

    expect(wrapper.get('.pagination').text()).toContain('Página 1 de 3')
    expect(wrapper.get('.pagination a').attributes('href')).toBe(`/tiendas/${STORE}?nota=vainilla&pagina=2`)
  })

  it('says when no perfume matches the search', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: () => json(200, page([])),
    })

    const { wrapper } = await open(`/tiendas/${STORE}?texto=nada`)

    expect(wrapper.text()).toContain('No hay perfumes que coincidan con la búsqueda.')
  })

  it('marks the filter that the API rejects', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: () =>
        problem(422, 'datos-invalidos', 'Los datos enviados no son válidos.', {
          errores: { precio_maximo: ['El precio máximo debe ser al menos 0.'] },
        }),
    })

    const { wrapper } = await open(`/tiendas/${STORE}?precio_maximo=-5`)

    expect(wrapper.get('#error-precio_maximo').text()).toBe('El precio máximo debe ser al menos 0.')
    expect(wrapper.get('#precio_maximo').attributes('aria-invalid')).toBe('true')
  })

  it('shows "No encontrado" for a store that does not exist', async () => {
    fakeApi({
      'GET /tiendas/no-existe': () => problem(404, 'no-encontrado', 'No existe la tienda solicitada.'),
    })

    const { wrapper } = await open('/tiendas/no-existe')

    expect(wrapper.get('h1').text()).toBe('No encontrado')
    expect(wrapper.find('.showcase').exists()).toBe(false)
  })
})
