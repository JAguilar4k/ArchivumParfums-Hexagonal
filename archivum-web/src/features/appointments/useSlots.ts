import { ref, shallowRef } from 'vue'

import { api } from '@/api/client'
import { callApi, type ApiProblem } from '@/api/problem'
import type { Slot } from './appointment'
import { formatCalendarDay } from './time'

export interface SlotQuery {
  tienda: string
  servicio: string
  fecha: string
  /** Only the slots of this person; without it, every person who attends appointments. */
  persona?: string | null
}

/**
 * The free slots of one day (#129), always from `GET /tiendas/{tienda}/disponibilidad`: the browser never works them
 * out. `summary` says how many there are, for the status line that screen readers announce.
 */
export function useSlots() {
  const slots = shallowRef<Slot[]>([])
  const day = ref('')
  const loading = ref(false)
  const problem = ref<ApiProblem | null>(null)
  const summary = ref('')

  function clear(): void {
    slots.value = []
    day.value = ''
    problem.value = null
    summary.value = ''
  }

  async function load(query: SlotQuery): Promise<void> {
    loading.value = true
    problem.value = null

    const outcome = await callApi(() =>
      api.GET('/tiendas/{tienda}/disponibilidad', {
        params: {
          path: { tienda: query.tienda },
          query: { servicio: query.servicio, fecha: query.fecha, ...(query.persona ? { persona: query.persona } : {}) },
        },
      }),
    )

    loading.value = false

    if (outcome.problem) {
      clear()
      problem.value = outcome.problem

      return
    }

    slots.value = outcome.data.data
    day.value = query.fecha
    summary.value = summaryOf(slots.value.length, query.fecha)
  }

  return { slots, day, loading, problem, summary, load, clear }
}

function summaryOf(count: number, day: string): string {
  const words = formatCalendarDay(day)

  if (count === 0) {
    return `No hay horarios libres el ${words}. Pruebe con otro día.`
  }

  return count === 1 ? `Hay un horario libre el ${words}.` : `Hay ${count} horarios libres el ${words}.`
}
