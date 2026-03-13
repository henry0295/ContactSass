<template>
  <main-layout>
    <div class="max-w-7xl mx-auto">
      <PageHeader
        title="Campaigns"
        description="Manage your email, SMS, and voice campaigns"
      >
        <template #actions>
          <router-link
            to="/campaigns/create"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
          >
            New Campaign
          </router-link>
        </template>
      </PageHeader>

      <div class="mt-8">
        <div v-if="campaignStore.loading" class="text-center py-12">
          <p class="text-gray-600">Loading campaigns...</p>
        </div>

        <div v-else-if="campaignStore.campaigns.length === 0" class="text-center py-12">
          <p class="text-gray-600 mb-4">No campaigns yet</p>
          <router-link
            to="/campaigns/create"
            class="inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
          >
            Create Your First Campaign
          </router-link>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div
            v-for="campaign in campaignStore.campaigns"
            :key="campaign.id"
            class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition"
          >
            <div class="flex justify-between items-start mb-4">
              <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ campaign.name }}</h3>
                <p class="text-sm text-gray-500">{{ campaign.channel }}</p>
              </div>
              <StatusBadge :status="campaign.status" />
            </div>
            <p class="text-sm text-gray-600 mb-4">
              Created {{ new Date(campaign.created_at).toLocaleDateString() }}
            </p>
            <div class="flex gap-2">
              <router-link
                :to="`/campaigns/${campaign.id}`"
                class="flex-1 px-3 py-2 bg-blue-100 text-blue-600 rounded text-sm text-center hover:bg-blue-200 transition"
              >
                View
              </router-link>
              <button
                @click="deleteCampaign(campaign.id)"
                class="flex-1 px-3 py-2 bg-red-100 text-red-600 rounded text-sm hover:bg-red-200 transition"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main-layout>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useCampaignStore } from '@/stores/campaigns'
import { useAuthStore } from '@/stores/auth'
import MainLayout from '@/layouts/MainLayout.vue'
import PageHeader from '@/components/PageHeader.vue'
import StatusBadge from '@/components/StatusBadge.vue'

const campaignStore = useCampaignStore()
const authStore = useAuthStore()

onMounted(async () => {
  if (authStore.currentTenant) {
    await campaignStore.fetchCampaigns(authStore.currentTenant.id)
  }
})

const deleteCampaign = async (campaignId: string) => {
  if (window.confirm('Are you sure?')) {
    if (authStore.currentTenant) {
      await campaignStore.deleteCampaign(authStore.currentTenant.id, campaignId)
    }
  }
}
</script>
