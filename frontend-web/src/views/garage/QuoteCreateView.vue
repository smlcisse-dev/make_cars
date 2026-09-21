<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import { fetchAppointments } from '@/api/appointments'
import { createExpressClient } from '@/api/garageClients'
import { createQuote, createQuoteForAppointment } from '@/api/quotes'
import AppButton from '@/shared/components/AppButton.vue'
import type { Appointment } from '@/types/appointment'
import type { QuoteLineInput } from '@/types/quote'
import { extractApiErrorMessage } from '@/utils/apiError'
import { BENIN_PHONE_ERROR, normalizeBeninPhone } from '@/utils/beninPhone'
import QuoteLineEditor from '@/views/garage/QuoteLineEditor.vue'

// Deux entrées, à distinguer clairement (CLAUDE.md §5, ajout v0.9) : un devis
// naît d'un RDV confirmé, ou d'un client présent sans RDV (compte « express »).
type Mode = 'appointment' | 'walk_in'

const router = useRouter()

const mode = ref<Mode>('appointment')
const lines = ref<QuoteLineInput[]>([])
const linesValid = ref(false)

const appointments = ref<Appointment[]>([])
const appointmentId = ref<number | null>(null)
const isLoadingAppointments = ref(false)

const clientName = ref('')
const clientEmail = ref('')
const clientPhone = ref('')
const phoneTouched = ref(false)

const isSubmitting = ref(false)
const errorMessage = ref<string | null>(null)

const normalizedPhone = computed(() => normalizeBeninPhone(clientPhone.value))
const phoneError = computed(() =>
  phoneTouched.value && clientPhone.value !== '' && normalizedPhone.value === null ? BENIN_PHONE_ERROR : null,
)

const canSubmit = computed(() => {
  if (!linesValid.value || isSubmitting.value) {
    return false
  }
  if (mode.value === 'appointment') {
    return appointmentId.value !== null
  }
  return clientName.value.trim() !== '' && clientEmail.value.trim() !== '' && normalizedPhone.value !== null
})

onMounted(async () => {
  isLoadingAppointments.value = true
  try {
    // Première page seulement (15 RDV confirmés les plus récents).
    appointments.value = (await fetchAppointments({ status: 'confirmed' })).data
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, 'Impossible de charger vos rendez-vous confirmés.')
  } finally {
    isLoadingAppointments.value = false
  }
})

function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })
}

async function handleSubmit(): Promise<void> {
  if (!canSubmit.value) {
    return
  }
  isSubmitting.value = true
  errorMessage.value = null

  try {
    let quote
    let flash = 'Devis créé en brouillon.'

    if (mode.value === 'appointment') {
      // Si le RDV a déjà un devis, le backend répond 403 : son message est
      // affiché tel quel (rien ne l'indique côté frontend en amont).
      quote = await createQuoteForAppointment(appointmentId.value!, lines.value)
    } else {
      // Crée le compte express ou rattache un compte existant (idempotent :
      // si la création du devis échoue ensuite, on peut réessayer sans doublon).
      const { client, message } = await createExpressClient({
        name: clientName.value.trim(),
        email: clientEmail.value.trim(),
        phone: normalizedPhone.value!,
      })
      quote = await createQuote(client.id, lines.value)
      flash = `${message} ${flash}`
    }

    router.push({ name: 'garage.quotes.show', params: { id: quote.id }, query: { flash } })
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, 'La création du devis a échoué. Réessayez.')
  } finally {
    isSubmitting.value = false
  }
}

const inputClasses =
  'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none'
</script>

<template>
  <form class="max-w-3xl space-y-6" @submit.prevent="handleSubmit">
    <div>
      <h2 class="text-lg font-semibold text-slate-900">Nouveau devis</h2>
      <p class="mt-1 text-sm text-slate-500">
        Le client devra accepter le devis avant tout démarrage de la prestation.
      </p>
    </div>

    <div class="flex gap-2">
      <AppButton :variant="mode === 'appointment' ? 'primary' : 'secondary'" @click="mode = 'appointment'">
        Depuis un RDV confirmé
      </AppButton>
      <AppButton :variant="mode === 'walk_in' ? 'primary' : 'secondary'" @click="mode = 'walk_in'">
        Sans RDV (client présent)
      </AppButton>
    </div>

    <section v-if="mode === 'appointment'" class="space-y-2">
      <label for="quote-appointment" class="block text-sm font-medium text-slate-700">Rendez-vous</label>
      <p v-if="isLoadingAppointments" class="text-sm text-slate-500">Chargement...</p>
      <p v-else-if="appointments.length === 0" class="text-sm text-slate-500">Aucun rendez-vous confirmé.</p>
      <select v-else id="quote-appointment" v-model.number="appointmentId" :class="inputClasses">
        <option :value="null" disabled>Choisir un rendez-vous...</option>
        <option v-for="appointment in appointments" :key="appointment.id" :value="appointment.id">
          {{ appointment.user.name }} — {{ formatDateTime(appointment.confirmed_at ?? appointment.requested_at) }}
          <template v-if="appointment.repair_service"> — {{ appointment.repair_service.name }}</template>
        </option>
      </select>
    </section>

    <section v-else class="space-y-3">
      <p class="text-sm text-slate-500">
        Si l'email ou le téléphone correspond à un compte automobiliste existant, il est rattaché automatiquement.
      </p>
      <div>
        <label for="client-name" class="block text-sm font-medium text-slate-700">Nom du client</label>
        <input id="client-name" v-model="clientName" type="text" maxlength="255" required :class="inputClasses" />
      </div>
      <div>
        <label for="client-email" class="block text-sm font-medium text-slate-700">Email</label>
        <input id="client-email" v-model="clientEmail" type="email" maxlength="255" required :class="inputClasses" />
      </div>
      <div>
        <label for="client-phone" class="block text-sm font-medium text-slate-700">Téléphone</label>
        <input
          id="client-phone"
          v-model="clientPhone"
          type="tel"
          placeholder="+229 01 23 45 67 89"
          required
          :class="inputClasses"
          @blur="phoneTouched = true"
        />
        <p v-if="phoneError" class="mt-1 text-xs text-rose-600">{{ phoneError }}</p>
      </div>
    </section>

    <section class="space-y-2">
      <h3 class="text-sm font-medium text-slate-700">Lignes du devis</h3>
      <QuoteLineEditor v-model="lines" @update:valid="linesValid = $event" />
    </section>

    <p v-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>

    <div class="flex gap-3">
      <AppButton type="submit" :loading="isSubmitting" :disabled="!canSubmit">Créer le devis</AppButton>
      <AppButton variant="ghost" @click="router.push({ name: 'garage.quotes' })">Annuler</AppButton>
    </div>
  </form>
</template>
