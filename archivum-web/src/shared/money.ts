const colones = new Intl.NumberFormat('es-CR', {
  style: 'currency',
  currency: 'CRC',
  maximumFractionDigits: 0,
})

const dollars = new Intl.NumberFormat('es-CR', { style: 'currency', currency: 'USD' })

/**
 * An amount in whole colones exactly as the API sends it, with the colón sign and the thousands separator
 * (`₡85 000`). The client never calculates a price: it only formats the ones of the API.
 */
export function formatColones(amount: number): string {
  return colones.format(amount)
}

/**
 * What a gateway charges, in the unit that the API sends in `monto_cobrado` (#92): whole colones in CRC and cents of
 * a dollar in USD, which PayPal charges (`USD 12,34`).
 */
export function formatCharge(amount: number, currency: string): string {
  return currency === 'USD' ? dollars.format(amount / 100) : formatColones(amount)
}
