// Any photo under perfumes/ is public. A photo that must not be shown is deleted from the bucket.
const photoKey = /^perfumes\/(?:[a-z0-9][a-z0-9_-]*\/)*[a-z0-9][a-z0-9_-]*\.(jpg|jpeg|png|webp)$/i
const variantWidths = new Set(['320', '640', '960'])
const imageTypes = new Map([
  ['jpg', 'image/jpeg'],
  ['jpeg', 'image/jpeg'],
  ['png', 'image/png'],
  ['webp', 'image/webp'],
])

function photoRequest(path) {
  const variant = /^\/imagenes\/(\d+)\/(.+)$/.exec(path)
  if (variant && !variantWidths.has(variant[1])) return null
  const key = variant ? variant[2] : path.slice(1)

  return photoKey.test(key) ? { key, width: variant ? Number(variant[1]) : null } : null
}

function serve(body, headers = {}) {
  const responseHeaders = new Headers(headers)
  responseHeaders.set('Cache-Control', 'public, max-age=3600, s-maxage=86400')
  responseHeaders.set('X-Content-Type-Options', 'nosniff')
  return new Response(body, { headers: responseHeaders })
}

async function loadPhoto(photo, env) {
  const object = await env.CATALOG_PHOTOS.get(photo.key)
  if (!object) return new Response(null, { status: 404 })

  if (photo.width === null) {
    const extension = photo.key.slice(photo.key.lastIndexOf('.') + 1)
    return serve(object.body, {
      'Content-Type': imageTypes.get(extension),
      'Content-Length': String(object.size),
      ETag: object.httpEtag,
    })
  }

  try {
    const result = await env.IMAGES.input(object.body).transform({ width: photo.width, fit: 'scale-down' })
      .output({ format: 'image/webp', quality: 80 })
    const response = result.response()
    if (response.ok) return serve(response.body, { 'Content-Type': 'image/webp' })
  } catch {
    // The free transformation quota may be exhausted during the month.
  }

  return new Response(null, {
    status: 302,
    headers: { Location: `/${photo.key}`, 'Cache-Control': 'public, max-age=300' },
  })
}

export default {
  async fetch(request, env, context) {
    if (request.method !== 'GET' && request.method !== 'HEAD') {
      return new Response(null, { status: 405, headers: { Allow: 'GET, HEAD' } })
    }

    const url = new URL(request.url)
    const photo = photoRequest(url.pathname)
    if (!photo) return new Response(null, { status: 404 })

    url.search = ''
    const cacheKey = new Request(url)
    const cache = caches.default
    let response = await cache.match(cacheKey)
    if (!response) {
      response = await loadPhoto(photo, env)
      if (response.ok) context.waitUntil(cache.put(cacheKey, response.clone()))
    }

    return request.method === 'HEAD' ? new Response(null, response) : response
  },
}
