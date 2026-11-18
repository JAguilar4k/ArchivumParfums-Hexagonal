import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'

import { fakeApi, json } from '@/api/__tests__/fakeApi'
import { open, profile, STORE } from '@/features/storefront/__tests__/fixtures'

afterEach(() => {
  vi.unstubAllGlobals()
  document.body.innerHTML = ''
})

describe('help and glossary', () => {
  it('explains the words of perfumery that the pages use', async () => {
    fakeApi({ [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }) })

    const { wrapper } = await open('/ayuda')

    expect(wrapper.get('h1').text()).toBe('Ayuda y glosario')
    expect(document.title).toBe('Ayuda y glosario · ArchivumParfums')

    const terms = wrapper.findAll('.glossary dt').map((term) => term.attributes('id'))
    expect(terms).toEqual(
      expect.arrayContaining(['concentracion', 'decant', 'familia-olfativa', 'notas', 'ml', 'inteligencia-artificial']),
    )
    expect(wrapper.get('#ml abbr').attributes('title')).toBe('mililitros')
  })

  it('explains the orders, the payments and the states of an order', async () => {
    fakeApi({})

    const { wrapper } = await open('/ayuda')
    const terms = wrapper.findAll('.glossary dt').map((term) => term.attributes('id'))

    expect(wrapper.get('#ayuda-pedidos').text()).toBe('Pedidos y pagos')
    expect(terms).toEqual(expect.arrayContaining(['bajo-pedido', 'pasarela-de-pago', 'por-cobrar-ahora', 'saldo']))
    expect(wrapper.get('#ayuda-pedidos').element.parentElement?.textContent).toContain('Vencido sin pago')
  })

  it('explains the account of a customer and links to its creation', async () => {
    fakeApi({})

    const { wrapper } = await open('/ayuda')
    const section = wrapper.get('[aria-labelledby="ayuda-cuenta"]')

    expect(wrapper.get('#ayuda-cuenta').text()).toBe('Su cuenta')
    expect(section.get('a').attributes('href')).toBe('/crear-cuenta')
    // The password policy is the API's (#194): the page says where the form tells what is missing, never a rule.
    expect(section.text()).toContain('el formulario indica junto al campo qué le falta')
    expect(section.text()).not.toContain('caracteres')
  })

  it('opens from the footer of a store and goes to the term that a page links', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: () => json(200, { data: [], links: {}, meta: { current_page: 1, last_page: 1, total: 0 } }),
    })
    const { wrapper, router } = await open(`/tiendas/${STORE}`)

    await wrapper.get('footer a[href="/ayuda"]').trigger('click')
    await flushPromises()
    expect(router.currentRoute.value.name).toBe('help')

    await router.push('/ayuda#decant')
    await flushPromises()
    expect(document.activeElement?.id).toBe('decant')
  })
})
