import type { Account } from '../store'
import { json } from '@/api/__tests__/fakeApi'

export function tokenResponse(token: string, expiresAt: string): Response {
  return json(201, { data: { token, tipo: 'Bearer', vence_en: expiresAt } })
}

export function accountOf(rol: Account['rol']): Account {
  return {
    id: '0193b0d4-0000-7000-8000-000000000020',
    nombre: rol === 'cliente' ? 'Ana Cliente' : 'Dana Dueña',
    correo: 'cuenta@example.test',
    rol,
    tienda: rol === 'cliente' ? null : { id: '0193b0d4-0000-7000-8000-000000000030', nombre: 'ArchivumParfums', slug: 'archivum' },
  }
}
