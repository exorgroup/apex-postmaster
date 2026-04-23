import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { axiosInstance } from '@/composables/useApi'

export const useCreditsStore = defineStore('credits', () => {
  const balance = ref(0)
  const transactions = ref([])
  const packages = ref([])
  const loading = ref(false)
  const error = ref(null)

  const balanceFormatted = computed(() => {
    return (balance.value / 100).toFixed(2)
  })

  const hasCredits = computed(() => balance.value > 0)

  const fetchBalance = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.get('/credits/balance')
      balance.value = response.data.balanceCents || 0
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch balance'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const fetchTransactions = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.get('/credits/transactions')
      transactions.value = response.data
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch transactions'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const fetchPackages = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.get('/credits/packages')
      packages.value = response.data
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch packages'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const purchasePackage = async (packageId) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/credits/purchase', { packageId })
      if (response.data.checkoutUrl) {
        window.location.href = response.data.checkoutUrl
      }
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to initiate purchase'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const estimateCost = async (messageContent) => {
    try {
      const response = await axiosInstance.post('/credits/estimate', { content: messageContent })
      return { success: true, data: response.data }
    } catch (err) {
      return { success: false, error: err.response?.data?.error || 'Failed to estimate cost' }
    }
  }

  return {
    balance,
    transactions,
    packages,
    loading,
    error,
    balanceFormatted,
    hasCredits,
    fetchBalance,
    fetchTransactions,
    fetchPackages,
    purchasePackage,
    estimateCost
  }
})
