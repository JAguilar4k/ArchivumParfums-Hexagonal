import { describe, expectTypeOf, it } from 'vitest'

import { createApiClient, type Problem, type ValidationProblem } from '../client'

// These checks run when `npm run type-check` compiles the tests. Each
// expected-error directive marks a call that the contract must reject: if the
// contract stopped rejecting it, the directive would be unused and
// type-check would fail.
describe('contract types', () => {
  const client = createApiClient({ baseUrl: 'https://api.example.test/api/v1' })

  it('reject a route that does not exist', () => {
    expectTypeOf(() =>
      // @ts-expect-error: openapi/v1.json has no /no-existe route.
      client.GET('/no-existe'),
    ).toBeFunction()
  })

  it('reject a body without a required field', () => {
    expectTypeOf(() =>
      client.POST('/sesiones', {
        // @ts-expect-error: StoreSessionRequest requires `contrasena`.
        body: { correo: 'duena@example.test', dispositivo: 'web' },
      }),
    ).toBeFunction()
  })

  it('reject reading an attribute the response does not have', () => {
    expectTypeOf(async () => {
      const { data } = await client.GET('/')

      // @ts-expect-error: the response of GET / has no `tienda` attribute.
      return data?.tienda
    }).toBeFunction()
  })

  it('type the error responses as RFC 9457 problems', () => {
    expectTypeOf(async () => {
      const { error } = await client.POST('/sesiones', {
        body: { correo: 'duena@example.test', contrasena: 'secreto', dispositivo: 'web' },
      })

      return error
    }).returns.resolves.toEqualTypeOf<Problem | ValidationProblem | undefined>()
  })
})
