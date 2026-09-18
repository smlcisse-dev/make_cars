<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'

// Coquille de modale générique : overlay, cadre, titre, bouton de fermeture,
// zone de contenu (slot par défaut) et zone d'actions (slot `footer`). Les
// modales spécifiques (ex. ReasonPromptModal) se construisent par-dessus au
// lieu de dupliquer cette mécanique (overlay, Escape, Teleport) à chaque fois.
defineProps<{
  title: string
}>()

const emit = defineEmits<{ close: [] }>()

function handleKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    emit('close')
  }
}

// `onMounted`/`onUnmounted` sont des "hooks de cycle de vie" : Vue les
// appelle respectivement quand ce composant apparaît/disparaît du DOM. On
// s'en sert ici pour n'écouter le clavier que pendant que la modale existe
// (et bien retirer l'écouteur ensuite, sinon il s'accumulerait à chaque
// ouverture).
onMounted(() => window.addEventListener('keydown', handleKeydown))
onUnmounted(() => window.removeEventListener('keydown', handleKeydown))
</script>

<template>
  <!-- `Teleport` déplace ce bloc dans <body> au rendu : la modale s'affiche
       par-dessus tout le reste (pas emprisonnée par l'overflow/z-index d'un
       parent quelconque) même si elle est déclarée au milieu d'une page. -->
  <Teleport to="body">
    <div
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
      @click.self="emit('close')"
    >
      <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
        <div class="flex items-start justify-between">
          <h2 class="text-base font-semibold text-slate-900">{{ title }}</h2>
          <button
            type="button"
            class="text-slate-400 hover:text-slate-600"
            aria-label="Fermer"
            @click="emit('close')"
          >
            ✕
          </button>
        </div>

        <div class="mt-4">
          <slot />
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </Teleport>
</template>
