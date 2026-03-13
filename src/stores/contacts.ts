import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/api/client'

interface Contact {
  id: string
  first_name: string
  last_name: string
  email: string
  phone_e164: string
  status: 'active' | 'unsubscribed' | 'invalid'
  created_at: string
}

export const useContactStore = defineStore('contacts', () => {
  const contacts = ref<Contact[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const fetchContacts = async (tenantId: string) => {
    loading.value = true
    error.value = null
    try {
      const response = await apiClient.get(`/tenants/${tenantId}/contacts`)
      contacts.value = response.data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch contacts'
    } finally {
      loading.value = false
    }
  }

  const createContact = async (tenantId: string, data: any) => {
    loading.value = true
    error.value = null
    try {
      const response = await apiClient.post(`/tenants/${tenantId}/contacts`, data)
      contacts.value.push(response.data.data)
      return response.data.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to create contact'
      throw error.value
    } finally {
      loading.value = false
    }
  }

  const importContacts = async (tenantId: string, file: File) => {
    loading.value = true
    error.value = null
    try {
      const formData = new FormData()
      formData.append('file', file)
      const response = await apiClient.post(`/tenants/${tenantId}/contacts/import`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      await fetchContacts(tenantId)
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to import contacts'
      throw error.value
    } finally {
      loading.value = false
    }
  }

  return {
    contacts,
    loading,
    error,
    fetchContacts,
    createContact,
    importContacts,
  }
})
