import { afterEach, describe, expect, it, vi } from 'vitest'

import { api, createApiClient, setTokenProvider, type Problem } from '../client'

const baseUrl = 'https://api.example.test/api/v1'

function respondWith(status: number, body: unknown, contentType = 'application/json') {
  return vi.fn(async (request: Request) => {
    void request

    return new Response(JSON.stringify(body), {
      status,
      headers: { 'Content-Type': contentType },
    })
  })
}

function sentRequest(fetch: ReturnType<typeof respondWith>): Request {
  const request = fetch.mock.calls[0]?.[0]

  if (!request) {
    throw new Error('The client did not send any request.')
  }

  return request
}

describe('createApiClient', () => {
  it('sends the request to the API address with Accept: application/json', async () => {
    const fetch = respondWith(200, { nombre: 'ArchivumParfums', version: 'v1' })
    const client = createApiClient({ baseUrl, fetch })

    const { data, error } = await client.GET('/')

    expect(error).toBeUndefined()
    expect(data).toEqual({ nombre: 'ArchivumParfums', version: 'v1' })
    expect(sentRequest(fetch).url).toBe(`${baseUrl}/`)
    expect(sentRequest(fetch).headers.get('Accept')).toBe('application/json')
  })

  it('sends no token when nobody is signed in', async () => {
    const fetch = respondWith(200, { nombre: 'ArchivumParfums', version: 'v1' })
    const client = createApiClient({ baseUrl, fetch })

    await client.GET('/')

    expect(sentRequest(fetch).headers.has('Authorization')).toBe(false)
  })

  it('sends the token of the session as a bearer token', async () => {
    const fetch = respondWith(200, { nombre: 'ArchivumParfums', version: 'v1' })
    const client = createApiClient({ baseUrl, fetch, token: () => '1|token-de-prueba' })

    await client.GET('/')

    expect(sentRequest(fetch).headers.get('Authorization')).toBe('Bearer 1|token-de-prueba')
  })

  it('fills the path parameters and the query from the contract', async () => {
    const fetch = respondWith(200, { data: [] })
    const client = createApiClient({ baseUrl, fetch })

    await client.GET('/tiendas/{tienda}/perfumes', {
      params: { path: { tienda: 'archivum' }, query: { nota: 'vainilla' } },
    })

    expect(sentRequest(fetch).url).toBe(`${baseUrl}/tiendas/archivum/perfumes?nota=vainilla`)
  })

  it('sends the body as JSON', async () => {
    const fetch = respondWith(201, { token: '1|token-de-prueba' })
    const client = createApiClient({ baseUrl, fetch })

    await client.POST('/sesiones', {
      body: { correo: 'duena@example.test', contrasena: 'secreto', dispositivo: 'web' },
    })

    const request = sentRequest(fetch)
    expect(request.method).toBe('POST')
    expect(request.headers.get('Content-Type')).toBe('application/json')
    expect(await request.json()).toEqual({
      correo: 'duena@example.test',
      contrasena: 'secreto',
      dispositivo: 'web',
    })
  })

  it('returns an error response as the RFC 9457 problem', async () => {
    const problem: Problem = {
      type: 'https://archivum.test/problemas/no-encontrado',
      title: 'No encontrado',
      status: 404,
      detail: 'No existe la tienda solicitada.',
      instance: 'urn:uuid:0193b0d4-0000-7000-8000-000000000000',
    }
    const fetch = respondWith(404, problem, 'application/problem+json')
    const client = createApiClient({ baseUrl, fetch })

    const { data, error, response } = await client.GET('/tiendas/{tienda}', {
      params: { path: { tienda: 'no-existe' } },
    })

    expect(data).toBeUndefined()
    expect(response.status).toBe(404)
    expect(error).toEqual(problem)
  })
})

describe('api', () => {
  afterEach(() => {
    setTokenProvider(() => null)
    vi.unstubAllGlobals()
  })

  it('uses VITE_API_URL and the token provider that the session registers', async () => {
    const fetch = respondWith(200, { nombre: 'ArchivumParfums', version: 'v1' })
    vi.stubGlobal('fetch', fetch)
    setTokenProvider(() => '2|token-de-la-sesion')

    await api.GET('/')

    expect(sentRequest(fetch).url).toBe(`${import.meta.env.VITE_API_URL}/`)
    expect(sentRequest(fetch).headers.get('Authorization')).toBe('Bearer 2|token-de-la-sesion')
  })
})
