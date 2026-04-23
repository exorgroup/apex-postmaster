import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { useRouter } from 'vue-router'
import { axiosInstance } from '@/composables/useApi'

export const useAuthStore = defineStore('auth', () => {
  const router = useRouter()

  const user = ref(null)
  const accessToken = ref(localStorage.getItem('accessToken') || null)
  const refreshToken = ref(localStorage.getItem('refreshToken') || null)
  const loading = ref(false)
  const error = ref(null)

  const isAuthenticated = computed(() => !!accessToken.value && !!user.value)
  const isPro = computed(() => ['pro', 'team'].includes(user.value?.plan))
  const isTeam = computed(() => user.value?.plan === 'team')
  const isAdmin = computed(() => user.value?.isAdmin === true)
  const currentPlan = computed(() => user.value?.plan || 'free')

  const setTokens = (access, refresh) => {
    accessToken.value = access
    refreshToken.value = refresh
    if (access) {
      localStorage.setItem('accessToken', access)
    } else {
      localStorage.removeItem('accessToken')
    }
    if (refresh) {
      localStorage.setItem('refreshToken', refresh)
    } else {
      localStorage.removeItem('refreshToken')
    }
  }

  const login = async (email, password) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/auth/login', { email, password })
      const { accessToken: at, refreshToken: rt, user: userData } = response.data.data
      setTokens(at, rt)
      user.value = userData
      router.push(userData.onboardingCompleted ? '/dashboard' : '/onboarding')
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.message || 'Login failed'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const register = async (email, password, name) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/auth/register', { email, password, name })
      const { accessToken: at, refreshToken: rt, user: userData } = response.data.data
      setTokens(at, rt)
      user.value = userData
      router.push('/onboarding')
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.message || 'Registration failed'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const loginWithProvider = async (provider) => {
    try {
      const response = await axiosInstance.get(`/auth/${provider}`)
      if (response.data.data?.url) {
        window.location.href = response.data.data.url
      }
    } catch (err) {
      error.value = 'Failed to initiate OAuth login'
    }
  }

  const handleOAuthCallback = async (token, refresh) => {
    setTokens(token, refresh)
    await fetchProfile()
    router.push(user.value?.onboardingCompleted ? '/dashboard' : '/onboarding')
  }

  const logout = () => {
    if (refreshToken.value) {
      axiosInstance.post('/auth/logout', { refreshToken: refreshToken.value }).catch(() => {})
    }
    user.value = null
    setTokens(null, null)
    router.push('/login')
  }

  const refreshAccessToken = async () => {
    if (!refreshToken.value) return false

    try {
      const response = await axiosInstance.post('/auth/refresh', {
        refreshToken: refreshToken.value
      })
      const { accessToken: at, refreshToken: rt } = response.data.data
      setTokens(at, rt || refreshToken.value)
      return true
    } catch (err) {
      logout()
      return false
    }
  }

  const fetchProfile = async () => {
    if (!accessToken.value) return

    try {
      const response = await axiosInstance.get('/auth/me')
      user.value = response.data.data.user
    } catch (err) {
      if (err.response?.status === 401) {
        const refreshed = await refreshAccessToken()
        if (refreshed) {
          return fetchProfile()
        }
      }
    }
  }

  const updateProfile = async (data) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.put('/auth/profile', data)
      user.value = { ...user.value, ...response.data.data.user }
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.message || 'Update failed'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const forgotPassword = async (email) => {
    loading.value = true
    error.value = null

    try {
      await axiosInstance.post('/auth/forgot-password', { email })
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.message || 'Request failed'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const resetPassword = async (token, password) => {
    loading.value = true
    error.value = null

    try {
      await axiosInstance.post('/auth/reset-password', { token, password })
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.message || 'Reset failed'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const initialize = async () => {
    if (accessToken.value) {
      await fetchProfile()
    }
  }

  return {
    user,
    accessToken,
    refreshToken,
    loading,
    error,
    isAuthenticated,
    isPro,
    isTeam,
    isAdmin,
    currentPlan,
    login,
    register,
    loginWithProvider,
    handleOAuthCallback,
    logout,
    refreshAccessToken,
    fetchProfile,
    updateProfile,
    forgotPassword,
    resetPassword,
    initialize,
    setTokens
  }
})
