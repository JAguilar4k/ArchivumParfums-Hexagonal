import { expect, test } from '@playwright/test'
import { featuredPerfume, followingPerfume, perfumePath, showcasePath, storePath } from './demo'
import { mockApi } from './mockApi'

test('opens Inicio, redirects old filters, and preserves the catalog URL and page size', async ({ page }) => {
  await mockApi(page)
  await page.goto('/')
  await expect(page).toHaveURL(storePath)
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Inicio')
  await expect(page).toHaveTitle(/^Inicio · /)
  await expect(page.getByRole('heading', { level: 2, name: 'Una fragancia también guarda memoria' })).toBeVisible()
  await expect(page.getByRole('main').getByRole('link', { name: 'Explorar la colección' })).toHaveAttribute('href', showcasePath)

  await page.goto(`${storePath}?familia=Floral&momento=dia`)
  await expect(page).toHaveURL(`${showcasePath}?familia=Floral&momento=dia`)
  await expect(page.getByRole('main').getByRole('link', { name: /Alba de Azahar/ })).toBeVisible()
  await expect(page.getByRole('main').getByRole('link', { name: /Jardín de Orquídeas/ })).toHaveCount(0)
  await page.reload()
  await expect(page.getByRole('button', { name: 'Filtrar (2)' })).toBeVisible()
  await page.getByRole('button', { name: 'Quitar todos' }).click()
  await expect(page).toHaveURL(showcasePath)

  await expect(page.getByRole('main').getByRole('status').filter({ hasText: /Mostrando 1–12 de 14 perfumes/ })).toBeVisible()
  await page.getByRole('link', { name: 'Siguiente', exact: true }).click()
  await expect(page.getByRole('link', { name: new RegExp(followingPerfume.nombre) })).toBeVisible()
  await expect(page.locator('[aria-current="page"]').filter({ hasText: /^2$/ })).toBeVisible()
  await page.getByLabel('Por página').selectOption('24')
  await expect(page).toHaveURL(`${showcasePath}?por_pagina=24`)
  await expect(page.getByRole('main').getByRole('status').filter({ hasText: /Mostrando 1–14 de 14 perfumes/ })).toBeVisible()
})

test('requests a smaller photo for a catalog card than for the perfume detail', async ({ page }) => {
  const media = 'https://media.archivum.test'
  const variant = (width: number) => `${media}/cdn-cgi/image/width=${width},fit=contain/perfume.jpg`
  await page.setViewportSize({ width: 390, height: 844 })
  await mockApi(page, {
    featuredPhoto: {
      foto_url: `${media}/perfume.jpg`,
      foto_variantes: [320, 640, 960].map((ancho) => ({ ancho, url: variant(ancho) })),
    },
  })
  await page.route(`${media}/**`, (route) => {
    const width = Number(new URL(route.request().url()).pathname.match(/width=(\d+)/)?.[1] ?? 960)
    return route.fulfill({
      contentType: 'image/svg+xml',
      body: `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${width}"></svg>`,
    })
  })

  await page.goto(`${showcasePath}?texto=${encodeURIComponent(featuredPerfume.nombre)}`)
  const perfumeName = `${featuredPerfume.marca} ${featuredPerfume.nombre}`
  const link = page.getByRole('link', { name: perfumeName, exact: true })
  const thumbnail = page.locator('.perfume-card').filter({ has: link }).locator('img')
  await thumbnail.scrollIntoViewIfNeeded()
  await expect(thumbnail).toHaveAttribute('alt', '')
  await expect.poll(() => thumbnail.evaluate((image) => (image as HTMLImageElement).currentSrc)).toBe(variant(320))

  await link.click()
  const photo = page.getByRole('img', { name: `Botella de ${perfumeName}` })
  await photo.scrollIntoViewIfNeeded()
  await expect.poll(() => photo.evaluate((image) => (image as HTMLImageElement).currentSrc)).toBe(variant(640))
})

test('displays the original perfume photo when a transformation redirects', async ({ page, baseURL }) => {
  const original = new URL('/favicon.ico', baseURL).href
  const variant = new URL('/cdn-cgi/image/width=640,fit=contain,format=auto,onerror=redirect/perfume.jpg', baseURL).href
  await page.setViewportSize({ width: 390, height: 844 })
  await mockApi(page, { featuredPhoto: {
    foto_url: original,
    foto_variantes: [{ ancho: 640, url: variant }],
  } })
  await page.route((url) => url.href === variant, (route) => route.fulfill({
    status: 307,
    headers: { location: original },
  }))

  await page.goto(perfumePath())
  const photo = page.getByRole('img', { name: `Botella de ${featuredPerfume.marca} ${featuredPerfume.nombre}` })
  await photo.scrollIntoViewIfNeeded()
  await expect.poll(() => photo.evaluate((image) => (image as HTMLImageElement).currentSrc)).toBe(variant)
  await expect.poll(() => photo.evaluate((image) => (image as HTMLImageElement).naturalWidth)).toBeGreaterThan(0)
})

test('applies all catalog filters and keeps the previous results while loading', async ({ page }) => {
  await mockApi(page, { slowSearch: { text: 'Noctis', delayMs: 750 } })
  await page.goto(`${showcasePath}?pagina=2`)

  const filter = page.getByRole('button', { name: 'Filtrar (0)' })
  await filter.click()
  const dialog = page.getByRole('dialog', { name: 'Filtrar', exact: true })
  await expect(dialog).toBeVisible()
  await expect(dialog.locator(':focus')).toBeAttached()

  const text = dialog.getByLabel('Marca, nombre o nota')
  await text.fill('Noctis')
  await expect.poll(() => new URL(page.url()).searchParams.get('texto')).toBe('Noctis')
  await expect(page.locator('main [aria-busy="true"]')).toContainText(followingPerfume.nombre)
  await expect.poll(() => new URL(page.url()).searchParams.has('pagina')).toBe(false)

  await dialog.getByRole('radio', { name: 'Amaderada' }).check()
  await dialog.getByRole('radio', { name: 'Noche' }).check()
  await dialog.getByRole('radio', { name: 'Frío' }).check()
  await dialog.getByLabel('Nota', { exact: true }).fill('Oud')
  await dialog.getByLabel('Ocasión', { exact: true }).fill('Cena')
  const price = dialog.getByLabel('Precio máximo en colones')
  await price.fill('190000')

  await expect.poll(() => Object.fromEntries(new URL(page.url()).searchParams)).toEqual({
    texto: 'Noctis', familia: 'Amaderada', momento: 'noche', clima: 'frio', nota: 'Oud', ocasion: 'Cena', precio_maximo: '190000',
  })
  await expect(dialog.getByRole('button', { name: /Ver 1 perfume/ })).toBeVisible()

  await price.fill('-1')
  await expect(dialog.getByText('Escriba un precio positivo.')).toBeVisible()
  await expect(price).toHaveAttribute('aria-invalid', 'true')
  await price.fill('190000')
  await page.keyboard.press('Escape')
  await expect(dialog).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Filtrar (7)' })).toBeFocused()
  await expect(page.getByRole('main').getByRole('link', { name: new RegExp(featuredPerfume.nombre) })).toBeVisible()

  await page.goto(`${showcasePath}?texto=sin-coincidencias`)
  await expect(page.getByRole('main').getByText(/No hay perfumes que coincidan/).first()).toBeVisible()
  await page.goto(`/tiendas/inexistente/vitrina`)
  await expect(page.getByRole('heading', { level: 1, name: 'No encontrado' })).toBeVisible()
  await page.goto(`${storePath}/perfumes/inexistente`)
  await expect(page.getByRole('heading', { level: 1, name: 'No encontrado' })).toBeVisible()
})

test('keeps the header, theme, help anchors, and route focus working on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await page.emulateMedia({ colorScheme: 'light' })
  await mockApi(page)
  await page.goto('/')
  await page.getByRole('button', { name: 'Activar el tema oscuro' }).click()
  await expect(page.getByRole('button', { name: 'Activar el tema claro' })).toHaveAttribute('aria-pressed', 'true')
  await page.reload()
  await expect(page.getByRole('button', { name: 'Activar el tema claro' })).toBeVisible()

  await expect(page.getByRole('link', { name: 'Escribir a la tienda por WhatsApp' })).toHaveAttribute('href', 'https://wa.me/50600000000')
  const search = page.getByRole('button', { name: 'Buscar perfumes', exact: true })
  await search.click()
  await page.getByRole('searchbox').fill('Noctis')
  await page.getByRole('searchbox').press('Enter')
  await expect(page).toHaveURL(`${showcasePath}?texto=Noctis`)
  const heading = page.getByRole('heading', { level: 1, name: 'Vitrina' })
  await expect(heading).toBeFocused()
  await expect(page).toHaveTitle(/^Vitrina/)
  await expect(page.getByRole('main')).toHaveCount(1)

  const menu = page.getByRole('button', { name: 'Abrir el menú' })
  await menu.click()
  await page.getByRole('dialog').getByRole('link', { name: 'Decants', exact: true }).click()
  await expect(page).toHaveURL('/ayuda#decant')
  await expect(page.locator('#decant')).toBeFocused()

  const skip = page.getByRole('link', { name: 'Saltar al contenido' })
  await skip.focus()
  await skip.press('Enter')
  await expect(page.getByRole('main')).toBeFocused()
  await page.goto('/no-existe')
  await expect(page.getByRole('heading', { level: 1, name: 'No encontrado' })).toBeVisible()
  await page.goto(perfumePath())
  await expect(page.getByRole('heading', { level: 1, name: featuredPerfume.nombre })).toBeVisible()
})
