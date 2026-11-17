import { afterEach, describe, expect, it } from 'vitest'
import { mount, type VueWrapper } from '@vue/test-utils'

import { fieldAttributes, interpretResponse } from '@/api/problem'
import ExampleForm from './ExampleForm.vue'

function validationProblem(errores: Record<string, string[]>) {
  const response = new Response(null, {
    status: 422,
    headers: { 'Content-Type': 'application/problem+json' },
  })

  return interpretResponse(response, {
    type: 'https://archivum.test/problemas/datos-invalidos',
    title: 'Datos inválidos',
    status: 422,
    detail: 'Los datos enviados no son válidos.',
    instance: 'urn:uuid:0193b0d4-0000-7000-8000-000000000003',
    errores,
  })
}

let wrapper: VueWrapper | null = null

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
})

describe('field errors of a 422', () => {
  it('marks each field of errores next to its control and focuses the first one of the form', async () => {
    wrapper = mount(ExampleForm, { attachTo: document.body })

    // errores lists contrasena before correo, but correo comes first in the form.
    await (wrapper.vm as unknown as { show: (p: unknown) => Promise<void> }).show(
      validationProblem({
        contrasena: ['La contraseña debe tener al menos 12 caracteres.'],
        correo: ['El correo ya está registrado.'],
      }),
    )

    const correo = wrapper.get('#correo')
    expect(correo.attributes('aria-invalid')).toBe('true')
    expect(wrapper.get(`#${correo.attributes('aria-describedby')}`).text()).toBe('El correo ya está registrado.')
    expect(wrapper.get('#contrasena').attributes('aria-describedby')).toBe('error-contrasena')
    expect(wrapper.get('#error-contrasena').text()).toBe('La contraseña debe tener al menos 12 caracteres.')
    expect(wrapper.get('#nombre').attributes('aria-invalid')).toBeUndefined()
    expect(wrapper.find('#error-nombre').exists()).toBe(false)
    expect(document.activeElement).toBe(correo.element)
  })
})

describe('fieldAttributes', () => {
  it('points to the help text of a field, and to its messages after it when it is invalid', () => {
    const problem = validationProblem({ correo: ['El correo ya está registrado.'] })

    expect(fieldAttributes(null, 'correo', 'pista-correo')).toEqual({ 'aria-describedby': 'pista-correo' })
    expect(fieldAttributes(problem, 'correo', 'pista-correo')).toEqual({
      'aria-invalid': 'true',
      'aria-describedby': 'pista-correo error-correo',
    })
    expect(fieldAttributes(problem, 'nombre')).toEqual({})
  })
})
