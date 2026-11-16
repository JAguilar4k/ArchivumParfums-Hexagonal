import { afterEach, describe, expect, it, vi } from 'vitest'

import { fakeApi, json, problem } from '@/api/__tests__/fakeApi'
import { open, plain, profile, STORE } from './fixtures'

afterEach(() => {
  vi.unstubAllGlobals()
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
  presentaciones: [{ ml: 100, precio_venta: 125000 }],
  decants: [{ botella_id: 'b-1', ml: 5, precio: 9500 }],
}

describe('perfume detail', () => {
  it('shows the notes by layer, the tags, the presentations and the decants with their prices', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes/p-1`]: () => json(200, { data: detail }),
    })

    const { wrapper } = await open(`/tiendas/${STORE}/perfumes/p-1`)
    const text = plain(wrapper.get('.perfume-detail').text())

    expect(wrapper.get('.perfume-detail h2').text()).toBe('Maison Archivum Oud Nocturno')
    expect(text).toContain('SalidaBergamota')
    expect(text).toContain('CorazónRosa, Oud')
    expect(text).toContain('FondoVainilla, Ámbar')
    expect(text).toContain('MomentoNoche')
    expect(text).toContain('ClimaFrío, Templado')
    expect(text).toContain('Botella de 100 ml · ₡125 000')
    expect(text).toContain('Decant de 5 ml · ₡9 500')
    // The description is text: its markup is shown, never rendered.
    expect(text).toContain('<b>vainilla</b>')
    expect(wrapper.find('.perfume-detail b').exists()).toBe(false)
  })

  it('shows "No encontrado" for a perfume that is inactive or of another store', async () => {
    fakeApi({
      [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
      [`GET /tiendas/${STORE}/perfumes/ajeno`]: () => problem(404, 'no-encontrado', 'No existe el perfume solicitado.'),
    })

    const { wrapper } = await open(`/tiendas/${STORE}/perfumes/ajeno`)

    expect(wrapper.get('.perfume-detail h1').text()).toBe('No encontrado')
  })
})
