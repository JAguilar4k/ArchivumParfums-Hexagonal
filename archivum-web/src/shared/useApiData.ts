import { computed, ref, shallowRef, type Ref, type ShallowRef } from 'vue'

import type { ApiOutcome, ApiProblem } from '@/api/problem'

export interface ApiData<T> {
  data: ShallowRef<T | null>
  problem: Ref<ApiProblem | null>
  loading: Ref<boolean>
  /** The data arrived and `isEmpty` says there is nothing to show. */
  empty: Ref<boolean>
  reload: () => Promise<void>
}

/**
 * The three states of a view that asks the API for data (#126): loading, error
 * and empty, for LoadingState, ErrorState and EmptyState.
 *
 * ```ts
 * const perfumes = useApiData(
 *   () => callApi(() => api.GET('/tiendas/{tienda}/perfumes', { params })),
 *   (page) => page.data.length === 0,
 * )
 * onMounted(perfumes.reload)
 * ```
 */
export function useApiData<T>(
  load: () => Promise<ApiOutcome<T>>,
  isEmpty: (data: T) => boolean = () => false,
): ApiData<T> {
  const data = shallowRef<T | null>(null)
  const problem = ref<ApiProblem | null>(null)
  const loading = ref(false)
  const empty = computed(() => data.value !== null && isEmpty(data.value))

  async function reload(): Promise<void> {
    loading.value = true
    problem.value = null

    const outcome = await load()

    data.value = outcome.data
    problem.value = outcome.problem
    loading.value = false
  }

  return { data, problem, loading, empty, reload }
}
