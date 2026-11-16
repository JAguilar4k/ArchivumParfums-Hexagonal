import { api } from '@/api/client'
import { callApi } from '@/api/problem'
import type { CartLine } from './store'

/**
 * The positions of the decants that the showcase no longer offers (#130). A 409 of the order says that a bottle ran
 * out of ml without naming the line, so the cart reads the detail of each perfume again: the showcase offers a decant
 * only while its bottle has those ml free (#128). A perfume that the showcase hides marks all its decants; one whose
 * detail could not be read marks none.
 */
export async function soldOutDecants(tienda: string, lines: readonly CartLine[]): Promise<Set<number>> {
  const perfumes = [...new Set(lines.filter((line) => line.kind === 'decant').map((line) => line.perfumeId))]
  const offers = new Map<string, Set<string>>()

  await Promise.all(
    perfumes.map(async (perfume) => {
      const outcome = await callApi(() =>
        api.GET('/tiendas/{tienda}/perfumes/{perfume}', { params: { path: { tienda, perfume } } }),
      )

      if (outcome.data) {
        offers.set(perfume, new Set(outcome.data.data.decants.map((decant) => `${decant.botella_id}:${decant.ml}`)))
      } else if (outcome.problem.kind === 'not-found') {
        offers.set(perfume, new Set())
      }
    }),
  )

  const soldOut = new Set<number>()

  lines.forEach((line, index) => {
    const offered = line.kind === 'decant' ? offers.get(line.perfumeId) : undefined

    if (line.kind === 'decant' && offered && !offered.has(`${line.bottleId}:${line.ml}`)) {
      soldOut.add(index)
    }
  })

  return soldOut
}
