import { describe, expect, it } from 'vitest'

import {
  callApi,
  INTERNAL_MESSAGE,
  interpretException,
  interpretResponse,
  NETWORK_MESSAGE,
  NetworkError,
  parseRetryAfter,
  RATE_LIMITED_MESSAGE,
} from '../problem'

const requestId = '0193b0d4-0000-7000-8000-000000000001'

function problem(slug: string, status: number, extra: Record<string, unknown> = {}) {
  return {
    type: `https://archivum.test/problemas/${slug}`,
    title: 'Título del problema',
    status,
    detail: 'Detalle del problema.',
    instance: `urn:uuid:${requestId}`,
    ...extra,
  }
}

function response(status: number, headers: Record<string, string> = {}) {
  return new Response(null, {
    status,
    headers: { 'Content-Type': 'application/problem+json', ...headers },
  })
}

describe('interpretResponse', () => {
  it('reads a 422 datos-invalidos with the messages of each field', () => {
    const body = problem('datos-invalidos', 422, {
      detail: 'Los datos enviados no son válidos.',
      errores: {
        correo: ['El correo ya está registrado.'],
        'lineas.0.cantidad': ['La cantidad debe ser al menos 1.', 'La cantidad debe ser entera.'],
      },
    })

    expect(interpretResponse(response(422), body)).toEqual({
      kind: 'validation',
      status: 422,
      type: 'datos-invalidos',
      message: 'Los datos enviados no son válidos.',
      fieldErrors: {
        correo: ['El correo ya está registrado.'],
        'lineas.0.cantidad': ['La cantidad debe ser al menos 1.', 'La cantidad debe ser entera.'],
      },
      requestId,
      retryAfterSeconds: null,
    })
  })

  it('reads a 404 no-encontrado as the not-found kind with its detail', () => {
    const body = problem('no-encontrado', 404, { detail: 'No existe la tienda solicitada.' })

    expect(interpretResponse(response(404), body)).toMatchObject({
      kind: 'not-found',
      status: 404,
      type: 'no-encontrado',
      message: 'No existe la tienda solicitada.',
      requestId,
    })
  })

  it('reads a 409 conflicto with its detail', () => {
    const body = problem('conflicto', 409, { detail: 'Ese horario ya no está disponible.' })

    expect(interpretResponse(response(409), body)).toMatchObject({
      kind: 'conflict',
      type: 'conflicto',
      message: 'Ese horario ya no está disponible.',
    })
  })

  it('reads a 403 prohibido as the forbidden kind', () => {
    expect(interpretResponse(response(403), problem('prohibido', 403))).toMatchObject({
      kind: 'forbidden',
      status: 403,
    })
  })

  it('reads a 401 as the unauthenticated kind, which the session handles', () => {
    expect(interpretResponse(response(401), problem('no-autenticado', 401))).toMatchObject({
      kind: 'unauthenticated',
      status: 401,
    })
  })

  it('answers a 500 error-interno with the generic message and the request identifier', () => {
    const body = problem('error-interno', 500, { detail: 'select * from users' })

    expect(interpretResponse(response(500), body)).toMatchObject({
      kind: 'internal',
      status: 500,
      message: INTERNAL_MESSAGE,
      requestId,
    })
  })

  it('answers a response that is not problem+json with the generic message and the identifier of its header', () => {
    const html = new Response(null, {
      status: 502,
      headers: { 'Content-Type': 'text/html', 'X-Request-Id': requestId },
    })

    const result = interpretResponse(html, '<html><body>Bad Gateway</body></html>')

    expect(result).toMatchObject({ kind: 'internal', status: 502, message: INTERNAL_MESSAGE, requestId })
    expect(result.message).not.toContain('<html>')
  })

  it('does not trust a JSON body that is not problem+json', () => {
    const json = new Response(null, { status: 400, headers: { 'Content-Type': 'application/json' } })

    expect(interpretResponse(json, { message: 'Server Error' })).toMatchObject({
      kind: 'internal',
      message: INTERNAL_MESSAGE,
    })
  })

  it('says how long to wait on a 429, from Retry-After', () => {
    const body = problem('demasiadas-solicitudes', 429)

    expect(interpretResponse(response(429, { 'Retry-After': '30' }), body)).toMatchObject({
      kind: 'rate-limited',
      retryAfterSeconds: 30,
      message: 'Hizo demasiadas solicitudes. Intente de nuevo en 30 segundos.',
    })
    expect(interpretResponse(response(429, { 'Retry-After': '1' }), body).message).toBe(
      'Hizo demasiadas solicitudes. Intente de nuevo en 1 segundo.',
    )
    expect(interpretResponse(response(429, { 'Retry-After': '90' }), body).message).toBe(
      'Hizo demasiadas solicitudes. Intente de nuevo en 2 minutos.',
    )
  })

  it('falls back to the detail of a 429 without Retry-After', () => {
    const body = problem('demasiadas-solicitudes', 429, { detail: '', title: '' })

    expect(interpretResponse(response(429), body)).toMatchObject({
      kind: 'rate-limited',
      retryAfterSeconds: null,
      message: RATE_LIMITED_MESSAGE,
    })
  })

  it('shows the title when the problem brings no detail', () => {
    const body = problem('conflicto', 409, { detail: '', title: 'Conflicto' })

    expect(interpretResponse(response(409), body).message).toBe('Conflicto')
  })

  it('takes the identifier from the header when instance is not a urn:uuid', () => {
    const body = problem('conflicto', 409, { instance: '/api/v1/cuenta' })

    expect(interpretResponse(response(409, { 'X-Request-Id': requestId }), body).requestId).toBe(requestId)
  })
})

describe('interpretException', () => {
  it('answers a network failure with its own message and the identifier that the client sent', () => {
    expect(interpretException(new NetworkError(requestId))).toEqual({
      kind: 'network',
      status: null,
      type: null,
      message: NETWORK_MESSAGE,
      fieldErrors: {},
      requestId,
      retryAfterSeconds: null,
    })
  })

  it('answers any other exception as a network failure without identifier', () => {
    expect(interpretException(new TypeError('Failed to fetch'))).toMatchObject({
      kind: 'network',
      requestId: null,
    })
  })
})

describe('parseRetryAfter', () => {
  it('reads seconds and HTTP dates', () => {
    const now = Date.parse('2026-11-15T12:00:00Z')

    expect(parseRetryAfter('120', now)).toBe(120)
    expect(parseRetryAfter('Sun, 15 Nov 2026 12:00:45 GMT', now)).toBe(45)
    expect(parseRetryAfter('Sun, 15 Nov 2026 11:00:00 GMT', now)).toBe(0)
    expect(parseRetryAfter('pronto', now)).toBeNull()
    expect(parseRetryAfter(null, now)).toBeNull()
  })
})

describe('callApi', () => {
  it('returns the data of a successful call', async () => {
    const outcome = await callApi(async () => ({
      data: { nombre: 'ArchivumParfums' },
      response: new Response(null, { status: 200 }),
    }))

    expect(outcome).toEqual({ data: { nombre: 'ArchivumParfums' }, problem: null })
  })

  it('returns the problem of a failed call', async () => {
    const outcome = await callApi(async () => ({
      error: problem('conflicto', 409),
      response: response(409),
    }))

    expect(outcome.data).toBeNull()
    expect(outcome.problem).toMatchObject({ kind: 'conflict' })
  })

  it('returns a network problem instead of throwing', async () => {
    const outcome = await callApi(async () => {
      throw new NetworkError(requestId)
    })

    expect(outcome.problem).toMatchObject({ kind: 'network', requestId })
  })
})
