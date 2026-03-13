<template>
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-3xl font-bold text-gray-900">{{ title }}</h1>
      <p v-if="description" class="text-gray-600 mt-1">{{ description }}</p>
    </div>
    <slot name="actions"></slot>
  </div>
</template>

<script setup lang="ts">
defineProps({
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    default: '',
  },
})
</script>
