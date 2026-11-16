<script setup lang="ts">
import { RouterLink } from 'vue-router'

import MlAmount from '@/shared/MlAmount.vue'
import { formatColones } from '@/shared/money'
import { isClosed, lineStatusLabel, statusLabel, type Order } from './order'

/**
 * An order with the amounts exactly as the API calculated them (#130): the client adds nothing up. The time to pay is
 * a rule of the store (R25), so the summary says until when the order waits for its payment.
 */
defineProps<{ order: Order }>()

const dateTime = new Intl.DateTimeFormat('es-CR', { dateStyle: 'long', timeStyle: 'short' })
</script>

<template>
  <section class="page-section order-summary" :aria-labelledby="`resumen-${order.id}`">
    <h2 :id="`resumen-${order.id}`">Resumen del pedido</h2>
    <dl class="facts">
      <div>
        <dt>Tienda</dt>
        <dd>{{ order.tienda.nombre ?? 'La tienda' }}</dd>
      </div>
      <div>
        <dt>Estado</dt>
        <dd class="order-summary__status">{{ statusLabel(order.estado) }}</dd>
      </div>
      <div>
        <dt>Subtotal</dt>
        <dd>{{ formatColones(order.subtotal) }}</dd>
      </div>
      <div>
        <dt>Por cobrar ahora</dt>
        <dd>{{ formatColones(order.por_cobrar_ahora) }}</dd>
      </div>
      <div>
        <dt>Pagado</dt>
        <dd>{{ formatColones(order.pagado) }}</dd>
      </div>
      <div v-if="!isClosed(order)">
        <dt>Saldo</dt>
        <dd>{{ formatColones(order.saldo) }}</dd>
      </div>
    </dl>
    <p>
      Vea en la ayuda <RouterLink to="/ayuda#ayuda-pedidos">qué significan los montos y los estados de un pedido</RouterLink>.
    </p>
    <p v-if="order.estado === 'pendiente_de_pago' && order.vence_en" class="order-summary__deadline">
      Si no se paga antes del {{ dateTime.format(new Date(order.vence_en)) }}, el pedido vence y sus decants vuelven a
      quedar disponibles para otras personas.
    </p>

    <h3>Productos</h3>
    <ul class="order-summary__lines">
      <li v-for="line in order.lineas" :key="line.id">
        <template v-if="line.tipo === 'botella'">
          {{ line.perfume ?? 'Perfume' }} · {{ line.cantidad }} {{ line.cantidad === 1 ? 'botella' : 'botellas' }} de
          <MlAmount v-if="line.presentacion_ml" :amount="line.presentacion_ml" /><template v-else>?</template> ·
          {{ formatColones(line.precio_unitario) }} cada una · {{ lineStatusLabel(line.estado) }}
        </template>
        <template v-else>
          {{ line.perfume ?? 'Perfume' }} · decant de <MlAmount v-if="line.ml" :amount="line.ml" /><template v-else>?</template> ·
          {{ formatColones(line.precio_unitario) }} · {{ lineStatusLabel(line.estado) }}
        </template>
      </li>
    </ul>
  </section>
</template>
