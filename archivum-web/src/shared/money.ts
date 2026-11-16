const colones = new Intl.NumberFormat('es-CR', {
  style: 'currency',
  currency: 'CRC',
  maximumFractionDigits: 0,
})

/**
 * An amount in whole colones exactly as the API sends it, with the colón sign and the thousands separator
 * (`₡85 000`). The client never calculates a price: it only formats the ones of the API.
 */
export function formatColones(amount: number): string {
  return colones.format(amount)
}
