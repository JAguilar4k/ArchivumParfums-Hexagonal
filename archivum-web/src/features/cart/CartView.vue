<script setup lang="ts">
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import { api } from '@/api/client'
import { callApi, type ApiProblem } from '@/api/problem'
import { useSessionStore } from '@/features/session/store'
import EmptyState from '@/shared/EmptyState.vue'
import MlAmount from '@/shared/MlAmount.vue'
import ProblemNotice from '@/shared/ProblemNotice.vue'
import { formatColones } from '@/shared/money'
import { soldOutDecants } from './availability'
import { useCartStore, type CartLine } from './store'

/**
 * The cart of a store (#130). "Crear pedido" sends `POST /tiendas/{tienda}/pedidos` with identities and quantities
 * only, and opens the order with the amounts that the API calculated. A 422 marks each line by its position
 * (`lineas.N.*`), and a 409 for missing ml marks the decants that ran out. Removing a line announces it and takes the
 * focus to the title, because the button that was used disappears.
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
const notice = ref('')
const heading = useTemplateRef<HTMLElement>('heading')

watch(tienda, (slug) => void cart.load(slug), { immediate: true })

/** A line as a screen reader says it, with "mililitros" in words. */
function spokenLine(line: CartLine): string {
  return `${line.perfume}, ${line.kind === 'bottle' ? 'botella' : 'decant'} de ${line.ml} mililitros`
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
  const line = lines.value[index]

  cart.remove(tienda.value, index)
  problem.value = null
  soldOut.value = new Set()
  notice.value = line ? `Se quitó del carrito: ${spokenLine(line)}.` : ''
  void nextTick(() => heading.value?.focus())
}

async function placeOrder(): Promise<void> {
  if (sending.value) {
    return
  }

  sending.value = true
  problem.value = null
  soldOut.value = new Set()
  notice.value = ''

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
  <div class="page cart">
    <h1 ref="heading" tabindex="-1">Carrito</h1>
    <!-- Always in the page, so screen readers announce what was removed. -->
    <p class="results-summary" role="status">{{ notice }}</p>

    <EmptyState v-if="lines.length === 0" message="Su carrito está vacío.">
      <RouterLink :to="{ name: 'storefront', params: { tienda } }" class="action-link">Ver la vitrina</RouterLink>
    </EmptyState>
    <template v-else>
      <ul class="cart__lines">
        <li v-for="(line, index) in lines" :key="index" class="card cart-line">
          <p :id="`linea-${index}`" class="cart-line__text">
            {{ line.perfume }} · {{ line.kind === 'bottle' ? 'botella' : 'decant' }} de <MlAmount :amount="line.ml" />
          </p>
          <p class="cart-line__price">{{ formatColones(line.price) }} en la vitrina</p>
          <div v-if="line.kind === 'bottle'" class="form-field cart-line__quantity">
            <label :for="`cantidad-${index}`">Cantidad de botellas</label>
            <input
              :id="`cantidad-${index}`"
              type="number"
              min="1"
              step="1"
              inputmode="numeric"
              :name="`lineas.${index}.cantidad`"
              :value="line.quantity"
              :aria-invalid="lineMessages(index).length > 0 ? 'true' : undefined"
              :aria-describedby="lineMessages(index).length > 0 ? `linea-${index} error-linea-${index}` : `linea-${index}`"
              @change="changeQuantity(index, $event)"
            />
          </div>
          <p v-if="lineMessages(index).length > 0" :id="`error-linea-${index}`" class="field-error cart-line__error">
            {{ lineMessages(index).join(' ') }}
          </p>
          <div class="form-actions">
            <!-- The visible text starts the name, which says which line it removes (WCAG 2.5.3). -->
            <button type="button" class="button--secondary" :aria-label="`Quitar ${spokenLine(line)}`" @click="remove(index)">
              Quitar
            </button>
          </div>
        </li>
      </ul>
      <div class="prose cart__note">
        <p>
          El subtotal y lo que se cobra ahora los calcula la tienda al crear el pedido, con los precios del momento.
          Después tendrá un tiempo para pagarlo: la página del pedido le dice hasta cuándo.
        </p>
      </div>
      <ProblemNotice v-if="problem" :problem="problem" />
      <div class="form-actions">
        <!-- aria-disabled and not disabled: the button keeps the focus while the order is created. -->
        <button
          v-if="session.isSignedIn"
          type="button"
          :aria-disabled="sending ? 'true' : undefined"
          @click="placeOrder"
        >
          {{ sending ? 'Creando el pedido…' : 'Crear pedido' }}
        </button>
        <RouterLink v-else :to="{ name: 'sign-in', query: { redirect: route.fullPath } }" class="button">
          Inicie sesión para crear el pedido
        </RouterLink>
      </div>
    </template>
  </div>
</template>
