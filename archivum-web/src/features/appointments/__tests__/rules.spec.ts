import { describe, expect, it } from 'vitest'

import { appointmentLinkText, appointmentTitle, canChange, namedItems, peopleOf, statusLabel } from '../appointment'
import {
  calendarDayOf,
  formatCalendarDay,
  formatDay,
  formatHour,
  formatHours,
  todayAtOffsetOf,
  todayIn,
} from '../time'
import { ANA, LUIS, slotAt } from './fixtures'

/** The same text with plain spaces, because Intl may separate "a. m." with a narrow one. */
function plain(text: string): string {
  return text.replace(/\s/g, ' ')
}

describe('the clock of the store', () => {
  it('reads the day and the hour of an instant as the store wrote them, whatever the zone of the browser', () => {
    // 11:30 p. m. in Costa Rica is already Sunday in UTC; the page says Saturday, the day of the store.
    expect(formatDay('2026-11-21T23:30:00-06:00')).toBe('sábado, 21 de noviembre de 2026')
    expect(plain(formatHour('2026-11-21T23:30:00-06:00'))).toBe('11:30 p. m.')
    expect(plain(formatHours('2026-11-21T09:00:00-06:00', '2026-11-21T09:45:00-06:00'))).toBe('9:00 a. m. a 9:45 a. m.')
    expect(calendarDayOf('2026-11-21T23:30:00-06:00')).toBe('2026-11-21')
    expect(formatCalendarDay('2026-11-21')).toBe('sábado, 21 de noviembre de 2026')
  })

  it('knows today in the zone of the store, or at the offset of its instants', () => {
    const lateNightInCostaRica = new Date('2026-11-17T03:00:00Z')

    expect(todayIn('America/Costa_Rica', lateNightInCostaRica)).toBe('2026-11-16')
    expect(todayIn('Zona/Inexistente', lateNightInCostaRica)).toMatch(/^\d{4}-\d{2}-\d{2}$/)
    expect(todayAtOffsetOf('2026-11-21T09:00:00-06:00', lateNightInCostaRica)).toBe('2026-11-16')
    expect(todayAtOffsetOf('2026-11-21T09:00:00Z', lateNightInCostaRica)).toBe('2026-11-17')
  })

  it('leaves a text that is not an instant as it came', () => {
    expect(formatDay('mañana')).toBe('mañana')
    expect(calendarDayOf('mañana')).toBe('')
  })
})

describe('an appointment', () => {
  it('takes its day and hour as its name', () => {
    expect(plain(appointmentTitle({ inicia_en: '2026-11-21T17:15:00-06:00' }))).toBe(
      'Cita del sábado, 21 de noviembre de 2026, 5:15 p. m.',
    )
  })

  it('joins any state but the confirmed one to the text of its link', () => {
    const start = '2026-11-21T17:15:00-06:00'

    expect(plain(appointmentLinkText({ estado: 'confirmada', inicia_en: start }))).toBe(
      'Cita del sábado, 21 de noviembre de 2026, 5:15 p. m.',
    )
    expect(plain(appointmentLinkText({ estado: 'cancelada', inicia_en: start }))).toBe(
      'Cita del sábado, 21 de noviembre de 2026, 5:15 p. m., cancelada',
    )
    expect(plain(appointmentLinkText({ estado: 'apartada', inicia_en: start }))).toBe(
      'Cita del sábado, 21 de noviembre de 2026, 5:15 p. m., apartada para usted',
    )
  })

  it('writes each state of the API for people', () => {
    expect(statusLabel('confirmada')).toBe('Confirmada')
    expect(statusLabel('no_asistio')).toBe('No asistió')
    expect(statusLabel('estado_nuevo')).toBe('estado nuevo')
  })

  it('admits changes only while it is active and ahead', () => {
    const now = new Date('2026-11-16T12:00:00Z')

    expect(canChange({ estado: 'confirmada', inicia_en: '2026-11-21T09:00:00-06:00' }, now)).toBe(true)
    expect(canChange({ estado: 'cancelada', inicia_en: '2026-11-21T09:00:00-06:00' }, now)).toBe(false)
    expect(canChange({ estado: 'confirmada', inicia_en: '2026-11-10T09:00:00-06:00' }, now)).toBe(false)
  })

  it('names who attends a slot, and leaves out an item that is not a person', () => {
    expect(peopleOf(slotAt('09:00', [ANA]))).toBe('Ana Solís')
    expect(peopleOf(slotAt('09:00', [ANA, LUIS]))).toBe('Ana Solís o Luis Mora')
    expect(namedItems([ANA, { id: 3 }, null, 'Luis'])).toEqual([ANA])
  })
})
