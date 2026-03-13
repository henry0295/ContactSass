<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="text-center">
      <h1 class="text-6xl font-bold text-gray-900 mb-4">404</h1>
      <p class="text-2xl text-gray-600 mb-8">Page not found</p>
      <router-link
        to="/dashboard"
        class="inline-block px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition"
      >
        Go to Dashboard
      </router-link>
    </div>
  </div>
</template>

<script setup lang="ts"></script>
