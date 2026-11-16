import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import { api, setUnauthenticatedHandler } from '@/api/client'
import App from '@/App.vue'
import { errorRoutes } from '@/features/errors/routes'
import { homeRoutes } from '@/features/home/routes'
import { installSession } from '../install'
import { sessionRoutes } from '../routes'
import { STORAGE_KEY, useSessionStore } from '../store'
import { accountOf, fakeApi, json, problem, tokenResponse } from './fakeApi'

const IN_ONE_HOUR = new Date(Date.now() + 60 * 60 * 1000).toISOString()

async function start(path: string) {
  const pinia = createPinia()
  setActivePinia(pinia)

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      ...homeRoutes,
      ...sessionRoutes,
      { path: '/cuenta/citas', name: 'my-appointments', component: { render: () => null }, meta: { roles: ['cliente'] } },
      ...errorRoutes,
    ],
  })
  installSession(router)
  await router.push(path)

  const wrapper = mount(App, { global: { plugins: [pinia, router] }, attachTo: document.body })
  await flushPromises()

  return { wrapper, router }
}

beforeEach(() => {
  sessionStorage.clear()
})

afterEach(() => {
  useSessionStore().clear()
  setUnauthenticatedHandler(() => {})
  vi.unstubAllGlobals()
  document.body.innerHTML = ''
})

describe('session flow', () => {
  it('signs in from the guard and returns to the route that the visitor asked for', async () => {
    fakeApi({
      'POST /sesiones': () => tokenResponse('1|primero', IN_ONE_HOUR),
      'GET /cuenta': () => json(200, { data: accountOf('cliente') }),
    })
    const { wrapper, router } = await start('/cuenta/citas')
    expect(router.currentRoute.value.name).toBe('sign-in')

    await wrapper.get('#correo').setValue('ana@example.test')
    await wrapper.get('#contrasena').setValue('secreto-largo')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(router.currentRoute.value.fullPath).toBe('/cuenta/citas')
    expect(wrapper.get('.session-menu').text()).toContain('Ana Cliente')
  })

  it('shows the message of wrong credentials, clears the password and keeps the email', async () => {
    fakeApi({
      'POST /sesiones': () => problem(401, 'credenciales-invalidas', 'El correo o la contraseña no son correctos.'),
    })
    const { wrapper, router } = await start('/iniciar-sesion')

    await wrapper.get('#correo').setValue('ana@example.test')
    await wrapper.get('#contrasena').setValue('equivocada')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('sign-in')
    expect(wrapper.get('[role="alert"]').text()).toContain('El correo o la contraseña no son correctos.')
    expect((wrapper.get('#correo').element as HTMLInputElement).value).toBe('ana@example.test')
    expect((wrapper.get('#contrasena').element as HTMLInputElement).value).toBe('')
  })

  it('marks the fields of a 422 and focuses the first one', async () => {
    fakeApi({
      'POST /sesiones': () =>
        problem(422, 'datos-invalidos', 'Los datos enviados no son válidos.', {
          errores: { correo: ['El correo no tiene un formato válido.'] },
        }),
    })
    const { wrapper } = await start('/iniciar-sesion')

    await wrapper.get('#correo').setValue('ana')
    await wrapper.get('#contrasena').setValue('secreto-largo')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('#error-correo').text()).toBe('El correo no tiene un formato válido.')
    expect(wrapper.get('#correo').attributes('aria-invalid')).toBe('true')
    expect(document.activeElement).toBe(wrapper.get('#correo').element)
  })

  it('ends the session on a 401 and sends the account to the sign-in, to come back afterwards', async () => {
    sessionStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({ token: '1|revocado', expiresAt: IN_ONE_HOUR, account: accountOf('cliente') }),
    )
    fakeApi({ 'GET /cuenta': () => problem(401, 'no-autenticado', 'Debe iniciar sesión para realizar esta acción.') })
    const { router } = await start('/cuenta/citas')
    expect(router.currentRoute.value.name).toBe('my-appointments')

    await api.GET('/cuenta')
    await flushPromises()

    expect(useSessionStore().isSignedIn).toBe(false)
    expect(sessionStorage.getItem(STORAGE_KEY)).toBeNull()
    expect(router.currentRoute.value.name).toBe('sign-in')
    expect(router.currentRoute.value.query.redirect).toBe('/cuenta/citas')
  })

  it('signs out from the menu', async () => {
    sessionStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({ token: '1|guardado', expiresAt: IN_ONE_HOUR, account: accountOf('cliente') }),
    )
    const { requests } = fakeApi({ 'DELETE /sesiones/actual': () => new Response(null, { status: 204 }) })
    const { wrapper, router } = await start('/cuenta/citas')

    await wrapper.get('.session-menu button').trigger('click')
    await flushPromises()

    expect(requests[0]?.method).toBe('DELETE')
    expect(useSessionStore().isSignedIn).toBe(false)
    // The home view is loaded on demand, so the navigation takes a moment longer.
    await vi.waitFor(() => expect(router.currentRoute.value.name).toBe('home'))
    expect(wrapper.get('.session-menu').text()).toContain('Iniciar sesión')
  })
})
