import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import apiClient from '@/api/client'

interface Campaign {
  id: string
  name: string
  channel: 'email' | 'sms' | 'voice'
  status: 'draft' | 'scheduled' | 'running' | 'paused' | 'completed' | 'failed'
  created_at: string
  updated_at: string
}

export const useCampaignStore = defineStore('campaigns', () => {
  const campaigns = ref<Campaign[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchCampaigns = async (tenantId: string) => {
    loading.value = true
    error.value = null
    try {
      const response = await apiClient.get(`/tenants/${tenantId}/campaigns`)
      campaigns.value = response.data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch campaigns'
    } finally {
      loading.value = false
    }
  }

  const createCampaign = async (tenantId: string, data: any) => {
    loading.value = true
    error.value = null
    try {
      const response = await apiClient.post(`/tenants/${tenantId}/campaigns`, data)
      campaigns.value.push(response.data.data)
      return response.data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to create campaign'
      throw error.value
    } finally {
      loading.value = false
    }
  }

  const updateCampaign = async (tenantId: string, campaignId: string, data: any) => {
    loading.value = true
    error.value = null
    try {
      const response = await apiClient.put(`/tenants/${tenantId}/campaigns/${campaignId}`, data)
      const idx = campaigns.value.findIndex(c => c.id === campaignId)
      if (idx !== -1) {
        campaigns.value[idx] = response.data.data
      }
      return response.data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to update campaign'
      throw error.value
    } finally {
      loading.value = false
    }
  }

  const deleteCampaign = async (tenantId: string, campaignId: string) => {
    loading.value = true
    error.value = null
    try {
      await apiClient.delete(`/tenants/${tenantId}/campaigns/${campaignId}`)
      campaigns.value = campaigns.value.filter(c => c.id !== campaignId)
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to delete campaign'
      throw error.value
    } finally {
      loading.value = false
    }
  }

  return {
    campaigns,
    loading,
    error,
    fetchCampaigns,
    createCampaign,
    updateCampaign,
    deleteCampaign,
  }
})
