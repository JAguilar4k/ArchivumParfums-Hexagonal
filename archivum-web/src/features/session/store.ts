import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

import { api, setTokenProvider } from '@/api/client'
import { callApi, type ApiProblem } from '@/api/problem'
import type { components } from '@/api/schema'

/** The account that owns the token, from `GET /cuenta`. */
export type Account = components['schemas']['CurrentAccountResource']

export type Role = Account['rol']

/** Key of the session in sessionStorage. */
export const STORAGE_KEY = 'archivum.session'

/** Name of this device in the tokens of the account. */
export const DEVICE_NAME = 'archivum-web'

/** The token is renewed this long before `vence_en`. */
export const RENEW_BEFORE_MS = 5 * 60 * 1000

/** When a renewal fails without a 401, it is tried again after this long. */
export const RENEW_RETRY_MS = 30 * 1000

interface StoredSession {
  token: string
  expiresAt: string
  account: Account
}

/**
 * The session of the browser (#126): the token, when it expires and the
 * account with its role and store. The token lives in sessionStorage, never in
 * the URL or in the logs, and it is renewed before it expires. The role only
 * guides the navigation: the API decides the permissions (#102, #104).
 */
export const useSessionStore = defineStore('session', () => {
  const token = ref<string | null>(null)
  const expiresAt = ref<string | null>(null)
  const account = ref<Account | null>(null)
  let renewalTimer: ReturnType<typeof setTimeout> | null = null

  const isSignedIn = computed(() => token.value !== null && account.value !== null)
  const role = computed<Role | null>(() => account.value?.rol ?? null)

  // Every request of the api client carries the token of this session.
  setTokenProvider(() => token.value)

  /** Signs in and loads the account. Returns the problem when it fails. */
  async function signIn(correo: string, contrasena: string): Promise<ApiProblem | null> {
    const created = await callApi(() =>
      api.POST('/sesiones', { body: { correo, contrasena, dispositivo: DEVICE_NAME } }),
    )

    if (created.problem) {
      return created.problem
    }

    token.value = created.data.data.token
    expiresAt.value = created.data.data.vence_en

    const current = await callApi(() => api.GET('/cuenta'))

    if (current.problem) {
      clear()

      return current.problem
    }

    account.value = current.data.data
    save()
    scheduleRenewal()

    return null
  }

  /** Revokes the token in the API and forgets the session in the browser. */
  async function signOut(): Promise<void> {
    if (token.value) {
      await callApi(() => api.DELETE('/sesiones/actual'))
    }

    clear()
  }

  /** Swaps the token for a new one while it is still valid. */
  async function renew(): Promise<void> {
    if (!token.value) {
      return
    }

    const renewed = await callApi(() => api.POST('/sesiones/actual/renovacion'))

    if (renewed.problem) {
      // An expired or revoked token cannot be renewed: the session is over.
      if (renewed.problem.kind === 'unauthenticated' || hasExpired()) {
        clear()
      } else {
        renewalTimer = setTimeout(() => void renew(), RENEW_RETRY_MS)
      }

      return
    }

    token.value = renewed.data.data.token
    expiresAt.value = renewed.data.data.vence_en
    save()
    scheduleRenewal()
  }

  /** Takes back the session of this tab after a reload, unless it expired. */
  function restore(): void {
    const stored = read()

    if (!stored || Date.parse(stored.expiresAt) <= Date.now()) {
      clear()

      return
    }

    token.value = stored.token
    expiresAt.value = stored.expiresAt
    account.value = stored.account
    scheduleRenewal()
  }

  /** Forgets the token and the account, in memory and in sessionStorage. */
  function clear(): void {
    if (renewalTimer) {
      clearTimeout(renewalTimer)
      renewalTimer = null
    }

    token.value = null
    expiresAt.value = null
    account.value = null

    try {
      sessionStorage.removeItem(STORAGE_KEY)
    } catch {
      // Storage may be unavailable (private mode): there is nothing to remove.
    }
  }

  function scheduleRenewal(): void {
    if (renewalTimer) {
      clearTimeout(renewalTimer)
    }

    const delay = Date.parse(expiresAt.value ?? '') - Date.now() - RENEW_BEFORE_MS
    renewalTimer = setTimeout(() => void renew(), Math.max(0, delay))
  }

  function hasExpired(): boolean {
    return !expiresAt.value || Date.parse(expiresAt.value) <= Date.now()
  }

  function save(): void {
    if (!token.value || !expiresAt.value || !account.value) {
      return
    }

    const stored: StoredSession = { token: token.value, expiresAt: expiresAt.value, account: account.value }

    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(stored))
    } catch {
      // Without storage the session lasts until the tab reloads.
    }
  }

  function read(): StoredSession | null {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY)

      return raw ? (JSON.parse(raw) as StoredSession) : null
    } catch {
      return null
    }
  }

  return { token, expiresAt, account, isSignedIn, role, signIn, signOut, renew, restore, clear }
})
