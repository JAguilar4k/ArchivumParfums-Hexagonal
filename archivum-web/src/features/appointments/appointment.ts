import type { components } from '@/api/schema'
import { formatDay, formatHour, isAhead } from './time'

export type Appointment = components['schemas']['CustomerAppointmentResource']
export type Service = components['schemas']['PublicService']
export type Slot = components['schemas']['AvailableSlotResource']

/** A person or a perfume as the API names it inside an appointment or a free slot. */
export interface Named {
  id: string
  nombre: string
}

function isNamed(item: unknown): item is Named {
  return (
    typeof item === 'object' &&
    item !== null &&
    'id' in item &&
    typeof item.id === 'string' &&
    'nombre' in item &&
    typeof item.nombre === 'string'
  )
}

/**
 * The people of a free slot or the perfumes of an appointment. The contract describes them only as a list, so each
 * item is checked before it is shown.
 */
export function namedItems(items: readonly unknown[]): Named[] {
  return items.filter(isNamed)
}

const STATUS_LABELS: Record<string, string> = {
  confirmada: 'Confirmada',
  apartada: 'Apartada para usted',
  cancelada: 'Cancelada',
  atendida: 'Atendida',
  no_asistio: 'No asistió',
  vencida: 'Vencida',
}

/** The state of an appointment as the API gives it, written for people: "no_asistio" is "No asistió". */
export function statusLabel(status: string): string {
  return STATUS_LABELS[status] ?? status.replace(/_/g, ' ')
}

/** The name of an appointment in the pages: its day and hour in the store. The page of the appointment takes it. */
export function appointmentTitle(appointment: Pick<Appointment, 'inicia_en'>): string {
  return `Cita del ${formatDay(appointment.inicia_en)}, ${formatHour(appointment.inicia_en)}`
}

/**
 * The text of the link to an appointment in the list of the customer. A customer may cancel an appointment and book the
 * same hour again, so every state but "confirmada" joins the name and the two links read differently (WCAG 2.4.9).
 */
export function appointmentLinkText(appointment: Pick<Appointment, 'estado' | 'inicia_en'>): string {
  const title = appointmentTitle(appointment)

  return appointment.estado === 'confirmada' ? title : `${title}, ${statusLabel(appointment.estado).toLowerCase()}`
}

/**
 * Whether the customer may still ask to move or cancel the appointment: it is active and has not started. The deadline
 * of the store is a rule of the API, which answers 409 with its explanation; the page does not repeat it.
 */
export function canChange(appointment: Pick<Appointment, 'estado' | 'inicia_en'>, now: Date = new Date()): boolean {
  return ['confirmada', 'apartada'].includes(appointment.estado) && isAhead(appointment.inicia_en, now)
}

/** Who attends a slot, in words: "Ana Solís", "Ana Solís o Luis Mora". */
export function peopleOf(slot: Slot): string {
  const names = namedItems(slot.personas).map((person) => person.nombre)

  return names.length <= 1 ? (names[0] ?? '') : `${names.slice(0, -1).join(', ')} o ${names[names.length - 1]}`
}
