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

    expect(wrapper.get('.store-header__name').text()).toBe('ArchivumParfums')
    // The name follows the logo, so the logo is not read twice.
    expect(wrapper.get('.store-header__logo').attributes('alt')).toBe('')
    expect(wrapper.get('h1').text()).toBe('Vitrina')
    expect(document.title).toBe('Vitrina · ArchivumParfums')
    expect(wrapper.get('.store-header__place').text()).toContain('Escazú, San Rafael')

    const card = wrapper.get('.perfume-card')
    expect(card.get('h3 a').text()).toBe('Maison Archivum Oud Nocturno')
    expect(card.get('h3 a').attributes('href')).toBe(`/tiendas/${STORE}/perfumes/p-1`)
    expect(plain(card.text())).toContain('Botella de 50 ml · ₡85 000')
    expect(plain(card.text())).toContain('Botella de 100 ml · ₡125 000')
    expect(card.get('abbr').attributes('title')).toBe('mililitros')
    expect(wrapper.get('.results-summary').text()).toBe('Se encontró 1 perfume.')
  })

  it('describes each filter with an example', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: () => json(200, page([oud])),
    })

    const { wrapper } = await open(`/tiendas/${STORE}`)

    expect(wrapper.get('label[for="texto"]').text()).toBe('Marca o nombre')
    expect(wrapper.get('#texto').attributes('aria-describedby')).toBe('pista-texto')
    expect(wrapper.get('#pista-precio_maximo').text()).toContain('Solo números')
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

  it('searches by the maximum price written in its number field', async () => {
    const { requests } = fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: () => json(200, page([oud])),
    })
    const { wrapper, router } = await open(`/tiendas/${STORE}`)

    await wrapper.get('#nota').setValue('vainilla')
    await wrapper.get('#precio_maximo').setValue('150000')
    await wrapper.get('form.filters').trigger('submit')
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({ nota: 'vainilla', precio_maximo: '150000' })
    expect(requests[requests.length - 1]?.url).toContain('precio_maximo=150000')
  })

  it('links the next page keeping the filters', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: () => json(200, page([oud], 1, 3)),
    })

    const { wrapper } = await open(`/tiendas/${STORE}?nota=vainilla`)

    expect(wrapper.get('.pagination').text()).toContain('Página 1 de 3')
    expect(wrapper.get('.pagination a').text()).toBe('Página siguiente')
    expect(wrapper.get('.pagination a').attributes('href')).toBe(`/tiendas/${STORE}?nota=vainilla&pagina=2`)
    expect(wrapper.get('.results-summary').text()).toBe('Se encontró 1 perfume. Página 1 de 3.')
  })

  it('moves the focus to the list after a change of page, because the link used may disappear', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: (request) =>
        new URL(request.url).searchParams.get('pagina') === '2' ? json(200, page([oud], 2, 2)) : json(200, page([oud], 1, 2)),
    })
    const { wrapper, router } = await open(`/tiendas/${STORE}`)

    await wrapper.get('.pagination a').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({ pagina: '2' })
    expect(wrapper.get('.pagination a').text()).toBe('Página anterior')
    expect(document.activeElement?.id).toBe('perfumes-titulo')
  })

  it('says when no perfume matches the search', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: () => json(200, page([])),
    })

    const { wrapper } = await open(`/tiendas/${STORE}?texto=nada`)

    expect(wrapper.get('.results-summary').text()).toContain('No hay perfumes que coincidan con la búsqueda.')
    expect(wrapper.find('.card-list').exists()).toBe(false)
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
