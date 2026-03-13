<template>
  <div
    :class="[
      'inline-flex px-3 py-1 rounded-full text-sm font-medium',
      statusClasses[status] || 'bg-gray-100 text-gray-800',
    ]"
  >
    {{ statusLabel[status] || status }}
  </div>
</template>

<script setup lang="ts">
defineProps({
  status: {
    type: String,
    required: true,
  },
})

const statusClasses: Record<string, string> = {
  'active': 'bg-green-100 text-green-800',
  'draft': 'bg-yellow-100 text-yellow-800',
  'completed': 'bg-blue-100 text-blue-800',
  'paused': 'bg-orange-100 text-orange-800',
  'failed': 'bg-red-100 text-red-800',
  'pending': 'bg-gray-100 text-gray-800',
}

const statusLabel: Record<string, string> = {
  'active': 'Active',
  'draft': 'Draft',
  'completed': 'Completed',
  'paused': 'Paused',
  'failed': 'Failed',
  'pending': 'Pending',
}
</script>
