// Statut de validation admin partagé par les inscriptions, services et
// produits — mêmes valeurs côté backend (App\Enums\RegistrationStatus /
// RepairServiceStatus / ProductStatus), toutes "pending"/"approved"/
// "rejected" (CLAUDE.md §5, règle 5).
export type ReviewStatus = 'pending' | 'approved' | 'rejected'
