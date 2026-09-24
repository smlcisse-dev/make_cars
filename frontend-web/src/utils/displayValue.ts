// Valeur affichée d'un champ de fiche admin : « Non fourni » quand il est
// vide (comptes validés avant v0.26/v0.27, ou profil pas encore rempli).
export function displayValue(value: string | number | null | undefined): string {
  return value === null || value === undefined || value === '' ? 'Non fourni' : String(value)
}
