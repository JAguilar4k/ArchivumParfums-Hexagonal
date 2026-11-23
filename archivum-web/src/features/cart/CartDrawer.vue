<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import PerfumeArt from '@/features/storefront/PerfumeArt.vue'
import MlAmount from '@/shared/MlAmount.vue'
import SideDrawer from '@/shared/SideDrawer.vue'
import StoreIcon from '@/shared/StoreIcon.vue'
import { formatColones } from '@/shared/money'
import { cartDrawerRequest, closeCartDrawer } from './drawer'
import { useCartStore, type CartLine } from './store'

const cart = useCartStore()
const route = useRoute()
const notice = ref('')
const open = computed({
  get: () => cartDrawerRequest.value !== null,
  set: (visible) => { if (!visible) closeCartDrawer() },
})
const slug = computed(() => cartDrawerRequest.value?.slug ?? '')
const lines = computed(() => cart.linesOf(slug.value))
const count = computed(() => cart.count(slug.value))
const subtotal = computed(() => lines.value.reduce((total, line) => total + lineTotal(line), 0))

watch(cartDrawerRequest, (request) => {
  if (request) cart.load(request.slug)
  notice.value = request?.message ?? ''
}, { immediate: true })

watch(() => route.path, closeCartDrawer)

function lineTotal(line: CartLine): number {
  return line.price * (line.kind === 'bottle' ? line.quantity : 1)
}

function lineDescription(line: CartLine): string {
  const kind = line.kind === 'bottle' ? 'botella' : 'decant'
  return `${line.perfume}, ${kind} de ${line.ml} mililitros`
}

function remove(index: number, line: CartLine): void {
  cart.remove(slug.value, index)
  notice.value = `Se quitó del carrito: ${lineDescription(line)}.`
  void nextTick(() => document.getElementById('cart-drawer-title')?.focus())
}
</script>

<template>
  <SideDrawer
    v-model:open="open"
    class="cart-drawer"
    :title="`Tu carrito (${count})`"
    heading-id="cart-drawer-title"
    close-label="Cerrar el carrito"
    v-bind="cartDrawerRequest?.trigger ? { trigger: cartDrawerRequest.trigger } : {}"
  >
    <p class="cart-drawer__notice" role="status">{{ notice }}</p>
    <p v-if="!lines.length" class="cart-drawer__empty">Tu carrito está vacío.</p>
    <ul v-else class="cart-drawer__lines">
      <li v-for="(line, index) in lines" :key="index" class="cart-drawer__line">
        <PerfumeArt :id="line.perfumeId" :family="line.family ?? null" :photo="line.photo ?? null" class="cart-drawer__art" />
        <div class="cart-drawer__product">
          <p class="cart-drawer__name">{{ line.perfume }}</p>
          <p class="cart-drawer__variant">
            {{ line.kind === 'bottle' ? 'Botella' : 'Decant' }} de <MlAmount :amount="line.ml" />
          </p>
        </div>
        <div class="cart-drawer__line-actions">
          <div v-if="line.kind === 'bottle'" class="cart-drawer__quantity" role="group" :aria-label="`Cantidad de ${lineDescription(line)}`">
            <button
              type="button"
              class="icon-button"
              :aria-label="`Reducir cantidad de ${lineDescription(line)}`"
              :aria-disabled="line.quantity === 1 ? 'true' : undefined"
              @click="cart.setQuantity(slug, index, line.quantity - 1)"
            ><StoreIcon name="minus" :size="16" /></button>
            <span>{{ line.quantity }}</span>
            <button
              type="button"
              class="icon-button"
              :aria-label="`Aumentar cantidad de ${lineDescription(line)}`"
              @click="cart.setQuantity(slug, index, line.quantity + 1)"
            ><StoreIcon name="plus" :size="16" /></button>
          </div>
          <span v-else class="cart-drawer__variant">1 unidad</span>
          <strong>{{ formatColones(lineTotal(line)) }}</strong>
        </div>
        <button
          type="button"
          class="cart-drawer__remove"
          :aria-label="`Quitar ${lineDescription(line)}`"
          @click="remove(index, line)"
        >Quitar</button>
      </li>
    </ul>
    <template #footer>
      <div class="cart-drawer__checkout">
        <template v-if="lines.length">
          <p class="cart-drawer__subtotal"><span>Subtotal</span><strong>{{ formatColones(subtotal) }}</strong></p>
          <RouterLink :to="{ name: 'cart', params: { tienda: slug } }" class="button" @click="closeCartDrawer">
            Hacer el pedido
          </RouterLink>
        </template>
        <button type="button" class="button--secondary" @click="closeCartDrawer">Seguir viendo</button>
      </div>
    </template>
  </SideDrawer>
</template>

<style scoped>
.cart-drawer__notice {
  padding: 0.75rem 1.25rem;
  background: var(--color-success-background);
  color: var(--color-success);
  font-weight: 600;
}

.cart-drawer__notice:empty { padding: 0; }
.cart-drawer__empty { padding: 1.25rem; }

.cart-drawer__lines {
  padding: 0 1.25rem;
  list-style: none;
}

.cart-drawer__line {
  display: grid;
  grid-template-columns: clamp(2.75rem, 18vw, 4rem) minmax(0, 1fr);
  align-items: start;
  gap: 0 0.75rem;
  padding-block: 1rem 0.5rem;
  border-bottom: 1px solid var(--color-divider);
}

.cart-drawer__art {
  aspect-ratio: 1;
  border-radius: 0.75rem;
  overflow: hidden;
}

.cart-drawer__art :deep(svg) {
  max-height: 3rem;
  max-width: 75%;
}

.cart-drawer__name { font-weight: 700; }
.cart-drawer__variant { color: var(--color-text-muted); font-size: 0.9375rem; }

.cart-drawer__line-actions,
.cart-drawer__subtotal {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.25rem 0.5rem;
}

.cart-drawer__line-actions,
.cart-drawer__remove { grid-column: 2; }

.cart-drawer__quantity {
  display: inline-flex;
  align-items: center;
  border: 1px solid var(--color-border);
  border-radius: var(--button-radius);
  font-weight: 600;
}

.cart-drawer__quantity button { min-width: var(--target-size); }

.cart-drawer__remove {
  justify-self: start;
  padding-inline: 0.25rem;
  border-color: transparent;
  background: transparent;
  color: var(--color-link);
  font-size: 0.875rem;
  text-decoration: underline;
}

.cart-drawer__remove:hover {
  border-color: transparent;
  background: var(--color-surface-alt);
}

.cart-drawer__checkout {
  display: grid;
  gap: 0.75rem;
}

.cart-drawer__checkout .button { min-height: 3.25rem; }
</style>
