<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import { api } from '@/api/client'
import { callApi, type ApiProblem } from '@/api/problem'
import { useSessionStore } from '@/features/session/store'
import EmptyState from '@/shared/EmptyState.vue'
import ProblemNotice from '@/shared/ProblemNotice.vue'
import { formatColones } from '@/shared/money'
import { soldOutDecants } from './availability'
import { useCartStore, type CartLine } from './store'

/**
 * The cart of a store (#130). "Crear pedido" sends `POST /tiendas/{tienda}/pedidos` with identities and quantities
 * only, and opens the order with the amounts that the API calculated. A 422 marks each line by its position
 * (`lineas.N.*`), and a 409 for missing ml marks the decants that ran out.
 */
const route = useRoute()
const router = useRouter()
const cart = useCartStore()
const session = useSessionStore()

const tienda = computed(() => String(route.params.tienda))
const lines = computed(() => cart.linesOf(tienda.value))
const sending = ref(false)
const problem = ref<ApiProblem | null>(null)
const soldOut = ref<Set<number>>(new Set())

watch(tienda, (slug) => void cart.load(slug), { immediate: true })

function lineText(line: CartLine): string {
  return line.kind === 'bottle' ? `${line.perfume} · botella de ${line.ml} ml` : `${line.perfume} · decant de ${line.ml} ml`
}

/** The messages of a line: those of the 422 under `lineas.N`, and the mark of a decant that ran out. */
function lineMessages(index: number): string[] {
  const prefix = `lineas.${index}.`
  const messages = Object.entries(problem.value?.fieldErrors ?? {})
    .filter(([field]) => field.startsWith(prefix))
    .flatMap(([, texts]) => texts)

  return soldOut.value.has(index) ? [...messages, 'Ya no quedan ml suficientes en esta botella para este decant.'] : messages
}

function changeQuantity(index: number, event: Event): void {
  cart.setQuantity(tienda.value, index, Number((event.target as HTMLInputElement).value))
}

function remove(index: number): void {
  cart.remove(tienda.value, index)
  problem.value = null
  soldOut.value = new Set()
}

async function placeOrder(): Promise<void> {
  if (sending.value) {
    return
  }

  sending.value = true
  problem.value = null
  soldOut.value = new Set()

  const outcome = await callApi(() =>
    api.POST('/tiendas/{tienda}/pedidos', {
      params: { path: { tienda: tienda.value } },
      body: { lineas: cart.orderLines(tienda.value) },
    }),
  )

  if (outcome.problem) {
    problem.value = outcome.problem

    if (outcome.problem.kind === 'conflict') {
      soldOut.value = await soldOutDecants(tienda.value, lines.value)
    }

    sending.value = false

    return
  }

  cart.clear(tienda.value)
  await router.push({ name: 'order', params: { pedido: outcome.data.data.id } })
}
</script>

<template>
  <main class="cart">
    <h2>Carrito</h2>
    <EmptyState v-if="lines.length === 0" message="Su carrito está vacío.">
      <RouterLink :to="{ name: 'storefront', params: { tienda } }">Ver la vitrina</RouterLink>
    </EmptyState>
    <template v-else>
      <ul class="cart__lines">
        <li v-for="(line, index) in lines" :key="index" class="cart-line">
          <span class="cart-line__text">{{ lineText(line) }}</span>
          <span class="cart-line__price">{{ formatColones(line.price) }} en la vitrina</span>
          <label v-if="line.kind === 'bottle'">
            Cantidad
            <input
              type="number"
              min="1"
              step="1"
              :name="`lineas.${index}.cantidad`"
              :value="line.quantity"
              :aria-invalid="lineMessages(index).length > 0 ? 'true' : undefined"
              :aria-describedby="lineMessages(index).length > 0 ? `error-linea-${index}` : undefined"
              @change="changeQuantity(index, $event)"
            />
          </label>
          <button type="button" :aria-label="`Quitar ${lineText(line)}`" @click="remove(index)">Quitar</button>
          <p v-if="lineMessages(index).length > 0" :id="`error-linea-${index}`" class="field-error cart-line__error">
            {{ lineMessages(index).join(' ') }}
          </p>
        </li>
      </ul>
      <p class="cart__note">
        El subtotal, el depósito y el saldo los calcula la tienda al crear el pedido, con los precios del momento.
      </p>
      <ProblemNotice v-if="problem" :problem="problem" />
      <button v-if="session.isSignedIn" type="button" :disabled="sending" @click="placeOrder">Crear pedido</button>
      <RouterLink v-else :to="{ name: 'sign-in', query: { redirect: route.fullPath } }">
        Inicie sesión para crear el pedido
      </RouterLink>
    </template>
  </main>
</template>
