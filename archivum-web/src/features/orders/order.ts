import type { components } from '@/api/schema'

export type Order = components['schemas']['OrderResource']
export type OrderStatus = Order['estado']
export type OrderLine = Order['lineas'][number]
export type Payment = components['schemas']['PaymentResource']

const STATUS_LABELS: Record<OrderStatus, string> = {
  pendiente_de_pago: 'Pendiente de pago',
  confirmado: 'Confirmado',
  encargado: 'Encargado al proveedor',
  listo: 'Listo para entregar',
  entregado: 'Entregado',
  cancelado: 'Cancelado',
  vencido: 'Vencido sin pago',
}

const LINE_STATUS_LABELS: Record<OrderLine['estado'], string> = {
  pendiente: 'Pendiente',
  encargada: 'Encargada',
  recibida: 'Recibida',
  no_disponible: 'No disponible',
  reservada: 'Reservada',
  preparada: 'Preparada',
  liberada: 'Liberada',
}

/** The status of an order, in Spanish. */
export function statusLabel(status: OrderStatus): string {
  return STATUS_LABELS[status]
}

/** The status of a line of an order, in Spanish. */
export function lineStatusLabel(status: OrderLine['estado']): string {
  return LINE_STATUS_LABELS[status]
}

/**
 * Whether the order takes an online payment, as the API admits it (#92): the deposit or the total while it waits for
 * its payment, and the balance once it is ready. The API decides: this only shows or hides the button.
 */
export function canPay(order: Order): boolean {
  return (order.estado === 'pendiente_de_pago' && order.por_cobrar_ahora > order.pagado) ||
    (order.estado === 'listo' && order.saldo > 0)
}

/** What the next payment charges, from the amounts of the API: what is due now, or the balance. */
export function amountToPay(order: Order): number {
  return order.estado === 'pendiente_de_pago' ? order.por_cobrar_ahora - order.pagado : order.saldo
}

/**
 * Whether the customer may cancel the order, as the API admits it (#83): before it is ordered from the supplier and
 * before any decant is prepared. The API decides and answers 409 otherwise.
 */
export function canCancel(order: Order): boolean {
  return ['pendiente_de_pago', 'confirmado'].includes(order.estado) && !order.lineas.some((line) => line.estado === 'preparada')
}
