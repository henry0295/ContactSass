<template>
  <MainLayout>
    <PageHeader :title="pageTitle" />

    <div class="mt-8 max-w-4xl mx-auto">
      <!-- Progress Steps -->
      <div class="mb-8">
        <div class="flex items-center justify-between">
          <div
            v-for="(step, index) in steps"
            :key="step"
            class="flex-1 text-center"
          >
            <div
              :class="[
                'w-10 h-10 mx-auto rounded-full flex items-center justify-center mb-2',
                currentStep > index
                  ? 'bg-blue-500 text-white'
                  : currentStep === index
                  ? 'bg-blue-100 text-blue-600 border-2 border-blue-500'
                  : 'bg-gray-200 text-gray-600'
              ]"
            >
              {{ index + 1 }}
            </div>
            <p class="text-sm font-medium">{{ step }}</p>
          </div>
        </div>
        <div class="mt-4 h-1 bg-gray-200 rounded-full">
          <div
            :style="{ width: (currentStep / (steps.length - 1)) * 100 + '%' }"
            class="h-full bg-blue-500 rounded-full transition-all"
          ></div>
        </div>
      </div>

      <!-- Form Steps -->
      <form @submit.prevent="submitForm" class="bg-white rounded-lg shadow-md p-6">
        <!-- Step 1: Basics -->
        <div v-if="currentStep === 0" class="space-y-6">
          <FormInput
            v-model="form.name"
            label="Campaign Name"
            placeholder="E.g., Birthday Special Offer"
            :error="errors.name"
            @blur="validateField('name')"
          />
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2"
              >Channel</label
            >
            <div class="grid grid-cols-3 gap-3">
              <button
                v-for="channel in channels"
                :key="channel"
                type="button"
                @click="form.channel = channel"
                :class="[
                  'p-4 border-2 rounded-lg transition-all',
                  form.channel === channel
                    ? 'border-blue-500 bg-blue-50'
                    : 'border-gray-300 hover:border-gray-400'
                ]"
              >
                <div class="text-2xl mb-2">
                  {{ channelEmojis[channel] }}
                </div>
                <div class="font-medium capitalize">{{ channel }}</div>
              </button>
            </div>
            <p v-if="errors.channel" class="mt-1 text-sm text-red-500">
              {{ errors.channel }}
            </p>
          </div>

          <FormTextarea
            v-model="form.description"
            label="Description"
            placeholder="Describe your campaign..."
            :error="errors.description"
          />
        </div>

        <!-- Step 2: Content -->
        <div v-if="currentStep === 1" class="space-y-6">
          <div v-if="form.channel === 'email'">
            <FormInput
              v-model="form.email_subject"
              label="Subject Line"
              placeholder="E.g., You're invited!"
              :error="errors.email_subject"
            />
            <FormTextarea
              v-model="form.email_template"
              label="Email Body"
              placeholder="Use {{first_name}}, {{email}}, etc. for personalization"
              rows="8"
              :error="errors.email_template"
              class="mt-4"
            />
          </div>

          <div v-if="form.channel === 'sms'">
            <FormTextarea
              v-model="form.sms_template"
              label="SMS Message"
              placeholder="Keep it short! Use {{first_name}}, {{phone}}, etc."
              rows="4"
              :error="errors.sms_template"
            />
            <p class="text-sm text-gray-600 mt-2">
              Characters: {{ form.sms_template.length }}/160
            </p>
          </div>

          <div v-if="form.channel === 'voice'">
            <FormInput
              v-model="form.voice_script"
              label="Voice Script"
              placeholder="E.g., Press 1 for yes, 2 for no"
              :error="errors.voice_script"
            />
            <FormInput
              v-model="form.voice_audio_url"
              label="Audio URL (Optional)"
              placeholder="https://example.com/audio.mp3"
              type="url"
              class="mt-4"
            />
          </div>
        </div>

        <!-- Step 3: Recipients & Schedule -->
        <div v-if="currentStep === 2" class="space-y-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2"
              >Select Contact List</label
            >
            <select
              v-model="form.contact_list_id"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
              :disabled="loadingLists"
            >
              <option value="">-- Choose a contact list --</option>
              <option
                v-for="list in contactLists"
                :key="list.id"
                :value="list.id"
              >
                {{ list.name }} ({{ list.contact_count }} contacts)
              </option>
            </select>
            <p v-if="errors.contact_list_id" class="mt-1 text-sm text-red-500">
              {{ errors.contact_list_id }}
            </p>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2"
                >Schedule Type</label
              >
              <select
                v-model="form.schedule_type"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg"
              >
                <option value="now">Send Immediately</option>
                <option value="scheduled">Schedule for Later</option>
              </select>
            </div>

            <div v-if="form.schedule_type === 'scheduled'">
              <FormInput
                v-model="form.scheduled_at"
                label="Send At"
                type="datetime-local"
                :error="errors.scheduled_at"
              />
            </div>
          </div>
        </div>

        <!-- Step 4: Review -->
        <div v-if="currentStep === 3" class="space-y-6">
          <div class="bg-blue-50 border-l-4 border-blue-500 p-4">
            <h3 class="font-semibold text-blue-900 mb-2">Review Campaign</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span class="text-gray-600">Name:</span>
                <p class="font-medium">{{ form.name }}</p>
              </div>
              <div>
                <span class="text-gray-600">Channel:</span>
                <p class="font-medium capitalize">{{ form.channel }}</p>
              </div>
              <div>
                <span class="text-gray-600">Recipients:</span>
                <p class="font-medium">
                  {{
                    contactLists.find((l) => l.id === form.contact_list_id)
                      ?.contact_count || 0
                  }}
                  contacts
                </p>
              </div>
              <div>
                <span class="text-gray-600">Send:</span>
                <p class="font-medium">
                  {{
                    form.schedule_type === 'now'
                      ? 'Immediately'
                      : formatDate(form.scheduled_at)
                  }}
                </p>
              </div>
            </div>
          </div>

          <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4">
            <p class="text-sm text-yellow-800">
              ⚠️ Once sent, this campaign cannot be undone. Ensure all details
              are correct before proceeding.
            </p>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-8 flex justify-between">
          <button
            v-if="currentStep > 0"
            type="button"
            @click="previousStep"
            class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
          >
            ← Previous
          </button>
          <div v-else></div>

          <div class="flex gap-3">
            <router-link
              to="/campaigns"
              class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"
            >
              Cancel
            </router-link>

            <button
              v-if="currentStep < steps.length - 1"
              type="button"
              @click="nextStep"
              :disabled="!canProceedToNext"
              class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Next →
            </button>

            <button
              v-else
              type="submit"
              :disabled="loading"
              class="px-6 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 disabled:opacity-50"
            >
              {{ loading ? 'Creating...' : 'Create Campaign' }}
            </button>
          </div>
        </div>

        <!-- Error Alert -->
        <div
          v-if="formError"
          class="mt-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg"
        >
          {{ formError }}
        </div>
      </form>
    </div>
  </MainLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useCampaignsStore } from '../stores/campaigns'
import { useAuthStore } from '../stores/auth'
import MainLayout from '../layouts/MainLayout.vue'
import PageHeader from '../components/PageHeader.vue'
import FormInput from '../components/FormInput.vue'
import FormTextarea from '../components/FormTextarea.vue'

const router = useRouter()
const route = useRoute()
const campaignsStore = useCampaignsStore()
const authStore = useAuthStore()

const currentStep = ref(0)
const steps = ['Basics', 'Content', 'Recipients', 'Review']
const channels = ['email', 'sms', 'voice']
const channelEmojis = { email: '📧', sms: '💬', voice: '☎️' }

const form = ref({
  name: '',
  channel: 'email',
  description: '',
  email_subject: '',
  email_template: '',
  sms_template: '',
  voice_script: '',
  voice_audio_url: '',
  contact_list_id: '',
  schedule_type: 'now',
  scheduled_at: ''
})

const errors = ref({
  name: '',
  channel: '',
  description: '',
  email_subject: '',
  email_template: '',
  sms_template: '',
  voice_script: '',
  contact_list_id: '',
  scheduled_at: ''
})

const loading = ref(false)
const formError = ref('')
const loadingLists = ref(false)
const contactLists = ref<any[]>([])

const pageTitle = computed(() =>
  route.params.id ? 'Edit Campaign' : 'Create New Campaign'
)

const canProceedToNext = computed(() => {
  if (currentStep.value === 0) {
    return form.value.name && form.value.channel && form.value.description
  }
  if (currentStep.value === 1) {
    if (form.value.channel === 'email') {
      return form.value.email_subject && form.value.email_template
    }
    if (form.value.channel === 'sms') {
      return (
        form.value.sms_template &&
        form.value.sms_template.length > 0 &&
        form.value.sms_template.length <= 160
      )
    }
    if (form.value.channel === 'voice') {
      return form.value.voice_script
    }
  }
  if (currentStep.value === 2) {
    return form.value.contact_list_id
  }
  return true
})

const validateField = (field: string) => {
  const validators: any = {
    name: (v: string) => (v ? '' : 'Campaign name is required'),
    channel: (v: string) => (v ? '' : 'Channel is required'),
    description: (v: string) => (v ? '' : 'Description is required'),
    email_subject: (v: string) => (v ? '' : 'Subject is required'),
    email_template: (v: string) => (v ? '' : 'Email body is required'),
    sms_template: (v: string) =>
      v
        ? v.length > 160
          ? 'SMS must be 160 characters or less'
          : ''
        : 'SMS message is required',
    voice_script: (v: string) => (v ? '' : 'Voice script is required')
  }

  if (validators[field]) {
    errors.value[field as keyof typeof errors.value] = validators[field](
      form.value[field as keyof typeof form.value]
    )
  }
}

const nextStep = () => {
  if (currentStep.value < steps.length - 1) {
    currentStep.value++
  }
}

const previousStep = () => {
  if (currentStep.value > 0) {
    currentStep.value--
  }
}

const submitForm = async () => {
  loading.value = true
  formError.value = ''

  try {
    const tenantId = authStore.currentTenant?.id
    if (!tenantId) {
      throw new Error('No tenant selected')
    }

    const campaignData = {
      name: form.value.name,
      description: form.value.description,
      channel: form.value.channel,
      email_template: form.value.email_template,
      email_subject: form.value.email_subject,
      sms_template: form.value.sms_template,
      voice_script: form.value.voice_script,
      voice_audio_url: form.value.voice_audio_url,
      scheduled_at:
        form.value.schedule_type === 'scheduled'
          ? form.value.scheduled_at
          : null
    }

    if (route.params.id) {
      await campaignsStore.updateCampaign(
        tenantId,
        route.params.id as string,
        campaignData
      )
    } else {
      await campaignsStore.createCampaign(tenantId, campaignData)
    }

    router.push('/campaigns')
  } catch (error: any) {
    formError.value = error.message || 'Failed to save campaign'
  } finally {
    loading.value = false
  }
}

const formatDate = (dateString: string) => {
  if (!dateString) return 'Not set'
  return new Date(dateString).toLocaleString()
}

onMounted(async () => {
  try {
    // Cleanup: Esta parte requeriría una API para obtener contact lists
    // Por ahora, usamos datos de ejemplo
    contactLists.value = [
      { id: '1', name: 'Newsletter Subscribers', contact_count: 2500 },
      { id: '2', name: 'VIP Customers', contact_count: 180 },
      { id: '3', name: 'Trial Users', contact_count: 450 }
    ]
  } catch (error) {
    console.error('Failed to load contact lists:', error)
  }
})
</script>
