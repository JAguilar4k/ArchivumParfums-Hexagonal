import { describe, expect, it } from 'vitest'

import { leaveTo } from '../navigation'

describe('leaveTo', () => {
  it('never follows an address that is not http or https', () => {
    expect(leaveTo('javascript:alert(1)')).toBe(false)
    expect(leaveTo('data:text/html,<script>alert(1)</script>')).toBe(false)
    expect(leaveTo('/relativa')).toBe(false)
    expect(leaveTo('')).toBe(false)
  })
})
