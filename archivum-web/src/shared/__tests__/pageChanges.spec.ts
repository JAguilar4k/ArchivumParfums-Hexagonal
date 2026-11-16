import { afterEach, describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { defineComponent, h, ref } from 'vue'
import { createMemoryHistory, createRouter, type RouteRecordRaw } from 'vue-router'

import App from '@/App.vue'
import { announcement, installPageChanges, pageTitle, usePageTitle } from '../pageChanges'

const Page = (text: string) => defineComponent({ render: () => h('h1', text) })

// A view that learns its precise title after its data arrives, as the detail of a perfume.
const name = ref<string | null>(null)
const Detail = defineComponent({
  setup() {
    usePageTitle(() => name.value)

    return () => h('h1', name.value ?? 'Cargando…')
  },
})

const routes: RouteRecordRaw[] = [
  { path: '/', name: 'home', component: Page('Inicio'), meta: { title: 'Inicio' } },
  { path: '/vitrina', name: 'showcase', component: Page('Vitrina'), meta: { title: 'Vitrina' } },
  { path: '/perfume', name: 'perfume', component: Detail, meta: { title: 'Perfume' } },
  { path: '/sin-titulo', name: 'untitled', component: Page('Sin título') },
  // The session menu of the header links to the sign-in, and the footer to the help.
  { path: '/iniciar-sesion', name: 'sign-in', component: Page('Iniciar sesión'), meta: { title: 'Iniciar sesión' } },
  {
    path: '/ayuda',
    name: 'help',
    component: defineComponent({ render: () => h('dl', [h('dt', { id: 'decant', tabindex: -1 }, 'Decant')]) }),
    meta: { title: 'Ayuda y glosario' },
  },
]

async function start(path: string) {
  const router = createRouter({ history: createMemoryHistory(), routes })
  installPageChanges(router)
  await router.push(path)

  const wrapper = mount(App, { global: { plugins: [createPinia(), router] }, attachTo: document.body })
  await flushPromises()

  return { wrapper, router }
}

afterEach(() => {
  document.body.innerHTML = ''
  announcement.value = ''
  name.value = null
})

describe('page changes', () => {
  it('names the tab after the page, then the site', async () => {
    await start('/vitrina')

    expect(document.title).toBe('Vitrina · ArchivumParfums')
    expect(pageTitle(null)).toBe('ArchivumParfums')
  })

  it('leaves the focus and the announcer alone on the first page of the tab', async () => {
    await start('/vitrina')

    expect(document.activeElement).toBe(document.body)
    expect(announcement.value).toBe('')
  })

  it('moves the focus to the main content and announces the page after a navigation', async () => {
    const { router } = await start('/')

    await router.push('/vitrina')
    await flushPromises()

    expect(document.activeElement?.id).toBe('contenido')
    expect(document.title).toBe('Vitrina · ArchivumParfums')
    expect(document.querySelector('[role="status"]')?.textContent).toBe('Vitrina')
  })

  it('keeps the focus where it is when only the query changes', async () => {
    const { wrapper, router } = await start('/vitrina')
    const link = wrapper.get('.app-header__brand').element as HTMLElement
    link.focus()

    await router.push('/vitrina?texto=oud')
    await flushPromises()

    expect(document.activeElement).toBe(link)
  })

  it('uses the site alone for a route without a title', async () => {
    const { router } = await start('/')

    await router.push('/sin-titulo')
    await flushPromises()

    expect(document.title).toBe('ArchivumParfums')
    expect(announcement.value).toBe('ArchivumParfums')
  })

  it('gives the tab the precise title of the view once its data arrives', async () => {
    const { router } = await start('/')

    await router.push('/perfume')
    await flushPromises()
    expect(document.title).toBe('Perfume · ArchivumParfums')

    name.value = 'Oud Nocturno'
    await flushPromises()
    expect(document.title).toBe('Oud Nocturno · ArchivumParfums')
  })

  it('takes the focus to the part of the page that a link names', async () => {
    const { router } = await start('/vitrina')

    await router.push('/ayuda#decant')
    await flushPromises()

    expect(document.activeElement?.id).toBe('decant')
    expect(announcement.value).toBe('Ayuda y glosario')
  })

  it('links every page to the help from its footer', async () => {
    const { wrapper } = await start('/vitrina')

    expect(wrapper.get('footer a').text()).toBe('Ayuda y glosario')
    expect(wrapper.get('footer a').attributes('href')).toBe('/ayuda')
  })

  it('skips to the main content from the first link of the page', async () => {
    const { wrapper } = await start('/vitrina')
    const skip = wrapper.get('.skip-link')

    expect(skip.text()).toBe('Saltar al contenido')
    expect(skip.attributes('href')).toBe('#contenido')

    await skip.trigger('click')

    expect(document.activeElement?.id).toBe('contenido')
  })
})
