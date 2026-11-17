import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'

import { fakeApi, json, problem } from '@/api/__tests__/fakeApi'
import { open, plain, profile, STORE } from '@/features/storefront/__tests__/fixtures'

afterEach(() => {
  vi.unstubAllGlobals()
  document.body.innerHTML = ''
})

function recommendation(origen: 'ia' | 'reglas', razon: string) {
  return {
    data: {
      origen,
      preferencias: { notas: ['oud'], momento: 'noche', clima: null, ocasion: null, presupuesto: null },
      perfumes: [
        {
          id: 'p-1',
          marca: 'Maison Archivum',
          nombre: 'Oud Nocturno',
          razon,
          presentaciones: [{ ml: 50, precio_venta: 85000 }],
        },
      ],
    },
  }
}

async function ask(texto: string) {
  const opened = await open(`/tiendas/${STORE}/recomendaciones`)
  await opened.wrapper.get('#texto-recomendacion').setValue(texto)
  await opened.wrapper.get('.recommender form').trigger('submit')
  await flushPromises()

  return opened
}

describe('recommender', () => {
  it('warns next to the text that it goes to an external AI provider', async () => {
    fakeApi({ [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }) })

    const { wrapper } = await open(`/tiendas/${STORE}/recomendaciones`)

    expect(wrapper.get('h1').text()).toBe('Pedir una recomendación')
    expect(document.title).toBe('Pedir una recomendación · ArchivumParfums')
    expect(wrapper.get('#aviso-ia').text()).toContain('proveedor externo de inteligencia artificial')
    expect(wrapper.get('#pista-recomendacion').text()).toContain('Por ejemplo')
    expect(wrapper.get('#texto-recomendacion').attributes('aria-describedby')).toBe('pista-recomendacion aviso-ia')
  })

  it('shows the perfumes of the AI with their reason as plain text and a link to the detail', async () => {
    const { requests } = fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`POST /tiendas/${STORE}/recomendaciones`]: () =>
        json(200, recommendation('ia', 'Tiene oud y es ideal <img src=x onerror=alert(1)> para la noche.')),
    })

    const { wrapper } = await ask('Algo con oud para la noche')

    expect(await requests[requests.length - 1]?.json()).toEqual({ texto: 'Algo con oud para la noche' })
    expect(wrapper.get('.recommender__origin').text()).toBe('Recomendado con inteligencia artificial.')
    expect(wrapper.get('.results-summary').text()).toBe('Le recomendamos 1 perfume.')
    expect(wrapper.get('.recommendation__reason').text()).toBe('Tiene oud y es ideal <img src=x onerror=alert(1)> para la noche.')
    expect(wrapper.find('.recommendations img').exists()).toBe(false)
    expect(wrapper.get('.recommendations h3 a').attributes('href')).toBe(`/tiendas/${STORE}/perfumes/p-1`)
    expect(plain(wrapper.get('.recommendations').text())).toContain('Botella de 50 ml · ₡85 000')
  })

  it('says when the perfumes come from the rules', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`POST /tiendas/${STORE}/recomendaciones`]: () => json(200, recommendation('reglas', 'Coincide con la nota oud.')),
    })

    const { wrapper } = await ask('Algo con oud')

    expect(wrapper.get('.recommender__origin').text()).toBe(
      'Recomendado con las reglas de la tienda, sin inteligencia artificial.',
    )
  })

  it('asks for the text before sending it and takes the focus to the field', async () => {
    const { requests } = fakeApi({ [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }) })
    const { wrapper } = await open(`/tiendas/${STORE}/recomendaciones`)

    await wrapper.get('.recommender form').trigger('submit')
    await flushPromises()

    const field = wrapper.get('#texto-recomendacion')
    expect(requests.filter((request) => request.method === 'POST')).toHaveLength(0)
    expect(wrapper.get('#error-texto').text()).toBe('Escriba qué le gusta para recomendarle perfumes.')
    expect(field.attributes('aria-invalid')).toBe('true')
    expect(field.attributes('aria-describedby')).toBe('pista-recomendacion aviso-ia error-texto')
    expect(document.activeElement).toBe(field.element)
  })

  it('says when no perfume matches the description', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`POST /tiendas/${STORE}/recomendaciones`]: () =>
        json(200, { data: { ...recommendation('reglas', 'x').data, perfumes: [] } }),
    })

    const { wrapper } = await ask('Algo que no existe')

    expect(wrapper.get('.results-summary').text()).toBe(
      'No encontramos perfumes para esa descripción. Pruebe con otras palabras.',
    )
    expect(wrapper.find('.recommendations').exists()).toBe(false)
  })

  it('shows the wait, keeps the button busy and sends the text only once', async () => {
    let answer: (response: Response) => void = () => {}
    const { requests } = fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`POST /tiendas/${STORE}/recomendaciones`]: () => new Promise<Response>((resolve) => (answer = resolve)),
    })
    const { wrapper } = await open(`/tiendas/${STORE}/recomendaciones`)
    await wrapper.get('#texto-recomendacion').setValue('Algo con oud')

    await wrapper.get('.recommender form').trigger('submit')
    await wrapper.get('.recommender form').trigger('submit')
    await flushPromises()

    // aria-disabled and not disabled: the button keeps the focus while it waits.
    expect(wrapper.get('.recommender button').attributes('aria-disabled')).toBe('true')
    expect(wrapper.get('.recommender .loading-state').text()).toContain('Puede tardar unos segundos')
    expect(wrapper.get('.recommender .loading-state').attributes('role')).toBe('status')
    expect(requests.filter((request) => request.method === 'POST')).toHaveLength(1)

    answer(json(200, recommendation('ia', 'Tiene oud.')))
    await flushPromises()

    expect(wrapper.find('.recommender .loading-state').exists()).toBe(false)
    expect(wrapper.get('.recommender button').attributes('aria-disabled')).toBeUndefined()
  })

  it('says when to try again after a 429', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`POST /tiendas/${STORE}/recomendaciones`]: () =>
        problem(429, 'demasiadas-solicitudes', 'Superó el límite de solicitudes.', {}, { 'Retry-After': '40' }),
    })

    const { wrapper } = await ask('Algo con oud')

    expect(wrapper.get('[role="alert"]').text()).toContain('Intente de nuevo en 40 segundos.')
  })
})
