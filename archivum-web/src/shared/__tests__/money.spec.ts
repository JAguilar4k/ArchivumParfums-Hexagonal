import { describe, expect, it } from 'vitest'

import { formatColones } from '../money'

describe('formatColones', () => {
  it('shows whole colones with the thousands separator and without decimals', () => {
    expect(formatColones(85000).replace(/\s/g, ' ')).toBe('₡85 000')
    expect(formatColones(1250000).replace(/\s/g, ' ')).toBe('₡1 250 000')
    expect(formatColones(900)).toBe('₡900')
  })
})
