import { ref } from 'vue'

/**
 * What the page of an appointment says when it opens right after the customer booked it or changed its hour (#129).
 * The page that did it leaves the message here before it navigates, and the page of that appointment takes it once.
 */
const pending = ref<{ appointmentId: string; message: string } | null>(null)

export function leaveNotice(appointmentId: string, message: string): void {
  pending.value = { appointmentId, message }
}

/** The message left for this appointment, only once; an empty text when there is none. */
export function takeNotice(appointmentId: string): string {
  const notice = pending.value
  pending.value = null

  return notice?.appointmentId === appointmentId ? notice.message : ''
}
