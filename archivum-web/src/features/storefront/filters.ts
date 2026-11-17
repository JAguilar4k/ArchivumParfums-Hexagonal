import type { LocationQuery, LocationQueryRaw } from 'vue-router'

import type { operations } from '@/api/schema'

/** Query of `GET /tiendas/{tienda}/perfumes`, as the contract defines it. */
export type ShowcaseQuery = NonNullable<operations['catalog.showcase.index']['parameters']['query']>

export type Moment = NonNullable<ShowcaseQuery['momento']>
export type Climate = NonNullable<ShowcaseQuery['clima']>

export const MOMENTS: readonly Moment[] = ['dia', 'tarde', 'noche']
export const CLIMATES: readonly Climate[] = ['calido', 'templado', 'frio']

/**
 * What the visitor writes in the filters form; every field is text, as in the inputs, except the maximum price:
 * v-model turns what is written in an input of type number into a number.
 */
export interface FilterForm {
  texto: string
  familia: string
  nota: string
  momento: string
  clima: string
  ocasion: string
  precio_maximo: string | number
}

const TEXT_FILTERS = ['texto', 'familia', 'nota', 'ocasion'] as const

/** The form as the URL says, so a shared search opens with the same filters. */
export function formFromQuery(query: LocationQuery): FilterForm {
  return {
    texto: textOf(query.texto),
    familia: textOf(query.familia),
    nota: textOf(query.nota),
    momento: textOf(query.momento),
    clima: textOf(query.clima),
    ocasion: textOf(query.ocasion),
    precio_maximo: textOf(query.precio_maximo),
  }
}

/** The URL query of a search: only the filled filters, and always the first page. */
export function queryFromForm(form: FilterForm): LocationQueryRaw {
  const query: LocationQueryRaw = {}

  for (const [name, value] of Object.entries(form)) {
    const text = String(value).trim()

    if (text) {
      query[name] = text
    }
  }

  return query
}

/**
 * The query for the API, from the URL. A value the contract does not allow (an unknown moment or a price that is
 * not a number) goes as it is, so the API answers 422 and the form marks the field.
 */
export function showcaseQuery(query: LocationQuery): ShowcaseQuery {
  const result: ShowcaseQuery = {}

  for (const name of TEXT_FILTERS) {
    const text = textOf(query[name])

    if (text) {
      result[name] = text
    }
  }

  const momento = textOf(query.momento)
  const clima = textOf(query.clima)
  const precio = textOf(query.precio_maximo)
  const pagina = Number(textOf(query.pagina))

  if (momento) {
    result.momento = momento as Moment
  }

  if (clima) {
    result.clima = clima as Climate
  }

  if (precio) {
    result.precio_maximo = precio as unknown as number
  }

  if (Number.isInteger(pagina) && pagina > 1) {
    result.pagina = pagina
  }

  return result
}

function textOf(value: LocationQuery[string] | undefined): string {
  const first = Array.isArray(value) ? value[0] : value

  return typeof first === 'string' ? first : ''
}
