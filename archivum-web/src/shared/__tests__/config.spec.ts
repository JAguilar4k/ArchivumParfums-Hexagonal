import { describe, expect, it } from 'vitest'

import { apiUrl } from '../config'

describe('apiUrl', () => {
  it('returns the API address from VITE_API_URL', () => {
    expect(apiUrl({ VITE_API_URL: 'https://api.example.test/api/v1' })).toBe(
      'https://api.example.test/api/v1',
    )
  })

  it('drops the trailing slash so paths can be appended', () => {
    expect(apiUrl({ VITE_API_URL: 'https://api.example.test/api/v1/' })).toBe(
      'https://api.example.test/api/v1',
    )
  })

  it.each([undefined, '', '   '])('fails when VITE_API_URL is %j', (value) => {
    expect(() => apiUrl({ VITE_API_URL: value })).toThrow('VITE_API_URL is not set')
  })
})
