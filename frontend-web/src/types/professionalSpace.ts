// Espace professionnel concerné : détermine le préfixe d'URL de l'API
// (/garage/... ou /market-space/...). Les deux espaces exposent exactement
// les mêmes routes de profil (CLAUDE.md §5, ajout v0.20).
export type ProfessionalSpace = 'garage' | 'market-space'
