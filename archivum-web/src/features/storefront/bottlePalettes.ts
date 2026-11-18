const palettes = new Map([
  ['amaderada', { background: '#eadfce', ink: '#5b3a1e', glass: '#b98b5e' }],
  ['floral', { background: '#f4e1e5', ink: '#7e2f4a', glass: '#d79aae' }],
  ['acuatica', { background: '#dde9ee', ink: '#24566a', glass: '#8fbccc' }],
  ['oriental', { background: '#efdfc8', ink: '#7a3d0c', glass: '#d39a55' }],
  ['citrica', { background: '#f2ecd2', ink: '#6b5a0e', glass: '#e2cf6e' }],
  ['aromatica', { background: '#e1e8d9', ink: '#3e5a2e', glass: '#9fbb88' }],
])

const fallback = { background: '#efe5d8', ink: '#453a34', glass: '#a99a8f' }

export function bottlePalette(family: string | null | undefined) {
  const key = family?.trim().normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase() ?? ''
  return palettes.get(key) ?? fallback
}
