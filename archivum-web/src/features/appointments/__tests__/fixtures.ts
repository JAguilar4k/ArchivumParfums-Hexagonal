import type { components } from '@/api/schema'
import { STORE } from '@/features/storefront/__tests__/fixtures'

export const SERVICE_ID = '0193b0d4-0000-7000-8000-000000000301'
export const ANA = { id: '0193b0d4-0000-7000-8000-000000000311', nombre: 'Ana Solís' }
export const LUIS = { id: '0193b0d4-0000-7000-8000-000000000312', nombre: 'Luis Mora' }
export const APPOINTMENT_ID = '0193b0d4-0000-7000-8000-000000000321'
export const PERFUME = { id: '0193b0d4-0000-7000-8000-000000000331', nombre: 'Ámbar Nocturno' }

/** A Saturday in the clock of the store, far enough ahead for the appointment to admit changes. */
export const DAY = '2030-11-23'

export function serviceOf(overrides: Partial<components['schemas']['PublicService']> = {}) {
  return { id: SERVICE_ID, nombre: 'Prueba de olores', duracion_minutos: 45, precio: 0, maximo_perfumes: 5, ...overrides }
}

/** A free slot of DAY, from the hour to 45 minutes later, with the offset of Costa Rica. */
export function slotAt(hour: string, people = [ANA, LUIS]) {
  const [hours = '0', minutes = '0'] = hour.split(':')
  const end = new Date(Date.UTC(2000, 0, 1, Number(hours), Number(minutes) + 45))
  const endHour = `${String(end.getUTCHours()).padStart(2, '0')}:${String(end.getUTCMinutes()).padStart(2, '0')}`

  return { inicia_en: `${DAY}T${hour}:00-06:00`, termina_en: `${DAY}T${endHour}:00-06:00`, personas: people }
}

export function appointmentOf(overrides: Partial<components['schemas']['CustomerAppointmentResource']> = {}) {
  return {
    id: APPOINTMENT_ID,
    tienda: { id: '0193b0d4-0000-7000-8000-000000000030', nombre: 'ArchivumParfums', slug: STORE },
    estado: 'confirmada',
    servicio: { id: SERVICE_ID, nombre: 'Prueba de olores' },
    persona: LUIS,
    inicia_en: `${DAY}T09:00:00-06:00`,
    termina_en: `${DAY}T09:45:00-06:00`,
    lugar_cita: 'Escazú, San Rafael, 200 m norte del parque central',
    perfumes: [PERFUME],
    ...overrides,
  }
}

export function showcaseOf(perfumes: Array<{ id: string; nombre: string }>) {
  return perfumes.map((perfume) => ({
    id: perfume.id,
    marca: 'Maison Archivum',
    nombre: perfume.nombre,
    concentracion: 'Eau de Parfum',
    familia: 'Amaderada',
    foto_url: null,
    etiquetas: { momento: [], clima: [], ocasion: [] },
    presentaciones: [],
  }))
}
