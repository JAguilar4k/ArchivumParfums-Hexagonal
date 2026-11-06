import createClient from 'openapi-fetch'

import { apiUrl } from '@/shared/config'
import type { components, paths } from './schema'

/** RFC 9457 problem that the API returns on every error (`application/problem+json`). */
export type Problem = components['schemas']['Problema']

/** Problem of a failed validation (422): `errores` holds the messages of each field. */
export type ValidationProblem = components['schemas']['ProblemaDeValidacion']

/** Returns the token of the current session, or null when nobody is signed in. */
export type TokenProvider = () => string | null

export interface ApiClientOptions {
  /** Base URL of the API, `apiUrl()` by default. */
  baseUrl?: string
  /** Token of the session to send as `Authorization: Bearer`. */
  token?: TokenProvider
  /** fetch implementation, replaced in the tests. */
  fetch?: (request: Request) => Promise<Response>
}

/**
 * HTTP client typed from openapi/v1.json: routes, parameters, bodies and
 * responses come from src/api/schema.ts, so a call the contract does not
 * allow fails `npm run type-check`.
 */
export function createApiClient(options: ApiClientOptions = {}) {
  const client = createClient<paths>({
    baseUrl: options.baseUrl ?? apiUrl(),
    headers: { Accept: 'application/json' },
    // Looks fetch up on every request, so the tests can replace it.
    fetch: options.fetch ?? ((request) => globalThis.fetch(request)),
  })

  const token = options.token ?? (() => null)

  client.use({
    onRequest({ request }) {
      const value = token()

      if (value) {
        request.headers.set('Authorization', `Bearer ${value}`)
      }

      return request
    },
  })

  return client
}

let sessionToken: TokenProvider = () => null

/**
 * Tells the client how to read the token of the current session. The session
 * (#126) registers it once; until then every request goes without a token.
 */
export function setTokenProvider(provider: TokenProvider): void {
  sessionToken = provider
}

/** Client that the whole application uses. */
export const api = createApiClient({ token: () => sessionToken() })
