import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'

import { fakeApi, json, problem } from '@/api/__tests__/fakeApi'
import { accountOf, tokenResponse } from '@/features/session/__tests__/accounts'
import { useSessionStore } from '@/features/session/store'
import { cleanUp, open, page, profile, STORE } from '@/features/storefront/__tests__/fixtures'

afterEach(cleanUp)

const IN_ONE_HOUR = new Date(Date.now() + 60 * 60 * 1000).toISOString()
const PASSWORD = 'una-frase-secreta-larga'

async function fill(wrapper: Awaited<ReturnType<typeof open>>['wrapper'], accept = true): Promise<void> {
  await wrapper.get('#nombre').setValue('Ana Cliente')
  await wrapper.get('#correo').setValue('ana@clientes.test')
  await wrapper.get('#contrasena').setValue(PASSWORD)

  if (accept) {
    await wrapper.get('#acepta_privacidad').setValue(true)
  }
}

/** Every value that this tab keeps in sessionStorage and localStorage. */
function storedText(): string {
  return [sessionStorage, localStorage]
    .flatMap((storage) => Object.keys(storage).map((key) => storage.getItem(key) ?? ''))
    .join('\n')
}

describe('"Crear cuenta"', () => {
  it('creates the account without a role or a store, signs in and returns to the route the visitor wanted', async () => {
    const { requests } = fakeApi({
      'POST /cuentas': () => json(201, { data: accountOf('cliente') }),
      'POST /sesiones': () => tokenResponse('1|nuevo', IN_ONE_HOUR),
      'GET /cuenta': () => json(200, { data: accountOf('cliente') }),
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
    })
    const { wrapper, router } = await open(`/crear-cuenta?redirect=/tiendas/${STORE}/carrito`)

    expect(wrapper.get('#acepta_privacidad').element).toHaveProperty('checked', false)

    await fill(wrapper)
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    await vi.waitFor(() => expect(router.currentRoute.value.name).toBe('cart'))

    const registration = requests.find((request) => new URL(request.url).pathname.endsWith('/cuentas'))
    expect(await registration?.json()).toEqual({
      nombre: 'Ana Cliente',
      correo: 'ana@clientes.test',
      contrasena: PASSWORD,
      acepta_privacidad: true,
    })
    expect(useSessionStore().isSignedIn).toBe(true)
    expect(storedText()).not.toContain(PASSWORD)
  })

  it('returns to the showcase when the visitor came from nowhere', async () => {
    fakeApi({
      'POST /cuentas': () => json(201, { data: accountOf('cliente') }),
      'POST /sesiones': () => tokenResponse('1|nuevo', IN_ONE_HOUR),
      'GET /cuenta': () => json(200, { data: accountOf('cliente') }),
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes`]: () => json(200, page([])),
    })
    const { wrapper, router } = await open('/crear-cuenta')

    await fill(wrapper)
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    await vi.waitFor(() => expect(router.currentRoute.value.name).toBe('storefront'))
  })

  it('names the page and says what each field is for before the visitor writes', async () => {
    fakeApi({})
    const { wrapper } = await open('/crear-cuenta')

    expect(document.title).toBe('Crear cuenta · ArchivumParfums')
    expect(wrapper.findAll('h1').map((title) => title.text())).toEqual(['Crear cuenta'])
    expect(wrapper.find('.register main').exists()).toBe(false)

    for (const field of ['nombre', 'correo', 'contrasena']) {
      const hint = wrapper.get(`#pista-${field}`)
      expect(wrapper.get(`#${field}`).attributes('aria-describedby')).toBe(`pista-${field}`)
      expect(hint.text()).not.toBe('')
    }
    // The password policy is the API's (#194): the hint gives advice and says where the 422 tells what is missing.
    const passwordHint = wrapper.get('#pista-contrasena').text()
    expect(passwordHint).toContain('Una frase de varias palabras')
    expect(passwordHint).toContain('se indica aquí qué le falta')
    expect(passwordHint).not.toMatch(/\d/)
  })

  it('shows the password to check it, and hides it again', async () => {
    fakeApi({})
    const { wrapper } = await open('/crear-cuenta')
    const password = wrapper.get('#contrasena')

    expect(password.attributes('type')).toBe('password')

    await wrapper.get('#mostrar_contrasena').setValue(true)
    expect(password.attributes('type')).toBe('text')
    expect(password.attributes('autocomplete')).toBe('new-password')

    await wrapper.get('#mostrar_contrasena').setValue(false)
    expect(password.attributes('type')).toBe('password')
  })

  it('does not send the form while the privacy box is not checked, and takes the focus to the box', async () => {
    const { requests } = fakeApi({})
    const { wrapper } = await open('/crear-cuenta')

    await fill(wrapper, false)
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(requests).toHaveLength(0)
    expect(wrapper.get('#error-acepta_privacidad').text()).toBe('Debe aceptar el aviso de privacidad para crear la cuenta.')
    expect(wrapper.get('#acepta_privacidad').attributes('aria-invalid')).toBe('true')
    expect(wrapper.get('#acepta_privacidad').attributes('aria-describedby')).toBe('aviso-privacidad error-acepta_privacidad')
    expect(document.activeElement?.id).toBe('acepta_privacidad')

    // The message goes away as soon as the box is checked.
    await wrapper.get('#acepta_privacidad').setValue(true)
    expect(wrapper.find('#error-acepta_privacidad').exists()).toBe(false)
    expect(wrapper.get('#acepta_privacidad').attributes('aria-invalid')).toBeUndefined()
    expect(wrapper.get('#acepta_privacidad').attributes('aria-describedby')).toBe('aviso-privacidad')
  })

  it('marks each field of a 422 and keeps what was written, except the password', async () => {
    fakeApi({
      'POST /cuentas': () =>
        problem(422, 'datos-invalidos', 'Los datos enviados no son válidos.', {
          errores: {
            correo: ['El correo ya está registrado.'],
            contrasena: ['La contraseña debe tener al menos 12 caracteres.'],
            acepta_privacidad: ['Debe aceptar el aviso de privacidad.'],
          },
        }),
    })
    const { wrapper } = await open('/crear-cuenta')

    await fill(wrapper)
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('#error-correo').text()).toBe('El correo ya está registrado.')
    expect(wrapper.get('#error-contrasena').text()).toBe('La contraseña debe tener al menos 12 caracteres.')
    expect(wrapper.get('#error-acepta_privacidad').text()).toBe('Debe aceptar el aviso de privacidad.')
    expect(wrapper.get('#acepta_privacidad').attributes('aria-invalid')).toBe('true')
    expect(wrapper.get('#correo').attributes('aria-describedby')).toBe('pista-correo error-correo')
    expect((wrapper.get('#nombre').element as HTMLInputElement).value).toBe('Ana Cliente')
    expect((wrapper.get('#correo').element as HTMLInputElement).value).toBe('ana@clientes.test')
    expect((wrapper.get('#contrasena').element as HTMLInputElement).value).toBe('')
    expect(document.activeElement?.id).toBe('correo')
  })

  it('sends one registration for a double click', async () => {
    let answer: (response: Response) => void = () => {}
    const { requests } = fakeApi({ 'POST /cuentas': () => new Promise<Response>((resolve) => (answer = resolve)) })
    const { wrapper } = await open('/crear-cuenta')

    await fill(wrapper)
    await wrapper.get('form').trigger('submit')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(requests).toHaveLength(1)
    // aria-disabled and not disabled: the button keeps the focus while it waits.
    expect(wrapper.get('button[type="submit"]').attributes('aria-disabled')).toBe('true')
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeUndefined()

    answer(problem(422, 'datos-invalidos', 'Los datos enviados no son válidos.', { errores: {} }))
    await flushPromises()

    expect(wrapper.get('button[type="submit"]').attributes('aria-disabled')).toBeUndefined()
  })

  it('opens the sign-in with the email written when the account exists but the sign-in fails', async () => {
    fakeApi({
      'POST /cuentas': () => json(201, { data: accountOf('cliente') }),
      'POST /sesiones': () => problem(503, 'servicio-no-disponible', 'El servicio no está disponible.'),
    })
    const { wrapper, router } = await open(`/crear-cuenta?redirect=/tiendas/${STORE}/carrito`)

    await fill(wrapper)
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    await vi.waitFor(() => expect(router.currentRoute.value.name).toBe('sign-in'))

    expect(router.currentRoute.value.query).toEqual({ redirect: `/tiendas/${STORE}/carrito` })
    expect(router.currentRoute.value.fullPath).not.toContain('ana@clientes.test')
    expect((wrapper.get('#correo').element as HTMLInputElement).value).toBe('ana@clientes.test')
    expect((wrapper.get('#contrasena').element as HTMLInputElement).value).toBe('')
    expect(wrapper.get('.sign-in .status-message').text()).toBe('Se creó su cuenta. Escriba su contraseña para iniciar sesión.')
  })

  it('is linked from the sign-in, keeping the route to return to', async () => {
    fakeApi({})
    const { wrapper } = await open('/iniciar-sesion?redirect=/cuenta/pedidos')

    expect(wrapper.get('.sign-in__register a').attributes('href')).toBe('/crear-cuenta?redirect=/cuenta/pedidos')
    expect(wrapper.find('.sign-in .status-message').exists()).toBe(false)
  })

  it('links to the sign-in, keeping the route to return to', async () => {
    fakeApi({})
    const { wrapper } = await open('/crear-cuenta?redirect=/cuenta/pedidos')

    expect(wrapper.get('.register__sign-in a').attributes('href')).toBe('/iniciar-sesion?redirect=/cuenta/pedidos')
  })
})
