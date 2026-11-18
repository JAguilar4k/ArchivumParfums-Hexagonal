import { expect, test } from '@playwright/test'
import { appointmentDay, appointmentsPath, demoAppointment, featuredPerfume, perfumePath, service, slot, storePath } from './demo'
import { mockApi } from './mockApi'
import { signIn } from './signIn'

test.use({ timezoneId: 'Pacific/Auckland' })

test('recovers a taken time, books with the API offset, and reschedules and cancels an appointment', async ({ page }) => {
  const api = await mockApi(page, { firstSlotTaken: true })
  await page.goto('/iniciar-sesion')
  await signIn(page)
  await expect(page).toHaveURL(new RegExp(storePath))

  const bookingPath = `${storePath}/citas/nueva`
  await page.goto(bookingPath)
  const main = page.getByRole('main')
  await expect(main.getByText('Prueba de olores', { exact: false }).first()).toBeVisible()
  await main.getByLabel('Día de la cita').fill(appointmentDay)
  await main.getByRole('button', { name: 'Ver los horarios libres' }).click()
  const firstSlot = main.getByRole('radio', { name: /^9:00/ })
  await firstSlot.check()
  await main.getByRole('combobox', { name: 'Persona que le atiende' }).selectOption({ label: 'Asesora de prueba' })
  await main.getByLabel('Buscar un perfume por marca o nombre').fill('Oud Nocturno')
  await main.getByRole('button', { name: 'Buscar perfumes' }).click()
  await main.getByRole('button', { name: 'Agregar Oud Nocturno a la cita' }).click()
  const submit = main.getByRole('button', { name: 'Reservar la cita' })
  await submit.click()
  await expect(main.getByRole('alert')).toContainText('Ese horario ya no está disponible.')
  const nextSlot = main.getByRole('radio', { name: /^10:00/ })
  await expect(nextSlot).toBeFocused()
  await expect(firstSlot).toHaveCount(0)
  await nextSlot.check()
  await submit.click()

  const detail = `${appointmentsPath}/${demoAppointment.id}`
  await expect(page).toHaveURL(detail)
  await expect(main.getByRole('status').filter({ hasText: 'Se reservó su cita de prueba.' })).toBeVisible()
  const bookings = api.requests.filter((request) =>
    request.method() === 'POST' && new URL(request.url()).pathname === `/api/v1${storePath}/citas`,
  )
  expect(bookings.map((request) => request.postDataJSON())).toEqual(['09', '10'].map((hour) => ({
    servicio_id: service.id,
    inicia_en: slot(hour).inicia_en,
    persona_id: demoAppointment.persona.id,
    perfumes: [featuredPerfume.id],
  })))

  await main.getByRole('link', { name: 'Ver todas mis citas' }).click()
  await expect(page).toHaveURL(appointmentsPath)
  await main.getByRole('link', { name: 'Pasadas', exact: true }).click()
  await expect(page).toHaveURL(`${appointmentsPath}/pasadas`)
  await expect(main.getByRole('link', { name: /Cita del .*atendida/ })).toBeVisible()
  await main.getByRole('link', { name: 'Próximas', exact: true }).click()
  await main.getByRole('link', { name: /^Cita del / }).click()

  await main.getByRole('link', { name: 'Cambiar la hora' }).click()
  await expect(page).toHaveURL(`${detail}/reprogramar`)
  await main.getByRole('radio', { name: /^11:00/ }).check()
  await main.getByRole('button', { name: 'Cambiar a esta hora' }).click()
  await expect(main.getByRole('heading', { level: 1 })).toContainText('11:00')
  await expect(main.getByRole('status').filter({ hasText: 'Se cambió la hora de su cita.' })).toBeVisible()
  await main.getByRole('button', { name: 'Cancelar la cita', exact: true }).click()
  await expect(main.getByRole('button', { name: 'No, mantener la cita' })).toBeFocused()
  await main.getByRole('button', { name: 'Sí, cancelar la cita' }).click()
  await expect(main.getByRole('status').filter({ hasText: 'Se canceló la cita.' })).toBeVisible()
  await expect(main.getByRole('heading', { level: 1 })).toBeFocused()
})

test('displays recommendation validation, reasons and API presentations', async ({ page }) => {
  await mockApi(page)
  await page.goto(`${storePath}/recomendaciones`)
  const main = page.getByRole('main')
  const submit = main.getByRole('button', { name: 'Recomendar' })
  const description = main.getByLabel('Describa lo que le gusta')
  await description.fill('Una solicitud de prueba demasiado breve')
  await submit.click()
  await expect(description).toHaveAttribute('aria-invalid', 'true')
  await expect(description).toBeFocused()
  await expect(main.locator('#error-texto')).toHaveText('Amplíe la descripción.')
  await description.fill('Busco un perfume con oud para una cena de noche.')
  await submit.click()
  await expect(main.getByText('Combina oud e incienso para una cena de noche.')).toBeVisible()
  await expect(main.getByText(/Botella de 50\s*ml/)).toContainText(/185\s*000/)
  await expect(main.getByRole('link', { name: /Oud Nocturno/ })).toHaveAttribute('href', perfumePath())
})
