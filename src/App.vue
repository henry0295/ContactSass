<template>
  <div id="app">
    <router-view />
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { onMounted } from 'vue'

const authStore = useAuthStore()

onMounted(() => {
  // Try to restore auth from localStorage
  authStore.loadSession()
})
</script>

<style scoped></style>
