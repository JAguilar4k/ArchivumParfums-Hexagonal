import type { RouteRecordRaw } from 'vue-router'

/**
 * The trial appointments (#129). The booking lives under the header of its store and asks for no session until the
 * moment of booking; the appointments of the customer ask for a session with the role `cliente`, and the API also
 * checks that each one belongs to the account.
 */
export const appointmentRoutes: RouteRecordRaw[] = [
  {
    path: '/tiendas/:tienda/citas',
    component: () => import('@/features/storefront/StoreLayout.vue'),
    children: [
      {
        path: 'nueva',
        name: 'book-appointment',
        component: () => import('./BookingView.vue'),
        meta: { title: 'Reservar una cita de prueba' },
      },
    ],
  },
  {
    path: '/cuenta/citas',
    name: 'appointments',
    component: () => import('./AppointmentsView.vue'),
    props: { period: 'proximas' },
    meta: { roles: ['cliente'], title: 'Mis citas' },
  },
  {
    path: '/cuenta/citas/pasadas',
    name: 'past-appointments',
    component: () => import('./AppointmentsView.vue'),
    props: { period: 'pasadas' },
    meta: { roles: ['cliente'], title: 'Mis citas pasadas' },
  },
  {
    path: '/cuenta/citas/:cita',
    name: 'appointment',
    component: () => import('./AppointmentView.vue'),
    // The view replaces it with the day and the hour of the appointment once it arrives.
    meta: { roles: ['cliente'], title: 'Cita' },
  },
  {
    path: '/cuenta/citas/:cita/reprogramar',
    name: 'reschedule-appointment',
    component: () => import('./RescheduleView.vue'),
    meta: { roles: ['cliente'], title: 'Cambiar la hora de la cita' },
  },
]
