// Miroir de App\Enums\AccountType côté backend (CLAUDE.md §3). On utilise un
// type union de chaînes plutôt qu'un `enum` TypeScript : le tsconfig du
// projet active `erasableSyntaxOnly`, qui interdit les constructions TS qui
// ne peuvent pas être simplement "effacées" à la compilation (dont `enum`,
// qui génère du code JS réel). Un type union n'existe qu'à la vérification
// des types, il disparaît entièrement une fois compilé.
export type AccountType = 'automobiliste' | 'garagiste' | 'market_space' | 'admin'

// Complétude du profil public d'un professionnel (CLAUDE.md §5, ajout
// v0.20) : `missing_fields` liste les éléments manquants (noms de colonnes du
// profil, plus `opening_hours` et `images`). Vaut `null` pour les rôles sans
// profil (admin, automobiliste) ou tant que le dossier n'est pas approuvé, et
// peut être absent d'une session enregistrée avant cet ajout.
export interface ProfileStatus {
  is_complete: boolean
  missing_fields: string[]
}

// Reflète UserResource côté backend (app/Http/Resources/UserResource.php).
export interface User {
  id: number
  name: string
  email: string | null
  phone: string | null
  role: AccountType
  role_label: string
  is_express: boolean
  profile_status?: ProfileStatus | null
}
