<template>
  <div class="p-6 bg-white rounded-lg shadow">
    <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ title }}</h3>
    <p class="text-3xl font-bold text-blue-600">{{ value }}</p>
    <p class="text-sm text-gray-600 mt-2">{{ icon }}</p>
  </div>
</template>

<script setup lang="ts" lang-="ts">
defineProps({
  title: {
    type: String,
    required: true,
  },
  value: {
    type: [String, Number],
    required: true,
  },
  icon: {
    type: String,
    default: '📊',
  },
})
</script>
