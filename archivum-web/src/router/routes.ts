import type { RouteRecordRaw } from 'vue-router'

import { appointmentRoutes } from '@/features/appointments/routes'
import { errorRoutes } from '@/features/errors/routes'
import { helpRoutes } from '@/features/help/routes'
import { orderRoutes } from '@/features/orders/routes'
import { registrationRoutes } from '@/features/registration/routes'
import { sessionRoutes } from '@/features/session/routes'
import { storefrontRoutes } from '@/features/storefront/routes'

// Each feature exports its own routes from src/features/<feature>/routes.ts.
// errorRoutes goes last, because it catches every path that nothing else matches.
export const routes: RouteRecordRaw[] = [
  ...storefrontRoutes,
  ...orderRoutes,
  ...appointmentRoutes,
  ...sessionRoutes,
  ...helpRoutes,
  ...registrationRoutes,
  ...errorRoutes,
]
