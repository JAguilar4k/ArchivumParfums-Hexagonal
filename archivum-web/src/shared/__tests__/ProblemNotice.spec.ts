import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'

import { interpretException, interpretResponse, NetworkError, type ApiProblem } from '@/api/problem'
import ProblemNotice from '../ProblemNotice.vue'

const requestId = '0193b0d4-0000-7000-8000-000000000002'

function problemOf(slug: string, status: number, detail = 'Detalle del problema.'): ApiProblem {
  const response = new Response(null, {
    status,
    headers: { 'Content-Type': 'application/problem+json' },
  })

  return interpretResponse(response, {
    type: `https://archivum.test/problemas/${slug}`,
    title: 'Título',
    status,
    detail,
    instance: `urn:uuid:${requestId}`,
  })
}

function mountNotice(problem: ApiProblem) {
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/', component: {} }] })

  return mount(ProblemNotice, { props: { problem }, global: { plugins: [router] } })
}

describe('ProblemNotice', () => {
  it('shows the detail of the problem in an accessible notice, never the raw JSON', () => {
    const wrapper = mountNotice(problemOf('conflicto', 409, 'Ese horario ya no está disponible.'))

    const notice = wrapper.get('[role="alert"]')
    expect(notice.text()).toContain('Ese horario ya no está disponible.')
    expect(wrapper.text()).not.toContain('"type"')
    expect(wrapper.text()).not.toContain(requestId)
  })

  it('shows the generic message and the identifier for support on an internal error', () => {
    const wrapper = mountNotice(problemOf('error-interno', 500))

    expect(wrapper.get('[role="alert"]').text()).toContain('Ocurrió un error inesperado.')
    expect(wrapper.get('code').text()).toBe(requestId)
  })

  it('shows the identifier that the client sent on a network failure', () => {
    const wrapper = mountNotice(interpretException(new NetworkError(requestId)))

    expect(wrapper.text()).toContain('No se pudo conectar con el servidor.')
    expect(wrapper.get('code').text()).toBe(requestId)
  })

  it('shows how long to wait on a 429', () => {
    const response = new Response(null, {
      status: 429,
      headers: { 'Content-Type': 'application/problem+json', 'Retry-After': '45' },
    })
    const problem = interpretResponse(response, {
      type: 'https://archivum.test/problemas/demasiadas-solicitudes',
      title: 'Demasiadas solicitudes',
      status: 429,
      detail: 'Superó el límite de solicitudes.',
      instance: `urn:uuid:${requestId}`,
    })

    expect(mountNotice(problem).get('[role="alert"]').text()).toContain('Intente de nuevo en 45 segundos.')
  })

  it('shows the "Sin permiso" view on a 403', () => {
    const wrapper = mountNotice(problemOf('prohibido', 403))

    expect(wrapper.get('h1').text()).toBe('Sin permiso')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('shows the "No encontrado" view on a 404', () => {
    const wrapper = mountNotice(problemOf('no-encontrado', 404))

    expect(wrapper.get('h1').text()).toBe('No encontrado')
  })
})
