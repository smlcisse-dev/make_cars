// Longueur minimale d'un mot de passe : même règle que le backend
// (`Password::defaults()`, 8 caractères), vérifiée avant envoi pour un
// message immédiat ; le backend reste l'autorité.
export const PASSWORD_MIN_LENGTH = 8
