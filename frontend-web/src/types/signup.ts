// Parcours d'inscription professionnelle en deux temps (CLAUDE.md §5, ajout
// v0.26) : miroir de RegisterProfessionalRequest et de
// PendingProfessionalRegistrationResource côté backend.

// Seuls ces deux types de comptes s'inscrivent depuis le web : l'automobiliste
// passe par l'application mobile, l'admin n'a pas d'inscription publique.
// `Extract` garde, parmi les valeurs de `AccountType`, celles qui figurent
// dans la liste donnée : si l'une d'elles disparaissait du type d'origine, le
// compilateur le signalerait ici.
import type { AccountType } from '@/types/user'

export type SignupAccountType = Extract<AccountType, 'garagiste' | 'market_space'>

export interface SignupForm {
  first_name: string
  last_name: string
  email: string
  phone: string
  password: string
  password_confirmation: string
}

// Demande en attente de vérification : aucun compte n'existe encore.
// `verification_id` est l'uuid public de la demande, jamais un id interne.
export interface PendingSignup {
  verification_id: string
  email: string
  code_expires_at: string
  resend_available_at: string
}
