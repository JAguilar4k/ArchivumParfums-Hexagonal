import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'

import { fakeApi, json, problem } from '@/api/__tests__/fakeApi'
import { ORDER_ID, orderOf } from '@/features/orders/__tests__/fixtures'
import { cleanUp, open, plain, profile, STORE } from '@/features/storefront/__tests__/fixtures'
import { CART_STORAGE_PREFIX, type CartLine } from '../store'

afterEach(cleanUp)

const detail = {
  id: 'p-1',
  marca: 'Maison Archivum',
  nombre: 'Oud Nocturno',
  concentracion: 'Eau de Parfum',
  genero: 'Unisex',
  familia: 'Amaderada',
  descripcion: null,
  foto_url: null,
  notas: { salida: [], corazon: [], fondo: [] },
  etiquetas: { momento: [], clima: [], ocasion: [] },
  presentaciones: [{ id: 'p-1-100', ml: 100, precio_venta: 125000 }],
  decants: [{ botella_id: 'b-1', presentacion_ml: 100, ml: 5, precio: 9500 }],
}

const inCart: CartLine[] = [
  { kind: 'bottle', perfumeId: 'p-1', perfume: 'Maison Archivum Oud Nocturno', presentationId: 'p-1-100', ml: 100, quantity: 1, price: 125000 },
  { kind: 'decant', perfumeId: 'p-1', perfume: 'Maison Archivum Oud Nocturno', bottleId: 'b-1', ml: 5, price: 9500 },
]

function withCart(lines: CartLine[]): void {
  localStorage.setItem(CART_STORAGE_PREFIX + STORE, JSON.stringify(lines))
}

describe('cart', () => {
  it('takes a bottle and a decant from the detail, and creates the order with identities and quantities only', async () => {
    const { requests } = fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes/p-1`]: () => json(200, { data: detail }),
      [`POST /tiendas/${STORE}/pedidos`]: () => json(201, { data: orderOf() }),
      [`GET /cuenta/pedidos/${ORDER_ID}`]: () => json(200, { data: orderOf() }),
    })
    const { wrapper, router } = await open(`/tiendas/${STORE}/perfumes/p-1`, 'cliente')

    await wrapper.get('[aria-label="Agregar al carrito la botella de 100 mililitros"]').trigger('click')
    await wrapper.get('[aria-label="Cerrar el carrito"]').trigger('click')
    await wrapper.get('.detail-offer input[value="decant:100:5"]').setValue()
    await wrapper.get('[aria-label="Agregar al carrito el decant de 5 mililitros"]').trigger('click')

    expect(plain(wrapper.get('.cart-drawer [role="status"]').text())).toBe('Se agregó Oud Nocturno, decant de 5 mililitros.')
    expect(wrapper.find('[aria-label="Carrito, 2 productos"]').exists()).toBe(true)

    await router.push(`/tiendas/${STORE}/carrito`)
    await flushPromises()

    expect(wrapper.findAll('.cart-line__text').map((line) => plain(line.text()))).toEqual([
      'Maison Archivum Oud Nocturno · botella de 100 ml',
      'Maison Archivum Oud Nocturno · decant de 5 ml',
    ])

    await wrapper.get('.cart > .form-actions > button').trigger('click')
    await flushPromises()

    const sent = requests.find((request) => request.method === 'POST')
    expect(await sent?.json()).toEqual({
      lineas: [
        { presentacion_id: 'p-1-100', cantidad: 1 },
        { botella_id: 'b-1', ml: 5 },
      ],
    })
    // The order view loads lazily, like every view.
    await vi.waitFor(() => expect(wrapper.find('.order-summary').exists()).toBe(true))
    expect(router.currentRoute.value.name).toBe('order')
    expect(localStorage.getItem(CART_STORAGE_PREFIX + STORE)).toBeNull()

    const text = plain(wrapper.get('.order-summary').text())
    expect(text).toContain('EstadoPendiente de pago')
    expect(text).toContain('Subtotal₡134 500')
    expect(text).toContain('Por cobrar ahora₡72 000')
    expect(text).toContain('Saldo₡134 500')
    // The time ends with "a. m." or "p. m.", so the sentence goes on after a comma and never shows two periods.
    expect(plain(wrapper.get('.order-summary__deadline').text())).toMatch(
      /^Si no se paga antes del .+ [ap]\. m\., el pedido vence y sus decants vuelven/,
    )
  })

  it('keeps the cart after a reload and asks a visitor to sign in before creating the order', async () => {
    withCart(inCart)
    fakeApi({ [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }) })

    const { wrapper } = await open(`/tiendas/${STORE}/carrito`)

    expect(wrapper.findAll('.cart-line')).toHaveLength(2)
    expect(wrapper.find('.cart > .form-actions > button').exists()).toBe(false)
    expect(wrapper.get('.cart a[href^="/iniciar-sesion"]').attributes('href')).toBe(
      `/iniciar-sesion?redirect=/tiendas/${STORE}/carrito`,
    )
  })

  it('announces a removed line and takes the focus to the title, because its button disappears', async () => {
    withCart(inCart)
    fakeApi({ [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }) })
    const { wrapper } = await open(`/tiendas/${STORE}/carrito`, 'cliente')

    const remove = wrapper.findAll('.cart-line button')[1]
    expect(remove?.attributes('aria-label')).toBe('Quitar Maison Archivum Oud Nocturno, decant de 5 mililitros')

    await remove?.trigger('click')
    await flushPromises()

    expect(wrapper.findAll('.cart-line')).toHaveLength(1)
    expect(wrapper.get('.cart .results-summary').text()).toBe(
      'Se quitó del carrito: Maison Archivum Oud Nocturno, decant de 5 mililitros.',
    )
    expect(document.activeElement).toBe(wrapper.get('.cart h1').element)
  })

  it('marks the decant whose bottle ran out of ml when the API answers 409', async () => {
    withCart(inCart)
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`POST /tiendas/${STORE}/pedidos`]: () =>
        problem(409, 'conflicto', 'La botella no tiene suficientes ml disponibles para esa operación o está vacía.'),
      // Someone else took the last ml: the showcase no longer offers that decant.
      [`GET /tiendas/${STORE}/perfumes/p-1`]: () => json(200, { data: { ...detail, decants: [] } }),
    })
    const { wrapper, router } = await open(`/tiendas/${STORE}/carrito`, 'cliente')

    await wrapper.get('.cart > .form-actions > button').trigger('click')
    await flushPromises()

    const [bottleLine, decantLine] = wrapper.findAll('.cart-line')
    expect(router.currentRoute.value.name).toBe('cart')
    expect(wrapper.get('.problem-notice').text()).toContain('La botella no tiene suficientes ml disponibles')
    expect(bottleLine?.find('.cart-line__error').exists()).toBe(false)
    expect(decantLine?.get('.cart-line__error').text()).toBe('Ya no quedan ml suficientes en esta botella para este decant.')
    expect(wrapper.get('.cart > .form-actions > button').attributes('aria-disabled')).toBeUndefined()
  })

  it('marks each line that a 422 names by its position', async () => {
    withCart(inCart)
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`POST /tiendas/${STORE}/pedidos`]: () =>
        problem(422, 'datos-invalidos', 'Los datos enviados no son válidos.', {
          errores: { 'lineas.0.presentacion_id': ['Ese producto no está disponible para pedirlo.'] },
        }),
    })
    const { wrapper } = await open(`/tiendas/${STORE}/carrito`, 'cliente')

    await wrapper.get('.cart > .form-actions > button').trigger('click')
    await flushPromises()

    const [bottleLine, decantLine] = wrapper.findAll('.cart-line')
    expect(bottleLine?.get('.cart-line__error').text()).toBe('Ese producto no está disponible para pedirlo.')
    expect(bottleLine?.get('input').attributes('aria-describedby')).toBe('linea-0 error-linea-0')
    expect(decantLine?.find('.cart-line__error').exists()).toBe(false)
  })

  it('marks a quantity below 1 and sends no order until the customer fixes it', async () => {
    withCart(inCart)
    const { requests } = fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`POST /tiendas/${STORE}/pedidos`]: () => json(201, { data: orderOf() }),
      [`GET /cuenta/pedidos/${ORDER_ID}`]: () => json(200, { data: orderOf() }),
    })
    const { wrapper } = await open(`/tiendas/${STORE}/carrito`, 'cliente')
    const quantity = wrapper.get('#cantidad-0')

    await quantity.setValue('0')

    // What the customer wrote stays in the field, marked, instead of an order of 1 that the page does not show.
    expect((quantity.element as HTMLInputElement).value).toBe('0')
    expect(quantity.attributes('aria-invalid')).toBe('true')
    expect(quantity.attributes('aria-describedby')).toBe('linea-0 error-linea-0')
    expect(wrapper.get('#error-linea-0').text()).toBe(
      'Escriba una cantidad de 1 botella o más. Para no pedir esta botella, use «Quitar».',
    )

    await wrapper.get('.cart > .form-actions > button').trigger('click')
    await flushPromises()

    expect(requests.some((request) => request.method === 'POST')).toBe(false)
    expect(document.activeElement).toBe(quantity.element)

    await quantity.setValue('2')

    expect(quantity.attributes('aria-invalid')).toBeUndefined()
    expect(wrapper.find('#error-linea-0').exists()).toBe(false)

    await wrapper.get('.cart > .form-actions > button').trigger('click')
    await flushPromises()

    const sent = requests.find((request) => request.method === 'POST')
    expect(await sent?.json()).toEqual({
      lineas: [
        { presentacion_id: 'p-1-100', cantidad: 2 },
        { botella_id: 'b-1', ml: 5 },
      ],
    })
  })

  it('says in the notice what a 422 says of the whole list, such as too many lines', async () => {
    withCart(inCart)
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`POST /tiendas/${STORE}/pedidos`]: () =>
        problem(422, 'datos-invalidos', 'Los datos enviados no son válidos.', {
          errores: { lineas: ['El campo líneas del pedido no debe tener más de 20 elementos.'] },
        }),
    })
    const { wrapper } = await open(`/tiendas/${STORE}/carrito`, 'cliente')

    await wrapper.get('.cart > .form-actions > button').trigger('click')
    await flushPromises()

    // Inside the alert, so it is announced with the message of the notice.
    const notice = wrapper.get('.cart .problem-notice')
    expect(notice.attributes('role')).toBe('alert')
    expect(notice.get('.problem-notice__message').text()).toBe('Los datos enviados no son válidos.')
    expect(notice.get('.cart__error').text()).toBe('El campo líneas del pedido no debe tener más de 20 elementos.')
    expect(wrapper.find('.cart-line__error').exists()).toBe(false)
  })
})
