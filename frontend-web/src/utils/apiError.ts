import axios from 'axios'

// Le catch générique `catch { errorMessage.value = '...' }` masque la vraie
// cause (backend éteint, CORS, 403, 500...) : impossible de diagnostiquer
// depuis l'UI, et rien n'apparaît non plus dans la console du navigateur.
// Ce helper journalise l'erreur réelle (visible en devtools) et renvoie un
// message adapté au cas — réseau (pas de réponse du tout, ex. backend non
// démarré) vs. erreur applicative (le backend a répondu avec un message).
export function extractApiErrorMessage(error: unknown, fallback: string): string {
  console.error(error)

  if (axios.isAxiosError(error)) {
    if (error.code === 'ECONNABORTED') {
      return 'Le serveur met trop de temps à répondre (délai dépassé). Réessayez.'
    }
    if (!error.response) {
      return 'Impossible de joindre le serveur. Vérifiez que le backend est démarré et accessible.'
    }

    const data = error.response.data as { message?: string } | undefined
    if (data?.message) {
      return data.message
    }
  }

  return fallback
}

// Erreurs de validation Laravel (422) : `{ errors: { champ: ['message', …] } }`.
// Renvoie le premier message de chaque champ, pour l'afficher sous le champ
// concerné ; objet vide si l'erreur n'est pas un 422 de validation.
export function extractValidationErrors(error: unknown): Record<string, string> {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return {}
  }

  const errors = (error.response.data as { errors?: Record<string, string[]> } | undefined)?.errors
  if (!errors) {
    return {}
  }

  // `Object.entries` transforme l'objet en liste de paires [clé, valeur] ;
  // `Object.fromEntries` fait l'inverse, après transformation de chaque paire.
  return Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [field, messages[0] ?? '']),
  )
}
