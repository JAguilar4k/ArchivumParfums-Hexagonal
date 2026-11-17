import type { ComputedRef, InjectionKey } from 'vue'

import type { components } from '@/api/schema'

export type StoreProfile = components['schemas']['PublicBusiness']

/**
 * The profile of the store that StoreLayout loads (#47), for the pages under it: the booking of an appointment (#129)
 * reads the time zone of the store from it.
 */
export const STORE_PROFILE: InjectionKey<ComputedRef<StoreProfile | null>> = Symbol('store-profile')
