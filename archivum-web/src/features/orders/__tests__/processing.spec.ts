import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'

import { fakeApi, json, problem } from '@/api/__tests__/fakeApi'
import { cleanUp, open } from '@/features/storefront/__tests__/fixtures'
import { PENDING_PAYMENT_KEY, POLL_DURATION_MS, POLL_INTERVAL_MS, savePendingPayment } from '../pendingPayment'
import { ORDER_ID, PAYMENT_ID, orderOf, paymentOf } from './fixtures'

const ORDER_PATH = `GET /cuenta/pedidos/${ORDER_ID}`
const PAYMENT_PATH = `GET /cuenta/pagos/${PAYMENT_ID}`

function accountRequests(requests: Request[]): Request[] {
  return requests.filter(({ url }) => new URL(url).pathname.startsWith('/api/v1/cuenta/'))
}

beforeEach(() => {
  // Only the timers of the polling are fake: flushPromises and the dates stay real.
  vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
})

afterEach(() => {
  cleanUp()
  vi.useRealTimers()
})

async function waitPolls(count: number): Promise<void> {
  for (let poll = 0; poll < count; poll++) {
    await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS)
    await flushPromises()
  }
}

describe('"procesando"', () => {
  it('still polls an order saved without a payment ID and ignores the success parameter in the address', async () => {
    sessionStorage.setItem(PENDING_PAYMENT_KEY, JSON.stringify({ orderId: ORDER_ID, paidBefore: 0 }))
    let confirmed = false
    const { requests } = fakeApi({
      [ORDER_PATH]: () =>
        json(200, { data: confirmed ? orderOf({ estado: 'confirmado', pagado: 72000, saldo: 62500 }) : orderOf() }),
    })

    const { wrapper } = await open('/pagos/procesando?estado=exito&status=succeeded&token=cs_test_123', 'cliente')
    await waitPolls(3)

    expect(wrapper.get('.payment-processing__waiting').text()).toContain('Procesando tu pago…')
    expect(accountRequests(requests)).toHaveLength(4)

    // The verified notice of the gateway arrives at the API.
    confirmed = true
    await waitPolls(1)

    expect(wrapper.find('.payment-processing__waiting').exists()).toBe(false)
    expect(wrapper.get('.payment-processing__done').text()).toBe('Pago confirmado.')
    expect(wrapper.get('.order-summary__status').text()).toBe('Confirmado')
    // One status line says each state, so screen readers announce it.
    expect(wrapper.get('.payment-processing__done').attributes('role')).toBe('status')
    expect(sessionStorage.getItem(PENDING_PAYMENT_KEY)).toBeNull()

    await waitPolls(2)
    expect(accountRequests(requests)).toHaveLength(5)
  })

  it('announces a rejection when the API marks the payment failed and stops polling', async () => {
    savePendingPayment({ orderId: ORDER_ID, paidBefore: 0, paymentId: PAYMENT_ID })
    let rejected = false
    const { requests } = fakeApi({
      [ORDER_PATH]: () => json(200, { data: orderOf() }),
      [PAYMENT_PATH]: () => json(200, { data: paymentOf({ estado: rejected ? 'fallido' : 'pendiente' }) }),
    })

    const { wrapper } = await open('/pagos/procesando?estado=fallido', 'cliente')
    expect(wrapper.get('.payment-processing__status').text()).toBe('Procesando tu pago…')
    expect(sessionStorage.getItem(PENDING_PAYMENT_KEY)).not.toBeNull()

    rejected = true
    await waitPolls(1)

    const status = wrapper.get('.payment-processing__status')
    expect(status.attributes('role')).toBe('status')
    expect(status.text()).toBe('El pago fue rechazado. Consulte el pedido para volver a intentarlo.')
    expect(wrapper.find('.payment-processing__waiting').exists()).toBe(false)
    expect(sessionStorage.getItem(PENDING_PAYMENT_KEY)).toBeNull()

    await waitPolls(2)
    expect(accountRequests(requests).map(({ method, url }) => `${method} ${new URL(url).pathname.slice('/api/v1'.length)}`))
      .toEqual([ORDER_PATH, PAYMENT_PATH, ORDER_PATH, PAYMENT_PATH])
  })

  it('waits for the API to credit the order even when the payment is confirmed', async () => {
    savePendingPayment({ orderId: ORDER_ID, paidBefore: 0, paymentId: PAYMENT_ID })
    let credited = false
    const { requests } = fakeApi({
      [ORDER_PATH]: () => json(200, {
        data: credited ? orderOf({ estado: 'confirmado', pagado: 72000, saldo: 62500 }) : orderOf(),
      }),
      [PAYMENT_PATH]: () => json(200, { data: paymentOf({ estado: 'confirmado' }) }),
    })

    const { wrapper } = await open('/pagos/procesando', 'cliente')
    expect(wrapper.get('.payment-processing__status').text()).toBe('Procesando tu pago…')

    credited = true
    await waitPolls(1)

    expect(wrapper.get('.payment-processing__done').text()).toBe('Pago confirmado.')
    expect(accountRequests(requests).filter(({ url }) => url.endsWith(`/pagos/${PAYMENT_ID}`))).toHaveLength(1)
  })

  it('says after 2 min that the payment is still in process and that "Mis pedidos" will show it', async () => {
    savePendingPayment({ orderId: ORDER_ID, paidBefore: 0 })
    const { requests } = fakeApi({ [ORDER_PATH]: () => json(200, { data: orderOf() }) })

    const { wrapper } = await open('/pagos/procesando', 'cliente')
    await waitPolls(POLL_DURATION_MS / POLL_INTERVAL_MS)

    expect(wrapper.get('.payment-processing__timeout').text()).toContain('Verás su estado en «Mis pedidos»')
    expect(accountRequests(requests)).toHaveLength(POLL_DURATION_MS / POLL_INTERVAL_MS + 1)

    await waitPolls(2)
    expect(accountRequests(requests)).toHaveLength(POLL_DURATION_MS / POLL_INTERVAL_MS + 1)
  })

  it('waits for the balance of an order that was already paid in part', async () => {
    savePendingPayment({ orderId: ORDER_ID, paidBefore: 72000 })
    let paid = 72000
    fakeApi({ [ORDER_PATH]: () => json(200, { data: orderOf({ estado: 'listo', pagado: paid, saldo: 134500 - paid }) }) })

    const { wrapper } = await open('/pagos/procesando', 'cliente')
    expect(wrapper.find('.payment-processing__waiting').exists()).toBe(true)

    paid = 134500
    await waitPolls(1)

    expect(wrapper.get('.payment-processing__done').text()).toBe('Pago confirmado.')
  })

  it('keeps asking after a network failure, and stops when the order expired', async () => {
    savePendingPayment({ orderId: ORDER_ID, paidBefore: 0 })
    let answers = 0
    fakeApi({
      [ORDER_PATH]: () => {
        answers++

        if (answers === 1) {
          throw new TypeError('Failed to fetch')
        }

        return json(200, { data: orderOf({ estado: 'vencido' }) })
      },
    })

    const { wrapper } = await open('/pagos/procesando', 'cliente')
    expect(wrapper.find('.payment-processing__waiting').exists()).toBe(true)

    await waitPolls(1)

    expect(wrapper.get('.payment-processing__closed').text()).toBe(
      'El pedido está «Vencido sin pago», así que el pago no se aplicó.',
    )
  })

  it('stops at an order that the API does not find', async () => {
    savePendingPayment({ orderId: ORDER_ID, paidBefore: 0 })
    const { requests } = fakeApi({ [ORDER_PATH]: () => problem(404, 'no-encontrado', 'Ese pedido o esa línea no existe.') })

    const { wrapper } = await open('/pagos/procesando', 'cliente')
    await waitPolls(2)

    // The page keeps its only heading of level 1 and says the problem in its status line.
    expect(wrapper.findAll('.payment-processing h1').map((title) => title.text())).toEqual(['Pago del pedido'])
    expect(wrapper.get('.payment-processing__failed').text()).toContain('No encontramos el pedido de este pago.')
    expect(accountRequests(requests)).toHaveLength(1)
  })

  it('sends to "Mis pedidos" when this tab started no payment, without checking an order', async () => {
    const { requests } = fakeApi({})

    const { wrapper } = await open('/pagos/procesando?estado=exito', 'cliente')

    expect(wrapper.get('.payment-processing__missing').text()).toContain('No hay un pago en curso en esta pestaña.')
    expect(accountRequests(requests)).toHaveLength(0)
  })
})
