import { describe, expect, it } from 'vitest'

import { amountToPay, canCancel, canPay, statusLabel } from '../order'
import { orderOf } from './fixtures'

describe('order rules of the views', () => {
  it('offers to pay what is due now while the order waits, and the balance once it is ready', () => {
    expect(canPay(orderOf())).toBe(true)
    expect(amountToPay(orderOf())).toBe(72000)
    expect(canPay(orderOf({ estado: 'confirmado', pagado: 72000, saldo: 62500 }))).toBe(false)
    expect(canPay(orderOf({ estado: 'listo', pagado: 72000, saldo: 62500 }))).toBe(true)
    expect(amountToPay(orderOf({ estado: 'listo', pagado: 72000, saldo: 62500 }))).toBe(62500)
    expect(canPay(orderOf({ estado: 'listo', pagado: 134500, saldo: 0 }))).toBe(false)
    expect(canPay(orderOf({ estado: 'confirmado', por_cobrar_ahora: 0, saldo: 0 }))).toBe(false)
  })

  it('offers to cancel before the order is ordered or a decant is prepared', () => {
    const prepared = orderOf({ estado: 'confirmado' })
    prepared.lineas[1]!.estado = 'preparada'

    expect(canCancel(orderOf())).toBe(true)
    expect(canCancel(orderOf({ estado: 'confirmado' }))).toBe(true)
    expect(canCancel(prepared)).toBe(false)
    expect(canCancel(orderOf({ estado: 'encargado' }))).toBe(false)
    expect(canCancel(orderOf({ estado: 'cancelado' }))).toBe(false)
  })

  it('names each status in Spanish', () => {
    expect(statusLabel('pendiente_de_pago')).toBe('Pendiente de pago')
    expect(statusLabel('listo')).toBe('Listo para entregar')
    expect(statusLabel('vencido')).toBe('Vencido sin pago')
  })
})
