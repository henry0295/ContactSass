<template>
  <button
    :class="[
      'px-6 py-2 rounded-lg font-medium transition disabled:opacity-50 disabled:cursor-not-allowed',
      variantClasses[variant],
      sizeClasses[size],
    ]"
    :disabled="disabled"
  >
    <slot></slot>
  </button>
</template>

<script setup lang="ts">
defineProps({
  variant: {
    type: String as () => 'primary' | 'secondary' | 'danger' | 'ghost',
    default: 'primary',
  },
  size: {
    type: String as () => 'sm' | 'md' | 'lg',
    default: 'md',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})

const variantClasses: Record<string, string> = {
  'primary': 'bg-blue-600 hover:bg-blue-700 text-white',
  'secondary': 'bg-gray-200 hover:bg-gray-300 text-gray-900',
  'danger': 'bg-red-600 hover:bg-red-700 text-white',
  'ghost': 'bg-transparent hover:bg-gray-100 text-gray-900 border border-gray-300',
}

const sizeClasses: Record<string, string> = {
  'sm': 'px-3 py-1 text-sm',
  'md': 'px-4 py-2 text-base',
  'lg': 'px-6 py-3 text-lg',
}
</script>
