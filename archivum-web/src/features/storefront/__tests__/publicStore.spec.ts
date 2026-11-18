import { describe, expect, it } from 'vitest'

import { setTokenProvider } from '@/api/client'
import { fakeApi, json, problem } from '@/api/__tests__/fakeApi'
import { useApiData } from '@/shared/useApiData'
import { cachedPerfume, cachedShowcase, loadPerfume, loadShowcase, prefetchPerfume } from '../publicStore'

const tienda = 'demostracion'
const perfume = 'perfume-demo'
const catalogPath = `/tiendas/${tienda}/perfumes`
const detailPath = `${catalogPath}/${perfume}`

describe('public store reads', () => {
  it('isolates cache entries by store, filters and page and sends public requests without a session', async () => {
    const { requests } = fakeApi({ [`GET ${catalogPath}`]: () => json(200, { data: [] }) })
    setTokenProvider(() => '1|demo')

    try {
      await loadShowcase(tienda, { familia: 'Floral', pagina: 2, por_pagina: 12 })

      expect(cachedShowcase(tienda, { por_pagina: 12, familia: 'Floral', pagina: 2 })).toEqual({ data: [] })
      expect(cachedShowcase(tienda, { familia: 'Floral', pagina: 3, por_pagina: 12 })).toBeNull()
      expect(cachedShowcase(tienda, { familia: 'Acuática', pagina: 2, por_pagina: 12 })).toBeNull()
      expect(cachedShowcase('otra-tienda', { familia: 'Floral', pagina: 2, por_pagina: 12 })).toBeNull()
      expect(requests[0]?.headers.has('Authorization')).toBe(false)
      expect(requests[0]?.credentials).toBe('omit')
    } finally {
      setTokenProvider(() => null)
    }
  })

  it('combines concurrent prefetches and revalidates when the page reads cached data', async () => {
    let reads = 0
    const { requests } = fakeApi({
      [`GET ${detailPath}`]: () => json(200, { data: { nombre: ++reads === 1 ? 'Día floral' : 'Tarde floral' } }),
    })

    await Promise.all([prefetchPerfume(tienda, perfume), prefetchPerfume(tienda, perfume)])
    expect(requests).toHaveLength(1)

    const detail = useApiData(() => loadPerfume(tienda, perfume), undefined, { cached: () => cachedPerfume(tienda, perfume) })
    const revalidation = detail.reload()
    expect(detail.data.value?.data.nombre).toBe('Día floral')
    expect(detail.loading.value).toBe(false)

    await revalidation
    expect(detail.data.value?.data.nombre).toBe('Tarde floral')
    expect(requests).toHaveLength(2)
  })

  it('invalidates a previously public perfume when the API returns 404', async () => {
    let available = true
    fakeApi({
      [`GET ${detailPath}`]: () => available ? json(200, { data: {} }) : problem(404, 'no-encontrado', 'No existe.'),
    })

    await loadPerfume(tienda, perfume)
    available = false

    const result = await loadPerfume(tienda, perfume)
    expect(result.problem?.kind).toBe('not-found')
    expect(cachedPerfume(tienda, perfume)).toBeNull()
  })

  it('keeps the previous results until the latest filter arrives and ignores an older response', async () => {
    const responses = new Map<string, (response: Response) => void>()
    fakeApi({
      [`GET ${catalogPath}`]: (request) => {
        const filter = new URL(request.url).searchParams.get('texto')
        return filter ? new Promise((resolve) => responses.set(filter, resolve)) : json(200, { data: [{ id: 'original' }] })
      },
    })

    let texto = ''
    const results = useApiData(() => loadShowcase(tienda, { texto }), undefined, {
      cached: () => cachedShowcase(tienda, { texto }),
      keepPrevious: true,
    })
    await results.reload()

    texto = 'anterior'
    const older = results.reload()
    texto = 'actual'
    const latest = results.reload()
    expect(results.data.value?.data[0]?.id).toBe('original')
    expect(results.loading.value).toBe(true)

    await Promise.resolve()
    responses.get('actual')?.(json(200, { data: [{ id: 'nuevo' }] }))
    await latest
    responses.get('anterior')?.(json(200, { data: [{ id: 'anterior' }] }))
    await older

    expect(results.data.value?.data[0]?.id).toBe('nuevo')
    expect(results.loading.value).toBe(false)
  })
})
