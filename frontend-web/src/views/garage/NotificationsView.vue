<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { fetchNotifications, markNotificationRead } from '@/api/notifications'
import AppPagination from '@/shared/components/AppPagination.vue'
import type { PushNotification } from '@/types/notification'
import { extractApiErrorMessage } from '@/utils/apiError'

const notifications = ref<PushNotification[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)
const actionErrorMessage = ref<string | null>(null)

async function loadNotifications(): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchNotifications(currentPage.value)
    notifications.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, 'Impossible de charger les notifications. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

function goToPage(page: number): void {
  currentPage.value = page
  loadNotifications()
}

// Met à jour la ligne en mémoire avec la notification renvoyée, sans
// recharger toute la liste.
async function handleClick(notification: PushNotification): Promise<void> {
  if (notification.read_at !== null) {
    return
  }

  actionErrorMessage.value = null

  try {
    const updated = await markNotificationRead(notification.id)
    const index = notifications.value.findIndex((n) => n.id === notification.id)
    if (index !== -1) {
      notifications.value[index] = updated
    }
  } catch (error) {
    actionErrorMessage.value = extractApiErrorMessage(error, 'Impossible de marquer cette notification comme lue.')
  }
}

function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })
}

onMounted(loadNotifications)
</script>

<template>
  <div class="max-w-3xl space-y-4">
    <div>
      <h2 class="text-lg font-semibold text-slate-900">Notifications</h2>
      <p class="mt-1 text-sm text-slate-500">Cliquez sur une notification non lue pour la marquer comme lue.</p>
    </div>

    <p v-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>
    <p v-else-if="isLoading" class="text-sm text-slate-500">Chargement...</p>

    <template v-else>
      <p v-if="actionErrorMessage" class="rounded-md bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ actionErrorMessage }}</p>
      <p v-if="notifications.length === 0" class="text-sm text-slate-500">Aucune notification pour l'instant.</p>

      <ul v-else class="space-y-2">
        <li v-for="notification in notifications" :key="notification.id">
          <button
            type="button"
            :class="[
              'block w-full rounded-lg border p-4 text-left',
              notification.read_at === null
                ? 'border-sky-200 bg-sky-50 hover:bg-sky-100'
                : 'cursor-default border-slate-200 bg-white',
            ]"
            @click="handleClick(notification)"
          >
            <div class="flex items-start justify-between gap-3">
              <p :class="['text-sm text-slate-900', notification.read_at === null ? 'font-semibold' : 'font-medium']">
                <span v-if="notification.read_at === null" class="mr-1.5 inline-block h-2 w-2 rounded-full bg-sky-500" />
                {{ notification.title }}
              </p>
              <span class="shrink-0 text-xs text-slate-400">{{ formatDateTime(notification.created_at) }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-600">{{ notification.body }}</p>
          </button>
        </li>
      </ul>

      <AppPagination
        v-if="notifications.length > 0"
        :current-page="currentPage"
        :last-page="lastPage"
        @update:current-page="goToPage"
      />
    </template>
  </div>
</template>
