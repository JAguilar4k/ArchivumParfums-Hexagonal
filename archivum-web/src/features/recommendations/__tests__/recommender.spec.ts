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

    expect(wrapper.get('#aviso-ia').text()).toContain('proveedor externo de inteligencia artificial')
    expect(wrapper.get('#texto-recomendacion').attributes('aria-describedby')).toBe('aviso-ia')
  })

  it('shows the perfumes of the AI with their reason as plain text and a link to the detail', async () => {
    const { requests } = fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`POST /tiendas/${STORE}/recomendaciones`]: () =>
        json(200, recommendation('ia', 'Tiene oud y es ideal <img src=x onerror=alert(1)> para la noche.')),
    })

    const { wrapper } = await ask('Algo con oud para la noche')

    expect(await requests[requests.length - 1]?.json()).toEqual({ texto: 'Algo con oud para la noche' })
    expect(wrapper.get('.recommender__origin').text()).toBe('Recomendado con IA')
    expect(wrapper.get('.recommendation__reason').text()).toBe('Tiene oud y es ideal <img src=x onerror=alert(1)> para la noche.')
    expect(wrapper.find('.recommendations img').exists()).toBe(false)
    expect(wrapper.get('.recommendations h4 a').attributes('href')).toBe(`/tiendas/${STORE}/perfumes/p-1`)
    expect(plain(wrapper.get('.recommendations').text())).toContain('50 ml · ₡85 000')
  })

  it('says when the perfumes come from the rules', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`POST /tiendas/${STORE}/recomendaciones`]: () => json(200, recommendation('reglas', 'Coincide con la nota oud.')),
    })

    const { wrapper } = await ask('Algo con oud')

    expect(wrapper.get('.recommender__origin').text()).toBe('Recomendado por reglas')
  })

  it('shows the wait, keeps the button disabled and sends the text only once', async () => {
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

    expect(wrapper.get('.recommender button').attributes('disabled')).toBeDefined()
    expect(wrapper.get('[role="status"]').text()).toContain('Puede tardar unos segundos')
    expect(requests.filter((request) => request.method === 'POST')).toHaveLength(1)

    answer(json(200, recommendation('ia', 'Tiene oud.')))
    await flushPromises()

    expect(wrapper.find('[role="status"]').exists()).toBe(false)
    expect(wrapper.get('.recommender button').attributes('disabled')).toBeUndefined()
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
