<template>
  <MainLayout>
    <PageHeader title="Settings" />

    <div class="mt-8 max-w-4xl mx-auto">
      <!-- Settings Tabs -->
      <div class="flex border-b border-gray-200 mb-6">
        <button
          v-for="tab in tabs"
          :key="tab"
          @click="activeTab = tab"
          :class="[
            'px-4 py-2 font-medium text-sm border-b-2 transition-colors',
            activeTab === tab
              ? 'border-blue-500 text-blue-600'
              : 'border-transparent text-gray-600 hover:text-gray-900'
          ]"
        >
          {{ tab }}
        </button>
      </div>

      <!-- Tenant Settings -->
      <div v-if="activeTab === 'Tenant'" class="bg-white rounded-lg shadow-md p-6 space-y-6">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Tenant Name
          </label>
          <input
            v-model="tenantSettings.name"
            type="text"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Domain Name
          </label>
          <input
            v-model="tenantSettings.domain"
            type="text"
            placeholder="e.g., example.com"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Logo URL
          </label>
          <input
            v-model="tenantSettings.logoUrl"
            type="url"
            placeholder="https://..."
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
          />
          <img
            v-if="tenantSettings.logoUrl"
            :src="tenantSettings.logoUrl"
            alt="Logo"
            class="mt-2 h-16 rounded"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Tenant Status
          </label>
          <select
            v-model="tenantSettings.status"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg"
          >
            <option value="active">Active</option>
            <option value="suspended">Suspended</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>

        <div class="pt-4 flex justify-end gap-3">
          <button
            @click="resetTenantSettings"
            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            @click="saveTenantSettings"
            :disabled="savingTenant"
            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50"
          >
            {{ savingTenant ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </div>

      <!-- Email Settings -->
      <div v-if="activeTab === 'Email'" class="bg-white rounded-lg shadow-md p-6 space-y-6">
        <div class="bg-blue-50 border border-blue-200 p-4 rounded-lg mb-4">
          <p class="text-sm text-blue-800">
            🔗 Connected to Amazon SES for reliable email delivery
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Default From Email
          </label>
          <input
            v-model="emailSettings.defaultFrom"
            type="email"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
          />
          <p class="text-xs text-gray-500 mt-1">
            Sender email address for all campaigns
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Webhook URL (for events)
          </label>
          <input
            v-model="emailSettings.webhookUrl"
            type="url"
            placeholder="https://your-app.com/webhooks/ses"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Daily Email Limit
          </label>
          <input
            v-model.number="emailSettings.dailyLimit"
            type="number"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div class="pt-4 flex justify-end gap-3">
          <button
            @click="testEmailDelivery"
            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
          >
            📧 Test Email
          </button>
          <button
            @click="saveEmailSettings"
            :disabled="savingEmail"
            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50"
          >
            {{ savingEmail ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </div>

      <!-- SMS Settings -->
      <div v-if="activeTab === 'SMS'" class="bg-white rounded-lg shadow-md p-6 space-y-6">
        <div class="bg-green-50 border border-green-200 p-4 rounded-lg mb-4">
          <p class="text-sm text-green-800">
            🔗 Connected to Amazon SNS for SMS delivery
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            SMS Sender ID
          </label>
          <input
            v-model="smsSettings.senderId"
            type="text"
            placeholder="e.g., MYCOMPANY"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
          />
          <p class="text-xs text-gray-500 mt-1">
            Brand identifier or short code for SMS messages
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Per-Minute SMS Limit
          </label>
          <input
            v-model.number="smsSettings.perMinuteLimit"
            type="number"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label class="flex items-center">
            <input
              v-model="smsSettings.doubleOptIn"
              type="checkbox"
              class="rounded border-gray-300"
            />
            <span class="ml-2 text-sm text-gray-700">
              Require double opt-in for SMS campaigns
            </span>
          </label>
        </div>

        <div class="pt-4 flex justify-end gap-3">
          <button
            @click="testSmsDelivery"
            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
          >
            💬 Test SMS
          </button>
          <button
            @click="saveSmsSettings"
            :disabled="savingSms"
            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50"
          >
            {{ savingSms ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </div>

      <!-- Voice Settings -->
      <div v-if="activeTab === 'Voice'" class="bg-white rounded-lg shadow-md p-6 space-y-6">
        <div class="bg-purple-50 border border-purple-200 p-4 rounded-lg mb-4">
          <p class="text-sm text-purple-800">
            🔗 Connected to FreeSWITCH for voice call delivery
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Caller ID
          </label>
          <input
            v-model="voiceSettings.callerId"
            type="tel"
            placeholder="+1234567890"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Per-Minute Call Limit
          </label>
          <input
            v-model.number="voiceSettings.perMinuteLimit"
            type="number"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Default Voice (TTS)
          </label>
          <select
            v-model="voiceSettings.defaultVoice"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg"
          >
            <option value="male">Male</option>
            <option value="female">Female</option>
          </select>
        </div>

        <div class="pt-4 flex justify-end gap-3">
          <button
            @click="testVoiceCall"
            class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
          >
            ☎️ Test Call
          </button>
          <button
            @click="saveVoiceSettings"
            :disabled="savingVoice"
            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50"
          >
            {{ savingVoice ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </div>

      <!-- Team Settings -->
      <div v-if="activeTab === 'Team'" class="bg-white rounded-lg shadow-md p-6 space-y-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="font-semibold">Team Members</h3>
          <button
            @click="showInviteModal = true"
            class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600"
          >
            + Invite User
          </button>
        </div>

        <div class="divide-y">
          <div
            v-for="member in teamMembers"
            :key="member.id"
            class="py-4 flex items-center justify-between"
          >
            <div>
              <p class="font-medium">{{ member.name }}</p>
              <p class="text-sm text-gray-600">{{ member.email }}</p>
            </div>
            <div class="flex items-center gap-4">
              <select
                v-model="member.role"
                class="px-3 py-1 border border-gray-300 rounded text-sm"
              >
                <option value="admin">Admin</option>
                <option value="operator">Operator</option>
                <option value="analyst">Analyst</option>
                <option value="viewer">Viewer</option>
              </select>
              <button
                @click="removeMember(member.id)"
                class="text-red-500 hover:text-red-700 text-sm"
              >
                Remove
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Success Message -->
      <div
        v-if="successMessage"
        class="mt-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg"
      >
        ✅ {{ successMessage }}
      </div>
    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import MainLayout from '../layouts/MainLayout.vue'
import PageHeader from '../components/PageHeader.vue'

const activeTab = ref('Tenant')
const tabs = ['Tenant', 'Email', 'SMS', 'Voice', 'Team']

const tenantSettings = ref({
  name: 'Acme Corp',
  domain: 'acme-corp.example.com',
  logoUrl: '',
  status: 'active'
})

const emailSettings = ref({
  defaultFrom: 'noreply@acme-corp.com',
  webhookUrl: '',
  dailyLimit: 50000
})

const smsSettings = ref({
  senderId: 'ACME',
  perMinuteLimit: 100,
  doubleOptIn: false
})

const voiceSettings = ref({
  callerId: '+1234567890',
  perMinuteLimit: 50,
  defaultVoice: 'female'
})

const teamMembers = ref([
  { id: 1, name: 'John Doe', email: 'john@example.com', role: 'admin' },
  { id: 2, name: 'Jane Smith', email: 'jane@example.com', role: 'operator' },
  { id: 3, name: 'Bob Johnson', email: 'bob@example.com', role: 'analyst' }
])

const savingTenant = ref(false)
const savingEmail = ref(false)
const savingSms = ref(false)
const savingVoice = ref(false)
const successMessage = ref('')
const showInviteModal = ref(false)

const saveTenantSettings = async () => {
  savingTenant.value = true
  try {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1000))
    successMessage.value = 'Tenant settings saved successfully!'
    setTimeout(() => (successMessage.value = ''), 3000)
  } finally {
    savingTenant.value = false
  }
}

const saveEmailSettings = async () => {
  savingEmail.value = true
  try {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1000))
    successMessage.value = 'Email settings saved successfully!'
    setTimeout(() => (successMessage.value = ''), 3000)
  } finally {
    savingEmail.value = false
  }
}

const saveSmsSettings = async () => {
  savingSms.value = true
  try {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1000))
    successMessage.value = 'SMS settings saved successfully!'
    setTimeout(() => (successMessage.value = ''), 3000)
  } finally {
    savingSms.value = false
  }
}

const saveVoiceSettings = async () => {
  savingVoice.value = true
  try {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1000))
    successMessage.value = 'Voice settings saved successfully!'
    setTimeout(() => (successMessage.value = ''), 3000)
  } finally {
    savingVoice.value = false
  }
}

const resetTenantSettings = () => {
  tenantSettings.value = {
    name: 'Acme Corp',
    domain: 'acme-corp.example.com',
    logoUrl: '',
    status: 'active'
  }
}

const testEmailDelivery = async () => {
  console.log('Testing email delivery...')
  successMessage.value = 'Test email sent! Check your inbox.'
  setTimeout(() => (successMessage.value = ''), 3000)
}

const testSmsDelivery = async () => {
  console.log('Testing SMS delivery...')
  successMessage.value = 'Test SMS sent!'
  setTimeout(() => (successMessage.value = ''), 3000)
}

const testVoiceCall = async () => {
  console.log('Testing voice call...')
  successMessage.value = 'Test call initiated!'
  setTimeout(() => (successMessage.value = ''), 3000)
}

const removeMember = (memberId: number) => {
  teamMembers.value = teamMembers.value.filter(m => m.id !== memberId)
  successMessage.value = 'Team member removed!'
  setTimeout(() => (successMessage.value = ''), 3000)
}
</script>
