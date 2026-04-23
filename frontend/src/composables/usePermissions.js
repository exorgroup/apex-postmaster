import { computed, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'

// Default limits (fallback if API fails)
const DEFAULT_LIMITS = {
  free: {
    postsPerMonth: 15,
    socialAccounts: 2,
    aiGenerationsPerMonth: 10,
    aiCreditsMonthly: 0,
    includedSeats: 1,
    seatPrice: 0,
    maxSeats: 1,
    bulkImport: false,
    contentRecycling: false,
    streams: 0,
    approvalWorkflows: false,
    analyticsHistoryDays: 7
  },
  creator: {
    postsPerMonth: -1,
    socialAccounts: 3,
    aiGenerationsPerMonth: 50,
    aiCreditsMonthly: 200,
    includedSeats: 1,
    seatPrice: 800,
    maxSeats: 5,
    bulkImport: false,
    contentRecycling: false,
    streams: 0,
    approvalWorkflows: false,
    analyticsHistoryDays: 30
  },
  pro: {
    postsPerMonth: -1,
    socialAccounts: 8,
    aiGenerationsPerMonth: 200,
    aiCreditsMonthly: 500,
    includedSeats: 1,
    seatPrice: 1200,
    maxSeats: 10,
    bulkImport: true,
    contentRecycling: true,
    streams: 5,
    approvalWorkflows: false,
    analyticsHistoryDays: 90
  },
  team: {
    postsPerMonth: -1,
    socialAccounts: 20,
    aiGenerationsPerMonth: 500,
    aiCreditsMonthly: 1000,
    includedSeats: 5,
    seatPrice: 1500,
    maxSeats: 25,
    bulkImport: true,
    contentRecycling: true,
    streams: 20,
    approvalWorkflows: true,
    analyticsHistoryDays: 365
  },
  agency: {
    postsPerMonth: -1,
    socialAccounts: 50,
    aiGenerationsPerMonth: 1000,
    aiCreditsMonthly: 2500,
    includedSeats: 15,
    seatPrice: 1800,
    maxSeats: 50,
    bulkImport: true,
    contentRecycling: true,
    streams: 50,
    approvalWorkflows: true,
    analyticsHistoryDays: -1
  }
}

// Shared state for plan limits (fetched from API)
const planLimits = ref(null)
const limitsLoaded = ref(false)

// Fetch plan limits from API
async function fetchPlanLimits() {
  if (limitsLoaded.value) return

  try {
    const response = await fetch('/api/config/plans')
    const data = await response.json()
    if (data.success) {
      // Transform API response to match our format
      const plans = data.data.plans || data.data
      const transformPlan = (plan) => ({
        postsPerMonth: plan.posts_per_month,
        socialAccounts: plan.social_accounts,
        aiGenerationsPerMonth: plan.ai_generations,
        aiCreditsMonthly: plan.ai_credits_monthly || 0,
        includedSeats: plan.included_seats || 1,
        seatPrice: plan.seat_price || 0,
        maxSeats: plan.max_seats || 1,
        bulkImport: plan.bulk_import,
        contentRecycling: plan.recycling || false,
        streams: plan.streams,
        approvalWorkflows: plan.approval || false,
        analyticsHistoryDays: plan.analytics_days
      })

      planLimits.value = {
        free: transformPlan(plans.free),
        creator: plans.creator ? transformPlan(plans.creator) : DEFAULT_LIMITS.creator,
        pro: transformPlan(plans.pro),
        team: transformPlan(plans.team),
        agency: plans.agency ? transformPlan(plans.agency) : DEFAULT_LIMITS.agency
      }
      limitsLoaded.value = true
    }
  } catch (error) {
    console.error('Failed to fetch plan limits:', error)
    // Use defaults on error
    planLimits.value = DEFAULT_LIMITS
    limitsLoaded.value = true
  }
}

// Initialize fetch on first import
fetchPlanLimits()

export function usePermissions() {
  const auth = useAuthStore()

  const PLAN_LIMITS = computed(() => {
    return planLimits.value || DEFAULT_LIMITS
  })

  const currentLimits = computed(() => {
    const plan = auth.user?.plan || 'free'
    const limits = PLAN_LIMITS.value
    return limits[plan] || limits.free
  })

  const canUse = (feature) => {
    const limits = currentLimits.value
    switch (feature) {
      case 'bulkImport':
        return limits.bulkImport
      case 'contentRecycling':
        return limits.contentRecycling
      case 'streams':
        return limits.streams > 0
      case 'approvalWorkflows':
        return limits.approvalWorkflows
      case 'team':
        return auth.isTeam
      default:
        return true
    }
  }

  const getLimit = (limitKey) => {
    return currentLimits.value[limitKey] || 0
  }

  // Helper to check if a limit is unlimited (-1)
  const isUnlimited = (value) => value === -1

  const remainingPosts = computed(() => {
    const limit = currentLimits.value.postsPerMonth
    if (isUnlimited(limit)) return Infinity
    const used = auth.user?.usage?.postsThisMonth || 0
    return Math.max(0, limit - used)
  })

  const remainingAI = computed(() => {
    const limit = currentLimits.value.aiGenerationsPerMonth
    if (isUnlimited(limit)) return Infinity
    const used = auth.user?.usage?.aiGenerationsThisMonth || 0
    return Math.max(0, limit - used)
  })

  const remainingAccounts = computed(() => {
    const limit = currentLimits.value.socialAccounts
    if (isUnlimited(limit)) return Infinity
    const used = auth.user?.connectedAccountsCount || 0
    return Math.max(0, limit - used)
  })

  const canSchedulePost = computed(() => {
    const limit = currentLimits.value.postsPerMonth
    return isUnlimited(limit) || remainingPosts.value > 0
  })

  const canUseAI = computed(() => {
    const limit = currentLimits.value.aiGenerationsPerMonth
    return isUnlimited(limit) || remainingAI.value > 0
  })

  const canConnectAccount = computed(() => {
    const limit = currentLimits.value.socialAccounts
    return isUnlimited(limit) || remainingAccounts.value > 0
  })

  const usagePercentage = (metric) => {
    const limits = currentLimits.value
    const usage = auth.user?.usage || {}

    switch (metric) {
      case 'posts':
        if (isUnlimited(limits.postsPerMonth)) return 0
        return Math.min(100, ((usage.postsThisMonth || 0) / limits.postsPerMonth) * 100)
      case 'ai':
        if (isUnlimited(limits.aiGenerationsPerMonth)) return 0
        return Math.min(100, ((usage.aiGenerationsThisMonth || 0) / limits.aiGenerationsPerMonth) * 100)
      case 'accounts':
        if (isUnlimited(limits.socialAccounts)) return 0
        return Math.min(100, ((auth.user?.connectedAccountsCount || 0) / limits.socialAccounts) * 100)
      default:
        return 0
    }
  }

  const requiresUpgrade = (feature) => {
    if (canUse(feature)) return null

    const upgradeMap = {
      bulkImport: 'pro',
      contentRecycling: 'pro',
      streams: 'pro',
      approvalWorkflows: 'team',
      team: 'team'
    }

    return upgradeMap[feature] || 'pro'
  }

  // Function to refresh limits from API
  const refreshLimits = async () => {
    limitsLoaded.value = false
    await fetchPlanLimits()
  }

  return {
    PLAN_LIMITS,
    currentLimits,
    canUse,
    getLimit,
    remainingPosts,
    remainingAI,
    remainingAccounts,
    canSchedulePost,
    canUseAI,
    canConnectAccount,
    usagePercentage,
    requiresUpgrade,
    refreshLimits
  }
}

export default usePermissions
