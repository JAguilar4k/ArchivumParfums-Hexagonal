import { flushPromises } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'

import { fakeApi, json } from '@/api/__tests__/fakeApi'
import { openCartDrawer } from '@/features/cart/drawer'
import { useCartStore } from '@/features/cart/store'
import { cleanUp, open, page, plain, profile, STORE } from '@/features/storefront/__tests__/fixtures'
import { THEME_STORAGE_KEY } from '../useTheme'

function openFrame() {
  fakeApi({
    [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
    [`GET /tiendas/${STORE}/perfumes`]: () => json(200, page([])),
  })
  return open('/iniciar-sesion')
}

afterEach(() => {
  cleanUp()
  vi.unstubAllEnvs()
  delete document.documentElement.dataset.theme
})

describe('store frame', () => {
  it('starts with the system theme and stores a choice for the next page load', async () => {
    vi.stubGlobal('matchMedia', () => ({ matches: true, addEventListener: vi.fn(), removeEventListener: vi.fn() }))
    const { wrapper } = await openFrame()
    const theme = wrapper.get('button[aria-label="Activar el tema claro"]')
    expect(theme.attributes('aria-pressed')).toBe('true')
    expect(document.documentElement.dataset.theme).toBe('dark')

    await theme.trigger('click')
    expect(theme.attributes('aria-label')).toBe('Activar el tema oscuro')
    expect(theme.attributes('aria-pressed')).toBe('false')
    expect(localStorage.getItem(THEME_STORAGE_KEY)).toBe('light')
    wrapper.unmount()

    const { wrapper: reloaded } = await openFrame()
    expect(reloaded.get('button[aria-label="Activar el tema oscuro"]').attributes('aria-pressed')).toBe('false')
    expect(document.documentElement.dataset.theme).toBe('light')
    reloaded.unmount()
  })

  it('links to the configured WhatsApp chat in the footer and floating shortcut', async () => {
    vi.stubEnv('VITE_STORE_WHATSAPP', '50600000000')
    const { wrapper } = await openFrame()
    const link = wrapper.get('a[aria-label="Escribir a la tienda por WhatsApp"]')

    expect(link.attributes('href')).toBe('https://wa.me/50600000000')
    expect(link.attributes('rel')).toBe('noopener')
    expect(wrapper.get('.app-footer a[href="https://wa.me/50600000000"]').text()).toBe('Escribir por WhatsApp')
    wrapper.unmount()
  })

  it('omits both WhatsApp links when no number is set', async () => {
    vi.stubEnv('VITE_STORE_WHATSAPP', '')
    const { wrapper } = await openFrame()

    expect(wrapper.find('a[href^="https://wa.me/"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('opens the cart with the latest selection, changes a quantity, removes it and restores focus', async () => {
    const { wrapper } = await openFrame()
    const cart = useCartStore()
    const trigger = wrapper.get('button[aria-label="Carrito, 0 productos"]').element as HTMLButtonElement
    trigger.focus()

    cart.addBottle(STORE, { perfumeId: 'p1', perfume: 'Oud Nocturno', presentationId: 'p1-50', ml: 50, price: 9500 })
    openCartDrawer({ slug: STORE, message: 'Se agregó Oud Nocturno, botella de 50 ml.', trigger })
    await flushPromises()
    expect(trigger.getAttribute('aria-label')).toBe('Carrito, 1 producto')

    const drawer = wrapper.get('dialog.cart-drawer')
    const title = drawer.get('h2')
    expect(drawer.attributes('open')).toBeDefined()
    expect(document.activeElement).toBe(title.element)
    expect(title.text()).toBe('Su carrito (1)')
    expect(drawer.get('[role="status"]').text()).toContain('Se agregó Oud Nocturno, botella de 50 ml.')

    const reduce = drawer.get('button[aria-label^="Reducir cantidad"]')
    expect(reduce.attributes('aria-disabled')).toBe('true')
    await drawer.get('button[aria-label^="Aumentar cantidad"]').trigger('click')
    expect(title.text()).toBe('Su carrito (2)')
    expect(plain(drawer.get('.cart-drawer__subtotal').text())).toContain('₡19 000')
    await reduce.trigger('click')
    expect(title.text()).toBe('Su carrito (1)')

    await drawer.get('button[aria-label^="Quitar Oud Nocturno"]').trigger('click')
    expect(drawer.text()).toContain('Su carrito está vacío.')
    expect(drawer.get('[role="status"]').text()).toContain('Se quitó del carrito: Oud Nocturno')
    expect(document.activeElement).toBe(title.element)

    await drawer.trigger('cancel')
    expect(drawer.attributes('open')).toBeUndefined()
    expect(document.activeElement).toBe(trigger)
    wrapper.unmount()
  })
})
