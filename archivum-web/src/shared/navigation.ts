/**
 * Leaves the client for a page of another site, such as the checkout of a gateway (#130). Only an http or https
 * address is followed, so a value of the API can never run a `javascript:` address. Returns whether it left.
 */
export function leaveTo(address: string): boolean {
  let url: URL

  try {
    url = new URL(address)
  } catch {
    return false
  }

  if (url.protocol !== 'https:' && url.protocol !== 'http:') {
    return false
  }

  window.location.assign(url.href)

  return true
}
