import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { axiosInstance } from '@/composables/useApi'

export const usePlatformsStore = defineStore('platforms', () => {
  const accounts = ref([])
  const loading = ref(false)
  const error = ref(null)

  const connectedPlatforms = computed(() => {
    return [...new Set(accounts.value.map(a => a.platform))]
  })

  const accountForPlatform = (platform) => {
    return accounts.value.find(a => a.platform === platform && a.isActive)
  }

  const isConnected = (platform) => {
    return accounts.value.some(a => a.platform === platform && a.isActive)
  }

  const activeAccounts = computed(() => {
    return accounts.value.filter(a => a.isActive)
  })

  const fetchAccounts = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.get('/platforms')
      accounts.value = response.data.data || []
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch accounts'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const connectPlatform = async (platform) => {
    // Get token from auth store to pass to OAuth flow
    const { useAuthStore } = await import('@/stores/auth')
    const auth = useAuthStore()
    const token = auth.accessToken

    // Redirect to OAuth with token in query param
    window.location.href = `/api/platforms/connect/${platform}?token=${encodeURIComponent(token)}`
  }

  const disconnectPlatform = async (id) => {
    loading.value = true
    error.value = null

    try {
      await axiosInstance.delete(`/platforms/${id}`)
      accounts.value = accounts.value.filter(a => a.id !== id)
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to disconnect account'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const refreshToken = async (id) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post(`/platforms/${id}/refresh`)
      const index = accounts.value.findIndex(a => a.id === id)
      if (index !== -1) {
        accounts.value[index] = response.data
      }
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to refresh token'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  return {
    accounts,
    loading,
    error,
    connectedPlatforms,
    activeAccounts,
    accountForPlatform,
    isConnected,
    fetchAccounts,
    connectPlatform,
    disconnectPlatform,
    refreshToken
  }
})
