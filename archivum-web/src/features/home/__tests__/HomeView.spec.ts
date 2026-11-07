import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import HomeView from '../HomeView.vue'

describe('HomeView', () => {
  it('shows the store name in Spanish', () => {
    const wrapper = mount(HomeView)

    expect(wrapper.get('h1').text()).toBe('ArchivumParfums')
    expect(wrapper.text()).toContain('Citas de prueba de olores')
  })
})
