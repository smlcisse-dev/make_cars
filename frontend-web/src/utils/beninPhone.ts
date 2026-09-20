// Miroir de App\Rules\BeninPhoneNumber côté backend (CLAUDE.md §5, ajout
// v0.21) : numérotation béninoise à 10 chiffres, soit l'indicatif +229 suivi
// de 10 chiffres commençant par 01. Espaces, tirets, points et parenthèses
// sont tolérés ; `00229` est accepté à la place de `+229`. Le backend reste
// l'autorité — cette validation évite juste un aller-retour inutile.
export const BENIN_PHONE_ERROR =
  'Numéro invalide : indiquez +229 suivi de 10 chiffres commençant par 01 (ex. +229 01 23 45 67 89).'

// Forme canonique (`+2290123456789`, celle qui sera stockée), ou null si la
// saisie n'a pas la structure attendue.
export function normalizeBeninPhone(value: string): string | null {
  const compact = value.replace(/[\s\-.()]/g, '')
  const withPlus = compact.startsWith('00229') ? `+229${compact.slice(5)}` : compact
  const match = /^\+229(01\d{8})$/.exec(withPlus)
  return match ? `+229${match[1]}` : null
}
