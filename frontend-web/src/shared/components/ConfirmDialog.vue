<script setup lang="ts">
import AppButton from '@/shared/components/AppButton.vue'
import BaseModal from '@/shared/components/BaseModal.vue'
import { answerConfirm, pendingConfirm } from '@/utils/confirmDialog'

// Fenêtre de confirmation unique de l'application, montée une fois dans
// App.vue et pilotée par `confirmAction()` (utils/confirmDialog.ts). Fermer
// la fenêtre (croix, Échap, clic à côté) vaut « Annuler ».
</script>

<template>
  <BaseModal v-if="pendingConfirm" :title="pendingConfirm.title" @close="answerConfirm(false)">
    <p class="text-sm text-slate-600">{{ pendingConfirm.message }}</p>

    <template #footer>
      <AppButton variant="secondary" @click="answerConfirm(false)">
        {{ pendingConfirm.cancelLabel ?? 'Annuler' }}
      </AppButton>
      <AppButton :variant="pendingConfirm.variant ?? 'primary'" @click="answerConfirm(true)">
        {{ pendingConfirm.confirmLabel ?? 'Confirmer' }}
      </AppButton>
    </template>
  </BaseModal>
</template>
