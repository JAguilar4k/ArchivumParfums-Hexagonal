import { expect, test, type Locator, type Page } from '@playwright/test'
import { featuredPerfume, followingPerfume, perfumePath, showcasePath, storePath } from './demo'
import { mockApi } from './mockApi'

interface VisibleContent {
  selector: string
  text: string
}

/** Keep the timer in the browser, starting at the input event and ending at a frame with visible new content. */
async function measureNavigation(page: Page, trigger: Locator, target: VisibleContent): Promise<number> {
  await trigger.evaluate((element, { selector, text }) => {
    delete document.documentElement.dataset.navigationTime

    element.addEventListener('click', () => {
      const started = performance.now()

      function checkFrame() {
        const elapsed = performance.now() - started
        const visible = Array.from(document.querySelectorAll<HTMLElement>(selector)).some((candidate) =>
          candidate.textContent?.trim() === text
          && candidate.getClientRects().length > 0
          && getComputedStyle(candidate).visibility === 'visible',
        )

        if (visible || elapsed >= 1_000) {
          document.documentElement.dataset.navigationTime = visible ? String(elapsed) : 'Infinity'
        } else {
          window.requestAnimationFrame(checkFrame)
        }
      }

      window.requestAnimationFrame(checkFrame)
    }, { capture: true, once: true })
  }, target)

  await trigger.click()
  const result = page.locator('html[data-navigation-time]')
  await expect(result).toBeAttached()
  return Number(await result.getAttribute('data-navigation-time'))
}

test('shows cached, prefetched pages and details within 100 ms of navigation', async ({ page }, info) => {
  const api = await mockApi(page, { refreshDelayMs: 350 })
  const nextPage = page.waitForResponse((response) => {
    const url = new URL(response.url())
    return url.pathname === `/api/v1${storePath}/perfumes` && url.searchParams.get('pagina') === '2' && response.ok()
  })
  await page.goto(showcasePath)
  await expect(page.getByRole('heading', { name: 'Vitrina', exact: true })).toBeVisible()
  await (await nextPage).finished()

  const detailLink = page.getByRole('main').getByRole('link', { name: new RegExp(featuredPerfume.nombre) })
  const detailPrefetch = page.waitForResponse((response) =>
    new URL(response.url()).pathname === `/api/v1${perfumePath()}` && response.ok(),
  )
  await detailLink.focus()
  await (await detailPrefetch).finished()

  const detail = await measureNavigation(page, detailLink, { selector: 'main h1', text: featuredPerfume.nombre })
  await expect(page).toHaveURL(perfumePath())

  const headerShowcase = page.getByRole('banner').getByRole('link', { name: 'Vitrina', exact: true })
  const showcase = await measureNavigation(page, headerShowcase, {
    selector: `main a[href="${perfumePath()}"]`,
    text: featuredPerfume.nombre,
  })
  await expect(page).toHaveURL(showcasePath)

  const pagination = await measureNavigation(page, page.getByRole('link', { name: 'Siguiente', exact: true }), {
    selector: `main a[href="${perfumePath(followingPerfume.id)}"]`,
    text: followingPerfume.nombre,
  })

  const timings = { 'Volver a la vitrina': showcase, 'Abrir la página siguiente': pagination, 'Abrir un perfume': detail }
  await info.attach('Tiempos de navegación (ms)', { body: JSON.stringify(timings, null, 2), contentType: 'application/json' })

  for (const [transition, time] of Object.entries(timings)) {
    console.log(`${transition}: ${time.toFixed(1)} ms`)
    expect.soft(time, transition).toBeLessThanOrEqual(100)
  }

  const profileRequests = api.requests.filter((request) => new URL(request.url()).pathname === `/api/v1${storePath}`)
  expect(profileRequests).toHaveLength(1)
})
