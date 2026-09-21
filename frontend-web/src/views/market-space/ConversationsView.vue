<script setup lang="ts">
// Le chat Market Space est identique à celui du Garagiste (mêmes messages,
// mêmes images, même mise en page) : seul le préfixe des endpoints change,
// porté par la prop `space` — pas de copie de l'écran à maintenir en double.
import ConversationsView from '@/views/garage/ConversationsView.vue'
</script>

<template>
  <ConversationsView space="market-space" />
</template>
