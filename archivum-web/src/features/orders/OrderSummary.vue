<script setup lang="ts">
import { formatColones } from '@/shared/money'
import { lineStatusLabel, statusLabel, type Order, type OrderLine } from './order'

/** An order with the amounts exactly as the API calculated them (#130): the client adds nothing up. */
defineProps<{ order: Order }>()

const dateTime = new Intl.DateTimeFormat('es-CR', { dateStyle: 'medium', timeStyle: 'short' })

function lineText(line: OrderLine): string {
  const perfume = line.perfume ?? 'Perfume'

  return line.tipo === 'botella'
    ? `${perfume} · botella de ${line.presentacion_ml ?? '?'} ml × ${line.cantidad}`
    : `${perfume} · decant de ${line.ml ?? '?'} ml`
}
</script>

<template>
  <section class="order-summary" :aria-labelledby="`order-${order.id}`">
    <h2 :id="`order-${order.id}`">Pedido en {{ order.tienda.nombre ?? 'la tienda' }}</h2>
    <p class="order-summary__status">
      Estado: <strong>{{ statusLabel(order.estado) }}</strong>
    </p>
    <dl class="order-summary__amounts">
      <dt>Subtotal</dt>
      <dd>{{ formatColones(order.subtotal) }}</dd>
      <dt>Por cobrar ahora</dt>
      <dd>{{ formatColones(order.por_cobrar_ahora) }}</dd>
      <dt>Pagado</dt>
      <dd>{{ formatColones(order.pagado) }}</dd>
      <dt>Saldo</dt>
      <dd>{{ formatColones(order.saldo) }}</dd>
    </dl>
    <p v-if="order.estado === 'pendiente_de_pago' && order.vence_en" class="order-summary__deadline">
      Si no se paga, el pedido vence el {{ dateTime.format(new Date(order.vence_en)) }}.
    </p>
    <ul class="order-summary__lines">
      <li v-for="line in order.lineas" :key="line.id">
        {{ lineText(line) }} · {{ formatColones(line.precio_unitario) }} · {{ lineStatusLabel(line.estado) }}
      </li>
    </ul>
  </section>
</template>
