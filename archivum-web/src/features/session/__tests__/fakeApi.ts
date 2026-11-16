import { vi } from 'vitest'

import type { Account } from '../store'

type Handler = (request: Request) => Response | Promise<Response>

/**
 * Replaces fetch with the answers of each `METHOD /path` (without /api/v1),
 * and records every request. A request without an answer fails the test.
 */
export function fakeApi(routes: Record<string, Handler>) {
  const requests: Request[] = []
  const fetch = vi.fn(async (request: Request) => {
    requests.push(request)

    const path = new URL(request.url).pathname.replace(/^\/api\/v1/, '')
    const handler = routes[`${request.method} ${path}`]

    if (!handler) {
      throw new Error(`Unexpected request: ${request.method} ${path}`)
    }

    return handler(request)
  })

  vi.stubGlobal('fetch', fetch)

  return { fetch, requests }
}

export function json(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

export function problem(status: number, slug: string, detail: string, extra: Record<string, unknown> = {}): Response {
  return new Response(
    JSON.stringify({
      type: `https://archivum.test/problemas/${slug}`,
      title: 'Problema',
      status,
      detail,
      instance: 'urn:uuid:0193b0d4-0000-7000-8000-000000000010',
      ...extra,
    }),
    { status, headers: { 'Content-Type': 'application/problem+json' } },
  )
}

export function tokenResponse(token: string, expiresAt: string): Response {
  return json(201, { data: { token, tipo: 'Bearer', vence_en: expiresAt } })
}

export function accountOf(rol: Account['rol']): Account {
  return {
    id: '0193b0d4-0000-7000-8000-000000000020',
    nombre: rol === 'cliente' ? 'Ana Cliente' : 'Dana Dueña',
    correo: 'cuenta@example.test',
    rol,
    tienda: rol === 'cliente' ? null : { id: '0193b0d4-0000-7000-8000-000000000030', nombre: 'ArchivumParfums', slug: 'archivum' },
  }
}
