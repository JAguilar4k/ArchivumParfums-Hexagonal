import { describe, expect, it } from 'vitest'

import { amountToPay, canCancel, canPay, isClosed, statusLabel } from '../order'
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

  it('charges what the API still asks for now, even after a part of it was paid', () => {
    // por_cobrar_ahora already leaves out what is paid: the API answers due now − paid.
    const partlyPaid = orderOf({ por_cobrar_ahora: 42000, pagado: 30000 })
    const mostlyPaid = orderOf({ por_cobrar_ahora: 20000, pagado: 52000 })

    expect(canPay(partlyPaid)).toBe(true)
    expect(amountToPay(partlyPaid)).toBe(42000)
    expect(canPay(mostlyPaid)).toBe(true)
    expect(amountToPay(mostlyPaid)).toBe(20000)
    expect(canPay(orderOf({ por_cobrar_ahora: 0, pagado: 72000 }))).toBe(false)
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

  it('closes a cancelled or expired order, which owes nothing', () => {
    expect(isClosed(orderOf({ estado: 'cancelado' }))).toBe(true)
    expect(isClosed(orderOf({ estado: 'vencido' }))).toBe(true)
    expect(isClosed(orderOf())).toBe(false)
    expect(isClosed(orderOf({ estado: 'entregado' }))).toBe(false)
  })

  it('names each status in Spanish', () => {
    expect(statusLabel('pendiente_de_pago')).toBe('Pendiente de pago')
    expect(statusLabel('listo')).toBe('Listo para entregar')
    expect(statusLabel('vencido')).toBe('Vencido sin pago')
  })
})
