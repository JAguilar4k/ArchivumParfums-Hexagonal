import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

import { fakeApi, json, problem } from '@/api/__tests__/fakeApi'
import { DEVICE_NAME, RENEW_BEFORE_MS, STORAGE_KEY, useSessionStore } from '../store'
import { accountOf, tokenResponse } from './accounts'

const NOW = Date.parse('2026-11-16T12:00:00Z')
const IN_ONE_HOUR = '2026-11-16T13:00:00Z'

beforeEach(() => {
  vi.useFakeTimers({ now: NOW })
  setActivePinia(createPinia())
  sessionStorage.clear()
})

afterEach(() => {
  useSessionStore().clear()
  vi.useRealTimers()
  vi.unstubAllGlobals()
})

describe('signIn', () => {
  it('creates the token, loads the account and keeps them in sessionStorage without the password', async () => {
    const { requests } = fakeApi({
      'POST /sesiones': () => tokenResponse('1|primero', IN_ONE_HOUR),
      'GET /cuenta': () => json(200, { data: accountOf('dueno') }),
    })
    const session = useSessionStore()

    expect(await session.signIn('duena@example.test', 'secreto-largo')).toBeNull()

    expect(await requests[0]?.json()).toEqual({
      correo: 'duena@example.test',
      contrasena: 'secreto-largo',
      dispositivo: DEVICE_NAME,
    })
    expect(requests[1]?.headers.get('Authorization')).toBe('Bearer 1|primero')
    expect(session.isSignedIn).toBe(true)
    expect(session.role).toBe('dueno')
    expect(session.account?.tienda?.slug).toBe('archivum')

    const stored = sessionStorage.getItem(STORAGE_KEY) ?? ''
    expect(JSON.parse(stored)).toMatchObject({ token: '1|primero', expiresAt: IN_ONE_HOUR })
    expect(stored).not.toContain('secreto-largo')
  })

  it('returns the message of wrong credentials and keeps nobody signed in', async () => {
    fakeApi({
      'POST /sesiones': () => problem(401, 'credenciales-invalidas', 'El correo o la contraseña no son correctos.'),
    })
    const session = useSessionStore()

    const failure = await session.signIn('duena@example.test', 'otra')

    expect(failure).toMatchObject({ kind: 'unauthenticated', message: 'El correo o la contraseña no son correctos.' })
    expect(session.isSignedIn).toBe(false)
    expect(sessionStorage.getItem(STORAGE_KEY)).toBeNull()
  })
})

describe('signOut', () => {
  it('revokes the token in the API and forgets the session in the browser', async () => {
    const { requests } = fakeApi({
      'POST /sesiones': () => tokenResponse('1|primero', IN_ONE_HOUR),
      'GET /cuenta': () => json(200, { data: accountOf('cliente') }),
      'DELETE /sesiones/actual': () => new Response(null, { status: 204 }),
    })
    const session = useSessionStore()
    await session.signIn('ana@example.test', 'secreto-largo')

    await session.signOut()

    const revoke = requests[2]
    expect(revoke?.method).toBe('DELETE')
    expect(revoke?.headers.get('Authorization')).toBe('Bearer 1|primero')
    expect(session.token).toBeNull()
    expect(session.account).toBeNull()
    expect(sessionStorage.getItem(STORAGE_KEY)).toBeNull()
  })
})

describe('renewal', () => {
  it('renews the token before vence_en and keeps the new one', async () => {
    const { requests } = fakeApi({
      'POST /sesiones': () => tokenResponse('1|primero', IN_ONE_HOUR),
      'GET /cuenta': () => json(200, { data: accountOf('cliente') }),
      'POST /sesiones/actual/renovacion': () => tokenResponse('2|segundo', '2026-11-16T14:00:00Z'),
    })
    const session = useSessionStore()
    await session.signIn('ana@example.test', 'secreto-largo')

    await vi.advanceTimersByTimeAsync(60 * 60 * 1000 - RENEW_BEFORE_MS - 1)
    expect(requests).toHaveLength(2)

    await vi.advanceTimersByTimeAsync(1)

    expect(requests[2]?.headers.get('Authorization')).toBe('Bearer 1|primero')
    expect(session.token).toBe('2|segundo')
    expect(session.expiresAt).toBe('2026-11-16T14:00:00Z')
    expect(JSON.parse(sessionStorage.getItem(STORAGE_KEY) ?? '')).toMatchObject({ token: '2|segundo' })
  })

  it('ends the session when the token can no longer be renewed', async () => {
    fakeApi({
      'POST /sesiones': () => tokenResponse('1|primero', IN_ONE_HOUR),
      'GET /cuenta': () => json(200, { data: accountOf('cliente') }),
      'POST /sesiones/actual/renovacion': () => problem(401, 'no-autenticado', 'Debe iniciar sesión.'),
    })
    const session = useSessionStore()
    await session.signIn('ana@example.test', 'secreto-largo')

    await vi.advanceTimersByTimeAsync(60 * 60 * 1000)

    expect(session.isSignedIn).toBe(false)
    expect(sessionStorage.getItem(STORAGE_KEY)).toBeNull()
  })

  it('tries again when the renewal gets no response and the token is still valid', async () => {
    let attempts = 0
    fakeApi({
      'POST /sesiones': () => tokenResponse('1|primero', IN_ONE_HOUR),
      'GET /cuenta': () => json(200, { data: accountOf('cliente') }),
      'POST /sesiones/actual/renovacion': () => {
        attempts += 1

        if (attempts === 1) {
          throw new TypeError('Failed to fetch')
        }

        return tokenResponse('2|segundo', '2026-11-16T14:00:00Z')
      },
    })
    const session = useSessionStore()
    await session.signIn('ana@example.test', 'secreto-largo')

    await vi.advanceTimersByTimeAsync(60 * 60 * 1000 - RENEW_BEFORE_MS)
    expect(session.token).toBe('1|primero')

    await vi.advanceTimersByTimeAsync(30 * 1000)
    expect(session.token).toBe('2|segundo')
  })
})

describe('restore', () => {
  it('takes back the session of the tab after a reload', () => {
    sessionStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({ token: '1|guardado', expiresAt: IN_ONE_HOUR, account: accountOf('colaborador') }),
    )
    const session = useSessionStore()

    session.restore()

    expect(session.token).toBe('1|guardado')
    expect(session.role).toBe('colaborador')
  })

  it('forgets a stored session whose token already expired', () => {
    sessionStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({ token: '1|vencido', expiresAt: '2026-11-16T11:59:59Z', account: accountOf('cliente') }),
    )
    const session = useSessionStore()

    session.restore()

    expect(session.isSignedIn).toBe(false)
    expect(sessionStorage.getItem(STORAGE_KEY)).toBeNull()
  })
})
