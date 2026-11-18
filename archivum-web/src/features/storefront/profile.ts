import { computed, provide, watch, type ComputedRef, type InjectionKey } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import type { components } from '@/api/schema'
import { useCartStore } from '@/features/cart/store'
import { defaultBusinessSlug } from '@/shared/config'
import { setSiteName } from '@/shared/pageChanges'
import { useApiData } from '@/shared/useApiData'
import { cachedProfile, cachedShowcase, loadProfile, loadShowcase } from './publicStore'

export type StoreProfile = components['schemas']['PublicBusiness']

/** The profile shared by the store frame and views such as booking, which uses the store time zone. */
export const STORE_PROFILE: InjectionKey<ComputedRef<StoreProfile | null>> = Symbol('store-profile')
export const STORE_FAMILIES: InjectionKey<ComputedRef<string[]>> = Symbol('store-families')
export const STORE_FRAME: InjectionKey<ReturnType<typeof provideStoreFrame>> = Symbol('store-frame')

const familyQuery = { por_pagina: 100 }

export function provideStoreFrame() {
  const route = useRoute()
  const router = useRouter()
  const cart = useCartStore()
  const tienda = computed(() => typeof route.params.tienda === 'string' ? route.params.tienda : defaultBusinessSlug())
  const profile = useApiData(() => loadProfile(tienda.value), undefined, { cached: () => cachedProfile(tienda.value) })

  const familyPage = useApiData(() => {
    const cached = cachedShowcase(tienda.value, familyQuery)
    return cached ? Promise.resolve({ data: cached, problem: null }) : loadShowcase(tienda.value, familyQuery)
  }, undefined, { cached: () => cachedShowcase(tienda.value, familyQuery) })

  const publicProfile = computed(() => profile.data.value?.data ?? null)
  const families = computed(() => {
    if (publicProfile.value?.slug !== tienda.value) {
      return []
    }

    const available = familyPage.data.value?.data.flatMap(({ familia }) => familia ? [familia] : []) ?? []
    return [...new Set(available)].sort((first, second) => first.localeCompare(second, 'es'))
  })

  const frame = { tienda, profile, families }
  provide(STORE_PROFILE, publicProfile)
  provide(STORE_FAMILIES, families)
  provide(STORE_FRAME, frame)

  watch(tienda, (slug) => {
    cart.load(slug)

    if (router.hasRoute('storefront')) {
      void profile.reload()
    }
  }, { immediate: true })

  watch(publicProfile, (value) => {
    setSiteName(value?.nombre)

    if (value) {
      void familyPage.reload()
    }
  }, { immediate: true })

  return frame
}
