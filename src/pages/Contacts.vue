<template>
  <main-layout>
    <div class="max-w-7xl mx-auto">
      <PageHeader title="Contacts" description="Manage your contact lists and import new contacts">
        <template #actions>
          <router-link
            to="/contacts/import"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
          >
            Import Contacts
          </router-link>
        </template>
      </PageHeader>

      <div class="mt-8">
        <div v-if="contactStore.loading" class="text-center py-12">
          <p class="text-gray-600">Loading contacts...</p>
        </div>

        <div v-else-if="contactStore.contacts.length === 0" class="text-center py-12">
          <p class="text-gray-600 mb-4">No contacts yet</p>
          <router-link
            to="/contacts/import"
            class="inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
          >
            Import Your First Contacts
          </router-link>
        </div>

        <div v-else class="bg-white rounded-lg shadow overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Name
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Email
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Phone
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                    Status
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="contact in contactStore.contacts" :key="contact.id">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {{ contact.first_name }} {{ contact.last_name }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                    {{ contact.email }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                    {{ contact.phone_e164 }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <StatusBadge :status="contact.status" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </main-layout>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useContactStore } from '@/stores/contacts'
import { useAuthStore } from '@/stores/auth'
import MainLayout from '@/layouts/MainLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import StatusBadge from '@/components/StatusBadge.vue'

const contactStore = useContactStore()
const authStore = useAuthStore()

onMounted(async () => {
  if (authStore.currentTenant) {
    await contactStore.fetchContacts(authStore.currentTenant.id)
  }
})
</script>
