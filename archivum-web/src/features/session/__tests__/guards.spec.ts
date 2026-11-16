import { describe, expect, it } from 'vitest'
import { createMemoryHistory, createRouter, type RouteRecordRaw } from 'vue-router'

import { redirectTarget, sessionGuard, type SessionState } from '../guards'
import type { Role } from '../store'

const empty = { render: () => null }

const routes: RouteRecordRaw[] = [
  { path: '/', name: 'home', component: empty },
  { path: '/iniciar-sesion', name: 'sign-in', component: empty },
  { path: '/crear-cuenta', name: 'register', component: empty },
  { path: '/sin-permiso', name: 'forbidden', component: empty },
  { path: '/cuenta/citas', name: 'my-appointments', component: empty, meta: { roles: ['cliente'] } },
  {
    path: '/panel',
    meta: { roles: ['dueno'] },
    children: [
      { path: '', name: 'panel', component: empty },
      { path: 'perfumes', name: 'panel-perfumes', component: empty },
    ],
  },
  { path: '/panel/agenda', name: 'agenda', component: empty, meta: { roles: ['dueno', 'colaborador'] } },
]

function signedInAs(role: Role | null): SessionState {
  return { isSignedIn: role !== null, role }
}

async function navigate(path: string, session: SessionState) {
  const router = createRouter({ history: createMemoryHistory(), routes })
  router.beforeEach((to) => sessionGuard(to, session))
  await router.push(path)

  return router.currentRoute.value
}

describe('sessionGuard', () => {
  it('lets anybody open a public route', async () => {
    expect((await navigate('/', signedInAs(null))).name).toBe('home')
  })

  it('sends a visitor who opens an account view to the sign-in, with the route to return to', async () => {
    const route = await navigate('/cuenta/citas?estado=confirmada', signedInAs(null))

    expect(route.name).toBe('sign-in')
    expect(route.query.redirect).toBe('/cuenta/citas?estado=confirmada')
  })

  it('keeps a customer out of the panel', async () => {
    expect((await navigate('/panel', signedInAs('cliente'))).name).toBe('forbidden')
    expect((await navigate('/panel/agenda', signedInAs('cliente'))).name).toBe('forbidden')
    expect((await navigate('/cuenta/citas', signedInAs('cliente'))).name).toBe('my-appointments')
  })

  it('lets a staff member into the agenda only', async () => {
    expect((await navigate('/panel/agenda', signedInAs('colaborador'))).name).toBe('agenda')
    expect((await navigate('/panel', signedInAs('colaborador'))).name).toBe('forbidden')
    expect((await navigate('/panel/perfumes', signedInAs('colaborador'))).name).toBe('forbidden')
  })

  it('lets the owner into the whole panel, child routes included', async () => {
    expect((await navigate('/panel', signedInAs('dueno'))).name).toBe('panel')
    expect((await navigate('/panel/perfumes', signedInAs('dueno'))).name).toBe('panel-perfumes')
    expect((await navigate('/panel/agenda', signedInAs('dueno'))).name).toBe('agenda')
  })

  it('sends a signed-in account away from the sign-in and the registration', async () => {
    expect((await navigate('/iniciar-sesion', signedInAs('cliente'))).name).toBe('home')
    expect((await navigate('/crear-cuenta', signedInAs('cliente'))).name).toBe('home')
    expect((await navigate('/crear-cuenta', signedInAs(null))).name).toBe('register')
  })
})

describe('redirectTarget', () => {
  it('returns only paths of this application', () => {
    expect(redirectTarget('/cuenta/citas?estado=confirmada')).toBe('/cuenta/citas?estado=confirmada')
    expect(redirectTarget('//otro.sitio/robar')).toBe('/')
    expect(redirectTarget('https://otro.sitio')).toBe('/')
    expect(redirectTarget(undefined)).toBe('/')
    expect(redirectTarget(['/a', '/b'])).toBe('/')
  })
})
