/**
 * Client configuration. Every value comes from the Vite environment variables
 * declared in .env.example, so no address is written in the code.
 */

type Env = Partial<Pick<ImportMetaEnv, 'VITE_API_URL' | 'VITE_DEFAULT_BUSINESS_SLUG' | 'VITE_STORE_WHATSAPP'>>

/**
 * Base URL of the API, without a trailing slash (for example `http://localhost:8000/api/v1`).
 */
export function apiUrl(env: Env = import.meta.env): string {
  const url = env.VITE_API_URL?.trim()

  if (!url) {
    throw new Error('VITE_API_URL is not set: copy .env.example to .env and set the API address.')
  }

  return url.replace(/\/+$/, '')
}

/**
 * Slug of the store that the root of the client opens (for example `archivumparfums`, the pilot store of the seeders).
 */
export function defaultBusinessSlug(env: Env = import.meta.env): string {
  const slug = env.VITE_DEFAULT_BUSINESS_SLUG?.trim()

  if (!slug) {
    throw new Error('VITE_DEFAULT_BUSINESS_SLUG is not set: copy .env.example to .env and set the slug of the store.')
  }

  return slug
}

export function storeWhatsApp(env: Env = import.meta.env): string {
  return env.VITE_STORE_WHATSAPP?.trim() ?? ''
}
