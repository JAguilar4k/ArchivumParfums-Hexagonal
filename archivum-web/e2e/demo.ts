import type { components } from '../src/api/schema'

type Schemas = components['schemas']
type Perfume = Schemas['ShowcasePerfumeDetail']

export const storeSlug = 'archivumparfums'
export const storePath = `/tiendas/${storeSlug}`
export const showcasePath = `${storePath}/vitrina`
export const storeProfile: Schemas['PublicBusiness'] = {
  slug: storeSlug,
  nombre: 'ArchivumParfums',
  logo_url: null,
  lugar_cita: 'San Carlos, Costa Rica',
  zona_horaria: 'America/Costa_Rica',
}

function perfumeId(number: number): string {
  return `0193b0d4-0000-7000-8000-${number.toString(16).padStart(12, '0')}`
}

function perfume(number: number, marca: string, nombre: string, familia: string, precio: number): Perfume {
  const id = number * 4
  return {
    id: perfumeId(id),
    marca,
    nombre,
    familia,
    concentracion: 'EDP',
    genero: 'unisex',
    foto_url: null,
    descripcion: null,
    notas: { salida: ['Bergamota'], corazon: ['Rosa', 'Incienso'], fondo: ['Oud', 'Vainilla'] },
    etiquetas: { momento: ['noche'], clima: ['frio'], ocasion: ['Cena'] },
    presentaciones: [
      { id: perfumeId(id + 1), ml: 50, precio_venta: precio },
      { id: perfumeId(id + 2), ml: 100, precio_venta: precio + 60_000 },
    ],
    decants: [{ botella_id: perfumeId(id + 3), presentacion_ml: 100, ml: 5, precio: 9_500 }],
  }
}

export const featuredPerfume = perfume(101, 'Maison Noctis', 'Oud Nocturno', 'Amaderada', 185_000)
export const followingPerfume = perfume(201, 'Casa Sotobosque', 'Vetiver de Montaña', 'Amaderada', 135_000)

const alba = perfume(103, 'Casa Aurora', 'Alba de Azahar', 'Floral', 98_000)
alba.etiquetas = { momento: ['dia'], clima: ['calido'], ocasion: ['Boda'] }
alba.notas.corazon = ['Azahar']

export const perfumes = [
  perfume(102, 'Atelier Brisa', 'Sal Marina', 'Acuática', 120_000),
  alba,
  perfume(104, 'Casa Aurora', 'Neroli de Abril', 'Cítrica', 115_000),
  perfume(105, 'Estudio Lumbre', 'Bergamota y Miel', 'Cítrica', 99_000),
  featuredPerfume,
  perfume(106, 'Maison Noctis', 'Tabaco Dulce', 'Oriental', 210_000),
  perfume(107, 'Niebla Botánica', 'Cedro y Bruma', 'Amaderada', 135_000),
  perfume(108, 'Niebla Botánica', 'Jardín de Orquídeas', 'Floral', 160_000),
  perfume(109, 'Niebla Botánica', 'Lluvia de Pino', 'Aromática', 102_000),
  perfume(110, 'Casa Sotobosque', 'Bosque de Ámbar', 'Amaderada', 140_000),
  perfume(111, 'Casa Sotobosque', 'Cuero Antiguo', 'Amaderada', 175_000),
  perfume(122, 'Casa Sotobosque', 'Enebro Gris', 'Aromática', 116_000),
  followingPerfume,
  perfume(202, 'Taller Rocío', 'Última Marea', 'Acuática', 108_000),
]

export function perfumePath(id = featuredPerfume.id): string {
  return `${storePath}/perfumes/${id}`
}

export const customer: Schemas['CurrentAccountResource'] = {
  id: '0193b0d4-0000-7000-8000-000000000020',
  nombre: 'Ana Cliente',
  correo: 'cliente@example.test',
  rol: 'cliente',
  tienda: null,
}

export const demoStore = { id: '0193b0d4-0000-7000-8000-000000000030', slug: storeSlug, nombre: storeProfile.nombre }
export const owner: Schemas['CurrentAccountResource'] = {
  ...customer,
  nombre: 'Cuenta Dueña',
  correo: 'duena@example.test',
  rol: 'dueno',
  tienda: demoStore,
}

export const service: Schemas['PublicService'] = {
  id: '0193b0d4-0000-7000-8000-000000000301',
  nombre: 'Prueba de olores',
  duracion_minutos: 45,
  precio: 0,
  maximo_perfumes: 5,
}
export const appointmentDay = '2035-11-24'
export const appointmentsPath = '/cuenta/citas'
const person = { id: '0193b0d4-0000-7000-8000-000000000311', nombre: 'Asesora de prueba' }
const secondPerson = { id: '0193b0d4-0000-7000-8000-000000000312', nombre: 'Asesor de prueba' }

export function slot(hour: string): Schemas['AvailableSlotResource'] {
  return {
    inicia_en: `${appointmentDay}T${hour}:00:00-06:00`,
    termina_en: `${appointmentDay}T${hour}:45:00-06:00`,
    personas: [person, secondPerson],
  }
}

export const demoAppointment: Schemas['CustomerAppointmentResource'] = {
  id: '0193b0d4-0000-7000-8000-000000000321',
  tienda: demoStore,
  estado: 'confirmada',
  servicio: service,
  persona: person,
  inicia_en: slot('09').inicia_en,
  termina_en: slot('09').termina_en,
  lugar_cita: storeProfile.lugar_cita,
  perfumes: [{ id: featuredPerfume.id, nombre: featuredPerfume.nombre }],
}

export const ordersPath = '/cuenta/pedidos'
export const demoOrder: Schemas['OrderResource'] = {
  id: '0193b0d4-0000-7000-8000-0000000000a1',
  tienda: demoStore,
  cliente: customer,
  estado: 'pendiente_de_pago',
  subtotal: 194_500,
  por_cobrar_ahora: 102_000,
  pagado: 0,
  saldo: 194_500,
  creado_en: '2026-11-15T18:00:00-06:00',
  vence_en: '2035-11-24T18:30:00-06:00',
  cancelado_en: null,
  lineas: [],
}

export const recommendation: Schemas['RecommendationResource'] = {
  origen: 'ia',
  preferencias: { notas: ['Oud'], momento: 'noche', clima: null, ocasion: null, presupuesto: null },
  perfumes: [{
    id: featuredPerfume.id,
    marca: featuredPerfume.marca,
    nombre: featuredPerfume.nombre,
    razon: 'Combina oud e incienso para una cena de noche.',
    presentaciones: featuredPerfume.presentaciones,
  }],
}
