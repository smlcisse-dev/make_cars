import { shallowRef } from 'vue'

// Fenêtre de confirmation de la plateforme, qui remplace `window.confirm`
// (fenêtre native du navigateur, titrée « localhost:5173 » et impossible à
// mettre en forme). Usage, depuis n'importe quel écran :
//
//   if (!(await confirmAction({ title: 'Supprimer ?', message: '…' }))) {
//     return
//   }
//
// Une seule fenêtre existe pour toute l'application (<ConfirmDialog />,
// monté une fois dans App.vue) ; ce module porte sa demande en cours.

export interface ConfirmOptions {
  title: string
  message: string
  confirmLabel?: string
  cancelLabel?: string
  // `danger` (rouge) pour une action destructrice ou irréversible.
  variant?: 'primary' | 'danger'
}

interface PendingConfirm extends ConfirmOptions {
  resolve: (confirmed: boolean) => void
}

// `shallowRef` : ref réactive dont Vue ne suit que le remplacement de la
// valeur entière (pas l'intérieur de l'objet) — suffisant ici, puisqu'on
// remplace toujours la demande d'un bloc, et ça évite de rendre réactive la
// fonction `resolve` qu'elle contient.
export const pendingConfirm = shallowRef<PendingConfirm | null>(null)

// Renvoie une `Promise<boolean>` : l'écran appelant « attend » (`await`) la
// réponse de l'utilisateur, exactement comme il attendait `window.confirm`,
// mais sans bloquer le navigateur. La promesse est résolue par
// `answerConfirm`, appelée par les boutons de <ConfirmDialog />.
export function confirmAction(options: ConfirmOptions): Promise<boolean> {
  // Une demande encore ouverte (cas improbable) est considérée comme annulée.
  pendingConfirm.value?.resolve(false)

  return new Promise<boolean>((resolve) => {
    pendingConfirm.value = { ...options, resolve }
  })
}

export function answerConfirm(confirmed: boolean): void {
  const current = pendingConfirm.value
  pendingConfirm.value = null
  current?.resolve(confirmed)
}
