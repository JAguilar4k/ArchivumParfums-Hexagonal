import { describe, expect, it } from 'vitest'

import { formatCharge, formatColones } from '../money'

describe('formatColones', () => {
  it('shows whole colones with the thousands separator and without decimals', () => {
    expect(formatColones(85000).replace(/\s/g, ' ')).toBe('₡85 000')
    expect(formatColones(1250000).replace(/\s/g, ' ')).toBe('₡1 250 000')
    expect(formatColones(900)).toBe('₡900')
  })
})

describe('formatCharge', () => {
  it('shows the cents of a charge in dollars and whole colones as they are', () => {
    expect(formatCharge(1234, 'USD').replace(/\s/g, ' ')).toBe('USD 12,34')
    expect(formatCharge(9500, 'CRC').replace(/\s/g, ' ')).toBe('₡9 500')
  })
})
