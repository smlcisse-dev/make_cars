import { computed, onMounted, onUnmounted, ref } from 'vue'

// Code à usage unique reçu par email, commun à la vérification d'inscription
// et au mot de passe oublié (mêmes règles côté backend :
// config/email_codes.php).
export const CODE_LENGTH = 6

export function isWellFormedCode(code: string): boolean {
  return new RegExp(`^\\d{${CODE_LENGTH}}$`).test(code)
}

export const CODE_FORMAT_ERROR = `Le code doit comporter ${CODE_LENGTH} chiffres.`

// Décompte avant le prochain envoi de code possible (60 s côté backend),
// commun à la vérification d'inscription et au mot de passe oublié.
//
// Une fonction `useXxx()` qui crée des refs et des hooks de cycle de vie est
// un « composable » : appelée dans le `<script setup>` d'un écran, elle
// branche sa logique sur cet écran (le minuteur démarre quand l'écran
// apparaît et s'arrête quand il disparaît).
export function useResendCountdown(initialAvailableAt: number | null = null) {
  // `now` est relu chaque seconde : `secondsLeft`, qui en dépend, est alors
  // recalculé et l'affichage suit.
  const now = ref(Date.now())
  const availableAt = ref<number | null>(initialAvailableAt)

  const secondsLeft = computed(() =>
    availableAt.value === null
      ? 0
      : Math.max(0, Math.ceil((availableAt.value - now.value) / 1000)),
  )

  // `ReturnType<typeof setInterval>` : le type exact renvoyé par setInterval,
  // sans avoir à savoir s'il s'agit d'un nombre (navigateur) ou d'un objet.
  let timer: ReturnType<typeof setInterval> | undefined
  onMounted(() => {
    timer = setInterval(() => {
      now.value = Date.now()
    }, 1000)
  })
  // Arrêter le minuteur en quittant la page, sinon il tournerait indéfiniment.
  onUnmounted(() => clearInterval(timer))

  function waitUntil(timestamp: number): void {
    now.value = Date.now()
    availableAt.value = timestamp
  }

  function waitSeconds(seconds: number): void {
    waitUntil(Date.now() + seconds * 1000)
  }

  return { secondsLeft, waitUntil, waitSeconds }
}
