import { vi } from 'vitest'

import { clearPublicStoreCache } from '@/features/storefront/publicStore'

type Handler = (request: Request) => Response | Promise<Response>

/**
 * Replaces fetch with the answers of each `METHOD /path` (without /api/v1),
 * and records every request. A request without an answer fails as a network
 * failure, and its message names it.
 */
export function fakeApi(routes: Record<string, Handler>) {
  clearPublicStoreCache()
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

export function problem(
  status: number,
  slug: string,
  detail: string,
  extra: Record<string, unknown> = {},
  headers: Record<string, string> = {},
): Response {
  return new Response(
    JSON.stringify({
      type: `https://archivum.test/problemas/${slug}`,
      title: 'Problema',
      status,
      detail,
      instance: 'urn:uuid:0193b0d4-0000-7000-8000-000000000010',
      ...extra,
    }),
    { status, headers: { 'Content-Type': 'application/problem+json', ...headers } },
  )
}
