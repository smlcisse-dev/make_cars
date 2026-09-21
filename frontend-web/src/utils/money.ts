// Montants en francs CFA (XOF, sans décimales). Le backend renvoie les
// montants sous forme de chaînes décimales ("15000.00") : on les convertit
// uniquement pour l'affichage, jamais pour un calcul de référence.
const formatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 })

export function formatAmount(value: string | number): string {
  return `${formatter.format(Number(value))} FCFA`
}
