/** The "procesando" view asks the API every 3 s, for 2 min (#130). */
export const POLL_INTERVAL_MS = 3000
export const POLL_DURATION_MS = 2 * 60 * 1000

/** Key in sessionStorage of the payment that went to the gateway. */
export const PENDING_PAYMENT_KEY = 'archivum.pago-en-curso'

/**
 * The payment that the customer started (#130): which order, and how much the API said it had paid before. The
 * gateway returns to one fixed address of the configuration of the API, which does not name the order or payment.
 * The stored IDs tell the tab what to ask the API. Older tabs may only have the order ID.
 */
export interface PendingPayment {
  orderId: string
  paidBefore: number
  paymentId?: string
}

export function savePendingPayment(payment: PendingPayment): void {
  try {
    sessionStorage.setItem(PENDING_PAYMENT_KEY, JSON.stringify(payment))
  } catch {
    // Without storage the "procesando" view sends the customer to "Mis pedidos".
  }
}

export function readPendingPayment(): PendingPayment | null {
  try {
    const raw = sessionStorage.getItem(PENDING_PAYMENT_KEY)
    const stored: unknown = raw ? JSON.parse(raw) : null

    if (typeof stored !== 'object' || stored === null) {
      return null
    }

    const { orderId, paidBefore, paymentId } = stored as Record<string, unknown>

    if (typeof orderId !== 'string' || typeof paidBefore !== 'number') {
      return null
    }

    return { orderId, paidBefore, ...(typeof paymentId === 'string' && paymentId ? { paymentId } : {}) }
  } catch {
    return null
  }
}

export function clearPendingPayment(): void {
  try {
    sessionStorage.removeItem(PENDING_PAYMENT_KEY)
  } catch {
    // Storage may be unavailable: there is nothing to remove.
  }
}
