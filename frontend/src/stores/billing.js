import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { axiosInstance } from '@/composables/useApi'

export const useBillingStore = defineStore('billing', () => {
  const subscription = ref(null)
  const usage = ref(null)
  const invoices = ref([])
  const coupons = ref([])
  const loading = ref(false)
  const error = ref(null)

  const currentPlan = computed(() => subscription.value?.plan || 'free')
  const isTrialing = computed(() => subscription.value?.status === 'trialing')

  const daysRemaining = computed(() => {
    if (!subscription.value?.currentPeriodEnd) return 0
    const endDate = new Date(subscription.value.currentPeriodEnd)
    const now = new Date()
    const diff = endDate - now
    return Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)))
  })

  const usagePercentage = (metric) => {
    if (!usage.value) return 0
    const limits = {
      free: { posts: 15, ai: 10, accounts: 2 },
      pro: { posts: Infinity, ai: 100, accounts: 5 },
      team: { posts: Infinity, ai: 500, accounts: 15 }
    }
    const plan = currentPlan.value
    const planLimits = limits[plan] || limits.free

    switch (metric) {
      case 'posts':
        if (planLimits.posts === Infinity) return 0
        return Math.min(100, ((usage.value.postsThisMonth || 0) / planLimits.posts) * 100)
      case 'ai':
        return Math.min(100, ((usage.value.aiGenerationsThisMonth || 0) / planLimits.ai) * 100)
      case 'accounts':
        return Math.min(100, ((usage.value.connectedAccounts || 0) / planLimits.accounts) * 100)
      default:
        return 0
    }
  }

  const fetchStatus = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.get('/billing/status')
      subscription.value = response.data.subscription
      usage.value = response.data.usage
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch billing status'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const createCheckout = async (plan, billingPeriod = 'monthly', couponCode = null, seats = 1) => {
    loading.value = true
    error.value = null

    try {
      const payload = { plan, billing_period: billingPeriod, seats }
      if (couponCode) {
        payload.coupon_code = couponCode
      }
      const response = await axiosInstance.post('/billing/checkout', payload)
      const checkoutData = response.data.data || response.data
      if (checkoutData.checkoutUrl) {
        window.location.href = checkoutData.checkoutUrl
      }
      return { success: true, data: checkoutData }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create checkout'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const openPortal = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/billing/portal')
      if (response.data.portalUrl) {
        window.open(response.data.portalUrl, '_blank')
      }
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to open portal'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const applyCoupon = async (code) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/billing/apply-coupon', { code })
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Invalid coupon code'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const validateCoupon = async (code) => {
    try {
      const response = await axiosInstance.get(`/billing/validate-coupon/${code}`)
      return { success: true, data: response.data }
    } catch (err) {
      return { success: false, error: err.response?.data?.error || 'Invalid coupon' }
    }
  }

  const fetchCoupons = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.get('/admin/coupons')
      coupons.value = response.data
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch coupons'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const createCoupon = async (data) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/admin/coupons', data)
      coupons.value.unshift(response.data)
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create coupon'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const deactivateCoupon = async (id) => {
    loading.value = true
    error.value = null

    try {
      await axiosInstance.delete(`/admin/coupons/${id}`)
      const index = coupons.value.findIndex(c => c.id === id)
      if (index !== -1) {
        coupons.value[index].isActive = false
      }
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to deactivate coupon'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const bulkGenerateCoupons = async (data) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/admin/coupons/bulk', data)
      coupons.value = [...response.data, ...coupons.value]
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to generate coupons'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const syncSubscription = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/billing/sync')
      return { success: true, data: response.data.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to sync subscription'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const syncStripeProducts = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/stripe/sync')
      return { success: true, data: response.data.data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to sync Stripe products'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const getStripeStatus = async () => {
    try {
      const response = await axiosInstance.get('/stripe/status')
      return { success: true, data: response.data.data }
    } catch (err) {
      return { success: false, error: err.response?.data?.message || 'Failed to get Stripe status' }
    }
  }

  const fetchSubscriptionDetails = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.get('/billing/details')
      const data = response.data.data || response.data
      subscription.value = {
        ...subscription.value,
        ...data,
        ...(data.subscription || {})
      }
      return { success: true, data }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch subscription details'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const addSeats = async (seats) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/billing/seats/add', { seats })
      const data = response.data.data || response.data
      return { success: true, data, message: response.data.message }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to add seats'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const removeSeats = async (seats) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/billing/seats/remove', { seats })
      const data = response.data.data || response.data
      return { success: true, data, message: response.data.message }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to remove seats'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const upgradePlan = async (plan) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/billing/upgrade', { plan })
      const data = response.data.data || response.data
      return { success: true, data, message: response.data.message }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to upgrade plan'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const downgradePlan = async (plan) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/billing/downgrade', { plan })
      const data = response.data.data || response.data
      return { success: true, data, message: response.data.message }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to downgrade plan'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const cancelSubscription = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/billing/cancel')
      const data = response.data.data || response.data
      return { success: true, data, message: response.data.message }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to cancel subscription'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const reactivateSubscription = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/billing/reactivate')
      const data = response.data.data || response.data
      return { success: true, data, message: response.data.message }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to reactivate subscription'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  return {
    subscription,
    usage,
    invoices,
    coupons,
    loading,
    error,
    currentPlan,
    isTrialing,
    daysRemaining,
    usagePercentage,
    fetchStatus,
    createCheckout,
    openPortal,
    applyCoupon,
    validateCoupon,
    fetchCoupons,
    createCoupon,
    deactivateCoupon,
    bulkGenerateCoupons,
    syncSubscription,
    syncStripeProducts,
    getStripeStatus,
    fetchSubscriptionDetails,
    addSeats,
    removeSeats,
    upgradePlan,
    downgradePlan,
    cancelSubscription,
    reactivateSubscription
  }
})
