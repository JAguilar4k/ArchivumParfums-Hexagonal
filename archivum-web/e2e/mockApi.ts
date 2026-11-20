import type { Page, Request, Route } from '@playwright/test'
import { setTimeout as delay } from 'node:timers/promises'
import type { components } from '../src/api/schema'
import {
  customer,
  demoAppointment,
  demoOrder,
  featuredPerfume,
  owner,
  perfumes,
  recommendation,
  service,
  slot,
  storeProfile,
  storeSlug,
} from './demo'

type Schemas = components['schemas']
type Perfume = Schemas['ShowcasePerfumeDetail']

interface Options {
  gateway?: 'onvo' | 'paypal'
  refreshDelayMs?: number
  slowSearch?: { text: string; delayMs: number }
  firstSlotTaken?: boolean
  soldOutOnFirstCheckout?: boolean
  featuredPhoto?: Pick<Perfume, 'foto_url' | 'foto_variantes'>
}

function json(route: Route, data: unknown, status = 200) {
  return route.fulfill({ status, json: data })
}

function problem(route: Route, status: number, type: string, detail: string, errores?: Record<string, string[]>) {
  return route.fulfill({
    status,
    contentType: 'application/problem+json',
    body: JSON.stringify({
      status,
      type: `https://api.example.test/problemas/${type}`,
      title: detail,
      detail,
      instance: `urn:uuid:${route.request().headers()['x-request-id']}`,
      errores,
    }),
  })
}

function notFound(route: Route) {
  return problem(route, 404, 'no-encontrado', 'Ese recurso no está disponible.')
}

function paginated<T>(items: readonly T[], count: number, page: number) {
  const start = (page - 1) * count
  const data = items.slice(start, start + count)
  return {
    data,
    links: { first: null, last: null, prev: null, next: null },
    meta: {
      current_page: page,
      from: data.length ? start + 1 : null,
      last_page: Math.max(1, Math.ceil(items.length / count)),
      links: [],
      path: null,
      per_page: count,
      to: data.length ? start + data.length : null,
      total: items.length,
    },
  }
}

function matches(actual: string, wanted: string | null): boolean {
  return !wanted || actual.toLocaleLowerCase('es').includes(wanted.toLocaleLowerCase('es'))
}

function filteredPerfumes(catalog: Perfume[], query: URLSearchParams): Perfume[] {
  return catalog.filter((perfume) => {
    const notes = Object.values(perfume.notas).flat().join(' ')
    const { momento, clima, ocasion } = perfume.etiquetas
    const price = Number(query.get('precio_maximo'))

    return matches(`${perfume.marca} ${perfume.nombre} ${notes}`, query.get('texto'))
      && matches(perfume.familia ?? '', query.get('familia'))
      && matches(notes, query.get('nota'))
      && matches(ocasion.join(' '), query.get('ocasion'))
      && (!query.has('momento') || momento.includes(query.get('momento')))
      && (!query.has('clima') || clima.includes(query.get('clima')))
      && (!price || perfume.presentaciones.some((bottle) => bottle.precio_venta <= price))
  })
}

export async function mockApi(page: Page, options: Options = {}) {
  const requests: Request[] = []
  const previouslyServed = new Set<string>()
  const gateway = options.gateway ?? 'onvo'
  const featuredPhoto = options.featuredPhoto
  const storePerfumes = featuredPhoto
    ? perfumes.map((perfume) => perfume.id === featuredPerfume.id ? { ...perfume, ...featuredPhoto } : perfume)
    : perfumes
  const secondStore = 'esencias-del-norte'
  let appointment = { ...demoAppointment }
  let order = { ...demoOrder }
  let payment: Schemas['PaymentResource'] | null = null
  let slotTaken = false
  let soldOut = false

  await page.route('**/sandbox-pago', (route) => route.fulfill({
    status: 302,
    headers: { Location: '/pagos/procesando?estado=exito' },
  }))

  await page.route('**/api/v1/**', async (route) => {
    const request = route.request()
    const url = new URL(request.url())
    const path = url.pathname.slice('/api/v1'.length)
    const parts = path.split('/').slice(1)
    const method = request.method()
    const body: unknown = request.postDataJSON()
    requests.push(request)

    if (path === '/cuentas' && method === 'POST') {
      return json(route, { data: customer }, 201)
    }

    if (path === '/sesiones' && method === 'POST') {
      const payload = body as Schemas['StoreSessionRequest']
      const token = payload.correo === owner.correo ? 'demo-owner' : 'demo-customer'
      return json(route, { data: { token, tipo: 'Bearer', vence_en: new Date(Date.now() + 3_600_000).toISOString() } }, 201)
    }

    if (path === '/sesiones/actual' && method === 'DELETE') {
      return route.fulfill({ status: 204 })
    }

    if (parts[0] === 'cuenta') {
      if (!request.headers().authorization) {
        return problem(route, 401, 'no-autenticado', 'Inicie sesión para continuar.')
      }

      if (path === '/cuenta' && method === 'GET') {
        const account = request.headers().authorization?.endsWith('demo-owner') ? owner : customer
        return json(route, { data: account })
      }

      if (path === '/cuenta/pedidos') {
        return json(route, paginated([order], 12, 1))
      }

      if (parts[1] === 'pagos' && payment && parts[2] === payment.id && method === 'GET') {
        return json(route, { data: payment })
      }

      if (parts[1] === 'pedidos' && parts[2] === order.id) {
        if (parts[3] === 'pagos' && method === 'POST') {
          payment = {
            id: '0193b0d4-0000-7000-8000-0000000000b1',
            proposito: 'deposito',
            monto: order.por_cobrar_ahora,
            moneda_cobrada: gateway === 'paypal' ? 'USD' : 'CRC',
            monto_cobrado: gateway === 'paypal' ? 19_870 : order.por_cobrar_ahora,
            pasarela: gateway,
            estado: 'pendiente',
            url_pago: `${url.origin}/sandbox-pago`,
            medio: 'tarjeta',
            consulta_pendiente: false,
          }
          return json(route, { data: payment }, 201)
        }

        if (parts[3] === 'cancelacion' && method === 'POST') {
          order = { ...order, estado: 'cancelado', cancelado_en: new Date().toISOString() }
        }

        return json(route, { data: order })
      }

      if (path === '/cuenta/citas') {
        const past = { ...demoAppointment, id: `${demoAppointment.id.slice(0, -1)}2`, estado: 'atendida',
          inicia_en: '2025-11-23T09:00:00-06:00', termina_en: '2025-11-23T09:45:00-06:00' }
        return json(route, paginated([url.searchParams.get('periodo') === 'pasadas' ? past : appointment], 12, 1))
      }

      if (parts[1] === 'citas' && parts[2] === appointment.id) {
        if (method === 'PATCH') {
          const { inicia_en } = body as Schemas['RescheduleAppointmentRequest']
          appointment = { ...appointment, inicia_en, termina_en: inicia_en.replace(':00:00', ':45:00') }
        }
        if (parts[3] === 'cancelacion' && method === 'POST') {
          appointment = { ...appointment, estado: 'cancelada' }
        }

        return json(route, { data: appointment })
      }

      return notFound(route)
    }

    if (parts[0] !== 'tiendas' || (parts[1] !== storeSlug && parts[1] !== secondStore)) {
      return notFound(route)
    }

    if (parts.length === 2) {
      const profile = parts[1] === storeSlug ? storeProfile : { ...storeProfile, slug: secondStore, nombre: 'Esencias del Norte' }
      return json(route, { data: profile })
    }

    if (parts[2] === 'perfumes' && method === 'GET') {
      if (options.refreshDelayMs && previouslyServed.has(url.href)) {
        await delay(options.refreshDelayMs)
      }
      previouslyServed.add(url.href)

      const catalog = parts[1] === storeSlug ? storePerfumes : []
      if (parts[3]) {
        const perfume = catalog.find((item) => item.id === parts[3])
        return perfume
          ? json(route, { data: { ...perfume, decants: soldOut ? [] : perfume.decants } })
          : notFound(route)
      }

      if (options.slowSearch && url.searchParams.get('texto') === options.slowSearch.text) {
        await delay(options.slowSearch.delayMs)
      }
      if (Number(url.searchParams.get('precio_maximo')) < 0) {
        return problem(route, 422, 'datos-invalidos', 'Revise el precio máximo.', { precio_maximo: ['Escriba un precio positivo.'] })
      }

      const filtered = filteredPerfumes(catalog, url.searchParams)
      return json(route, paginated(filtered, Number(url.searchParams.get('por_pagina') ?? 12), Number(url.searchParams.get('pagina') ?? 1)))
    }

    if (parts[2] === 'servicios') {
      return json(route, { data: [service] })
    }

    if (parts[2] === 'disponibilidad') {
      return json(route, { data: slotTaken ? [slot('10'), slot('11')] : [slot('09'), slot('10')] })
    }

    if (parts[2] === 'citas' && method === 'POST') {
      if (options.firstSlotTaken && !slotTaken) {
        slotTaken = true
        return problem(route, 409, 'conflicto', 'Ese horario ya no está disponible.')
      }
      const { inicia_en } = body as Schemas['StoreAppointment']
      appointment = { ...appointment, inicia_en, termina_en: inicia_en.replace(':00:00', ':45:00') }
      return json(route, { data: appointment }, 201)
    }

    if (parts[2] === 'pedidos' && method === 'POST') {
      if (options.soldOutOnFirstCheckout && !soldOut) {
        soldOut = true
        return problem(route, 409, 'conflicto', 'Ya no quedan mililitros disponibles.')
      }
      const { lineas } = body as Schemas['StoreOrder']
      order = { ...order, lineas: lineas.map((line, index) => ({
        id: `linea-${index}`,
        perfume: featuredPerfume.nombre,
        botella_id: line.botella_id ?? null,
        presentacion_id: line.presentacion_id ?? null,
        tipo: line.botella_id ? 'decant' : 'botella',
        cantidad: line.cantidad ?? 1,
        ml: line.ml ?? null,
        presentacion_ml: line.presentacion_id ? 50 : null,
        precio_unitario: line.botella_id ? 9_500 : 185_000,
        paquete_id: null,
        estado: 'pendiente',
      })) }
      return json(route, { data: order }, 201)
    }

    if (parts[2] === 'recomendaciones' && method === 'POST') {
      const { texto } = body as Schemas['StoreRecommendation']
      return texto === 'Una solicitud de prueba demasiado breve'
        ? problem(route, 422, 'datos-invalidos', 'Amplíe la descripción.', { texto: ['Amplíe la descripción.'] })
        : json(route, { data: recommendation })
    }

    if (parts[2] === 'paquetes') {
      return json(route, { data: [] })
    }

    return notFound(route)
  })

  return {
    requests,
    confirmPayment() {
      if (payment) payment = { ...payment, estado: 'confirmado' }
      order = { ...order, estado: 'confirmado', pagado: order.por_cobrar_ahora, por_cobrar_ahora: 0 }
    },
    rejectPayment() {
      if (!payment) throw new Error('The test must start a payment before rejecting it.')
      payment = { ...payment, estado: 'fallido', url_pago: null }
    },
  }
}
