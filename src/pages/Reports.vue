<template>
  <MainLayout>
    <PageHeader title="Reports & Analytics" />

    <div class="mt-8 grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
      <!-- Summary Stats -->
      <StatCard
        title="Total Campaigns"
        value="24"
        icon="📊"
      />
      <StatCard
        title="Messages Sent"
        value="145.2K"
        icon="✉️"
      />
      <StatCard
        title="Success Rate"
        value="94.8%"
        icon="✅"
      />
      <StatCard
        title="Bounce Rate"
        value="2.5%"
        icon="❌"
      />
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Channel</label>
          <select
            v-model="filters.channel"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg"
          >
            <option value="">All Channels</option>
            <option value="email">Email</option>
            <option value="sms">SMS</option>
            <option value="voice">Voice</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
          <select
            v-model="filters.status"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg"
          >
            <option value="">All Status</option>
            <option value="completed">Completed</option>
            <option value="running">Running</option>
            <option value="draft">Draft</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
          <input
            v-model="filters.dateFrom"
            type="date"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
          <input
            v-model="filters.dateTo"
            type="date"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg"
          />
        </div>
      </div>

      <div class="mt-4 flex justify-end gap-3">
        <button
          @click="resetFilters"
          class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
        >
          Reset
        </button>
        <button
          @click="applyFilters"
          class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600"
        >
          Apply Filters
        </button>
      </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
      <!-- Delivery Rate by Channel -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
          Delivery Rate by Channel
        </h3>
        <div class="space-y-4">
          <div v-for="channel in deliveryByChannel" :key="channel.name">
            <div class="flex justify-between mb-1">
              <span class="text-sm font-medium">{{ channel.name }}</span>
              <span class="text-sm text-gray-600">{{ channel.rate }}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
              <div
                :style="{ width: channel.rate + '%' }"
                :class="[
                  'h-2 rounded-full transition-all',
                  channel.rate > 90
                    ? 'bg-green-500'
                    : channel.rate > 70
                    ? 'bg-yellow-500'
                    : 'bg-red-500'
                ]"
              ></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Status Distribution -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
          Campaign Status Distribution
        </h3>
        <div class="flex justify-around">
          <div
            v-for="status in statusDistribution"
            :key="status.name"
            class="text-center"
          >
            <div
              :style="{ width: '100px', height: '100px' }"
              class="relative inline-flex items-center justify-center"
            >
              <svg class="w-full h-full" viewBox="0 0 100 100">
                <circle
                  cx="50"
                  cy="50"
                  r="45"
                  fill="none"
                  :stroke="status.color"
                  stroke-width="8"
                  stroke-dasharray="282.6"
                  :stroke-dashoffset="
                    282.6 - (282.6 * status.count) / totalCampaigns
                  "
                  transform="rotate(-90 50 50)"
                />
              </svg>
              <span class="absolute text-xl font-bold">{{ status.count }}</span>
            </div>
            <p class="mt-2 text-sm font-medium">{{ status.name }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Messages Over Time -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">
        Messages Sent Over Time
      </h3>
      <div class="h-64 flex items-end justify-around gap-2">
        <div
          v-for="(day, index) in messagesOverTime"
          :key="index"
          class="flex-1 flex flex-col items-center"
        >
          <div
            :style="{ height: (day.count / 5000) * 100 + '%' }"
            class="w-full bg-blue-500 rounded-t hover:bg-blue-600 transition-all"
            :title="`${day.count} messages`"
          ></div>
          <p class="text-xs text-gray-600 mt-2">{{ day.day }}</p>
        </div>
      </div>
    </div>

    <!-- Detailed Campaign Report -->
    <div class="bg-white rounded-lg shadow-md p-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Campaign Performance</h3>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left font-medium">Campaign</th>
              <th class="px-4 py-3 text-left font-medium">Channel</th>
              <th class="px-4 py-3 text-left font-medium">Sent</th>
              <th class="px-4 py-3 text-left font-medium">Delivered</th>
              <th class="px-4 py-3 text-left font-medium">Bounced</th>
              <th class="px-4 py-3 text-left font-medium">Success %</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr
              v-for="campaign in detailedCampaigns"
              :key="campaign.id"
              class="hover:bg-gray-50"
            >
              <td class="px-4 py-3 font-medium">{{ campaign.name }}</td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium" :class="channelClasses(campaign.channel)">
                  {{ campaign.channel }}
                </span>
              </td>
              <td class="px-4 py-3">{{ campaign.sent.toLocaleString() }}</td>
              <td class="px-4 py-3 text-green-600">{{ campaign.delivered.toLocaleString() }}</td>
              <td class="px-4 py-3 text-red-600">{{ campaign.bounced }}</td>
              <td class="px-4 py-3">
                <span :class="[
                  'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium',
                  campaign.successRate > 90 ? 'bg-green-100 text-green-800' : campaign.successRate > 70 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'
                ]">
                  {{ campaign.successRate }}%
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Export Section -->
    <div class="mt-8 flex justify-end gap-3">
      <button
        @click="exportCSV"
        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 flex items-center gap-2"
      >
        📥 Export CSV
      </button>
      <button
        @click="exportPDF"
        class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 flex items-center gap-2"
      >
        📄 Export PDF
      </button>
    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import MainLayout from '../layouts/MainLayout.vue'
import PageHeader from '../components/PageHeader.vue'
import StatCard from '../components/StatCard.vue'

const filters = ref({
  channel: '',
  status: '',
  dateFrom: '',
  dateTo: ''
})

const deliveryByChannel = ref([
  { name: 'Email', rate: 96 },
  { name: 'SMS', rate: 94 },
  { name: 'Voice', rate: 88 }
])

const statusDistribution = ref([
  { name: 'Completed', count: 18, color: '#10b981' },
  { name: 'Running', count: 2, color: '#3b82f6' },
  { name: 'Draft', count: 4, color: '#6b7280' }
])

const totalCampaigns = 24

const messagesOverTime = ref([
  { day: 'Mon', count: 3200 },
  { day: 'Tue', count: 4100 },
  { day: 'Wed', count: 2800 },
  { day: 'Thu', count: 4500 },
  { day: 'Fri', count: 5000 },
  { day: 'Sat', count: 2100 },
  { day: 'Sun', count: 1500 }
])

const detailedCampaigns = ref([
  {
    id: 1,
    name: 'Spring Sale 2024',
    channel: 'email',
    sent: 50000,
    delivered: 48500,
    bounced: 23,
    successRate: 97
  },
  {
    id: 2,
    name: 'SMS Flash Deal',
    channel: 'sms',
    sent: 25000,
    delivered: 23500,
    bounced: 8,
    successRate: 94
  },
  {
    id: 3,
    name: 'Voice Survey',
    channel: 'voice',
    sent: 10000,
    delivered: 8800,
    bounced: 45,
    successRate: 88
  },
  {
    id: 4,
    name: 'Newsletter March',
    channel: 'email',
    sent: 35000,
    delivered: 33200,
    bounced: 15,
    successRate: 95
  }
])

const applyFilters = () => {
  console.log('Filters applied:', filters.value)
  // Implementar lógica de filtrado
}

const resetFilters = () => {
  filters.value = {
    channel: '',
    status: '',
    dateFrom: '',
    dateTo: ''
  }
}

const channelClasses = (channel: string) => {
  const classes: Record<string, string> = {
    email: 'bg-blue-100 text-blue-800',
    sms: 'bg-green-100 text-green-800',
    voice: 'bg-purple-100 text-purple-800'
  }
  return classes[channel] || 'bg-gray-100 text-gray-800'
}

const exportCSV = () => {
  console.log('Exporting to CSV...')
  // Implementar exportación CSV
}

const exportPDF = () => {
  console.log('Exporting to PDF...')
  // Implementar exportación PDF
}
</script>
