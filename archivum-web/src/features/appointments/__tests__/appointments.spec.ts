import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'

import { fakeApi, json, problem } from '@/api/__tests__/fakeApi'
import { cleanUp, open, page, plain, STORE } from '@/features/storefront/__tests__/fixtures'
import { APPOINTMENT_ID, appointmentOf, DAY, LUIS, SERVICE_ID, slotAt } from './fixtures'

afterEach(cleanUp)

const DETAIL = `/cuenta/citas/${APPOINTMENT_ID}`

function button(wrapper: Awaited<ReturnType<typeof open>>['wrapper'], text: string) {
  const found = wrapper.findAll('button').find((candidate) => plain(candidate.text()) === text)

  if (!found) {
    throw new Error(`No button "${text}"`)
  }

  return found
}

describe('my appointments', () => {
  it('lists the upcoming appointments with their store, state and hours in the clock of the store', async () => {
    const { requests } = fakeApi({ 'GET /cuenta/citas': () => json(200, page([appointmentOf()])) })
    const { wrapper } = await open('/cuenta/citas', 'cliente')

    expect(Object.fromEntries(new URL(requests[0]?.url ?? 'http://x').searchParams)).toEqual({
      periodo: 'proximas',
      pagina: '1',
    })
    expect(wrapper.get('h1').text()).toBe('Mis citas')
    expect(document.title).toBe('Mis citas · ArchivumParfums')
    // The day and the hour name each appointment, so two links never share a text (WCAG 2.4.9).
    expect(plain(wrapper.get('.appointment-list h2 a').text())).toBe('Cita del sábado, 23 de noviembre de 2030, 9:00 a. m.')

    const facts = plain(wrapper.get('.appointment-list .facts').text())
    expect(facts).toContain('TiendaArchivumParfums')
    expect(facts).toContain('EstadoConfirmada')
    expect(facts).toContain('HoraDe 9:00 a. m. a 9:45 a. m.')
    expect(facts).toContain('Le atiendeLuis Mora')
    expect(facts).toContain('Perfumes para probarÁmbar Nocturno')
    expect(wrapper.get('.sub-nav a[aria-current="page"]').text()).toBe('Próximas')
  })

  it('shows the past appointments on their own page, with the state in Spanish', async () => {
    const { requests } = fakeApi({
      'GET /cuenta/citas': (request) =>
        json(
          200,
          page(new URL(request.url).searchParams.get('periodo') === 'pasadas' ? [appointmentOf({ estado: 'no_asistio' })] : []),
        ),
    })
    const { wrapper, router } = await open('/cuenta/citas', 'cliente')

    expect(wrapper.get('.empty-state').text()).toContain('No tiene citas próximas.')

    await router.push({ name: 'past-appointments' })
    await flushPromises()

    expect(new URL(requests[requests.length - 1]?.url ?? 'http://x').searchParams.get('periodo')).toBe('pasadas')
    expect(wrapper.get('h1').text()).toBe('Mis citas pasadas')
    // Any state but "confirmada" joins the text of the link, so an hour booked again does not repeat it.
    expect(plain(wrapper.get('.appointment-list h2 a').text())).toBe(
      'Cita del sábado, 23 de noviembre de 2030, 9:00 a. m., no asistió',
    )
    expect(wrapper.get('.appointment-list__status').text()).toBe('No asistió')
    expect(wrapper.get('.sub-nav a[aria-current="page"]').text()).toBe('Pasadas')
  })

  it('shows an appointment of another account as not found', async () => {
    fakeApi({ [`GET ${DETAIL}`]: () => problem(404, 'no-encontrado', 'La cita no existe.') })
    const { wrapper } = await open(DETAIL, 'cliente')

    expect(wrapper.get('h1').text()).toBe('No encontrado')
  })

  it('cancels an appointment after asking, and says so with the focus on the title', async () => {
    const { requests } = fakeApi({
      [`GET ${DETAIL}`]: () => json(200, { data: appointmentOf() }),
      [`POST ${DETAIL}/cancelacion`]: () => json(200, { data: appointmentOf({ estado: 'cancelada' }) }),
    })
    const { wrapper } = await open(DETAIL, 'cliente')

    await button(wrapper, 'Cancelar la cita').trigger('click')

    // The question takes the place of the button, and the focus goes to the choice that changes nothing.
    expect(document.activeElement).toBe(button(wrapper, 'No, mantener la cita').element)

    await button(wrapper, 'Sí, cancelar la cita').trigger('click')
    await flushPromises()

    expect(requests.filter((request) => request.method === 'POST')).toHaveLength(1)
    expect(wrapper.get('.appointment-view [role="status"]').text()).toBe('Se canceló la cita.')
    expect(wrapper.get('.appointment-view__status').text()).toBe('Cancelada')
    expect(document.activeElement).toBe(wrapper.get('h1').element)
    expect(wrapper.find('.appointment-view__actions').exists()).toBe(false)
  })

  it('explains a cancellation past the deadline of the store with the message of the API', async () => {
    fakeApi({
      [`GET ${DETAIL}`]: () => json(200, { data: appointmentOf() }),
      [`POST ${DETAIL}/cancelacion`]: () =>
        problem(409, 'conflicto', 'La cita solo se cambia o se cancela hasta 2 horas antes.'),
    })
    const { wrapper } = await open(DETAIL, 'cliente')

    await button(wrapper, 'Cancelar la cita').trigger('click')
    await button(wrapper, 'Sí, cancelar la cita').trigger('click')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toBe('La cita solo se cambia o se cancela hasta 2 horas antes.')
    expect(document.activeElement).toBe(button(wrapper, 'Cancelar la cita').element)
  })

  it('offers no changes for an appointment that already happened', async () => {
    fakeApi({
      [`GET ${DETAIL}`]: () =>
        json(200, {
          data: appointmentOf({ estado: 'atendida', inicia_en: '2026-01-10T09:00:00-06:00', termina_en: '2026-01-10T09:45:00-06:00' }),
        }),
    })
    const { wrapper } = await open(DETAIL, 'cliente')

    expect(wrapper.get('.appointment-view__status').text()).toBe('Atendida')
    expect(wrapper.find('.appointment-view__actions').exists()).toBe(false)
  })
})

describe('a new hour for an appointment', () => {
  const RESCHEDULE = `${DETAIL}/reprogramar`

  it('looks for the free slots of the same person in the store of the appointment, and moves it', async () => {
    const moved = appointmentOf({ inicia_en: `${DAY}T11:00:00-06:00`, termina_en: `${DAY}T11:45:00-06:00` })
    let current = appointmentOf()
    const { requests } = fakeApi({
      [`GET ${DETAIL}`]: () => json(200, { data: current }),
      [`GET /tiendas/${STORE}/disponibilidad`]: () => json(200, { data: [slotAt('10:00', [LUIS]), slotAt('11:00', [LUIS])] }),
      [`PATCH ${DETAIL}`]: () => {
        current = moved

        return json(200, { data: moved })
      },
    })
    const { wrapper, router } = await open(RESCHEDULE, 'cliente')

    // The page opens with the slots of the day of the appointment.
    const availability = requests.find((request) => request.url.includes('/disponibilidad'))
    expect(Object.fromEntries(new URL(availability?.url ?? 'http://x').searchParams)).toEqual({
      servicio: SERVICE_ID,
      fecha: DAY,
      persona: LUIS.id,
    })
    expect(wrapper.get('h1').text()).toBe('Cambiar la hora de la cita')
    expect(plain(wrapper.get('.reschedule .prose').text())).toContain(
      'Su cita es el sábado, 23 de noviembre de 2030, de 9:00 a. m. a 9:45 a. m., con Luis Mora.',
    )
    // The person is already chosen, so the options do not repeat it.
    expect(plain(wrapper.findAll('.slot-list .choice')[1]?.text() ?? '')).toBe('11:00 a. m. a 11:45 a. m.')

    await wrapper.get(`input[value="${slotAt('11:00').inicia_en}"]`).setValue()
    await wrapper.get('.reschedule form').trigger('submit')
    await flushPromises()

    const change = requests.find((request) => request.method === 'PATCH')
    expect(await change?.clone().json()).toEqual({ inicia_en: `${DAY}T11:00:00-06:00` })

    await vi.waitFor(() => expect(wrapper.find('.appointment-view .facts').exists()).toBe(true))
    expect(router.currentRoute.value.fullPath).toBe(DETAIL)
    expect(wrapper.get('.appointment-view [role="status"]').text()).toBe('Se cambió la hora de su cita.')
    expect(plain(wrapper.get('h1').text())).toBe('Cita del sábado, 23 de noviembre de 2030, 11:00 a. m.')
  })

  it('asks again for the slots when the new hour was taken meanwhile', async () => {
    let answers = 0
    const { requests } = fakeApi({
      [`GET ${DETAIL}`]: () => json(200, { data: appointmentOf() }),
      [`GET /tiendas/${STORE}/disponibilidad`]: () =>
        json(200, { data: answers++ === 0 ? [slotAt('10:00', [LUIS]), slotAt('11:00', [LUIS])] : [slotAt('11:00', [LUIS])] }),
      [`PATCH ${DETAIL}`]: () => problem(409, 'conflicto', 'Ese horario ya no está disponible.'),
    })
    const { wrapper } = await open(RESCHEDULE, 'cliente')

    await wrapper.get(`input[value="${slotAt('10:00').inicia_en}"]`).setValue()
    await wrapper.get('.reschedule form').trigger('submit')
    await flushPromises()

    expect(requests.filter((request) => request.url.includes('/disponibilidad'))).toHaveLength(2)
    expect(wrapper.get('[role="alert"]').text()).toBe('Ese horario ya no está disponible.')
    expect(wrapper.findAll('input[name="inicia_en"]')).toHaveLength(1)
  })

  it('asks for an hour before sending the change', async () => {
    const { requests } = fakeApi({
      [`GET ${DETAIL}`]: () => json(200, { data: appointmentOf() }),
      [`GET /tiendas/${STORE}/disponibilidad`]: () => json(200, { data: [slotAt('10:00', [LUIS])] }),
    })
    const { wrapper } = await open(RESCHEDULE, 'cliente')

    await wrapper.get('.reschedule form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('#error-inicia_en').text()).toBe('Elija una hora de la lista.')
    expect(document.activeElement).toBe(wrapper.get('input[name="inicia_en"]').element)
    expect(requests.some((request) => request.method === 'PATCH')).toBe(false)
  })
})
