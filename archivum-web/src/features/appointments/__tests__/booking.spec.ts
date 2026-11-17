import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'

import { fakeApi, json, problem } from '@/api/__tests__/fakeApi'
import { cleanUp, open, page, plain, profile, STORE } from '@/features/storefront/__tests__/fixtures'
import { ANA, APPOINTMENT_ID, appointmentOf, DAY, LUIS, PERFUME, serviceOf, showcaseOf, slotAt } from './fixtures'

afterEach(cleanUp)

const BOOKING = `/tiendas/${STORE}/citas/nueva`
const OTHER = { id: '0193b0d4-0000-7000-8000-000000000332', nombre: 'Cuero Blanco' }

/** The answers of the API that the page of the booking asks for, plus those of each test. */
function storeApi(extra: Record<string, (request: Request) => Response | Promise<Response>> = {}) {
  return fakeApi({
    [`GET /tiendas/${STORE}`]: () => json(200, { data: profile }),
    [`GET /tiendas/${STORE}/servicios`]: () => json(200, { data: [serviceOf()] }),
    [`GET /tiendas/${STORE}/perfumes`]: () => json(200, page(showcaseOf([PERFUME, OTHER]))),
    [`GET /tiendas/${STORE}/disponibilidad`]: () => json(200, { data: [slotAt('09:00'), slotAt('10:00', [ANA])] }),
    ...extra,
  })
}

function button(wrapper: Awaited<ReturnType<typeof open>>['wrapper'], text: string) {
  const found = wrapper.findAll('button').find((candidate) => plain(candidate.text()) === text)

  if (!found) {
    throw new Error(`No button "${text}"`)
  }

  return found
}

async function chooseDay(wrapper: Awaited<ReturnType<typeof open>>['wrapper']): Promise<void> {
  await wrapper.get('#fecha').setValue(DAY)
  await button(wrapper, 'Ver los horarios libres').trigger('click')
  await flushPromises()
}

describe('booking an appointment', () => {
  it('books a slot with the chosen person and perfumes, and opens the appointment with its day, hour and place', async () => {
    const { requests } = storeApi({
      [`POST /tiendas/${STORE}/citas`]: () => json(201, { data: appointmentOf() }),
      [`GET /cuenta/citas/${APPOINTMENT_ID}`]: () => json(200, { data: appointmentOf() }),
    })
    const { wrapper, router } = await open(BOOKING, 'cliente')

    expect(wrapper.get('h1').text()).toBe('Reservar una cita de prueba')
    expect(wrapper.get('.booking__service').text()).toBe('Servicio: Prueba de olores: 45 minutos, sin costo.')

    await chooseDay(wrapper)

    const availability = requests.find((request) => request.url.includes('/disponibilidad'))
    expect(Object.fromEntries(new URL(availability?.url ?? 'http://x').searchParams)).toEqual({
      servicio: serviceOf().id,
      fecha: DAY,
    })
    expect(plain(wrapper.get('.booking .results-summary').text())).toBe(
      'Hay 2 horarios libres el sábado, 23 de noviembre de 2030.',
    )
    expect(plain(wrapper.get('.slot-list legend').text())).toBe('Hora de la cita, el sábado, 23 de noviembre de 2030')
    expect(plain(wrapper.findAll('.slot-list .choice')[0]?.text() ?? '')).toBe(
      '9:00 a. m. a 9:45 a. m. Le atiende Ana Solís o Luis Mora',
    )

    await wrapper.get(`input[value="${slotAt('09:00').inicia_en}"]`).setValue()
    await wrapper.get('#persona').setValue(LUIS.id)
    await button(wrapper, 'Agregar').trigger('click')
    expect(plain(wrapper.get('.perfume-picker .results-summary').text())).toBe('Se agregó Ámbar Nocturno a la cita. Lleva 1 de 5.')

    await wrapper.get('form.booking__form').trigger('submit')
    await flushPromises()

    const booking = requests.find((request) => request.method === 'POST')
    expect(await booking?.clone().json()).toEqual({
      servicio_id: serviceOf().id,
      inicia_en: `${DAY}T09:00:00-06:00`,
      persona_id: LUIS.id,
      perfumes: [PERFUME.id],
    })
    // The page of the appointment loads lazily, like every view.
    await vi.waitFor(() => expect(wrapper.find('.appointment-view .facts').exists()).toBe(true))
    expect(router.currentRoute.value.fullPath).toBe(`/cuenta/citas/${APPOINTMENT_ID}`)
    expect(wrapper.get('h1').text()).toBe('Cita del sábado, 23 de noviembre de 2030, 9:00 a. m.')
    expect(wrapper.get('.appointment-view [role="status"]').text()).toBe('Se reservó su cita de prueba.')
    expect(plain(wrapper.get('.facts').text())).toContain('De 9:00 a. m. a 9:45 a. m., hora de la tienda')
    expect(wrapper.get('.facts').text()).toContain('Le atiendeLuis Mora')
    expect(wrapper.get('.facts').text()).toContain('LugarEscazú, San Rafael, 200 m norte del parque central')
  })

  it('says that the slot was taken, asks again for the slots of the day and keeps the rest of the choice', async () => {
    let answers = 0
    const { requests } = storeApi({
      [`GET /tiendas/${STORE}/disponibilidad`]: () =>
        json(200, { data: answers++ === 0 ? [slotAt('09:00'), slotAt('10:00')] : [slotAt('10:00')] }),
      [`POST /tiendas/${STORE}/citas`]: () => problem(409, 'conflicto', 'Ese horario ya no está disponible.'),
    })
    const { wrapper } = await open(BOOKING, 'cliente')

    await chooseDay(wrapper)
    await wrapper.get(`input[value="${slotAt('09:00').inicia_en}"]`).setValue()
    await wrapper.get('#persona').setValue(LUIS.id)
    await button(wrapper, 'Agregar').trigger('click')
    await wrapper.get('form.booking__form').trigger('submit')
    await flushPromises()

    expect(requests.filter((request) => request.url.includes('/disponibilidad'))).toHaveLength(2)
    expect(wrapper.get('[role="alert"]').text()).toBe('Ese horario ya no está disponible.')
    expect(plain(wrapper.get('.booking .results-summary').text())).toBe('Hay un horario libre el sábado, 23 de noviembre de 2030.')
    expect(wrapper.findAll('input[name="inicia_en"]').map((input) => (input.element as HTMLInputElement).value)).toEqual([
      slotAt('10:00').inicia_en,
    ])
    // The focus goes to the hours that are left, none chosen; the day, the person and the perfume stay.
    expect(document.activeElement).toBe(wrapper.get('input[name="inicia_en"]').element)
    expect((wrapper.get('input[name="inicia_en"]').element as HTMLInputElement).checked).toBe(false)
    expect((wrapper.get('#fecha').element as HTMLInputElement).value).toBe(DAY)
    expect(wrapper.get('.perfume-picker__count').text()).toBe('Elegidos: 1 de 5')

    await wrapper.get(`input[value="${slotAt('10:00').inicia_en}"]`).setValue()

    expect((wrapper.get('#persona').element as HTMLSelectElement).value).toBe(LUIS.id)
  })

  it('lets go of the chosen person when the next slot is not theirs', async () => {
    storeApi({
      [`GET /tiendas/${STORE}/disponibilidad`]: () => json(200, { data: [slotAt('09:00'), slotAt('10:00', [ANA]), slotAt('11:00')] }),
    })
    const { wrapper } = await open(BOOKING, 'cliente')

    await chooseDay(wrapper)
    await wrapper.get(`input[value="${slotAt('09:00').inicia_en}"]`).setValue()
    await wrapper.get('#persona').setValue(LUIS.id)
    await wrapper.get(`input[value="${slotAt('10:00').inicia_en}"]`).setValue()
    await wrapper.get(`input[value="${slotAt('11:00').inicia_en}"]`).setValue()

    expect((wrapper.get('#persona').element as HTMLSelectElement).value).toBe('')
  })

  it('marks the perfumes with the message of a 422', async () => {
    storeApi({
      [`POST /tiendas/${STORE}/citas`]: () =>
        problem(422, 'datos-invalidos', 'Los datos no son válidos.', {
          errores: { perfumes: ['Alguno de los perfumes no está disponible en esta tienda.'] },
        }),
    })
    const { wrapper } = await open(BOOKING, 'cliente')

    await chooseDay(wrapper)
    await wrapper.get(`input[value="${slotAt('09:00').inicia_en}"]`).setValue()
    await button(wrapper, 'Agregar').trigger('click')
    await wrapper.get('form.booking__form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('#error-perfumes').text()).toBe('Alguno de los perfumes no está disponible en esta tienda.')
    expect(wrapper.get('.perfume-picker').attributes('aria-describedby')).toBe('pista-perfumes error-perfumes')
  })

  it('asks for the day before looking for slots, without asking the API', async () => {
    const { requests } = storeApi()
    const { wrapper } = await open(BOOKING, 'cliente')

    await button(wrapper, 'Ver los horarios libres').trigger('click')
    await flushPromises()

    expect(wrapper.get('#error-fecha').text()).toBe('Elija el día de la cita.')
    expect(document.activeElement).toBe(wrapper.get('#fecha').element)
    expect(requests.some((request) => request.url.includes('/disponibilidad'))).toBe(false)
  })

  it('keeps the perfumes up to the maximum of the service', async () => {
    storeApi({ [`GET /tiendas/${STORE}/servicios`]: () => json(200, { data: [serviceOf({ maximo_perfumes: 1 })] }) })
    const { wrapper } = await open(BOOKING, 'cliente')

    await wrapper.findAll('.perfume-picker__results button')[0]?.trigger('click')
    await wrapper.findAll('.perfume-picker__results button')[1]?.trigger('click')

    expect(wrapper.get('.perfume-picker__count').text()).toBe('Elegidos: 1 de 1')
    expect(wrapper.findAll('.perfume-picker__results button')[1]?.attributes('aria-disabled')).toBe('true')
    expect(plain(wrapper.get('.perfume-picker .results-summary').text())).toBe(
      'Ya eligió un perfume, el máximo de este servicio. Quite uno para agregar otro.',
    )
  })

  it('sends a visitor to the sign-in with the choice, and comes back to the same choice', async () => {
    storeApi({ [`GET /tiendas/${STORE}/perfumes/${PERFUME.id}`]: () => json(200, { data: { ...PERFUME, marca: 'Maison Archivum' } }) })
    const first = await open(BOOKING)

    await chooseDay(first.wrapper)
    await first.wrapper.get(`input[value="${slotAt('09:00').inicia_en}"]`).setValue()
    await first.wrapper.get('#persona').setValue(ANA.id)
    await button(first.wrapper, 'Agregar').trigger('click')

    const signIn = first.wrapper.findAll('a').find((link) => link.text() === 'Inicie sesión para reservar')
    const target = new URL(signIn?.attributes('href') ?? '', 'http://x').searchParams.get('redirect') ?? ''
    const choice = new URL(target, 'http://x').searchParams

    expect(choice.get('servicio')).toBe(serviceOf().id)
    expect(choice.get('fecha')).toBe(DAY)
    expect(choice.get('inicio')).toBe(slotAt('09:00').inicia_en)
    expect(choice.get('persona')).toBe(ANA.id)
    expect(choice.get('perfumes')).toBe(PERFUME.id)

    first.wrapper.unmount()
    const back = await open(target, 'cliente')

    expect((back.wrapper.get('#fecha').element as HTMLInputElement).value).toBe(DAY)
    expect((back.wrapper.get(`input[value="${slotAt('09:00').inicia_en}"]`).element as HTMLInputElement).checked).toBe(true)
    expect((back.wrapper.get('#persona').element as HTMLSelectElement).value).toBe(ANA.id)
    expect(back.wrapper.get('.perfume-picker__chosen').text()).toContain('Ámbar Nocturno')
    expect(back.wrapper.findAll('button').some((candidate) => candidate.text() === 'Reservar la cita')).toBe(true)
  })

  it('tells the staff of a store that appointments are booked with a customer account', async () => {
    storeApi()
    const { wrapper } = await open(BOOKING, 'dueno')

    expect(wrapper.get('.booking__staff').text()).toBe('Las citas de prueba se reservan con una cuenta de cliente.')
    expect(wrapper.findAll('button').some((candidate) => candidate.text() === 'Reservar la cita')).toBe(false)
  })
})
