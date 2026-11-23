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
 * (`lineas.N.*`) and shows what it says of the whole list (`lineas`, such as too many lines), and a 409 for missing ml
 * marks the decants that ran out. A quantity below 1 stays written and marked until the customer fixes it, and the
 * order is not sent meanwhile. Removing a line announces it and takes the focus to the title, because the button that
 * was used disappears.
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
/** What the customer wrote in a quantity that is not a whole number from 1, by the position of its line. */
const invalidQuantities = ref<Record<number, string>>({})
const heading = useTemplateRef<HTMLElement>('heading')

const QUANTITY_MESSAGE = 'Escribe una cantidad de 1 botella o más. Para no pedir esta botella, usa «Quitar».'

watch(tienda, (slug) => void cart.load(slug), { immediate: true })

/** A line as a screen reader says it, with "mililitros" in words. */
function spokenLine(line: CartLine): string {
  return `${line.perfume}, ${line.kind === 'bottle' ? 'botella' : 'decant'} de ${line.ml} mililitros`
}

/**
 * The messages of a line: a quantity below 1, those of the 422 under `lineas.N`, and the mark of a decant that ran
 * out.
 */
function lineMessages(index: number): string[] {
  const prefix = `lineas.${index}.`
  const messages = Object.entries(problem.value?.fieldErrors ?? {})
    .filter(([field]) => field.startsWith(prefix))
    .flatMap(([, texts]) => texts)

  if (index in invalidQuantities.value) {
    messages.unshift(QUANTITY_MESSAGE)
  }

  return soldOut.value.has(index) ? [...messages, 'Ya no quedan ml suficientes en esta botella para este decant.'] : messages
}

/** What a 422 says of the whole list of lines, such as an order with more lines than the store takes. */
const listMessages = computed(() => problem.value?.fieldErrors.lineas ?? [])

/** The quantity that the field shows: what the customer wrote while it is not valid, and the one of the cart otherwise. */
function quantityShown(index: number, line: CartLine): string | number {
  return invalidQuantities.value[index] ?? (line.kind === 'bottle' ? line.quantity : 1)
}

function changeQuantity(index: number, event: Event): void {
  const written = (event.target as HTMLInputElement).value
  const quantity = Number(written)
  const invalid = { ...invalidQuantities.value }

  if (written.trim() !== '' && Number.isInteger(quantity) && quantity >= 1) {
    cart.setQuantity(tienda.value, index, quantity)
    delete invalid[index]
  } else {
    invalid[index] = written
  }

  invalidQuantities.value = invalid
}

function remove(index: number): void {
  const line = lines.value[index]

  cart.remove(tienda.value, index)
  problem.value = null
  soldOut.value = new Set()
  // The lines after it move up one place, so the marks by position no longer apply.
  invalidQuantities.value = {}
  notice.value = line ? `Se quitó del carrito: ${spokenLine(line)}.` : ''
  void nextTick(() => heading.value?.focus())
}

async function placeOrder(): Promise<void> {
  if (sending.value) {
    return
  }

  const firstInvalid = Object.keys(invalidQuantities.value).map(Number).sort((a, b) => a - b)[0]

  if (firstInvalid !== undefined) {
    // The field says what to fix; the order would otherwise go out with a quantity that the page does not show.
    document.getElementById(`cantidad-${firstInvalid}`)?.focus()

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

    <EmptyState v-if="lines.length === 0" message="Tu carrito está vacío.">
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
              :value="quantityShown(index, line)"
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
          Después tendrás un tiempo para pagarlo: la página del pedido te dice hasta cuándo.
        </p>
      </div>
      <ProblemNotice v-if="problem" :problem="problem">
        <p v-if="listMessages.length > 0" class="cart__error">{{ listMessages.join(' ') }}</p>
      </ProblemNotice>
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
