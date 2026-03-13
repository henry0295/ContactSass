<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <!-- Logo / Header -->
      <div class="text-center mb-8">
        <h1 class="text-4xl font-bold text-gray-900 mb-2">ContactSass</h1>
        <p class="text-gray-600">Multi-tenant Broadcasting Platform</p>
      </div>

      <!-- Card -->
      <div class="bg-white rounded-lg shadow-lg p-8">
        <!-- Tabs -->
        <div class="flex gap-0 mb-8 border-b border-gray-200">
          <button
            @click="currentTab = 'login'"
            :class="[
              'flex-1 py-2 px-4 text-center font-medium transition',
              currentTab === 'login'
                ? 'border-b-2 border-blue-500 text-blue-600'
                : 'text-gray-600 hover:text-gray-900',
            ]"
          >
            Login
          </button>
          <button
            @click="currentTab = 'register'"
            :class="[
              'flex-1 py-2 px-4 text-center font-medium transition',
              currentTab === 'register'
                ? 'border-b-2 border-blue-500 text-blue-600'
                : 'text-gray-600 hover:text-gray-900',
            ]"
          >
            Register
          </button>
        </div>

        <!-- Login Form -->
        <div v-if="currentTab === 'login'" class="space-y-4">
          <div v-if="error" class="p-3 bg-red-50 border border-red-200 rounded text-red-700 text-sm">
            {{ error }}
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input
              v-model="loginForm.email"
              type="email"
              placeholder="you@example.com"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input
              v-model="loginForm.password"
              type="password"
              placeholder="••••••••"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              @keyup.enter="handleLogin"
            />
          </div>

          <button
            @click="handleLogin"
            :disabled="isLoading"
            class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white font-medium py-2 px-4 rounded-lg transition"
          >
            {{ isLoading ? 'Logging in...' : 'Sign In' }}
          </button>
        </div>

        <!-- Register Form -->
        <div v-if="currentTab === 'register'" class="space-y-4">
          <div v-if="error" class="p-3 bg-red-50 border border-red-200 rounded text-red-700 text-sm">
            {{ error }}
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
            <input
              v-model="registerForm.name"
              type="text"
              placeholder="John Doe"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input
              v-model="registerForm.email"
              type="email"
              placeholder="you@example.com"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tenant Name</label>
            <input
              v-model="registerForm.tenantName"
              type="text"
              placeholder="My Company"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input
              v-model="registerForm.password"
              type="password"
              placeholder="••••••••"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
            <input
              v-model="registerForm.passwordConfirmation"
              type="password"
              placeholder="••••••••"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              @keyup.enter="handleRegister"
            />
          </div>

          <button
            @click="handleRegister"
            :disabled="isLoading"
            class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white font-medium py-2 px-4 rounded-lg transition"
          >
            {{ isLoading ? 'Creating account...' : 'Create Account' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const currentTab = ref<'login' | 'register'>('login')
const isLoading = ref(false)
const error = ref('')

const loginForm = ref({
  email: '',
  password: '',
})

const registerForm = ref({
  name: '',
  email: '',
  tenantName: '',
  password: '',
  passwordConfirmation: '',
})

const handleLogin = async () => {
  error.value = ''
  isLoading.value = true

  if (!loginForm.value.email || !loginForm.value.password) {
    error.value = 'Please fill in all fields'
    isLoading.value = false
    return
  }

  const result = await authStore.login(loginForm.value.email, loginForm.value.password)

  if (result.success) {
    router.push('/dashboard')
  } else {
    error.value = result.error || 'Login failed'
  }

  isLoading.value = false
}

const handleRegister = async () => {
  error.value = ''
  isLoading.value = true

  if (
    !registerForm.value.name ||
    !registerForm.value.email ||
    !registerForm.value.password ||
    !registerForm.value.passwordConfirmation ||
    !registerForm.value.tenantName
  ) {
    error.value = 'Please fill in all fields'
    isLoading.value = false
    return
  }

  if (registerForm.value.password !== registerForm.value.passwordConfirmation) {
    error.value = 'Passwords do not match'
    isLoading.value = false
    return
  }

  const result = await authStore.register(
    registerForm.value.name,
    registerForm.value.email,
    registerForm.value.password,
    registerForm.value.passwordConfirmation,
    registerForm.value.tenantName,
  )

  if (result.success) {
    router.push('/dashboard')
  } else {
    error.value = result.error || 'Registration failed'
  }

  isLoading.value = false
}
</script>
