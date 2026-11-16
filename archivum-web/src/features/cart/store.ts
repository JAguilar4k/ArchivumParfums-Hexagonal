import { defineStore } from 'pinia'
import { ref } from 'vue'

import type { components } from '@/api/schema'

/** The lines that `POST /tiendas/{tienda}/pedidos` takes. */
export type OrderLineInput = components['schemas']['StoreOrder']['lineas'][number]

/** Key of the cart of a store in localStorage, followed by the slug of the store. */
export const CART_STORAGE_PREFIX = 'archivum.carrito.'

/** A bottle on order: a presentation of a perfume and how many. */
export interface BottleLine {
  kind: 'bottle'
  perfumeId: string
  perfume: string
  presentationId: string
  ml: number
  quantity: number
  /** Price that the showcase showed, only to remind it: the API calculates the order. */
  price: number
}

/** One decant: some ml of an open bottle. Each decant is its own line, as the API counts them (#80). */
export interface DecantLine {
  kind: 'decant'
  perfumeId: string
  perfume: string
  bottleId: string
  ml: number
  price: number
}

export type CartLine = BottleLine | DecantLine

/**
 * The cart of each store (#130). It keeps only what the customer chose, in localStorage under the slug of the store,
 * so it survives a reload. It keeps no total: the order sends no prices, and the API calculates the subtotal and the
 * charge when it creates the order.
 */
export const useCartStore = defineStore('cart', () => {
  const carts = ref<Record<string, CartLine[]>>({})

  /** The lines of a store, once `load` read them from localStorage. */
  function linesOf(tienda: string): CartLine[] {
    return carts.value[tienda] ?? []
  }

  /** Reads the cart of a store from localStorage, once, and returns its lines. */
  function load(tienda: string): CartLine[] {
    if (!(tienda in carts.value)) {
      carts.value[tienda] = read(tienda)
    }

    return linesOf(tienda)
  }

  /** Adds a bottle, or one more of a presentation that is already in the cart. */
  function addBottle(tienda: string, line: Omit<BottleLine, 'kind' | 'quantity'>): void {
    const lines = load(tienda)
    const same = lines.find(
      (item): item is BottleLine => item.kind === 'bottle' && item.presentationId === line.presentationId,
    )

    if (same) {
      same.quantity += 1
    } else {
      lines.push({ kind: 'bottle', quantity: 1, ...line })
    }

    save(tienda)
  }

  /** Adds one decant. */
  function addDecant(tienda: string, line: Omit<DecantLine, 'kind'>): void {
    load(tienda).push({ kind: 'decant', ...line })
    save(tienda)
  }

  /** Changes how many bottles a line asks for; a whole number from 1. */
  function setQuantity(tienda: string, index: number, quantity: number): void {
    const line = linesOf(tienda)[index]

    if (line?.kind === 'bottle' && Number.isInteger(quantity) && quantity >= 1) {
      line.quantity = quantity
      save(tienda)
    }
  }

  function remove(tienda: string, index: number): void {
    linesOf(tienda).splice(index, 1)
    save(tienda)
  }

  /** Empties the cart of a store, after its order was created. */
  function clear(tienda: string): void {
    carts.value[tienda] = []

    try {
      localStorage.removeItem(CART_STORAGE_PREFIX + tienda)
    } catch {
      // Storage may be unavailable (private mode): there is nothing to remove.
    }
  }

  /** How many items the cart has: each bottle and each decant. */
  function count(tienda: string): number {
    return linesOf(tienda).reduce((total, line) => total + (line.kind === 'bottle' ? line.quantity : 1), 0)
  }

  /** The body of the order: identities and quantities only, never a price. */
  function orderLines(tienda: string): OrderLineInput[] {
    return linesOf(tienda).map((line) =>
      line.kind === 'bottle'
        ? { presentacion_id: line.presentationId, cantidad: line.quantity }
        : { botella_id: line.bottleId, ml: line.ml },
    )
  }

  function save(tienda: string): void {
    try {
      localStorage.setItem(CART_STORAGE_PREFIX + tienda, JSON.stringify(linesOf(tienda)))
    } catch {
      // Without storage the cart lasts until the page reloads.
    }
  }

  function read(tienda: string): CartLine[] {
    try {
      const raw = localStorage.getItem(CART_STORAGE_PREFIX + tienda)
      const stored: unknown = raw ? JSON.parse(raw) : []

      return Array.isArray(stored) ? stored.filter(isCartLine) : []
    } catch {
      return []
    }
  }

  return { carts, linesOf, load, addBottle, addDecant, setQuantity, remove, clear, count, orderLines }
})

/** Keeps only the lines that this version of the client wrote, so an old or edited value cannot break the cart. */
function isCartLine(value: unknown): value is CartLine {
  if (typeof value !== 'object' || value === null) {
    return false
  }

  const line = value as Record<string, unknown>
  const common =
    typeof line.perfumeId === 'string' &&
    typeof line.perfume === 'string' &&
    typeof line.ml === 'number' &&
    typeof line.price === 'number'

  if (line.kind === 'bottle') {
    return common && typeof line.presentationId === 'string' && Number.isInteger(line.quantity) && Number(line.quantity) >= 1
  }

  return line.kind === 'decant' && common && typeof line.bottleId === 'string'
}
