import { afterEach, describe, expect, it, vi } from 'vitest'

import { fakeApi, json, problem } from '@/api/__tests__/fakeApi'
import { CART_STORAGE_PREFIX } from '@/features/cart/store'
import { open, plain, profile, STORE } from './fixtures'

afterEach(() => {
  vi.unstubAllGlobals()
  localStorage.clear()
  document.body.innerHTML = ''
})

const detail = {
  id: 'p-1',
  marca: 'Maison Archivum',
  nombre: 'Oud Nocturno',
  concentracion: 'Eau de Parfum',
  genero: 'Unisex',
  familia: 'Amaderada',
  descripcion: 'Un oud seco con <b>vainilla</b>.',
  foto_url: null,
  notas: { salida: ['Bergamota'], corazon: ['Rosa', 'Oud'], fondo: ['Vainilla', 'Ámbar'] },
  etiquetas: { momento: ['noche'], clima: ['frio', 'templado'], ocasion: ['cena'] },
  presentaciones: [{ id: 'p-1-100', ml: 100, precio_venta: 125000 }],
  decants: [{ botella_id: 'b-1', presentacion_ml: 100, ml: 5, precio: 9500 }],
}

describe('perfume detail', () => {
  it('shows the notes by layer, the tags, the presentations and the decants with their prices', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes/p-1`]: () => json(200, { data: detail }),
    })

    const { wrapper } = await open(`/tiendas/${STORE}/perfumes/p-1`)
    const text = plain(wrapper.get('.perfume-detail').text())

    expect(wrapper.get('.perfume-detail h1').text()).toBe('Maison Archivum Oud Nocturno')
    expect(text).toContain('ConcentraciónEau de Parfum')
    expect(text).toContain('Familia olfativaAmaderada')
    expect(text).toContain('SalidaBergamota')
    expect(text).toContain('CorazónRosa, Oud')
    expect(text).toContain('FondoVainilla, Ámbar')
    expect(text).toContain('Momento del díaNoche')
    expect(text).toContain('ClimaFrío, Templado')
    expect(text).toContain('Botella de 100 ml · ₡125 000')
    expect(text).toContain('Decant de 5 ml · ₡9 500')
    // The description is text: its markup is shown, never rendered.
    expect(text).toContain('<b>vainilla</b>')
    expect(wrapper.find('.perfume-detail b').exists()).toBe(false)
  })

  it('shows once the same decant of two open bottles of the same presentation', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes/p-1`]: () =>
        json(200, {
          data: {
            ...detail,
            decants: [
              { botella_id: 'b-1', presentacion_ml: 100, ml: 2, precio: 2500 },
              { botella_id: 'b-1', presentacion_ml: 100, ml: 5, precio: 6300 },
              { botella_id: 'b-2', presentacion_ml: 100, ml: 2, precio: 2500 },
              { botella_id: 'b-2', presentacion_ml: 100, ml: 5, precio: 6300 },
            ],
          },
        }),
    })

    const { wrapper } = await open(`/tiendas/${STORE}/perfumes/p-1`)

    expect(wrapper.findAll('.decants li > span').map((offer) => plain(offer.text()))).toEqual([
      'Decant de 2 ml · ₡2 500',
      'Decant de 5 ml · ₡6 300',
    ])
    expect(wrapper.findAll('.decants button').map((button) => button.attributes('aria-label'))).toEqual([
      'Agregar al carrito el decant de 2 mililitros',
      'Agregar al carrito el decant de 5 mililitros',
    ])
  })

  it('names the bottle of each decant when the open bottles are of different presentations, because the price comes from it', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes/p-1`]: () =>
        json(200, {
          data: {
            ...detail,
            presentaciones: [
              { id: 'p-1-50', ml: 50, precio_venta: 85000 },
              { id: 'p-1-100', ml: 100, precio_venta: 125000 },
            ],
            decants: [
              { botella_id: 'b-1', presentacion_ml: 50, ml: 2, precio: 3400 },
              { botella_id: 'b-1', presentacion_ml: 50, ml: 5, precio: 8500 },
              { botella_id: 'b-2', presentacion_ml: 100, ml: 2, precio: 2500 },
            ],
          },
        }),
    })

    const { wrapper } = await open(`/tiendas/${STORE}/perfumes/p-1`)

    expect(wrapper.findAll('.decants li > span').map((offer) => plain(offer.text()))).toEqual([
      'Decant de 2 ml de la botella de 50 ml · ₡3 400',
      'Decant de 2 ml de la botella de 100 ml · ₡2 500',
      'Decant de 5 ml de la botella de 50 ml · ₡8 500',
    ])

    const add = wrapper.get('[aria-label="Agregar al carrito el decant de 2 mililitros de la botella de 100 mililitros"]')
    await add.trigger('click')

    expect(plain(wrapper.get('.perfume-detail__added').text())).toContain(
      'Se agregó al carrito el decant de 2 ml de la botella de 100 ml.',
    )
    expect(JSON.parse(localStorage.getItem(CART_STORAGE_PREFIX + STORE) ?? '[]')).toEqual([
      { kind: 'decant', perfumeId: 'p-1', perfume: 'Maison Archivum Oud Nocturno', bottleId: 'b-2', ml: 2, price: 2500 },
    ])
  })

  it('names the tab after the perfume and says where the page is in the store', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes/p-1`]: () => json(200, { data: { ...detail, concentracion: 'EDP' } }),
    })

    const { wrapper } = await open(`/tiendas/${STORE}/perfumes/p-1`)
    const trail = wrapper.get('nav.breadcrumbs')

    expect(document.title).toBe('Maison Archivum Oud Nocturno · ArchivumParfums')
    expect(trail.attributes('aria-label')).toBe('Ubicación')
    expect(trail.get('a').attributes('href')).toBe(`/tiendas/${STORE}`)
    expect(trail.get('[aria-current="page"]').text()).toBe('Maison Archivum Oud Nocturno')
    // The abbreviation of the concentration is never alone.
    expect(wrapper.get('.perfume-detail').text()).toContain('ConcentraciónEau de Parfum (EDP)')
  })

  it('shows "No encontrado" for a perfume that is inactive or of another store', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes/ajeno`]: () => problem(404, 'no-encontrado', 'No existe el perfume solicitado.'),
    })

    const { wrapper } = await open(`/tiendas/${STORE}/perfumes/ajeno`)

    expect(wrapper.get('.perfume-detail h1').text()).toBe('No encontrado')
    expect(document.title).toBe('No encontrado · ArchivumParfums')
  })
})
