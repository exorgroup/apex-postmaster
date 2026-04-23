import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { axiosInstance } from '@/composables/useApi'

export const usePostsStore = defineStore('posts', () => {
  const posts = ref([])
  const currentPost = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const filters = ref({
    status: null,
    platform: null,
    dateFrom: null,
    dateTo: null,
    campaignId: null
  })
  const pagination = ref({
    page: 1,
    perPage: 20,
    total: 0
  })

  const scheduledPosts = computed(() => posts.value.filter(p => p.status === 'scheduled'))
  const publishedPosts = computed(() => posts.value.filter(p => p.status === 'published'))
  const draftPosts = computed(() => posts.value.filter(p => p.status === 'draft'))
  const failedPosts = computed(() => posts.value.filter(p => p.status === 'failed'))

  const postsForDate = (date) => {
    const dateStr = date.toISOString().split('T')[0]
    return posts.value.filter(p => {
      if (!p.scheduledAt) return false
      return p.scheduledAt.startsWith(dateStr)
    })
  }

  const fetchPosts = async (customFilters = {}) => {
    loading.value = true
    error.value = null

    try {
      const params = {
        ...filters.value,
        ...customFilters,
        page: pagination.value.page,
        perPage: pagination.value.perPage
      }

      const response = await axiosInstance.get('/posts', { params })
      posts.value = response.data.posts || response.data
      pagination.value.total = response.data.total || posts.value.length
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch posts'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const fetchPost = async (id) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.get(`/posts/${id}`)
      currentPost.value = response.data
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch post'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const createPost = async (data) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/posts', data)
      posts.value.unshift(response.data)
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create post'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const updatePost = async (id, data) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.put(`/posts/${id}`, data)
      const index = posts.value.findIndex(p => p.id === id)
      if (index !== -1) {
        posts.value[index] = response.data
      }
      if (currentPost.value?.id === id) {
        currentPost.value = response.data
      }
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to update post'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const deletePost = async (id) => {
    loading.value = true
    error.value = null

    try {
      await axiosInstance.delete(`/posts/${id}`)
      posts.value = posts.value.filter(p => p.id !== id)
      if (currentPost.value?.id === id) {
        currentPost.value = null
      }
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to delete post'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const duplicatePost = async (id) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post(`/posts/${id}/duplicate`)
      posts.value.unshift(response.data)
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to duplicate post'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const reschedulePost = async (id, scheduledAt) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post(`/posts/${id}/reschedule`, { scheduledAt })
      const index = posts.value.findIndex(p => p.id === id)
      if (index !== -1) {
        posts.value[index] = response.data
      }
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to reschedule post'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const bulkImport = async (file) => {
    loading.value = true
    error.value = null

    try {
      const formData = new FormData()
      formData.append('file', file)

      const response = await axiosInstance.post('/posts/bulk', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to import posts'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const fetchCalendar = async (year, month) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.get('/posts/calendar', {
        params: { year, month }
      })
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch calendar'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const setFilters = (newFilters) => {
    filters.value = { ...filters.value, ...newFilters }
    pagination.value.page = 1
  }

  const setPage = (page) => {
    pagination.value.page = page
  }

  const clearCurrentPost = () => {
    currentPost.value = null
  }

  return {
    posts,
    currentPost,
    loading,
    error,
    filters,
    pagination,
    scheduledPosts,
    publishedPosts,
    draftPosts,
    failedPosts,
    postsForDate,
    fetchPosts,
    fetchPost,
    createPost,
    updatePost,
    deletePost,
    duplicatePost,
    reschedulePost,
    bulkImport,
    fetchCalendar,
    setFilters,
    setPage,
    clearCurrentPost
  }
})
