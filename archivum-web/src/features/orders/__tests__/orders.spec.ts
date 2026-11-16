import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'

import { fakeApi, json, problem } from '@/api/__tests__/fakeApi'
import { cleanUp, open, page, plain } from '@/features/storefront/__tests__/fixtures'
import { leaveTo } from '@/shared/navigation'
import { PENDING_PAYMENT_KEY } from '../pendingPayment'
import { ORDER_ID, orderOf, paymentOf } from './fixtures'

vi.mock('@/shared/navigation', () => ({ leaveTo: vi.fn(() => true) }))

afterEach(() => {
  cleanUp()
  vi.mocked(leaveTo).mockClear()
})

const ORDER_PATH = `/cuenta/pedidos/${ORDER_ID}`

describe('an order', () => {
  it('shows an order with nothing to charge now as confirmed, without going to the gateway', async () => {
    fakeApi({
      [`GET ${ORDER_PATH}`]: () =>
        json(200, { data: orderOf({ estado: 'confirmado', subtotal: 9500, por_cobrar_ahora: 0, saldo: 9500 }) }),
    })

    const { wrapper } = await open(ORDER_PATH, 'cliente')

    expect(wrapper.get('.order-summary__status').text()).toBe('Estado: Confirmado')
    expect(wrapper.get('.order-view__free').text()).toBe('No hay nada que cobrar ahora: el pedido quedó confirmado.')
    expect(wrapper.find('.pay-order').exists()).toBe(false)
  })

  it('sends one payment request for a double click on "Pagar" and goes to the checkout', async () => {
    let answer: (response: Response) => void = () => {}
    const { requests } = fakeApi({
      [`GET ${ORDER_PATH}`]: () => json(200, { data: orderOf() }),
      [`POST ${ORDER_PATH}/pagos`]: () => new Promise<Response>((resolve) => (answer = resolve)),
    })
    const { wrapper } = await open(ORDER_PATH, 'cliente')
    const button = wrapper.get('.pay-order > button')

    expect(plain(button.text())).toBe('Pagar ₡72 000')

    await button.trigger('click')
    await button.trigger('click')
    await flushPromises()

    expect(requests.filter((request) => request.method === 'POST')).toHaveLength(1)
    expect(button.attributes('disabled')).toBeDefined()

    answer(json(201, { data: paymentOf() }))
    await flushPromises()

    expect(leaveTo).toHaveBeenCalledExactlyOnceWith('https://checkout.onvopay.com/pay/cs_test_123')
    expect(JSON.parse(sessionStorage.getItem(PENDING_PAYMENT_KEY) ?? 'null')).toEqual({ orderId: ORDER_ID, paidBefore: 0 })
  })

  it('shows the amount in dollars of PayPal before going to the checkout', async () => {
    fakeApi({
      [`GET ${ORDER_PATH}`]: () => json(200, { data: orderOf() }),
      [`POST ${ORDER_PATH}/pagos`]: () =>
        json(201, {
          data: paymentOf({
            pasarela: 'paypal',
            moneda_cobrada: 'USD',
            monto_cobrado: 14118,
            url_pago: 'https://www.sandbox.paypal.com/checkoutnow?token=5O190127TN364715T',
          }),
        }),
    })
    const { wrapper } = await open(ORDER_PATH, 'cliente')

    await wrapper.get('.pay-order > button').trigger('click')
    await flushPromises()

    expect(plain(wrapper.get('.pay-order__foreign').text())).toContain('se le cobrarán USD 141,18 por ₡72 000')
    expect(leaveTo).not.toHaveBeenCalled()

    await wrapper.get('.pay-order__foreign button').trigger('click')

    expect(leaveTo).toHaveBeenCalledWith('https://www.sandbox.paypal.com/checkoutnow?token=5O190127TN364715T')
  })

  it('shows why the API refuses the payment and lets the customer try again', async () => {
    fakeApi({
      [`GET ${ORDER_PATH}`]: () => json(200, { data: orderOf() }),
      [`POST ${ORDER_PATH}/pagos`]: () => problem(409, 'conflicto', 'El pedido no admite ese pago.'),
    })
    const { wrapper } = await open(ORDER_PATH, 'cliente')

    await wrapper.get('.pay-order > button').trigger('click')
    await flushPromises()

    expect(wrapper.get('.pay-order .problem-notice').text()).toBe('El pedido no admite ese pago.')
    expect(wrapper.get('.pay-order > button').attributes('disabled')).toBeUndefined()
    expect(leaveTo).not.toHaveBeenCalled()
  })

  it('shows "No encontrado" for an order of another account', async () => {
    fakeApi({ [`GET ${ORDER_PATH}`]: () => problem(404, 'no-encontrado', 'Ese pedido o esa línea no existe.') })

    const { wrapper } = await open(ORDER_PATH, 'cliente')

    expect(wrapper.get('.order-view h1').text()).toBe('No encontrado')
  })
})

describe('"Mis pedidos"', () => {
  it('lists each order with its status in Spanish, what is paid and the balance, and offers to pay the balance', async () => {
    const ready = orderOf({ id: 'o-2', estado: 'listo', pagado: 72000, saldo: 62500 })
    const delivered = orderOf({ id: 'o-3', estado: 'entregado', pagado: 134500, saldo: 0 })
    fakeApi({ 'GET /cuenta/pedidos': () => json(200, page([ready, delivered])) })

    const { wrapper } = await open('/cuenta/pedidos', 'cliente')
    const [first, second] = wrapper.findAll('.orders__item')

    expect(plain(first?.text() ?? '')).toContain('Listo para entregar · Pagado ₡72 000 · Saldo ₡62 500')
    expect(plain(first?.get('.pay-order > button').text() ?? '')).toBe('Pagar ₡62 500')
    expect(first?.find('.cancel-order').exists()).toBe(false)
    expect(plain(second?.text() ?? '')).toContain('Entregado · Pagado ₡134 500 · Saldo ₡0')
    expect(second?.find('.pay-order').exists()).toBe(false)
  })

  it('cancels an order after the customer confirms, and reads the list again', async () => {
    let cancelled = false
    const { requests } = fakeApi({
      'GET /cuenta/pedidos': () =>
        json(200, page([orderOf(cancelled ? { estado: 'cancelado', cancelado_en: '2026-11-15T18:05:00-06:00' } : {})])),
      [`POST ${ORDER_PATH}/cancelacion`]: () => {
        cancelled = true

        return json(200, { data: orderOf({ estado: 'cancelado' }) })
      },
    })
    const { wrapper } = await open('/cuenta/pedidos', 'cliente')

    await wrapper.get('.cancel-order button').trigger('click')
    expect(wrapper.get('.cancel-order p').text()).toBe('¿Seguro que quiere cancelar este pedido?')

    await wrapper.get('.cancel-order button').trigger('click')
    await flushPromises()

    expect(requests.filter((request) => request.method === 'POST')).toHaveLength(1)
    expect(wrapper.get('.orders__status').text()).toBe('Cancelado')
    expect(wrapper.find('.cancel-order').exists()).toBe(false)
  })

  it('says so when there are no orders yet', async () => {
    fakeApi({ 'GET /cuenta/pedidos': () => json(200, page([])) })

    const { wrapper } = await open('/cuenta/pedidos', 'cliente')

    expect(wrapper.get('.empty-state').text()).toBe('Todavía no tiene pedidos.')
  })

  it('sends a visitor to the sign-in and the staff to "Sin permiso"', async () => {
    fakeApi({})

    const visitor = await open('/cuenta/pedidos')
    expect(visitor.router.currentRoute.value.name).toBe('sign-in')
    cleanUp()

    const staff = await open('/cuenta/pedidos', 'dueno')
    expect(staff.router.currentRoute.value.name).toBe('forbidden')
  })
})
