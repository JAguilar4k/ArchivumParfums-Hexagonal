import { createApiClient } from '@/api/client'
import { callApi, type ApiOutcome } from '@/api/problem'
import { apiUrl } from '@/shared/config'
import type { ShowcaseQuery } from './filters'
import { importPerfume, importShowcase } from './storeViews'

// These endpoints return the same data for every visitor. Use a client without a session.
const publicApi = createApiClient()
const baseUrl = apiUrl()
const entryLimit = 150

function storePath(tienda: string): string {
  return `/tiendas/${encodeURIComponent(tienda)}`
}

function perfumePath(tienda: string, perfume: string): string {
  return `${storePath(tienda)}/perfumes/${encodeURIComponent(perfume)}`
}

function showcasePath(tienda: string, query: ShowcaseQuery = {}): string {
  const params = new URLSearchParams()

  for (const [field, value] of Object.entries(query)) {
    if (value !== undefined) {
      params.set(field, String(value))
    }
  }

  params.sort()
  const search = params.toString()
  return `${storePath(tienda)}/perfumes${search ? `?${search}` : ''}`
}

function publicRead<Args extends unknown[], T>(
  path: (...args: Args) => string,
  fetchData: (...args: Args) => Promise<ApiOutcome<T>>,
) {
  const values = new Map<string, T>()
  const pending = new Map<string, Promise<ApiOutcome<T>>>()

  function load(...args: Args): Promise<ApiOutcome<T>> {
    const url = baseUrl + path(...args)
    const current = pending.get(url)

    if (current) {
      return current
    }

    const request = fetchData(...args).then((outcome) => {
      if (pending.get(url) !== request) {
        return outcome
      }

      pending.delete(url)

      if (outcome.problem === null) {
        values.delete(url)
        values.set(url, outcome.data)

        const oldest = values.keys().next()
        if (values.size > entryLimit && !oldest.done) {
          values.delete(oldest.value)
        }
      } else if (outcome.problem.kind === 'not-found' || outcome.problem.kind === 'forbidden') {
        values.delete(url)
      }

      return outcome
    })

    pending.set(url, request)
    return request
  }

  function cached(...args: Args): T | null {
    return values.get(baseUrl + path(...args)) ?? null
  }

  function clear(): void {
    values.clear()
    pending.clear()
  }

  return { load, cached, clear }
}

const profiles = publicRead(storePath, (tienda) =>
  callApi(() => publicApi.GET('/tiendas/{tienda}', { params: { path: { tienda } }, credentials: 'omit' })),
)

const showcases = publicRead(showcasePath, (tienda, query) =>
  callApi(() => publicApi.GET('/tiendas/{tienda}/perfumes', { params: { path: { tienda }, query }, credentials: 'omit' })),
)

const perfumes = publicRead(perfumePath, (tienda, perfume) =>
  callApi(() =>
    publicApi.GET('/tiendas/{tienda}/perfumes/{perfume}', { params: { path: { tienda, perfume } }, credentials: 'omit' }),
  ),
)

const services = publicRead(
  (tienda: string) => `${storePath(tienda)}/servicios`,
  (tienda) => callApi(() => publicApi.GET('/tiendas/{tienda}/servicios', { params: { path: { tienda } }, credentials: 'omit' })),
)

const packages = publicRead(
  (tienda: string) => `${storePath(tienda)}/paquetes`,
  (tienda) => callApi(() => publicApi.GET('/tiendas/{tienda}/paquetes', { params: { path: { tienda } }, credentials: 'omit' })),
)

export const { load: loadProfile, cached: cachedProfile } = profiles
export const { load: loadShowcase, cached: cachedShowcase } = showcases
export const { load: loadPerfume, cached: cachedPerfume } = perfumes
export const { load: loadServices, cached: cachedServices } = services
export const { load: loadPackages, cached: cachedPackages } = packages

export async function prefetchShowcase(tienda: string, query?: ShowcaseQuery): Promise<void> {
  await Promise.allSettled([importShowcase(), cachedShowcase(tienda, query) ?? loadShowcase(tienda, query)])
}

export async function prefetchPerfume(tienda: string, perfume: string): Promise<void> {
  await Promise.allSettled([importPerfume(), cachedPerfume(tienda, perfume) ?? loadPerfume(tienda, perfume)])
}

export function clearPublicStoreCache(): void {
  for (const reads of [profiles, showcases, perfumes, services, packages]) {
    reads.clear()
  }
}
