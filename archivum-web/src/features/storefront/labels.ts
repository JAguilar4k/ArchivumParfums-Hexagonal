/** Spanish words of the tags that the API sends without accents (`dia`, `calido`...). */
const LABELS: Record<string, string> = {
  dia: 'Día',
  tarde: 'Tarde',
  noche: 'Noche',
  calido: 'Cálido',
  templado: 'Templado',
  frio: 'Frío',
}

export function tagLabel(value: unknown): string {
  const text = String(value)

  return LABELS[text] ?? text
}
