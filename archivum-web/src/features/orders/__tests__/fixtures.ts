import type { Order, Payment } from '../order'

export const ORDER_ID = '0193b0d4-0000-7000-8000-0000000000a1'
export const PAYMENT_ID = '0193b0d4-0000-7000-8000-0000000000b1'

/** An order of ArchivumParfums with a bottle and a decant, waiting for its deposit; `changes` overrides any field. */
export function orderOf(changes: Partial<Order> = {}): Order {
  return {
    id: ORDER_ID,
    tienda: { id: '0193b0d4-0000-7000-8000-000000000030', nombre: 'ArchivumParfums' },
    cliente: { id: '0193b0d4-0000-7000-8000-000000000040', nombre: 'Ana Cliente' },
    estado: 'pendiente_de_pago',
    subtotal: 134500,
    por_cobrar_ahora: 72000,
    pagado: 0,
    saldo: 134500,
    creado_en: '2026-11-15T18:00:00-06:00',
    vence_en: '2026-11-15T18:30:00-06:00',
    cancelado_en: null,
    lineas: [
      {
        id: 'l-1',
        tipo: 'botella',
        presentacion_id: 'p-1-100',
        botella_id: null,
        perfume: 'Oud Nocturno',
        ml: null,
        presentacion_ml: 100,
        cantidad: 1,
        precio_unitario: 125000,
        paquete_id: null,
        estado: 'pendiente',
      },
      {
        id: 'l-2',
        tipo: 'decant',
        presentacion_id: null,
        botella_id: 'b-1',
        perfume: 'Oud Nocturno',
        ml: 5,
        presentacion_ml: null,
        cantidad: 1,
        precio_unitario: 9500,
        paquete_id: null,
        estado: 'reservada',
      },
    ],
    ...changes,
  }
}

/** A payment that ONVO is waiting for, in colones; `changes` overrides any field. */
export function paymentOf(changes: Partial<Payment> = {}): Payment {
  return {
    id: PAYMENT_ID,
    proposito: 'deposito',
    monto: 72000,
    moneda_cobrada: 'CRC',
    monto_cobrado: 72000,
    pasarela: 'onvo',
    estado: 'pendiente',
    url_pago: 'https://checkout.onvopay.com/pay/cs_test_123',
    medio: 'tarjeta',
    consulta_pendiente: false,
    ...changes,
  }
}
