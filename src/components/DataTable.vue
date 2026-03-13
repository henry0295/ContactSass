<template>
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg">
      <thead class="bg-gray-50">
        <tr>
          <th
            v-for="column in columns"
            :key="column"
            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
          >
            {{ column }}
          </th>
          <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
            Actions
          </th>
        </tr>
      </thead>
      <tbody class="bg-white divide-y divide-gray-200">
        <tr v-if="items.length === 0" class="hover:bg-gray-50">
          <td :colspan="columns.length + 1" class="px-6 py-4 text-center text-gray-500">
            No items found
          </td>
        </tr>
        <tr v-for="(item, idx) in items" :key="idx" class="hover:bg-gray-50">
          <td v-for="column in columns" :key="column" class="px-6 py-4 whitespace-nowrap">
            <div class="text-sm">
              {{ item[column.toLowerCase()] }}
            </div>
          </td>
          <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
            <slot name="actions" :item="item"></slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
defineProps({
  columns: {
    type: Array as () => string[],
    required: true,
  },
  items: {
    type: Array as () => any[],
    default: () => [],
  },
})
</script>
