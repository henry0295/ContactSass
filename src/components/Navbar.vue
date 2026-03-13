<template>
  <nav class="bg-white shadow">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <span class="text-xl font-bold text-blue-600">ContactSass</span>
          </div>
          <div class="hidden md:block">
            <div class="ml-10 flex items-baseline space-x-4">
              <nav-link to="/dashboard" label="Dashboard" />
              <nav-link to="/campaigns" label="Campaigns" />
              <nav-link to="/contacts" label="Contacts" />
              <nav-link to="/reports" label="Reports" />
            </div>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <button
            @click="openMenu = !openMenu"
            class="relative p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg"
          >
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
              <path
                d="M10 12a2 2 0 100-4 2 2 0 000 4z"
              ></path>
              <path
                fill-rule="evenodd"
                d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                clip-rule="evenodd"
              ></path>
            </svg>
          </button>
          <div v-if="openMenu" class="absolute right-4 top-16 bg-white shadow rounded-lg p-4 z-50">
            <button
              @click="handleLogout"
              class="block w-full text-left px-4 py-2 text-red-600 hover:bg-gray-100 rounded"
            >
              Logout
            </button>
          </div>
        </div>
      </div>
    </div>
  </nav>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()
const openMenu = ref(false)

const handleLogout = async () => {
  await authStore.logout()
  router.push('/login')
}
</script>

<script lang="ts">
import NavLink from './NavLink.vue'

export default {
  components: {
    NavLink,
  },
}
</script>
