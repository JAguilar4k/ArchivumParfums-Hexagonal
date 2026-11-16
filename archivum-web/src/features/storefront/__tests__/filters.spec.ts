import { describe, expect, it } from 'vitest'

import { formFromQuery, queryFromForm, showcaseQuery } from '../filters'

describe('filters', () => {
  it('fill the form from the URL, so a shared search opens with its filters', () => {
    expect(formFromQuery({ texto: 'oud', momento: 'noche', precio_maximo: '90000', otra: 'x' })).toEqual({
      texto: 'oud',
      familia: '',
      nota: '',
      momento: 'noche',
      clima: '',
      ocasion: '',
      precio_maximo: '90000',
    })
  })

  it('put in the URL only the filled filters, trimmed, starting again at the first page', () => {
    const form = { ...formFromQuery({}), texto: '  oud ', clima: 'frio', precio_maximo: '90000' }

    expect(queryFromForm(form)).toEqual({ texto: 'oud', clima: 'frio', precio_maximo: '90000' })
  })

  it('send to the API the filters of the URL and the page after the first one', () => {
    expect(
      showcaseQuery({ texto: 'oud', familia: 'Amaderada', nota: 'vainilla', ocasion: 'cena', momento: 'noche', clima: 'frio', precio_maximo: '90000', pagina: '2' }),
    ).toEqual({
      texto: 'oud',
      familia: 'Amaderada',
      nota: 'vainilla',
      ocasion: 'cena',
      momento: 'noche',
      clima: 'frio',
      precio_maximo: '90000',
      pagina: 2,
    })
    expect(showcaseQuery({ pagina: '1' })).toEqual({})
    expect(showcaseQuery({ pagina: 'muchas' })).toEqual({})
  })
})
