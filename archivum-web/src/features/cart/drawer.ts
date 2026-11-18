import { shallowRef } from 'vue'

interface CartDrawerRequest {
  slug: string
  message?: string
  trigger?: HTMLElement
}

export const cartDrawerRequest = shallowRef<CartDrawerRequest | null>(null)

export function openCartDrawer(request: CartDrawerRequest): void {
  cartDrawerRequest.value = request
}

export function closeCartDrawer(): void {
  cartDrawerRequest.value = null
}
