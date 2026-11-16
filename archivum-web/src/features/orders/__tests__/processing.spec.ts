import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'

import { fakeApi, json, problem } from '@/api/__tests__/fakeApi'
import { cleanUp, open } from '@/features/storefront/__tests__/fixtures'
import { PENDING_PAYMENT_KEY, POLL_DURATION_MS, POLL_INTERVAL_MS, savePendingPayment } from '../pendingPayment'
import { ORDER_ID, orderOf } from './fixtures'

const ORDER_PATH = `GET /cuenta/pedidos/${ORDER_ID}`

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
  it('stays in "procesando" with a success parameter in the address until the API credits the payment', async () => {
    savePendingPayment({ orderId: ORDER_ID, paidBefore: 0 })
    let confirmed = false
    const { requests } = fakeApi({
      [ORDER_PATH]: () =>
        json(200, { data: confirmed ? orderOf({ estado: 'confirmado', pagado: 72000, saldo: 62500 }) : orderOf() }),
    })

    const { wrapper } = await open('/pagos/procesando?estado=exito&status=succeeded&token=cs_test_123', 'cliente')
    await waitPolls(3)

    expect(wrapper.get('.payment-processing__waiting').text()).toContain('Procesando su pago…')
    expect(requests).toHaveLength(4)

    // The verified notice of the gateway arrives at the API.
    confirmed = true
    await waitPolls(1)

    expect(wrapper.find('.payment-processing__waiting').exists()).toBe(false)
    expect(wrapper.get('.payment-processing__done').text()).toBe('Pago confirmado.')
    expect(wrapper.get('.order-summary__status').text()).toBe('Estado: Confirmado')
    expect(sessionStorage.getItem(PENDING_PAYMENT_KEY)).toBeNull()

    await waitPolls(2)
    expect(requests).toHaveLength(5)
  })

  it('says after 2 min that the payment is still in process and that "Mis pedidos" will show it', async () => {
    savePendingPayment({ orderId: ORDER_ID, paidBefore: 0 })
    const { requests } = fakeApi({ [ORDER_PATH]: () => json(200, { data: orderOf() }) })

    const { wrapper } = await open('/pagos/procesando', 'cliente')
    await waitPolls(POLL_DURATION_MS / POLL_INTERVAL_MS)

    expect(wrapper.get('.payment-processing__timeout').text()).toContain('Verá su estado en «Mis pedidos»')
    expect(requests).toHaveLength(POLL_DURATION_MS / POLL_INTERVAL_MS + 1)

    await waitPolls(2)
    expect(requests).toHaveLength(POLL_DURATION_MS / POLL_INTERVAL_MS + 1)
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

    expect(wrapper.findAll('.payment-processing h1').map((title) => title.text())).toContain('No encontrado')
    expect(requests).toHaveLength(1)
  })

  it('sends to "Mis pedidos" when this tab started no payment, without asking the API', async () => {
    const { requests } = fakeApi({})

    const { wrapper } = await open('/pagos/procesando?estado=exito', 'cliente')

    expect(wrapper.get('.payment-processing__missing').text()).toContain('No hay un pago en curso en esta pestaña.')
    expect(requests).toHaveLength(0)
  })
})
