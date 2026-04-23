import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { axiosInstance } from '@/composables/useApi'
import { useAuthStore } from '@/stores/auth'

export const useAIStore = defineStore('ai', () => {
  const lastGeneration = ref(null)
  const loading = ref(false)
  const error = ref(null)

  const remainingGenerations = computed(() => {
    const auth = useAuthStore()
    const limits = { free: 10, pro: 100, team: 500 }
    const limit = limits[auth.user?.plan] || 10
    const used = auth.user?.usage?.aiGenerationsThisMonth || 0
    return Math.max(0, limit - used)
  })

  const createPost = async (topic, platform, tone = 'professional') => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/ai/create', { topic, platform, tone })
      lastGeneration.value = response.data
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to generate post'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const optimise = async (content, platform) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/ai/optimise', { content, platform })
      lastGeneration.value = response.data
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to optimise content'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const repurpose = async (content, fromPlatform, toPlatforms) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/ai/repurpose', {
        content,
        fromPlatform,
        toPlatforms
      })
      lastGeneration.value = response.data
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to repurpose content'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const suggestHashtags = async (content, platform) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/ai/hashtags', { content, platform })
      lastGeneration.value = response.data
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to suggest hashtags'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const generateThread = async (content) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/ai/thread', { content })
      lastGeneration.value = response.data
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to generate thread'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const scorePost = async (content, platform) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/ai/score', { content, platform })
      lastGeneration.value = response.data
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to score post'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const clearLastGeneration = () => {
    lastGeneration.value = null
    error.value = null
  }

  return {
    lastGeneration,
    loading,
    error,
    remainingGenerations,
    createPost,
    optimise,
    repurpose,
    suggestHashtags,
    generateThread,
    scorePost,
    clearLastGeneration
  }
})
