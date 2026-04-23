import axios from 'axios'
import { ref } from 'vue'

const baseURL = import.meta.env.VITE_API_URL || '/api'

const axiosInstance = axios.create({
  baseURL,
  headers: {
    'Content-Type': 'application/json'
  }
})

let authStore = null

const getAuthStore = async () => {
  if (!authStore) {
    const { useAuthStore } = await import('@/stores/auth')
    authStore = useAuthStore()
  }
  return authStore
}

axiosInstance.interceptors.request.use(
  async (config) => {
    const auth = await getAuthStore()
    if (auth.accessToken) {
      config.headers.Authorization = `Bearer ${auth.accessToken}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

axiosInstance.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config
    const auth = await getAuthStore()

    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true

      try {
        const refreshed = await auth.refreshAccessToken()
        if (refreshed) {
          originalRequest.headers.Authorization = `Bearer ${auth.accessToken}`
          return axiosInstance(originalRequest)
        }
      } catch (refreshError) {
        auth.logout()
        return Promise.reject(refreshError)
      }
    }

    return Promise.reject(error)
  }
)

export function useApi() {
  const loading = ref(false)
  const error = ref(null)
  const data = ref(null)

  const request = async (method, url, payload = null, config = {}) => {
    loading.value = true
    error.value = null
    data.value = null

    try {
      const response = await axiosInstance({
        method,
        url,
        data: payload,
        ...config
      })
      data.value = response.data
      return { data: response.data, error: null }
    } catch (err) {
      const errorMessage = err.response?.data?.error || err.response?.data?.message || err.message || 'An error occurred'
      error.value = errorMessage
      return { data: null, error: errorMessage }
    } finally {
      loading.value = false
    }
  }

  const get = (url, config = {}) => request('GET', url, null, config)
  const post = (url, payload, config = {}) => request('POST', url, payload, config)
  const put = (url, payload, config = {}) => request('PUT', url, payload, config)
  const del = (url, config = {}) => request('DELETE', url, null, config)

  return {
    loading,
    error,
    data,
    get,
    post,
    put,
    del,
    axios: axiosInstance
  }
}

export { axiosInstance }
export default useApi
