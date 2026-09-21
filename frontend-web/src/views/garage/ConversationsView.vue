<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue'

import { type ChatSpace, fetchConversations, fetchMessageImageBlob, fetchMessages, sendMessage } from '@/api/conversations'
import AppButton from '@/shared/components/AppButton.vue'
import AppPagination from '@/shared/components/AppPagination.vue'
import type { ChatMessage, Conversation } from '@/types/conversation'
import { extractApiErrorMessage } from '@/utils/apiError'

// Même écran pour les deux espaces : `space` choisit le préfixe des endpoints
// (Composition API : `defineProps` déclare les entrées typées du composant).
const props = withDefaults(defineProps<{ space?: ChatSpace }>(), { space: 'garage' })

// `ref` rend une valeur réactive : quand elle change, le template qui la lit
// est redessiné automatiquement (Composition API, CLAUDE.md §4).
const conversations = ref<Conversation[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const isLoadingList = ref(false)
const listError = ref<string | null>(null)

const selected = ref<Conversation | null>(null)
const messages = ref<ChatMessage[]>([])
const isLoadingThread = ref(false)
const threadError = ref<string | null>(null)

const draftBody = ref('')
const draftImage = ref<File | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const isSending = ref(false)
const sendError = ref<string | null>(null)
const imageBusyId = ref<number | null>(null)
const threadEnd = ref<HTMLElement | null>(null)

async function loadConversations(): Promise<void> {
  isLoadingList.value = true
  listError.value = null

  try {
    const response = await fetchConversations(currentPage.value, props.space)
    conversations.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (error) {
    listError.value = extractApiErrorMessage(error, 'Impossible de charger les conversations.')
  } finally {
    isLoadingList.value = false
  }
}

function goToPage(page: number): void {
  currentPage.value = page
  loadConversations()
}

async function scrollToEnd(): Promise<void> {
  await nextTick()
  threadEnd.value?.scrollIntoView({ block: 'end' })
}

async function selectConversation(conversation: Conversation): Promise<void> {
  selected.value = conversation
  messages.value = []
  threadError.value = null
  sendError.value = null
  draftBody.value = ''
  clearImage()
  isLoadingThread.value = true

  try {
    const response = await fetchMessages(conversation.id, 1, props.space)
    // L'API renvoie les plus récents d'abord : on inverse pour l'affichage.
    // Garde-fou : ignore la réponse si une autre conversation a été choisie entre-temps.
    if (selected.value?.id === conversation.id) {
      messages.value = [...response.data].reverse()
      scrollToEnd()
    }
  } catch (error) {
    if (selected.value?.id === conversation.id) {
      threadError.value = extractApiErrorMessage(error, 'Impossible de charger les messages.')
    }
  } finally {
    if (selected.value?.id === conversation.id) {
      isLoadingThread.value = false
    }
  }
}

function onFileChange(event: Event): void {
  const input = event.target as HTMLInputElement
  draftImage.value = input.files?.[0] ?? null
}

function clearImage(): void {
  draftImage.value = null
  if (fileInput.value) fileInput.value.value = ''
}

function canSend(): boolean {
  return draftBody.value.trim() !== '' || draftImage.value !== null
}

async function submitMessage(): Promise<void> {
  if (!selected.value || !canSend() || isSending.value) {
    return
  }

  const conversation = selected.value
  isSending.value = true
  sendError.value = null

  try {
    const message = await sendMessage(conversation.id, draftBody.value.trim() || null, draftImage.value, props.space)
    if (selected.value?.id === conversation.id) {
      messages.value.push(message)
      scrollToEnd()
    }
    conversation.last_message_at = message.created_at
    draftBody.value = ''
    clearImage()
  } catch (error) {
    sendError.value = extractApiErrorMessage(error, "Impossible d'envoyer le message.")
  } finally {
    isSending.value = false
  }
}

async function openImage(message: ChatMessage): Promise<void> {
  imageBusyId.value = message.id
  sendError.value = null

  try {
    const blob = await fetchMessageImageBlob(message.conversation_id, message.id, props.space)
    const objectUrl = URL.createObjectURL(blob)
    window.open(objectUrl, '_blank')
    setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000)
  } catch (error) {
    sendError.value = extractApiErrorMessage(error, 'Impossible de récupérer cette photo.')
  } finally {
    imageBusyId.value = null
  }
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })
}

onMounted(loadConversations)
</script>

<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-semibold text-slate-900">Messages</h2>
      <p class="mt-1 text-sm text-slate-500">
        Conversations ouvertes par vos clients. Vous pouvez y répondre, mais pas en démarrer une.
      </p>
    </div>

    <div class="grid gap-4 md:grid-cols-[18rem_1fr]">
      <!-- Colonne gauche : liste des conversations -->
      <aside class="rounded-md border border-slate-200 bg-white">
        <p v-if="listError" class="p-4 text-sm text-rose-600">{{ listError }}</p>
        <p v-else-if="isLoadingList" class="p-4 text-sm text-slate-500">Chargement...</p>
        <p v-else-if="conversations.length === 0" class="p-4 text-sm text-slate-500">Aucune conversation.</p>
        <ul v-else class="divide-y divide-slate-100">
          <li v-for="conversation in conversations" :key="conversation.id">
            <button
              type="button"
              class="w-full px-4 py-3 text-left hover:bg-slate-50"
              :class="{ 'bg-slate-100': selected?.id === conversation.id }"
              @click="selectConversation(conversation)"
            >
              <span class="block text-sm font-medium text-slate-900">{{ conversation.user.name }}</span>
              <span class="block text-xs text-slate-500">
                {{ conversation.last_message_at ? formatDate(conversation.last_message_at) : 'Aucun message' }}
              </span>
            </button>
          </li>
        </ul>
        <AppPagination
          v-if="lastPage > 1"
          :current-page="currentPage"
          :last-page="lastPage"
          @update:current-page="goToPage"
        />
      </aside>

      <!-- Colonne droite : fil de messages -->
      <section class="flex min-h-[24rem] flex-col rounded-md border border-slate-200 bg-white">
        <p v-if="!selected" class="m-auto p-4 text-sm text-slate-500">Choisissez une conversation.</p>

        <template v-else>
          <header class="border-b border-slate-200 px-4 py-3 text-sm font-medium text-slate-900">
            {{ selected.user.name }}
          </header>

          <div class="flex-1 space-y-3 overflow-y-auto p-4" style="max-height: 28rem">
            <p v-if="threadError" class="text-sm text-rose-600">{{ threadError }}</p>
            <p v-else-if="isLoadingThread" class="text-sm text-slate-500">Chargement...</p>
            <p v-else-if="messages.length === 0" class="text-sm text-slate-500">Aucun message.</p>

            <div
              v-for="message in messages"
              :key="message.id"
              class="max-w-[80%] rounded-md px-3 py-2 text-sm"
              :class="
                message.is_system
                  ? 'mx-auto bg-slate-100 text-slate-600'
                  : message.sender?.id === selected.user_id
                    ? 'bg-slate-100 text-slate-900'
                    : 'ml-auto bg-slate-800 text-white'
              "
            >
              <p v-if="message.is_system" class="text-xs font-semibold uppercase">Message système</p>
              <p v-else-if="message.sender?.id === selected.user_id" class="text-xs font-semibold">
                {{ message.sender?.name }}
              </p>
              <p v-if="message.body" class="whitespace-pre-line">{{ message.body }}</p>
              <RouterLink
                v-if="message.is_system && message.quote_id"
                :to="{ name: 'garage.quotes.show', params: { id: message.quote_id } }"
                class="mt-1 inline-block text-xs underline"
              >
                Voir le devis
              </RouterLink>
              <button
                v-if="message.has_image"
                type="button"
                class="mt-1 block text-xs underline"
                :disabled="imageBusyId === message.id"
                @click="openImage(message)"
              >
                {{ imageBusyId === message.id ? 'Ouverture...' : 'Voir la photo' }}
              </button>
              <p class="mt-1 text-[10px] opacity-70">{{ formatDate(message.created_at) }}</p>
            </div>
            <div ref="threadEnd" />
          </div>

          <form class="space-y-2 border-t border-slate-200 p-4" @submit.prevent="submitMessage">
            <p v-if="sendError" class="text-sm text-rose-600">{{ sendError }}</p>
            <textarea
              v-model="draftBody"
              rows="2"
              placeholder="Votre message..."
              class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
            />
            <div class="flex flex-wrap items-center justify-between gap-2">
              <input ref="fileInput" type="file" accept="image/*" class="text-sm" @change="onFileChange" />
              <AppButton type="submit" :loading="isSending" :disabled="!canSend()">Envoyer</AppButton>
            </div>
          </form>
        </template>
      </section>
    </div>
  </div>
</template>
