// Statut de validation admin partagé par les services et produits — mêmes
// valeurs côté backend (RepairServiceStatus / ProductStatus), toutes
// "pending"/"approved"/"rejected" (CLAUDE.md §5, règle 5). Le dossier
// d'inscription a son propre type depuis v0.26 (voir src/types/registration.ts).
export type ReviewStatus = 'pending' | 'approved' | 'rejected'
