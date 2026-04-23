import { ref } from 'vue'
import { defineStore } from 'pinia'
import { axiosInstance } from '@/composables/useApi'

export const useAnalyticsStore = defineStore('analytics', () => {
  const overview = ref(null)
  const postAnalytics = ref([])
  const hashtagData = ref([])
  const bestTimes = ref(null)
  const platformComparison = ref([])
  const loading = ref(false)
  const error = ref(null)

  const fetchOverview = async (dateRange = {}) => {
    loading.value = true
    error.value = null

    try {
      const params = {
        from: dateRange.from,
        to: dateRange.to
      }
      const response = await axiosInstance.get('/analytics/overview', { params })
      overview.value = response.data
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch overview'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const fetchPostAnalytics = async (postId) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.get(`/analytics/posts/${postId}`)
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch post analytics'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const fetchHashtags = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.get('/analytics/hashtags')
      hashtagData.value = response.data
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch hashtag data'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const fetchBestTimes = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.get('/analytics/best-times')
      bestTimes.value = response.data
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch best times'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const fetchPlatformComparison = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.get('/analytics/platforms')
      platformComparison.value = response.data
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch platform comparison'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  return {
    overview,
    postAnalytics,
    hashtagData,
    bestTimes,
    platformComparison,
    loading,
    error,
    fetchOverview,
    fetchPostAnalytics,
    fetchHashtags,
    fetchBestTimes,
    fetchPlatformComparison
  }
})
