import type { Page } from '@playwright/test'
import { customer } from './demo'

export async function signIn(page: Page, email = customer.correo) {
  const form = page.getByRole('main').locator('form').filter({ has: page.locator('input[autocomplete="current-password"]') })
  await form.getByLabel('Correo').fill(email)
  await form.getByLabel('Contraseña', { exact: true }).fill('En este ejemplo usamos una contraseña ficticia')
  await form.getByRole('button', { name: 'Iniciar sesión', exact: true }).click()
}
