/** Spanish words of the tags that the API sends without accents (`dia`, `calido`...). */
const LABELS: Record<string, string> = {
  dia: 'Día',
  tarde: 'Tarde',
  noche: 'Noche',
  calido: 'Cálido',
  templado: 'Templado',
  frio: 'Frío',
}

/** Names of the concentrations that a store may write as an abbreviation (WCAG 3.1.4). */
const CONCENTRATIONS: Record<string, string> = {
  EDC: 'Eau de Cologne',
  EDT: 'Eau de Toilette',
  EDP: 'Eau de Parfum',
}

export function tagLabel(value: unknown): string {
  const text = String(value)

  return LABELS[text] ?? text
}

/**
 * The concentration in words: "EDP" is "Eau de Parfum (EDP)", so the abbreviation is never alone. A concentration
 * that the store already wrote in words stays as it is.
 */
export function concentrationLabel(value: string | null | undefined): string {
  const text = value?.trim() ?? ''
  const name = CONCENTRATIONS[text.toUpperCase()]

  return name ? `${name} (${text})` : text
}
