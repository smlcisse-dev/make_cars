import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'

import type { PendingSignup, SignupAccountType, SignupForm } from '@/types/signup'

function emptyForm(): SignupForm {
  return {
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
  }
}

// Inscription professionnelle en cours (CLAUDE.md §5, ajout v0.26).
//
// EN MÉMOIRE UNIQUEMENT, volontairement : contrairement au store `auth`, rien
// n'est écrit dans le stockage du navigateur. Le formulaire contient le mot
// de passe en clair ; l'écrire dans le navigateur le laisserait lisible par
// n'importe quel script de la page et sur le disque, même après la fin de
// l'inscription. Conséquence assumée : un rechargement de page vide ce store.
// La page du code reste utilisable grâce à l'identifiant de la demande, porté
// par l'URL (/inscription/verification/:id) ; seul le mot de passe est à
// retaper si l'on revient au formulaire.
export const useSignupStore = defineStore('signup', () => {
  const accountType = ref<SignupAccountType | null>(null)
  // `reactive()` rend réactif un objet entier (chacune de ses propriétés),
  // sans `.value` : pratique pour un formulaire lié champ par champ avec
  // `v-model="signup.form.email"`.
  const form = reactive<SignupForm>(emptyForm())
  const pending = ref<PendingSignup | null>(null)

  function setPending(newPending: PendingSignup): void {
    pending.value = newPending
  }

  function reset(): void {
    accountType.value = null
    // `Object.assign` remplace les valeurs une à une : l'objet `form` reste le
    // même, donc les composants qui le lisent restent reliés à lui.
    Object.assign(form, emptyForm())
    pending.value = null
  }

  return { accountType, form, pending, setPending, reset }
})
