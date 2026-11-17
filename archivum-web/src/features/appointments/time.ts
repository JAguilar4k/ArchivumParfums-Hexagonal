/**
 * Dates and hours of the appointments (#129), always as the clock of the store shows them and never as the clock of
 * the browser: the API writes every instant with the offset of the store (`2026-11-21T09:00:00-06:00`), so the date and
 * the hour are read from the text itself. A customer in another time zone sees the hour at which they must be in the
 * store.
 */

const INSTANT = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::\d{2}(?:\.\d+)?)?(Z|[+-]\d{2}:\d{2})$/
const DAY = /^(\d{4})-(\d{2})-(\d{2})$/

// The parts are kept in UTC, so that formatting them never moves them to the zone of the browser.
const DAY_FORMAT = new Intl.DateTimeFormat('es-CR', {
  timeZone: 'UTC',
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
})
const HOUR_FORMAT = new Intl.DateTimeFormat('es-CR', { timeZone: 'UTC', hour: 'numeric', minute: '2-digit' })

/** The date and the hour that the store reads in an instant of the API, held in a Date in UTC. */
function storeClock(instant: string): Date | null {
  const match = INSTANT.exec(instant)

  if (!match) {
    return null
  }

  return new Date(
    Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]), Number(match[4]), Number(match[5])),
  )
}

/** A day `AAAA-MM-DD`, held in a Date in UTC. */
function dayClock(day: string): Date | null {
  const match = DAY.exec(day)

  return match ? new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]))) : null
}

/** The day of an instant, as the store says it: "sábado, 21 de noviembre de 2026". */
export function formatDay(instant: string): string {
  const clock = storeClock(instant)

  return clock ? DAY_FORMAT.format(clock) : instant
}

/** A day of the calendar (`2026-11-21`) in words: "sábado, 21 de noviembre de 2026". */
export function formatCalendarDay(day: string): string {
  const clock = dayClock(day)

  return clock ? DAY_FORMAT.format(clock) : day
}

/** The hour of an instant, as the store says it: "9:00 a. m.". */
export function formatHour(instant: string): string {
  const clock = storeClock(instant)

  return clock ? HOUR_FORMAT.format(clock) : instant
}

/** From one hour to the other: "9:00 a. m. a 9:45 a. m.". */
export function formatHours(startsAt: string, endsAt: string): string {
  return `${formatHour(startsAt)} a ${formatHour(endsAt)}`
}

/** The day of the calendar of an instant, as the store reads it (`2026-11-21`), for the field of the day. */
export function calendarDayOf(instant: string): string {
  return INSTANT.exec(instant)?.slice(1, 4).join('-') ?? ''
}

/**
 * Today in the time zone of the store (`America/Costa_Rica`), as the field of the day takes it. Without a zone, or
 * with one that the browser does not know, it is today in the browser.
 */
export function todayIn(timeZone: string | null | undefined, now: Date = new Date()): string {
  try {
    // The Canadian English format writes the date as AAAA-MM-DD.
    return new Intl.DateTimeFormat('en-CA', {
      timeZone: timeZone ?? undefined,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).format(now)
  } catch {
    return todayIn(undefined, now)
  }
}

/** Today at the offset of an instant of the API: the day of the store when only its instants are known. */
export function todayAtOffsetOf(instant: string, now: Date = new Date()): string {
  const offset = INSTANT.exec(instant)?.[6]

  if (!offset || offset === 'Z') {
    return now.toISOString().slice(0, 10)
  }

  const sign = offset.startsWith('-') ? -1 : 1
  const minutes = sign * (Number(offset.slice(1, 3)) * 60 + Number(offset.slice(4, 6)))

  return new Date(now.getTime() + minutes * 60_000).toISOString().slice(0, 10)
}

/** Whether an instant of the API is still ahead. */
export function isAhead(instant: string, now: Date = new Date()): boolean {
  return new Date(instant).getTime() > now.getTime()
}
