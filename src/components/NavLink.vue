<template>
  <router-link
    :to="to"
    :class="[
      'px-3 py-2 rounded-lg text-sm font-medium transition',
      isActive
        ? 'bg-blue-100 text-blue-600'
        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100',
    ]"
  >
    {{ label }}
  </router-link>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const props = defineProps({
  to: {
    type: String,
    required: true,
  },
  label: {
    type: String,
    required: true,
  },
})

const route = useRoute()

const isActive = computed(() => {
  return route.path.startsWith(props.to)
})
</script>
