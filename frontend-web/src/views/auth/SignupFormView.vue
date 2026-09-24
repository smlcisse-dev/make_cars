<script setup lang="ts">
import axios from 'axios'
import { nextTick, onMounted, ref, useTemplateRef } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { startSignup } from '@/api/signup'
import AppButton from '@/shared/components/AppButton.vue'
import { useSignupStore } from '@/stores/signup'
import type { SignupAccountType, SignupForm } from '@/types/signup'
import { extractApiErrorMessage, extractValidationErrors } from '@/utils/apiError'
import { BENIN_PHONE_ERROR, normalizeBeninPhone } from '@/utils/beninPhone'
import { PASSWORD_MIN_LENGTH } from '@/utils/password'

// Formulaire court d'inscription professionnelle (CLAUDE.md §5, ajout v0.26).
// Un seul écran pour les deux types de comptes, comme les écrans partagés
// (CLAUDE.md §4) : le routeur fixe le type via `props: { accountType: ... }`.
const props = defineProps<{ accountType: SignupAccountType }>()

const TITLES: Record<SignupAccountType, string> = {
  garagiste: 'Créer mon compte garagiste',
  market_space: 'Créer mon compte boutique',
}

const signup = useSignupStore()
const router = useRouter()
const route = useRoute()

// `keyof SignupForm` : uniquement les noms de champs du formulaire, ce qui
// empêche de rattacher une erreur à un champ qui n'existe pas.
const errors = ref<Partial<Record<keyof SignupForm, string>>>({})
const generalError = ref<string | null>(null)
const isSubmitting = ref(false)

// `useTemplateRef('emailInput')` donne accès à l'élément HTML portant
// `ref="emailInput"` dans le template (null tant qu'il n'est pas affiché).
const emailInput = useTemplateRef<HTMLInputElement>('emailInput')

onMounted(async () => {
  // Changer de type en cours de route (garagiste -> boutique) garde les
  // champs saisis : seul le type change.
  signup.accountType = props.accountType

  // Retour depuis « Modifier l'email » : curseur placé dans le champ email.
  if (route.query.focus === 'email') {
    // `nextTick` attend que Vue ait fini d'afficher le template.
    await nextTick()
    emailInput.value?.focus()
    emailInput.value?.select()
  }
})

function validate(): boolean {
  const form = signup.form
  const found: Partial<Record<keyof SignupForm, string>> = {}

  if (!form.first_name.trim()) found.first_name = 'Le prénom est obligatoire.'
  if (!form.last_name.trim()) found.last_name = 'Le nom est obligatoire.'
  if (!form.email.trim()) {
    found.email = "L'email est obligatoire."
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
    found.email = 'Adresse email invalide.'
  }
  if (!form.phone.trim()) {
    found.phone = 'Le téléphone est obligatoire.'
  } else if (normalizeBeninPhone(form.phone) === null) {
    found.phone = BENIN_PHONE_ERROR
  }
  if (form.password.length < PASSWORD_MIN_LENGTH) {
    found.password = `Le mot de passe doit contenir au moins ${PASSWORD_MIN_LENGTH} caractères.`
  }
  if (form.password_confirmation !== form.password) {
    found.password_confirmation = 'Les deux mots de passe ne correspondent pas.'
  }

  errors.value = found
  return Object.keys(found).length === 0
}

async function handleSubmit(): Promise<void> {
  generalError.value = null
  if (!validate()) return

  isSubmitting.value = true
  try {
    const pending = await startSignup({
      ...signup.form,
      email: signup.form.email.trim(),
      // `!` : la validation ci-dessus garantit que le numéro est valide.
      phone: normalizeBeninPhone(signup.form.phone)!,
      account_type: props.accountType,
    })
    signup.setPending(pending)
    await router.push({ name: 'signup.verify', params: { id: pending.verification_id } })
  } catch (error) {
    const fieldErrors = extractValidationErrors(error)
    if (Object.keys(fieldErrors).length > 0) {
      errors.value = fieldErrors
    } else if (axios.isAxiosError(error) && error.response?.status === 429) {
      generalError.value = 'Trop de tentatives. Réessayez dans une minute.'
    } else {
      generalError.value = extractApiErrorMessage(error, 'Une erreur est survenue. Réessayez.')
    }
  } finally {
    isSubmitting.value = false
  }
}

// `text-base` sur téléphone : en dessous de 16 px, Safari iOS zoome sur le
// champ à chaque saisie.
const inputClass =
  'mt-1 w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-slate-500 focus:outline-none sm:py-2 sm:text-sm'
</script>

<template>
  <div class="flex min-h-screen justify-center bg-slate-50 px-4 py-10 sm:items-center">
    <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
      <RouterLink :to="{ name: 'signup' }" class="text-sm text-slate-500 hover:text-slate-700">
        ← Changer d'activité
      </RouterLink>
      <h1 class="mt-3 text-xl font-semibold text-slate-900">{{ TITLES[accountType] }}</h1>
      <p class="mt-1 text-sm text-slate-500">
        Un code de vérification sera envoyé à votre adresse email.
      </p>

      <!-- `novalidate` : on affiche nos propres messages sous chaque champ
           plutôt que les bulles du navigateur. -->
      <form class="mt-6 space-y-4" novalidate @submit.prevent="handleSubmit">
        <div>
          <label for="first_name" class="block text-sm font-medium text-slate-700">Prénom</label>
          <input
            id="first_name"
            v-model="signup.form.first_name"
            type="text"
            autocomplete="given-name"
            :class="inputClass"
          />
          <p v-if="errors.first_name" class="mt-1 text-sm text-red-600">{{ errors.first_name }}</p>
        </div>

        <div>
          <label for="last_name" class="block text-sm font-medium text-slate-700">Nom</label>
          <input
            id="last_name"
            v-model="signup.form.last_name"
            type="text"
            autocomplete="family-name"
            :class="inputClass"
          />
          <p v-if="errors.last_name" class="mt-1 text-sm text-red-600">{{ errors.last_name }}</p>
        </div>

        <div>
          <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
          <input
            id="email"
            ref="emailInput"
            v-model="signup.form.email"
            type="email"
            inputmode="email"
            autocomplete="email"
            :class="inputClass"
          />
          <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email }}</p>
        </div>

        <div>
          <label for="phone" class="block text-sm font-medium text-slate-700">Téléphone</label>
          <input
            id="phone"
            v-model="signup.form.phone"
            type="tel"
            autocomplete="tel"
            placeholder="+229 01 23 45 67 89"
            :class="inputClass"
          />
          <p v-if="errors.phone" class="mt-1 text-sm text-red-600">{{ errors.phone }}</p>
        </div>

        <div>
          <label for="password" class="block text-sm font-medium text-slate-700">
            Mot de passe
          </label>
          <input
            id="password"
            v-model="signup.form.password"
            type="password"
            autocomplete="new-password"
            :class="inputClass"
          />
          <p v-if="errors.password" class="mt-1 text-sm text-red-600">{{ errors.password }}</p>
          <p v-else class="mt-1 text-xs text-slate-500">
            {{ PASSWORD_MIN_LENGTH }} caractères minimum.
          </p>
        </div>

        <div>
          <label for="password_confirmation" class="block text-sm font-medium text-slate-700">
            Confirmation du mot de passe
          </label>
          <input
            id="password_confirmation"
            v-model="signup.form.password_confirmation"
            type="password"
            autocomplete="new-password"
            :class="inputClass"
          />
          <p v-if="errors.password_confirmation" class="mt-1 text-sm text-red-600">
            {{ errors.password_confirmation }}
          </p>
        </div>

        <p v-if="generalError" class="text-sm text-red-600">{{ generalError }}</p>

        <AppButton type="submit" :loading="isSubmitting" class="w-full">
          Créer mon compte
        </AppButton>
      </form>

      <p class="mt-6 text-sm text-slate-500">
        Déjà inscrit ?
        <RouterLink :to="{ name: 'login' }" class="font-medium text-slate-900 underline">
          Se connecter
        </RouterLink>
      </p>
    </div>
  </div>
</template>
