import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page, type TestInfo } from '@playwright/test'
import { featuredPerfume, perfumePath, showcasePath, storePath } from './demo'
import { mockApi } from './mockApi'

const tags = ['wcag2a', 'wcag2aa', 'wcag2aaa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice']

async function audit(page: Page, name: string) {
  await test.step(name, async () => {
    const { violations } = await new AxeBuilder({ page }).withTags(tags).analyze()
    const findings = violations.map(({ id, nodes }) => ({
      id,
      nodes: nodes.map(({ target, failureSummary }) => ({ target, failureSummary })),
    }))
    expect.soft(findings, name).toEqual([])
  })
}

async function capture(page: Page, info: TestInfo, name: string, fullPage = false) {
  await page.evaluate(() => document.fonts.ready)
  const path = info.outputPath(`${name}.png`)
  await page.screenshot({ path, animations: 'disabled', fullPage })
  await info.attach(name, { path, contentType: 'image/png' })
}

async function openFilter(page: Page) {
  await page.getByRole('button', { name: /^Filtrar/ }).click()
  const dialog = page.getByRole('dialog', { name: 'Filtrar', exact: true })
  await expect(dialog).toBeVisible()
  return dialog
}

async function addBottle(page: Page) {
  await page.getByRole('button', { name: /^Agregar al carrito la botella/ }).click()
  const dialog = page.getByRole('dialog', { name: /^Tu carrito/ })
  await expect(dialog).toBeVisible()
  return dialog
}

for (const colorScheme of ['light', 'dark'] as const) {
  for (const width of [1280, 390]) {
    test(`axe WCAG 2.2 and screenshots with ${colorScheme} theme at ${width} px`, async ({ page }, info) => {
      test.setTimeout(120_000)
      await page.setViewportSize({ width, height: width === 1280 ? 960 : 844 })
      await page.emulateMedia({ colorScheme, reducedMotion: 'reduce' })
      await mockApi(page)

      await page.goto('/')
      await expect(page.getByRole('heading', { level: 1 })).toHaveText('Inicio')
      await expect(page.getByRole('main').getByRole('link', { name: /Oud Nocturno/ })).toBeVisible()
      await expect(page.locator('html')).toHaveCSS('color-scheme', colorScheme)
      await capture(page, info, `inicio-${colorScheme}-${width}`, true)
      await audit(page, 'Inicio')

      await page.goto(`${showcasePath}?precio_maximo=300000`)
      await expect(page.getByRole('main').getByRole('link', { name: /Oud Nocturno/ })).toBeVisible()
      await audit(page, 'Vitrina y fichas de filtros')
      await openFilter(page)
      await capture(page, info, `vitrina-filtrar-${colorScheme}-${width}`)
      await audit(page, 'Vitrina con Filtrar abierto')

      await page.goto(perfumePath())
      await expect(page.getByRole('heading', { level: 1, name: featuredPerfume.nombre })).toBeVisible()
      await expect(page.getByRole('main').getByRole('heading', { name: 'Completa tu selección' })).toBeVisible()
      await audit(page, 'Detalle con opciones y sugerencias')
      const drawer = await addBottle(page)
      await capture(page, info, `detalle-carrito-${colorScheme}-${width}`)
      await audit(page, 'Detalle con carrito lateral abierto')
      await drawer.getByRole('link', { name: 'Hacer el pedido' }).click()
      await expect(page.getByRole('heading', { level: 1, name: 'Carrito' })).toBeVisible()
      await audit(page, 'Carrito de la tienda')

      for (const [path, label] of [
        ['/iniciar-sesion', /^Iniciar sesión$/],
        ['/crear-cuenta', /^Crear (una )?cuenta$/],
        [`${storePath}/citas/nueva`, /^Reservar una cita de prueba$/],
        [`${storePath}/recomendaciones`, /^Pedir una recomendación$/],
      ] as const) {
        await page.goto(path)
        const h1 = page.getByRole('heading', { level: 1, name: label })
        await expect(h1).toBeVisible()
        await expect(page.getByRole('main').locator('form').first()).toBeVisible()
        if (path === '/iniciar-sesion') await capture(page, info, `mi-cuenta-${colorScheme}-${width}`)
        await audit(page, await h1.innerText())
      }
    })
  }
}

test('does not overflow horizontally at 320 px in pages or drawers', async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 760 })
  await mockApi(page)
  for (const [path, name] of [
    ['/', 'Inicio'],
    [showcasePath, 'Vitrina'],
    [perfumePath(), featuredPerfume.nombre],
    ['/iniciar-sesion', 'Iniciar sesión'],
    ['/crear-cuenta', /^Crear (una )?cuenta$/],
    [`${storePath}/citas/nueva`, 'Reservar una cita de prueba'],
    [`${storePath}/recomendaciones`, 'Pedir una recomendación'],
  ] as const) {
    await page.goto(path)
    await expect(page.getByRole('heading', { level: 1, name })).toBeVisible()
    if (path === '/') await expect(page.getByRole('main').getByRole('link', { name: /Oud Nocturno/ })).toBeVisible()
    if (path === showcasePath) await openFilter(page)
    if (path === perfumePath()) await addBottle(page)

    const overflows = await page.evaluate(() => {
      const width = document.documentElement.clientWidth
      const overflow = Array.from(document.querySelectorAll<HTMLElement>('html, [role="dialog"], dialog[open]'))
        .filter((element) => element.scrollWidth > element.clientWidth)
        .map((element) => `${element.tagName} ${element.className} (${element.scrollWidth} > ${element.clientWidth})`)
      if (overflow.length === 0) return []

      const beyondViewport = Array.from(document.querySelectorAll<HTMLElement>('main *, header *, footer *'))
        .filter((element) => Math.ceil(element.getBoundingClientRect().right) > width)
        .slice(0, 8)
        .map((element) => `${element.tagName} ${element.classList.value}`)
      return [...overflow, ...beyondViewport]
    })
    expect.soft(overflows, `Anchura en ${path}`).toEqual([])
  }
})
