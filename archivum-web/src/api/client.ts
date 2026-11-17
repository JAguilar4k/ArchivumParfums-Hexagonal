import createClient from 'openapi-fetch'

import { apiUrl } from '@/shared/config'
import { NetworkError, newRequestId, REQUEST_ID_HEADER } from './problem'
import type { components, paths } from './schema'

/** RFC 9457 problem that the API returns on every error (`application/problem+json`). */
export type Problem = components['schemas']['Problema']

/** Problem of a failed validation (422): `errores` holds the messages of each field. */
export type ValidationProblem = components['schemas']['ProblemaDeValidacion']

/** Returns the token of the current session, or null when nobody is signed in. */
export type TokenProvider = () => string | null

/** Called when the API answers 401 to a request that carried a token. */
export type UnauthenticatedHandler = () => void

export interface ApiClientOptions {
  /** Base URL of the API, `apiUrl()` by default. */
  baseUrl?: string
  /** Token of the session to send as `Authorization: Bearer`. */
  token?: TokenProvider
  /** What to do when the token stops working: the session signs out (#126). */
  onUnauthenticated?: UnauthenticatedHandler
  /** fetch implementation, replaced in the tests. */
  fetch?: (request: Request) => Promise<Response>
}

/**
 * HTTP client typed from openapi/v1.json: routes, parameters, bodies and
 * responses come from src/api/schema.ts, so a call the contract does not
 * allow fails `npm run type-check`.
 *
 * Every request carries its own `X-Request-Id`, so the client knows the
 * identifier even when no response arrives; a request without a response
 * throws `NetworkError` with it (#127).
 */
export function createApiClient(options: ApiClientOptions = {}) {
  const client = createClient<paths>({
    baseUrl: options.baseUrl ?? apiUrl(),
    headers: { Accept: 'application/json' },
    // Looks fetch up on every request, so the tests can replace it.
    fetch: options.fetch ?? ((request) => globalThis.fetch(request)),
  })

  const token = options.token ?? (() => null)
  const onUnauthenticated = options.onUnauthenticated ?? (() => {})

  client.use({
    onRequest({ request }) {
      const value = token()

      if (value) {
        request.headers.set('Authorization', `Bearer ${value}`)
      }

      if (!request.headers.has(REQUEST_ID_HEADER)) {
        request.headers.set(REQUEST_ID_HEADER, newRequestId())
      }

      return request
    },
    onResponse({ request, response }) {
      // A 401 to a request without a token (a wrong password) is not an expired session.
      if (response.status === 401 && request.headers.has('Authorization')) {
        onUnauthenticated()
      }

      return undefined
    },
    onError({ request, error }) {
      // A request that the view cancelled on purpose is not a network failure.
      if (error instanceof DOMException && error.name === 'AbortError') {
        return undefined
      }

      return new NetworkError(request.headers.get(REQUEST_ID_HEADER), error)
    },
  })

  return client
}

let sessionToken: TokenProvider = () => null
let sessionEnded: UnauthenticatedHandler = () => {}

/**
 * Tells the client how to read the token of the current session. The session
 * (#126) registers it once; until then every request goes without a token.
 */
export function setTokenProvider(provider: TokenProvider): void {
  sessionToken = provider
}

/**
 * Tells the client what to do when the API rejects the token with a 401: an
 * expired or revoked token can no longer be renewed, so the session ends (#126).
 */
export function setUnauthenticatedHandler(handler: UnauthenticatedHandler): void {
  sessionEnded = handler
}

/** Client that the whole application uses. */
export const api = createApiClient({
  token: () => sessionToken(),
  onUnauthenticated: () => sessionEnded(),
})
