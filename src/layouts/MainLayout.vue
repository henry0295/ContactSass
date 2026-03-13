<template>
  <div class="min-h-screen bg-gray-50">
    <Navbar />
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <slot></slot>
    </main>
  </div>
</template>

<script setup lang="ts" lang-="ts">
import Navbar from '@/components/Navbar.vue'
</script>
