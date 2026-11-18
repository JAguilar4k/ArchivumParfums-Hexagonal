import { nextTick, ref, watchEffect } from 'vue'
import type { RouteLocationNormalized, Router } from 'vue-router'

declare module 'vue-router' {
  interface RouteMeta {
    /** Name of the page, for the title of the tab and the announcement after a navigation (#126). */
    title?: string
  }
}

const FALLBACK_SITE_NAME = 'Tienda de perfumes'
let siteName = FALLBACK_SITE_NAME
let currentPage: string | null = null

/** Id of the main content: the target of "Saltar al contenido" and of the focus after a navigation. */
export const MAIN_CONTENT_ID = 'contenido'

/** What the announcer of App.vue reads after a navigation: the name of the new page. */
export const announcement = ref('')

/** Title of the tab: the page first, then the site (WCAG 2.4.2). */
export function pageTitle(page?: string | null): string {
  return page ? `${page} · ${siteName}` : siteName
}

export function setPageTitle(page?: string | null): void {
  currentPage = page ?? null
  document.title = pageTitle(page)
}

export function setSiteName(name?: string): void {
  siteName = name || FALLBACK_SITE_NAME
  setPageTitle(currentPage)
}

/**
 * Gives the tab a more precise title once the data of the view arrives, such as the name of a perfume. Until then,
 * and when it returns nothing, the tab keeps the title of the route.
 */
export function usePageTitle(page: () => string | null | undefined): void {
  watchEffect(() => {
    const title = page()

    if (title) {
      setPageTitle(title)
    }
  })
}

/**
 * Moves the focus to the main content. After a navigation it does not scroll, because the router already decides
 * where the page starts; "Saltar al contenido" scrolls to it.
 */
export function focusMainContent(options: FocusOptions = { preventScroll: true }): void {
  document.getElementById(MAIN_CONTENT_ID)?.focus(options)
}

/**
 * Moves the focus to the named section or the page heading. When data is pending, focus the main content until the
 * heading arrives. If the visitor has moved away by then, keep their focus where it is.
 */
function focusTarget(hash: string): MutationObserver | null {
  const main = document.getElementById(MAIN_CONTENT_ID)
  const section = hash ? document.getElementById(hash.slice(1)) : null

  if (section) {
    section.focus({ preventScroll: true })

    if (document.activeElement === section) {
      return null
    }
  }

  function focusHeading(): boolean {
    const heading = main?.querySelector('h1')
    heading?.setAttribute('tabindex', '-1')
    heading?.focus({ preventScroll: true })
    return Boolean(heading && document.activeElement === heading)
  }

  if (!main || focusHeading()) {
    return null
  }

  focusMainContent()
  const observer = new MutationObserver(() => {
    if (document.activeElement !== main || focusHeading()) {
      observer.disconnect()
    }
  })
  observer.observe(main, { childList: true, subtree: true })
  return observer
}

/**
 * After each navigation (#126): the tab takes the title of the route and, when the page changes, the focus goes to
 * the main content and the announcer reads the name of the page. Without it, the focus would stay on the link that
 * was used, which no longer exists, and a screen reader would not know that the page changed. A change of the query
 * alone, such as a search or a page of results, keeps the focus where it is; the view says what changed. A link to a
 * part of a page (#128) takes the focus there instead.
 */
export function installPageChanges(router: Router): void {
  let headingObserver: MutationObserver | null = null

  router.afterEach((to: RouteLocationNormalized, from: RouteLocationNormalized, failure) => {
    if (failure) {
      return
    }

    if (to.path === from.path && from.matched.length > 0) {
      if (to.hash && to.hash !== from.hash) {
        void nextTick(() => {
          headingObserver?.disconnect()
          headingObserver = focusTarget(to.hash)
        })
      }

      return
    }

    setPageTitle(to.meta.title)

    // The first route of the tab: the browser already announces the page.
    if (from.matched.length === 0) {
      return
    }

    headingObserver?.disconnect()
    announcement.value = ''
    void nextTick(() => {
      announcement.value = currentPage ?? siteName
      headingObserver = focusTarget(to.hash)
    })
  })
}
