import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

interface User {
  id: string
  name: string
  email: string
  avatar_url?: string
}

interface Tenant {
  id: string
  name: string
  role: string
  logo_url?: string
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const tenants = ref<Tenant[]>([])
  const currentTenant = ref<Tenant | null>(null)
  const token = ref<string | null>(null)

  const isAuthenticated = computed(() => !!token.value && !!user.value)

  const setToken = (newToken: string) => {
    token.value = newToken
    localStorage.setItem('auth_token', newToken)
    axios.defaults.headers.common['Authorization'] = `Bearer ${newToken}`
  }

  const setUser = (newUser: User, newTenants: Tenant[] = []) => {
    user.value = newUser
    tenants.value = newTenants
    if (newTenants.length > 0) {
      currentTenant.value = newTenants[0]
    }
    localStorage.setItem('user', JSON.stringify(newUser))
    localStorage.setItem('tenants', JSON.stringify(newTenants))
  }

  const login = async (email: string, password: string) => {
    try {
      const response = await axios.post('/api/auth/login', {
        email,
        password,
      })

      const { user: userData, tenants: userTenants, token: newToken } = response.data

      setToken(newToken)
      setUser(userData, userTenants)

      return { success: true }
    } catch (error: any) {
      const message = error.response?.data?.message || 'Login failed'
      return { success: false, error: message }
    }
  }

  const register = async (
    name: string,
    email: string,
    password: string,
    passwordConfirmation: string,
    tenantName: string,
  ) => {
    try {
      const response = await axios.post('/api/auth/register', {
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
        tenant_name: tenantName,
      })

      const { user: userData, token: newToken } = response.data

      setToken(newToken)
      setUser(userData)

      return { success: true }
    } catch (error: any) {
      const message = error.response?.data?.message || 'Registration failed'
      return { success: false, error: message }
    }
  }

  const logout = async () => {
    try {
      await axios.post('/api/auth/logout')
    } catch (error) {
      console.error('Logout error:', error)
    } finally {
      user.value = null
      tenants.value = []
      currentTenant.value = null
      token.value = null
      localStorage.removeItem('auth_token')
      localStorage.removeItem('user')
      localStorage.removeItem('tenants')
      delete axios.defaults.headers.common['Authorization']
    }
  }

  const loadSession = () => {
    const storedToken = localStorage.getItem('auth_token')
    const storedUser = localStorage.getItem('user')
    const storedTenants = localStorage.getItem('tenants')

    if (storedToken && storedUser) {
      token.value = storedToken
      user.value = JSON.parse(storedUser)
      if (storedTenants) {
        tenants.value = JSON.parse(storedTenants)
        if (tenants.value.length > 0) {
          currentTenant.value = tenants.value[0]
        }
      }
      axios.defaults.headers.common['Authorization'] = `Bearer ${storedToken}`
    }
  }

  const switchTenant = (tenantId: string) => {
    const tenant = tenants.value.find(t => t.id === tenantId)
    if (tenant) {
      currentTenant.value = tenant
      localStorage.setItem('current_tenant', tenantId)
    }
  }

  return {
    user,
    tenants,
    currentTenant,
    token,
    isAuthenticated,
    setToken,
    setUser,
    login,
    register,
    logout,
    loadSession,
    switchTenant,
  }
})
