<template>
  <main-layout>
    <div class="max-w-7xl mx-auto">
      <!-- Welcome Section -->
      <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-2">
          Welcome to ContactSass, {{ authStore.user?.name }}!
        </h2>
        <p class="text-gray-600">
          You have successfully logged in to your multi-tenant broadcasting platform.
        </p>
      </div>

      <!-- Stats Grid -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <StatCard title="Total Campaigns" :value="stats.campaigns" icon="📧" />
        <StatCard title="Active Contacts" :value="stats.contacts" icon="👥" />
        <StatCard title="Messages Sent" :value="stats.messagesSent" icon="✉️" />
        <StatCard title="Success Rate" :value="`${stats.successRate}%`" icon="✅" />
      </div>

      <!-- Quick Actions -->
      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <router-link
            to="/campaigns"
            class="p-4 border-2 border-blue-500 rounded-lg hover:bg-blue-50 transition text-left"
          >
            <div class="font-semibold text-blue-600">📧 Campaigns</div>
            <div class="text-sm text-gray-600">Create and manage your campaigns</div>
          </router-link>
          <router-link
            to="/contacts"
            class="p-4 border-2 border-green-500 rounded-lg hover:bg-green-50 transition text-left"
          >
            <div class="font-semibold text-green-600">👥 Contacts</div>
            <div class="text-sm text-gray-600">View, edit, or import contact lists</div>
          </router-link>
        </div>
      </div>
    </div>
  </main-layout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import MainLayout from '@/layouts/MainLayout.vue'
import StatCard from '@/components/StatCard.vue'

const authStore = useAuthStore()

const stats = ref({
  campaigns: 12,
  contacts: 5420,
  messagesSent: 87543,
  successRate: 98,
})
</script>
