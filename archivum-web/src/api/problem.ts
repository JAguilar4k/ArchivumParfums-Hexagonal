import type { Problem, ValidationProblem } from './client'

/** Header that identifies each request, in the request and in the response (#45). */
export const REQUEST_ID_HEADER = 'X-Request-Id'

/** What went wrong, in the terms that the views act on. */
export type ProblemKind =
  | 'validation' // 422 datos-invalidos: the form marks each field
  | 'unauthenticated' // 401: the session takes over (#126)
  | 'forbidden' // 403: the "Sin permiso" view
  | 'not-found' // 404: the "No encontrado" view
  | 'conflict' // 409
  | 'rate-limited' // 429: wait as long as Retry-After says
  | 'internal' // 500 error-interno, or a response that is not problem+json
  | 'network' // no response at all
  | 'other' // any other problem of the API (400, 405, 503...)

/** A failed call of the API, ready to show. */
export interface ApiProblem {
  kind: ProblemKind
  /** HTTP status, or null when no response arrived. */
  status: number | null
  /** Last part of the problem type (`datos-invalidos`, `conflicto`...), or null. */
  type: string | null
  /** Text to show, in Spanish. Never the raw response. */
  message: string
  /** Messages of each field, from the `errores` member of a 422. */
  fieldErrors: Record<string, string[]>
  /** Identifier to give to support: from `instance`, or from the `X-Request-Id` header. */
  requestId: string | null
  /** Seconds to wait before trying again, from `Retry-After` (429 only). */
  retryAfterSeconds: number | null
}

export const INTERNAL_MESSAGE = 'Ocurrió un error inesperado. Intente de nuevo más tarde.'
export const NETWORK_MESSAGE = 'No se pudo conectar con el servidor. Revise su conexión e intente de nuevo.'
export const RATE_LIMITED_MESSAGE = 'Hizo demasiadas solicitudes. Intente de nuevo más tarde.'

/** Error that the client throws when a request gets no response (#127). */
export class NetworkError extends Error {
  constructor(
    /** The `X-Request-Id` that the client sent. */
    readonly requestId: string | null,
    /** What fetch threw. */
    readonly reason: unknown = null,
  ) {
    super('The request to the API got no response.')
    this.name = 'NetworkError'
  }
}

/** Identifier for a new request: a UUID, which the API accepts and keeps (#45). */
export function newRequestId(): string {
  return crypto.randomUUID()
}

/**
 * Explains a response that is not successful. `body` is the `error` of the
 * api client: the parsed JSON, or the text when it was not JSON.
 */
export function interpretResponse(response: Response, body: unknown): ApiProblem {
  const status = response.status
  const headerId = response.headers.get(REQUEST_ID_HEADER)
  const problem = isProblemJson(response) && isProblem(body) ? body : null

  if (!problem) {
    return build('internal', status, null, INTERNAL_MESSAGE, headerId)
  }

  const type = problemType(problem.type)
  const requestId = requestIdFromInstance(problem.instance) ?? headerId
  const message = problem.detail || problem.title

  if (status === 429) {
    const seconds = parseRetryAfter(response.headers.get('Retry-After'))
    const text = seconds === null ? message || RATE_LIMITED_MESSAGE : retryMessage(seconds)

    return { ...build('rate-limited', status, type, text, requestId), retryAfterSeconds: seconds }
  }

  if (type === 'error-interno' || status === 500) {
    return build('internal', status, type, INTERNAL_MESSAGE, requestId)
  }

  if (status === 422) {
    const errors = isValidationProblem(problem) ? problem.errores : {}

    return { ...build('validation', status, type, message, requestId), fieldErrors: onlyMessages(errors) }
  }

  return build(kindOf(status), status, type, message, requestId)
}

/** Explains an exception thrown by a call: no response arrived. */
export function interpretException(exception: unknown): ApiProblem {
  const requestId = exception instanceof NetworkError ? exception.requestId : null

  return build('network', null, null, NETWORK_MESSAGE, requestId)
}

/** Result of `callApi`: the data, or the problem that explains why there is none. */
export type ApiOutcome<T> = { data: T; problem: null } | { data: null; problem: ApiProblem }

/** What a call of the api client returns (`FetchResponse` of openapi-fetch). */
type ApiResult =
  | { data: unknown; error?: never; response: Response }
  | { data?: never; error: unknown; response: Response }

/** The data of the successful branch of an `ApiResult`. */
type SuccessData<R> = R extends { data: infer D; error?: never } ? D : never

/**
 * Runs a call of the api client and returns its data, or the problem to show.
 * It never throws: a network failure is a problem too.
 *
 * ```ts
 * const { data, problem } = await callApi(() => api.GET('/tiendas/{tienda}', { params }))
 * ```
 */
export async function callApi<R extends ApiResult>(
  call: () => Promise<R>,
): Promise<ApiOutcome<SuccessData<R>>> {
  try {
    const result = await call()

    if (result.response.ok) {
      return { data: result.data as SuccessData<R>, problem: null }
    }

    return { data: null, problem: interpretResponse(result.response, result.error) }
  } catch (exception) {
    return { data: null, problem: interpretException(exception) }
  }
}

/** Messages of a form field, or an empty list. */
export function fieldMessages(problem: ApiProblem | null, field: string): string[] {
  return problem?.fieldErrors[field] ?? []
}

/** Id of the element that shows the messages of a field. */
export function fieldErrorId(field: string): string {
  return `error-${field.replace(/[^A-Za-z0-9_-]/g, '-')}`
}

/** Attributes that tie a control to its messages, for screen readers. */
export function fieldAttributes(
  problem: ApiProblem | null,
  field: string,
): { 'aria-invalid'?: 'true'; 'aria-describedby'?: string } {
  if (fieldMessages(problem, field).length === 0) {
    return {}
  }

  return { 'aria-invalid': 'true', 'aria-describedby': fieldErrorId(field) }
}

/**
 * Moves the focus to the first control of the form, in the order of the page,
 * whose `name` has messages. Returns whether it found one.
 */
export function focusFirstInvalidField(form: HTMLFormElement, problem: ApiProblem): boolean {
  for (const element of Array.from(form.elements)) {
    const name = element.getAttribute('name')

    if (name && fieldMessages(problem, name).length > 0 && element instanceof HTMLElement) {
      element.focus()

      return true
    }
  }

  return false
}

/** Text of a 429: how long to wait, in Spanish. */
export function retryMessage(seconds: number): string {
  return `Hizo demasiadas solicitudes. Intente de nuevo en ${waitText(seconds)}.`
}

/** Seconds of `Retry-After`, which is either a number of seconds or an HTTP date. */
export function parseRetryAfter(value: string | null, now: number = Date.now()): number | null {
  const text = value?.trim()

  if (!text) {
    return null
  }

  if (/^\d+$/.test(text)) {
    return Number(text)
  }

  const date = Date.parse(text)

  return Number.isNaN(date) ? null : Math.max(0, Math.ceil((date - now) / 1000))
}

function waitText(seconds: number): string {
  if (seconds < 60) {
    const wait = Math.max(1, seconds)

    return wait === 1 ? '1 segundo' : `${wait} segundos`
  }

  const minutes = Math.ceil(seconds / 60)

  return minutes === 1 ? '1 minuto' : `${minutes} minutos`
}

function build(
  kind: ProblemKind,
  status: number | null,
  type: string | null,
  message: string,
  requestId: string | null,
): ApiProblem {
  return { kind, status, type, message, fieldErrors: {}, requestId, retryAfterSeconds: null }
}

function kindOf(status: number): ProblemKind {
  switch (status) {
    case 401:
      return 'unauthenticated'
    case 403:
      return 'forbidden'
    case 404:
      return 'not-found'
    case 409:
      return 'conflict'
    default:
      return 'other'
  }
}

function isProblemJson(response: Response): boolean {
  return response.headers.get('Content-Type')?.includes('application/problem+json') ?? false
}

function isProblem(body: unknown): body is Problem {
  if (typeof body !== 'object' || body === null) {
    return false
  }

  const candidate = body as Record<string, unknown>

  return (
    typeof candidate.type === 'string' &&
    typeof candidate.title === 'string' &&
    typeof candidate.status === 'number'
  )
}

function isValidationProblem(problem: Problem): problem is ValidationProblem {
  const errors = (problem as Partial<ValidationProblem>).errores

  return typeof errors === 'object' && errors !== null
}

/** Keeps only the fields whose value is a list of texts. */
function onlyMessages(errors: Record<string, unknown>): Record<string, string[]> {
  const messages: Record<string, string[]> = {}

  for (const [field, value] of Object.entries(errors)) {
    if (Array.isArray(value)) {
      const texts = value.filter((item): item is string => typeof item === 'string')

      if (texts.length > 0) {
        messages[field] = texts
      }
    }
  }

  return messages
}

/** `https://.../problemas/conflicto` → `conflicto`. */
function problemType(type: string): string | null {
  return type.split('/').filter(Boolean).pop() ?? null
}

/** `urn:uuid:<id>` → `<id>`. */
function requestIdFromInstance(instance: unknown): string | null {
  const prefix = 'urn:uuid:'

  return typeof instance === 'string' && instance.startsWith(prefix) ? instance.slice(prefix.length) : null
}
