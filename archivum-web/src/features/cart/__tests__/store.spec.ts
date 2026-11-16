import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

import { CART_STORAGE_PREFIX, useCartStore } from '../store'

const bottle = { perfumeId: 'p-1', perfume: 'Maison Archivum Oud Nocturno', presentationId: 'p-1-100', ml: 100, price: 125000 }
const decant = { perfumeId: 'p-1', perfume: 'Maison Archivum Oud Nocturno', bottleId: 'b-1', ml: 5, price: 9500 }

/** A new Pinia, as after a reload of the page. */
function freshCart() {
  setActivePinia(createPinia())

  return useCartStore()
}

beforeEach(() => {
  localStorage.clear()
})

describe('cart store', () => {
  it('adds one more of a bottle that is already in the cart, and each decant as its own line', () => {
    const cart = freshCart()

    cart.addBottle('archivum', bottle)
    cart.addBottle('archivum', bottle)
    cart.addDecant('archivum', decant)
    cart.addDecant('archivum', decant)

    expect(cart.linesOf('archivum')).toHaveLength(3)
    expect(cart.linesOf('archivum')[0]).toMatchObject({ kind: 'bottle', quantity: 2 })
    expect(cart.count('archivum')).toBe(4)
  })

  it('sends only identities and quantities, never a price', () => {
    const cart = freshCart()
    cart.addBottle('archivum', bottle)
    cart.addDecant('archivum', decant)

    expect(cart.orderLines('archivum')).toEqual([
      { presentacion_id: 'p-1-100', cantidad: 1 },
      { botella_id: 'b-1', ml: 5 },
    ])
  })

  it('keeps the cart of each store in localStorage, so it survives a reload', () => {
    const before = freshCart()
    before.addBottle('archivum', bottle)
    before.addDecant('otra', decant)

    const after = freshCart()
    after.load('archivum')
    after.load('otra')

    expect(after.linesOf('archivum')).toEqual([{ kind: 'bottle', quantity: 1, ...bottle }])
    expect(after.linesOf('otra')).toEqual([{ kind: 'decant', ...decant }])
  })

  it('changes the quantity only to a whole number from 1, removes a line and empties the cart', () => {
    const cart = freshCart()
    cart.addBottle('archivum', bottle)
    cart.addDecant('archivum', decant)

    cart.setQuantity('archivum', 0, 3)
    cart.setQuantity('archivum', 0, 0)
    cart.setQuantity('archivum', 0, 1.5)
    expect(cart.linesOf('archivum')[0]).toMatchObject({ quantity: 3 })

    cart.remove('archivum', 1)
    expect(cart.count('archivum')).toBe(3)

    cart.clear('archivum')
    expect(cart.linesOf('archivum')).toEqual([])
    expect(localStorage.getItem(`${CART_STORAGE_PREFIX}archivum`)).toBeNull()
  })

  it('ignores a stored value that is not a cart of this client', () => {
    localStorage.setItem(`${CART_STORAGE_PREFIX}archivum`, JSON.stringify([{ kind: 'bottle', precio: 1 }, 'x', null]))
    localStorage.setItem(`${CART_STORAGE_PREFIX}rota`, '{no es json')

    const cart = freshCart()
    cart.load('archivum')
    cart.load('rota')

    expect(cart.linesOf('archivum')).toEqual([])
    expect(cart.linesOf('rota')).toEqual([])
  })
})
