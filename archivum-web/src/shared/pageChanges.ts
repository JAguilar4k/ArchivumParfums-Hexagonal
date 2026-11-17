import { nextTick, ref, watchEffect } from 'vue'
import type { RouteLocationNormalized, Router } from 'vue-router'

declare module 'vue-router' {
  interface RouteMeta {
    /** Name of the page, for the title of the tab and the announcement after a navigation (#126). */
    title?: string
  }
}

export const SITE_NAME = 'ArchivumParfums'

/** Id of the main content: the target of "Saltar al contenido" and of the focus after a navigation. */
export const MAIN_CONTENT_ID = 'contenido'

/** What the announcer of App.vue reads after a navigation: the name of the new page. */
export const announcement = ref('')

/** Title of the tab: the page first, then the site (WCAG 2.4.2). */
export function pageTitle(page?: string | null): string {
  return page ? `${page} · ${SITE_NAME}` : SITE_NAME
}

export function setPageTitle(page?: string | null): void {
  document.title = pageTitle(page)
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
 * Moves the focus to the part of the page that the hash of the URL names, such as a term of the glossary
 * (`/ayuda#decant`), or to the main content when there is none. The router scrolls to it.
 */
function focusTarget(hash: string): void {
  const target = hash ? document.getElementById(decodeURIComponent(hash.slice(1))) : null
  target?.focus({ preventScroll: true })

  if (!target || document.activeElement !== target) {
    focusMainContent()
  }
}

/**
 * After each navigation (#126): the tab takes the title of the route and, when the page changes, the focus goes to
 * the main content and the announcer reads the name of the page. Without it, the focus would stay on the link that
 * was used, which no longer exists, and a screen reader would not know that the page changed. A change of the query
 * alone, such as a search or a page of results, keeps the focus where it is; the view says what changed. A link to a
 * part of a page (#128) takes the focus there instead.
 */
export function installPageChanges(router: Router): void {
  router.afterEach((to: RouteLocationNormalized, from: RouteLocationNormalized, failure) => {
    if (failure) {
      return
    }

    setPageTitle(to.meta.title)

    // The first route of the tab: the browser already announces the page.
    if (from.matched.length === 0) {
      return
    }

    if (to.path !== from.path) {
      announcement.value = ''
      void nextTick(() => {
        announcement.value = to.meta.title ?? SITE_NAME
        focusTarget(to.hash)
      })
    } else if (to.hash && to.hash !== from.hash) {
      void nextTick(() => focusTarget(to.hash))
    }
  })
}
