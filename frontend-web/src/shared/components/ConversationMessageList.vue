<script setup lang="ts">
// Fil de messages d'une conversation client ↔ professionnel, en lecture
// seule, pour l'admin (fiche d'une réclamation, supervision des
// conversations). Aucune action possible : l'admin ne participe jamais à ces
// conversations (CLAUDE.md §5, ajout v0.8).
//
// Seuls les champs affichés sont exigés : la ressource complète du chat
// (`ChatMessage`) comme le résumé embarqué d'une réclamation conviennent.
interface ReadOnlyMessage {
  id: number
  is_system: boolean
  sender: { name: string } | null
  body: string | null
  has_image: boolean
  created_at: string
}

defineProps<{
  messages: ReadOnlyMessage[]
}>()

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })
}
</script>

<template>
  <ul class="space-y-3">
    <li v-if="messages.length === 0" class="text-sm text-slate-500">Aucun message échangé.</li>
    <li v-for="message in messages" :key="message.id" class="rounded-md bg-slate-50 px-3 py-2">
      <div class="flex flex-wrap items-center justify-between gap-x-3 text-xs text-slate-400">
        <span>{{ message.is_system ? 'Message système' : (message.sender?.name ?? 'Automobiliste') }}</span>
        <span>{{ formatDate(message.created_at) }}</span>
      </div>
      <p v-if="message.body" class="mt-1 whitespace-pre-line break-words text-sm text-slate-700">{{ message.body }}</p>
      <!-- Aucun endpoint admin ne sert les photos du chat : on signale
           seulement leur présence. -->
      <p v-if="message.has_image" class="mt-1 text-xs italic text-slate-500">
        Photo jointe (non consultable depuis l'administration)
      </p>
    </li>
  </ul>
</template>
