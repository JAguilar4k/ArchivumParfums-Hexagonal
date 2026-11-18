import { flushPromises } from '@vue/test-utils'
import { afterEach, expect, it } from 'vitest'

import { fakeApi, json } from '@/api/__tests__/fakeApi'
import { cleanUp, open, page, profile, STORE } from './fixtures'

afterEach(cleanUp)

it('shares one store layout and one profile request across all public store pages', async () => {
  const { requests } = fakeApi({
    [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
    [`GET /tiendas/${STORE}/perfumes`]: () => json(200, page([])),
    [`GET /tiendas/${STORE}/servicios`]: () => json(200, { data: [] }),
  })
  const { router, wrapper } = await open('/')
  const layout = router.currentRoute.value.matched[0]
  expect(router.currentRoute.value.name).toBe('store-home')

  for (const section of ['vitrina', 'recomendaciones', 'carrito', 'citas/nueva', 'vitrina']) {
    await router.push(`/tiendas/${STORE}/${section}`)
    await flushPromises()
    expect(router.currentRoute.value.matched[0]).toBe(layout)
    expect(wrapper.text()).not.toContain('Cargando la tienda')
  }

  const urls = requests.map(({ url }) => new URL(url))
  expect(urls.filter(({ pathname }) => pathname === `/api/v1/tiendas/${STORE}`)).toHaveLength(1)
  expect(urls.filter(({ searchParams }) => searchParams.get('por_pagina') === '100')).toHaveLength(1)
})
