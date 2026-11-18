import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'

import { fakeApi, json, problem } from '@/api/__tests__/fakeApi'
import { open, page, plain, profile, showcasePerfume, STORE } from './fixtures'

afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
  document.body.innerHTML = ''
})

const oud = showcasePerfume('p-1', 'Oud Nocturno', [[50, 85000], [100, 125000]])
const showcasePath = `/tiendas/${STORE}/vitrina`
const openFilters = '.showcase__filter-button'

describe('showcase', () => {
  it('opens the store of VITE_DEFAULT_BUSINESS_SLUG at the root', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: () => json(200, page([oud])),
    })

    const { router, wrapper } = await open('/')

    expect(router.currentRoute.value.fullPath).toBe(`/tiendas/${STORE}`)
    expect(router.currentRoute.value.name).toBe('store-home')
    expect(wrapper.get('h1').text()).toBe('Inicio')
    expect(document.title).toBe('Inicio · ArchivumParfums')
  })

  it('shows the profile of the store and its perfumes with the prices of the API', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: () => json(200, page([oud])),
      [`GET /tiendas/${STORE}/perfumes/p-1`]: () => json(200, {
        data: { ...oud, genero: null, descripcion: null, notas: { salida: [], corazon: [], fondo: [] }, decants: [] },
      }),
    })

    const { wrapper, router } = await open(showcasePath)

    expect(wrapper.get('.app-header__name').text()).toBe('ArchivumParfums')
    // The name follows the logo, so the logo is not read twice.
    expect(wrapper.get('.app-header__logo').attributes('alt')).toBe('')
    expect(wrapper.get('h1').text()).toBe('Vitrina')
    expect(document.title).toBe('Vitrina · ArchivumParfums')
    expect(wrapper.get('.app-footer__place').text()).toContain('Escazú, San Rafael')

    const card = wrapper.get('.perfume-card')
    expect(card.get('h3 a').attributes('aria-label')).toBe('Maison Archivum Oud Nocturno')
    expect(card.get('h3 a').attributes('href')).toBe(`/tiendas/${STORE}/perfumes/p-1`)
    expect(plain(card.get('.perfume-card__size').text())).toContain('Botella de 50 ml')
    expect(plain(card.get('.perfume-card__price').text())).toBe('₡85 000')
    expect(card.get('abbr').attributes('title')).toBe('mililitros')
    expect(wrapper.get('.results-summary').text()).toBe('Mostrando 1–1 de 1 perfumes')

    await router.push(`/tiendas/${STORE}/perfumes/p-1`)
    await flushPromises()
    const larger = wrapper.get('.detail-offer input[value="bottle:p-1-100"]')
    expect(plain(larger.element.parentElement?.textContent ?? '')).toMatch(/Botella de 100 ml\s*₡125 000/)
  })

  it('describes each filter with an example', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: () => json(200, page([oud])),
    })

    const { wrapper } = await open(showcasePath)
    await wrapper.get(openFilters).trigger('click')

    expect(wrapper.get('label[for="texto"]').text()).toBe('Marca, nombre o nota')
    expect(wrapper.get('#texto').attributes('aria-describedby')).toBe('pista-texto')
    expect(wrapper.get('#pista-precio_maximo').text()).toContain('Solo números')
  })

  it('moves focus inside the filter drawer and back to its trigger on Escape', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: () => json(200, page([oud])),
    })
    const { wrapper } = await open(showcasePath)
    const trigger = wrapper.get<HTMLButtonElement>(openFilters)
    trigger.element.focus()

    await trigger.trigger('click')

    const drawer = wrapper.get<HTMLDialogElement>('#showcase-filter-drawer')
    expect(drawer.element.open).toBe(true)
    expect(document.activeElement?.id).toBe('showcase-filter-title')

    await drawer.trigger('cancel')

    expect(drawer.element.open).toBe(false)
    expect(document.activeElement).toBe(trigger.element)
  })

  it('sends the filters of the URL to the API and fills the form with them', async () => {
    const { requests } = fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: () => json(200, page([oud])),
    })

    const { wrapper, router } = await open(`/tiendas/${STORE}?nota=vainilla&momento=noche&precio_maximo=90000`)
    await wrapper.get(openFilters).trigger('click')

    expect(router.currentRoute.value.path).toBe(showcasePath)
    const sent = new URL(requests.find((request) => request.url.includes('nota=vainilla'))?.url ?? '')
    expect(Object.fromEntries(sent.searchParams)).toEqual({ por_pagina: '12', nota: 'vainilla', momento: 'noche', precio_maximo: '90000' })
    expect((wrapper.get('#nota').element as HTMLInputElement).value).toBe('vainilla')
    expect(wrapper.get<HTMLInputElement>('input[name="momento"][value="noche"]').element.checked).toBe(true)
    expect((wrapper.get('#precio_maximo').element as HTMLInputElement).value).toBe('90000')
  })

  it('debounces writing and applies radio changes immediately, sharing combined filters in the URL', async () => {
    const { requests } = fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: () => json(200, page([oud])),
    })
    const { wrapper, router } = await open(`${showcasePath}?pagina=3`)
    await wrapper.get(openFilters).trigger('click')
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })

    await wrapper.get('#texto').setValue('ou')
    await vi.advanceTimersByTimeAsync(225)
    await wrapper.get('#texto').setValue('oud')
    await vi.advanceTimersByTimeAsync(299)
    expect(router.currentRoute.value.query).toEqual({ pagina: '3' })
    expect(requests.some((request) => request.url.includes('texto='))).toBe(false)

    await vi.advanceTimersByTimeAsync(1)
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({ texto: 'oud' })

    await wrapper.get('input[name="clima"][value="frio"]').setValue()
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({ texto: 'oud', clima: 'frio' })
    const latest = new URL(requests[requests.length - 1]?.url ?? '')
    expect(latest.searchParams.get('texto')).toBe('oud')
    expect(latest.searchParams.get('clima')).toBe('frio')
    expect(latest.searchParams.has('pagina')).toBe(false)
  })

  it('searches by the maximum price written in its number field', async () => {
    const { requests } = fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: () => json(200, page([oud])),
    })
    const { wrapper, router } = await open(showcasePath)
    await wrapper.get(openFilters).trigger('click')
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })

    await wrapper.get('#nota').setValue('vainilla')
    await wrapper.get('#precio_maximo').setValue('150000')
    await vi.advanceTimersByTimeAsync(300)
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({ nota: 'vainilla', precio_maximo: '150000' })
    expect(requests[requests.length - 1]?.url).toContain('precio_maximo=150000')
  })

  it('links the next page keeping the filters', async () => {
    const { requests } = fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: () => json(200, page([oud], 1, 3)),
    })

    const { wrapper } = await open(`${showcasePath}?nota=vainilla`)

    expect(wrapper.get('.pagination [aria-current="page"]').text()).toBe('1')
    expect(wrapper.get('.pagination [aria-label="Página 3"]').text()).toBe('3')
    expect(wrapper.get('.pagination a:last-child').text()).toBe('Siguiente')
    expect(wrapper.get('.pagination a:last-child').attributes('href')).toBe(`${showcasePath}?nota=vainilla&pagina=2`)
    expect(wrapper.get('.results-summary').text()).toBe('Mostrando 1–1 de 1 perfumes')
    expect(requests.some((request) => new URL(request.url).searchParams.get('pagina') === '2')).toBe(true)
  })

  it('keeps visible results until the new page size arrives', async () => {
    let finish: (response: Response) => void = () => {}
    const waiting = new Promise<Response>((resolve) => { finish = resolve })
    const other = showcasePerfume('p-2', 'Brisa de Prueba', [[50, 60000]])
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: (request) => new URL(request.url).searchParams.get('por_pagina') === '24'
        ? waiting
        : json(200, page([oud])),
    })
    const { wrapper, router } = await open(`${showcasePath}?pagina=2`)

    await wrapper.get('#showcase-page-size').setValue('24')
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({ por_pagina: '24' })
    expect(wrapper.get('.showcase__results').attributes('aria-busy')).toBe('true')
    expect(wrapper.get('.perfume-card__link').text()).toBe('Oud Nocturno')

    finish(json(200, page([other])))
    await flushPromises()

    expect(wrapper.get('.showcase__results').attributes('aria-busy')).toBe('false')
    expect(wrapper.get('.perfume-card__link').text()).toBe('Brisa de Prueba')
  })

  it('moves the focus to the list after a change of page, because the link used may disappear', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: (request) =>
        new URL(request.url).searchParams.get('pagina') === '2' ? json(200, page([oud], 2, 2)) : json(200, page([oud], 1, 2)),
    })
    const { wrapper, router } = await open(showcasePath)

    await wrapper.get('.pagination a').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.query).toEqual({ pagina: '2' })
    expect(wrapper.get('.pagination a').text()).toBe('Anterior')
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
