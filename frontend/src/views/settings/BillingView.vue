<script setup>
import { ref, computed, onMounted } from 'vue'
import { useBillingStore } from '@/stores/billing'
import { useAuthStore } from '@/stores/auth'
import { usePermissions } from '@/composables/usePermissions'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import Button from 'primevue/button'
import ProgressBar from 'primevue/progressbar'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import RadioButton from 'primevue/radiobutton'
import { useToast } from 'primevue/usetoast'

const billing = useBillingStore()
const auth = useAuthStore()
const { currentLimits, PLAN_LIMITS, usagePercentage } = usePermissions()
const toast = useToast()

const couponCode = ref('')
const validatingCoupon = ref(false)
const billingPeriod = ref('monthly')
const planData = ref(null)
const selectedPlanForCheckout = ref(null)
const seatCount = ref(1)

// Plan order for display
const planOrder = ['free', 'creator', 'pro', 'team', 'agency']

// Plan display info
const planInfo = {
  free: { name: 'Free', description: 'Perfect for getting started', cta: 'Current Plan' },
  creator: { name: 'Creator', description: 'For individual creators', cta: 'Upgrade' },
  pro: { name: 'Pro', description: 'For growing businesses', cta: 'Upgrade', highlighted: true },
  team: { name: 'Team', description: 'For teams & agencies', cta: 'Upgrade' },
  agency: { name: 'Agency', description: 'For large agencies', cta: 'Upgrade' }
}

// Fetch plan pricing from API
onMounted(async () => {
  try {
    const response = await fetch('/api/config/plans')
    const data = await response.json()
    if (data.success) {
      planData.value = data.data
    }
  } catch (error) {
    console.error('Failed to fetch plan data:', error)
  }
  billing.fetchStatus()
})

// Get base price for a plan based on billing period
const getBasePrice = (planKey) => {
  if (!planData.value?.plans?.[planKey]) return 0
  const plan = planData.value.plans[planKey]
  if (billingPeriod.value === 'annual' && plan.price_annual > 0) {
    return Math.round(plan.price_annual / 12 / 100)
  }
  return Math.round(plan.price_monthly / 100)
}

// Get price for display
const getPrice = (planKey) => {
  return getBasePrice(planKey)
}

// Get seat price for a plan
const getSeatPrice = (planKey) => {
  if (!planData.value?.plans?.[planKey]) return 0
  const plan = planData.value.plans[planKey]
  if (billingPeriod.value === 'annual') {
    // Apply ~20% discount for annual seat pricing
    return Math.round(plan.seat_price * 0.8 / 100)
  }
  return Math.round(plan.seat_price / 100)
}

// Get included seats for a plan
const getIncludedSeats = (planKey) => {
  if (!planData.value?.plans?.[planKey]) return 1
  return planData.value.plans[planKey].included_seats || 1
}

// Get max seats for a plan
const getMaxSeats = (planKey) => {
  if (!planData.value?.plans?.[planKey]) return 1
  return planData.value.plans[planKey].max_seats || 1
}

// Calculate additional seats beyond included
const additionalSeats = computed(() => {
  if (!selectedPlanForCheckout.value) return 0
  const included = getIncludedSeats(selectedPlanForCheckout.value)
  return Math.max(0, seatCount.value - included)
})

// Get total price based on base price + additional seats
const getTotalPrice = computed(() => {
  if (!selectedPlanForCheckout.value) return 0
  const basePrice = getBasePrice(selectedPlanForCheckout.value)
  const seatPrice = getSeatPrice(selectedPlanForCheckout.value)
  const extraSeats = additionalSeats.value
  return basePrice + (seatPrice * extraSeats)
})

// Get annual total for display
const getAnnualTotal = computed(() => {
  if (!selectedPlanForCheckout.value) return 0
  return getTotalPrice.value * 12
})

// Get annual savings percentage
const getAnnualSavings = (planKey) => {
  if (!planData.value?.plans?.[planKey]) return 0
  const plan = planData.value.plans[planKey]
  if (plan.price_monthly === 0) return 0
  const monthlyTotal = plan.price_monthly * 12
  return Math.round((1 - plan.price_annual / monthlyTotal) * 100)
}

// Build features list for a plan (detailed version for checkout)
const buildFeaturesDetailed = (planKey) => {
  const limits = PLAN_LIMITS.value[planKey]
  if (!limits) return []

  const features = []

  // Posts
  if (limits.postsPerMonth === -1) {
    features.push({ label: 'Posts', value: 'Unlimited' })
  } else if (limits.postsPerMonth > 0) {
    features.push({ label: 'Posts per month', value: limits.postsPerMonth })
  }

  // Social accounts
  if (limits.socialAccounts > 0) {
    features.push({ label: 'Social accounts', value: limits.socialAccounts })
  }

  // AI generations
  if (limits.aiGenerationsPerMonth === -1) {
    features.push({ label: 'AI generations', value: 'Unlimited' })
  } else if (limits.aiGenerationsPerMonth > 0) {
    features.push({ label: 'AI generations', value: limits.aiGenerationsPerMonth })
  }

  // AI credits
  if (limits.aiCreditsMonthly > 0) {
    features.push({ label: 'AI credits/month', value: limits.aiCreditsMonthly })
  }

  // Team members
  features.push({ label: 'Team members included', value: limits.teamMembers || 1 })

  // Streams
  if (limits.streams > 0) {
    features.push({ label: 'Social streams', value: limits.streams })
  }

  // Analytics
  if (limits.analyticsHistoryDays === -1) {
    features.push({ label: 'Analytics history', value: 'Unlimited' })
  } else if (limits.analyticsHistoryDays >= 365) {
    features.push({ label: 'Analytics history', value: '1 year' })
  } else if (limits.analyticsHistoryDays >= 30) {
    features.push({ label: 'Analytics history', value: `${Math.round(limits.analyticsHistoryDays / 30)} months` })
  } else if (limits.analyticsHistoryDays > 0) {
    features.push({ label: 'Analytics history', value: `${limits.analyticsHistoryDays} days` })
  }

  // Boolean features
  if (limits.bulkImport) features.push({ label: 'Bulk import', value: 'Yes', boolean: true })
  if (limits.contentRecycling) features.push({ label: 'Content recycling', value: 'Yes', boolean: true })
  if (limits.approvalWorkflows) features.push({ label: 'Approval workflows', value: 'Yes', boolean: true })

  return features
}

// Build features list for a plan (short version for cards)
const buildFeatures = (planKey) => {
  const limits = PLAN_LIMITS.value[planKey]
  if (!limits) return []

  const features = []

  // Posts
  if (limits.postsPerMonth === -1) {
    features.push('Unlimited posts')
  } else if (limits.postsPerMonth > 0) {
    features.push(`${limits.postsPerMonth} posts/month`)
  }

  // Social accounts
  if (limits.socialAccounts > 0) {
    features.push(`${limits.socialAccounts} social accounts`)
  }

  // AI generations
  if (limits.aiGenerationsPerMonth === -1) {
    features.push('Unlimited AI generations')
  } else if (limits.aiGenerationsPerMonth > 0) {
    features.push(`${limits.aiGenerationsPerMonth} AI generations`)
  }

  // AI credits
  if (limits.aiCreditsMonthly > 0) {
    features.push(`${limits.aiCreditsMonthly} AI credits/month`)
  }

  // Team members
  if (limits.teamMembers > 1) {
    features.push(`${limits.teamMembers} team members`)
  }

  // Features
  if (limits.bulkImport) features.push('Bulk import')
  if (limits.contentRecycling) features.push('Content recycling')
  if (limits.approvalWorkflows) features.push('Approval workflows')
  if (limits.streams > 0) features.push(`${limits.streams} social streams`)

  return features
}

// Format limit display
const formatLimit = (value) => {
  if (value === -1) return '∞'
  return value
}

// Get upgradeable plans (plans higher than current)
const upgradeablePlans = computed(() => {
  const currentPlan = auth.currentPlan || 'free'
  const currentIndex = planOrder.indexOf(currentPlan)
  return planOrder.filter((plan, index) => index > currentIndex)
})

// Check if a plan is the current plan
const isCurrentPlan = (planKey) => {
  return (auth.currentPlan || 'free') === planKey
}

// Check if plan is a downgrade
const isDowngrade = (planKey) => {
  const currentIndex = planOrder.indexOf(auth.currentPlan || 'free')
  const planIndex = planOrder.indexOf(planKey)
  return planIndex < currentIndex
}

// Increment/decrement seats
const incrementSeats = () => {
  if (!selectedPlanForCheckout.value) return
  const max = getMaxSeats(selectedPlanForCheckout.value)
  if (seatCount.value < max) {
    seatCount.value++
  }
}

const decrementSeats = () => {
  if (seatCount.value > 1) {
    seatCount.value--
  }
}

const handleUpgrade = async (planId) => {
  selectedPlanForCheckout.value = planId
  // Set initial seat count to included seats
  seatCount.value = getIncludedSeats(planId)
}

const checkoutLoading = ref(false)

const handleCheckout = async () => {
  if (!selectedPlanForCheckout.value) return
  checkoutLoading.value = true
  const period = billingPeriod.value === 'annual' ? 'annual' : 'monthly'
  const result = await billing.createCheckout(selectedPlanForCheckout.value, period, couponCode.value || null, seatCount.value)

  if (!result.success) {
    checkoutLoading.value = false
    toast.add({
      severity: 'error',
      summary: 'Checkout Failed',
      detail: result.error || 'Failed to create checkout session',
      life: 5000
    })
    return
  }
  // If successful, we'll be redirected - don't reset the form
}

const cancelCheckout = () => {
  selectedPlanForCheckout.value = null
  couponCode.value = ''
  seatCount.value = 1
}

const handleManageSubscription = async () => {
  showSubscriptionModal.value = true
  await fetchSubscriptionDetails()
}

// Subscription Management
const showSubscriptionModal = ref(false)
const subscriptionDetails = ref(null)
const managementAction = ref(null) // 'cancel', 'addSeats', 'removeSeats', 'changePlan'
const seatsToChange = ref(1)
const targetPlan = ref(null)
const actionLoading = ref(false)

const fetchSubscriptionDetails = async () => {
  const result = await billing.fetchSubscriptionDetails()
  if (result.success) {
    subscriptionDetails.value = result.data
  }
}

const closeSubscriptionModal = () => {
  showSubscriptionModal.value = false
  managementAction.value = null
  seatsToChange.value = 1
  targetPlan.value = null
}

const handleAddSeats = async () => {
  if (seatsToChange.value < 1) return
  actionLoading.value = true
  const result = await billing.addSeats(seatsToChange.value)
  actionLoading.value = false

  if (result.success) {
    toast.add({
      severity: 'success',
      summary: 'Seats Added',
      detail: result.message || `Added ${seatsToChange.value} seat(s)`,
      life: 5000
    })
    await fetchSubscriptionDetails()
    await auth.fetchProfile()
    managementAction.value = null
    seatsToChange.value = 1
  } else {
    toast.add({
      severity: 'error',
      summary: 'Failed',
      detail: result.error,
      life: 5000
    })
  }
}

const handleRemoveSeats = async () => {
  if (seatsToChange.value < 1) return
  actionLoading.value = true
  const result = await billing.removeSeats(seatsToChange.value)
  actionLoading.value = false

  if (result.success) {
    toast.add({
      severity: 'success',
      summary: 'Seat Reduction Scheduled',
      detail: result.message,
      life: 5000
    })
    await fetchSubscriptionDetails()
    managementAction.value = null
    seatsToChange.value = 1
  } else {
    toast.add({
      severity: 'error',
      summary: 'Failed',
      detail: result.error,
      life: 5000
    })
  }
}

const handlePlanUpgrade = async () => {
  if (!targetPlan.value) return
  actionLoading.value = true
  const result = await billing.upgradePlan(targetPlan.value)
  actionLoading.value = false

  if (result.success) {
    toast.add({
      severity: 'success',
      summary: 'Plan Upgraded',
      detail: result.message,
      life: 5000
    })
    await fetchSubscriptionDetails()
    await auth.fetchProfile()
    await billing.fetchStatus()
    managementAction.value = null
    targetPlan.value = null
  } else {
    toast.add({
      severity: 'error',
      summary: 'Upgrade Failed',
      detail: result.error,
      life: 5000
    })
  }
}

const handlePlanDowngrade = async () => {
  if (!targetPlan.value) return
  actionLoading.value = true
  const result = await billing.downgradePlan(targetPlan.value)
  actionLoading.value = false

  if (result.success) {
    toast.add({
      severity: 'success',
      summary: 'Downgrade Scheduled',
      detail: result.message,
      life: 5000
    })
    await fetchSubscriptionDetails()
    managementAction.value = null
    targetPlan.value = null
  } else {
    toast.add({
      severity: 'error',
      summary: 'Downgrade Failed',
      detail: result.error,
      life: 5000
    })
  }
}

const handleCancelSubscription = async () => {
  actionLoading.value = true
  const result = await billing.cancelSubscription()
  actionLoading.value = false

  if (result.success) {
    toast.add({
      severity: 'info',
      summary: 'Subscription Cancelled',
      detail: result.message,
      life: 7000
    })
    await fetchSubscriptionDetails()
    managementAction.value = null
  } else {
    toast.add({
      severity: 'error',
      summary: 'Cancellation Failed',
      detail: result.error,
      life: 5000
    })
  }
}

const handleReactivateSubscription = async () => {
  actionLoading.value = true
  const result = await billing.reactivateSubscription()
  actionLoading.value = false

  if (result.success) {
    toast.add({
      severity: 'success',
      summary: 'Subscription Reactivated',
      detail: result.message,
      life: 5000
    })
    await fetchSubscriptionDetails()
    managementAction.value = null
  } else {
    toast.add({
      severity: 'error',
      summary: 'Reactivation Failed',
      detail: result.error,
      life: 5000
    })
  }
}

// Get available plans for upgrade/downgrade
const availablePlansForChange = computed(() => {
  const currentPlan = auth.currentPlan || 'free'
  const currentIndex = planOrder.indexOf(currentPlan)
  return {
    upgrades: planOrder.filter((plan, index) => index > currentIndex && plan !== 'free'),
    downgrades: planOrder.filter((plan, index) => index < currentIndex)
  }
})

// Calculate seat limits for current plan
const currentPlanSeatLimits = computed(() => {
  if (!subscriptionDetails.value) return { max: 1, current: 1, active: 1, included: 1 }
  return {
    max: subscriptionDetails.value.maxSeats || 1,
    current: subscriptionDetails.value.seats || 1,
    active: subscriptionDetails.value.activeSeats || 1,
    included: subscriptionDetails.value.includedSeats || 1
  }
})

// Format date for display
const formatDate = (dateStr) => {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
}

// Sync subscription from Stripe
const syncingSubscription = ref(false)

const handleSyncSubscription = async () => {
  syncingSubscription.value = true
  try {
    const result = await billing.syncSubscription()
    if (result.success) {
      toast.add({
        severity: 'success',
        summary: 'Subscription Synced',
        detail: `Your plan is now: ${result.data.plan}`,
        life: 5000
      })
      // Refresh user data
      await auth.fetchProfile()
      // Refresh billing status
      await billing.fetchStatus()
    } else {
      toast.add({
        severity: 'error',
        summary: 'Sync Failed',
        detail: result.error,
        life: 5000
      })
    }
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Sync Failed',
      detail: err.message,
      life: 5000
    })
  } finally {
    syncingSubscription.value = false
  }
}

// Admin: Stripe sync
const syncingStripe = ref(false)
const stripeStatus = ref(null)

const handleSyncStripe = async () => {
  syncingStripe.value = true
  try {
    const result = await billing.syncStripeProducts()
    if (result.success) {
      toast.add({
        severity: 'success',
        summary: 'Stripe Sync Complete',
        detail: `Products and prices synced to Stripe`,
        life: 5000
      })
      stripeStatus.value = result.data
    } else {
      toast.add({
        severity: 'error',
        summary: 'Sync Failed',
        detail: result.error,
        life: 5000
      })
    }
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Sync Failed',
      detail: err.message,
      life: 5000
    })
  } finally {
    syncingStripe.value = false
  }
}

const fetchStripeStatus = async () => {
  const result = await billing.getStripeStatus()
  if (result.success) {
    stripeStatus.value = result.data
  }
}

// Fetch stripe status on mount for admins
onMounted(async () => {
  if (auth.isAdmin) {
    fetchStripeStatus()
  }
})
</script>

<template>
  <DashboardLayout>
    <div class="billingView apFadeIn">
      <div class="apPageHeader">
        <h1 class="apPageTitle">Billing</h1>
      </div>

      <div class="row g-4">
        <!-- Current Plan -->
        <div class="col-12 col-lg-7">
          <div class="apCard currentPlanCard">
            <div class="planHeader">
              <div>
                <h3>Current Plan</h3>
                <div class="planName">
                  <span class="planTitle">{{ planInfo[auth.currentPlan || 'free']?.name || 'Free' }}</span>
                  <Tag v-if="billing.isTrialing" value="Trial" severity="info" />
                </div>
              </div>
              <div class="planActions">
                <Button v-if="auth.currentPlan !== 'free'" label="Manage Subscription" outlined @click="handleManageSubscription" />
                <Button
                  :label="syncingSubscription ? 'Syncing...' : 'Sync from Stripe'"
                  :icon="syncingSubscription ? 'pi pi-spin pi-spinner' : 'pi pi-refresh'"
                  :disabled="syncingSubscription"
                  text
                  size="small"
                  @click="handleSyncSubscription"
                />
              </div>
            </div>

            <div class="usageSection">
              <h4>Usage This Month</h4>
              <div class="usageGrid">
                <div class="usageItem">
                  <div class="usageHeader">
                    <span>Posts</span>
                    <span class="usageValue">{{ billing.usage?.postsThisMonth || 0 }} / {{ formatLimit(currentLimits.postsPerMonth) }}</span>
                  </div>
                  <ProgressBar :value="usagePercentage('posts')" :showValue="false" />
                </div>
                <div class="usageItem">
                  <div class="usageHeader">
                    <span>AI Generations</span>
                    <span class="usageValue">{{ billing.usage?.aiGenerationsThisMonth || 0 }} / {{ formatLimit(currentLimits.aiGenerationsPerMonth) }}</span>
                  </div>
                  <ProgressBar :value="usagePercentage('ai')" :showValue="false" />
                </div>
                <div class="usageItem">
                  <div class="usageHeader">
                    <span>Connected Accounts</span>
                    <span class="usageValue">{{ billing.usage?.connectedAccounts || 0 }} / {{ formatLimit(currentLimits.socialAccounts) }}</span>
                  </div>
                  <ProgressBar :value="usagePercentage('accounts')" :showValue="false" />
                </div>
              </div>
            </div>

            <!-- Plan Features -->
            <div class="currentFeatures">
              <h4>Your Plan Includes</h4>
              <ul class="featureList">
                <li v-for="feature in buildFeatures(auth.currentPlan || 'free')" :key="feature">
                  <i class="pi pi-check"></i>
                  {{ feature }}
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Upgrade Options -->
        <div class="col-12 col-lg-5">
          <div class="apCard upgradeCard">
            <!-- Checkout Mode -->
            <template v-if="selectedPlanForCheckout">
              <div class="checkoutHeader">
                <h3>Complete Upgrade</h3>
                <Button icon="pi pi-times" text rounded @click="cancelCheckout" />
              </div>

              <!-- Plan Summary -->
              <div class="checkoutPlan">
                <div class="checkoutPlanName">{{ planInfo[selectedPlanForCheckout]?.name }} Plan</div>
                <div class="checkoutPlanPrice">
                  €{{ getPrice(selectedPlanForCheckout) }}<span class="perSeat">/month</span>
                </div>
                <p class="includedSeatsNote">
                  Includes {{ getIncludedSeats(selectedPlanForCheckout) }} {{ getIncludedSeats(selectedPlanForCheckout) === 1 ? 'seat' : 'seats' }}
                  <template v-if="getMaxSeats(selectedPlanForCheckout) > getIncludedSeats(selectedPlanForCheckout)">
                    · +€{{ getSeatPrice(selectedPlanForCheckout) }}/extra seat
                  </template>
                </p>
                <p v-if="billingPeriod === 'annual'" class="billingNote">Billed annually (save {{ getAnnualSavings(selectedPlanForCheckout) }}%)</p>
              </div>

              <!-- Package Details -->
              <div class="packageDetails">
                <h4>Package Includes</h4>
                <ul class="detailsList">
                  <li v-for="feature in buildFeaturesDetailed(selectedPlanForCheckout)" :key="feature.label">
                    <span class="detailLabel">{{ feature.label }}</span>
                    <span class="detailValue" :class="{ boolean: feature.boolean }">
                      <i v-if="feature.boolean" class="pi pi-check"></i>
                      <template v-else>{{ feature.value }}</template>
                    </span>
                  </li>
                </ul>
              </div>

              <!-- Seat Selection -->
              <div v-if="getMaxSeats(selectedPlanForCheckout) > 1" class="seatSelection">
                <h4>Number of Seats</h4>
                <div class="seatControls">
                  <Button
                    icon="pi pi-minus"
                    outlined
                    rounded
                    :disabled="seatCount <= 1"
                    @click="decrementSeats"
                  />
                  <InputNumber
                    v-model="seatCount"
                    :min="1"
                    :max="getMaxSeats(selectedPlanForCheckout)"
                    inputClass="seatInput"
                  />
                  <Button
                    icon="pi pi-plus"
                    outlined
                    rounded
                    :disabled="seatCount >= getMaxSeats(selectedPlanForCheckout)"
                    @click="incrementSeats"
                  />
                </div>
                <p class="seatNote">
                  {{ getIncludedSeats(selectedPlanForCheckout) }} {{ getIncludedSeats(selectedPlanForCheckout) === 1 ? 'seat' : 'seats' }} included
                  <template v-if="additionalSeats > 0">
                    · {{ additionalSeats }} additional @ €{{ getSeatPrice(selectedPlanForCheckout) }}/mo each
                  </template>
                </p>
                <p class="seatLimit">Maximum {{ getMaxSeats(selectedPlanForCheckout) }} seats</p>
              </div>

              <!-- Coupon -->
              <div class="couponSection">
                <label>Have a coupon code?</label>
                <div class="couponInput">
                  <InputText v-model="couponCode" placeholder="Enter coupon code" />
                </div>
              </div>

              <!-- Price Summary -->
              <div class="priceSummary">
                <div class="priceRow">
                  <span>{{ planInfo[selectedPlanForCheckout]?.name }} Plan (base)</span>
                  <span>€{{ getPrice(selectedPlanForCheckout) }}/mo</span>
                </div>
                <div v-if="additionalSeats > 0" class="priceRow">
                  <span>{{ additionalSeats }} additional {{ additionalSeats === 1 ? 'seat' : 'seats' }}</span>
                  <span>€{{ getSeatPrice(selectedPlanForCheckout) * additionalSeats }}/mo</span>
                </div>
                <div v-if="billingPeriod === 'annual'" class="priceRow subtotal">
                  <span>Monthly total</span>
                  <span>€{{ getTotalPrice }}</span>
                </div>
                <div v-if="billingPeriod === 'annual'" class="priceRow subtotal">
                  <span>Annual total (12 months)</span>
                  <span>€{{ getAnnualTotal }}</span>
                </div>
                <div class="priceRow total">
                  <span>Due today</span>
                  <span class="totalAmount">€{{ billingPeriod === 'annual' ? getAnnualTotal : getTotalPrice }}</span>
                </div>
              </div>

              <Button
                :label="checkoutLoading ? 'Redirecting to Stripe...' : 'Proceed to Payment'"
                :icon="checkoutLoading ? 'pi pi-spin pi-spinner' : null"
                :disabled="checkoutLoading"
                class="w-full checkoutBtn"
                @click="handleCheckout"
              />
              <p class="checkoutNote">You'll be redirected to Stripe for secure payment</p>
            </template>

            <!-- Plan Selection Mode -->
            <template v-else>
              <div class="upgradeHeader">
                <h3>Upgrade Your Plan</h3>
              </div>

              <!-- Billing Toggle -->
              <div class="billingToggle">
                <span :class="{ active: billingPeriod === 'monthly' }">Monthly</span>
                <button
                  type="button"
                  class="toggleSwitch"
                  :class="{ annual: billingPeriod === 'annual' }"
                  @click="billingPeriod = billingPeriod === 'monthly' ? 'annual' : 'monthly'"
                >
                  <span class="toggleKnob"></span>
                </button>
                <span :class="{ active: billingPeriod === 'annual' }">
                  Annual
                </span>
              </div>

              <div class="planOptions">
                <div
                  v-for="planKey in upgradeablePlans"
                  :key="planKey"
                  class="planOption"
                  :class="{ highlighted: planInfo[planKey]?.highlighted }"
                >
                  <div v-if="planInfo[planKey]?.highlighted" class="popularTag">Popular</div>
                  <div class="planOptionHeader">
                    <span class="planOptionName">{{ planInfo[planKey]?.name }}</span>
                    <span class="planOptionPrice">
                      €{{ getPrice(planKey) }}
                      <span class="perMonth">/mo</span>
                    </span>
                  </div>
                  <p v-if="billingPeriod === 'annual' && getAnnualSavings(planKey) > 0" class="annualNote">
                    Save {{ getAnnualSavings(planKey) }}% with annual billing
                  </p>
                  <ul class="planOptionFeatures">
                    <li v-for="feature in buildFeatures(planKey).slice(0, 4)" :key="feature">
                      <i class="pi pi-check"></i>
                      {{ feature }}
                    </li>
                    <li v-if="buildFeatures(planKey).length > 4" class="moreFeatures">
                      +{{ buildFeatures(planKey).length - 4 }} more features
                    </li>
                  </ul>
                  <Button
                    :label="`Upgrade to ${planInfo[planKey]?.name}`"
                    class="w-full"
                    :outlined="!planInfo[planKey]?.highlighted"
                    @click="handleUpgrade(planKey)"
                  />
                </div>

                <!-- No upgrades available -->
                <div v-if="upgradeablePlans.length === 0" class="noUpgrades">
                  <i class="pi pi-check-circle"></i>
                  <p>You're on our top plan!</p>
                  <span>Enjoy all premium features.</span>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>

      <!-- Subscription Management Modal -->
      <Dialog
        v-model:visible="showSubscriptionModal"
        :header="managementAction ? '' : 'Manage Subscription'"
        modal
        :style="{ width: '500px' }"
        :closable="!actionLoading"
        @hide="closeSubscriptionModal"
      >
        <!-- Main Menu -->
        <template v-if="!managementAction && subscriptionDetails">
          <div class="subscriptionInfo">
            <div class="subInfoRow">
              <span class="subInfoLabel">Current Plan</span>
              <span class="subInfoValue planBadge">{{ planInfo[subscriptionDetails.plan]?.name || subscriptionDetails.plan }}</span>
            </div>
            <div class="subInfoRow">
              <span class="subInfoLabel">Status</span>
              <Tag
                :value="subscriptionDetails.subscription?.cancelAtPeriodEnd ? 'Cancelling' : (subscriptionDetails.subscription?.status || 'Active')"
                :severity="subscriptionDetails.subscription?.cancelAtPeriodEnd ? 'warning' : 'success'"
              />
            </div>
            <div class="subInfoRow">
              <span class="subInfoLabel">Seats</span>
              <span class="subInfoValue">{{ currentPlanSeatLimits.active }} of {{ currentPlanSeatLimits.current }} in use</span>
            </div>
            <div v-if="subscriptionDetails.subscription?.currentPeriodEnd" class="subInfoRow">
              <span class="subInfoLabel">{{ subscriptionDetails.subscription?.cancelAtPeriodEnd ? 'Access Until' : 'Next Billing' }}</span>
              <span class="subInfoValue">{{ formatDate(subscriptionDetails.subscription.currentPeriodEnd) }}</span>
            </div>
          </div>

          <!-- Cancelled subscription notice -->
          <div v-if="subscriptionDetails.subscription?.cancelAtPeriodEnd" class="cancelledNotice">
            <i class="pi pi-info-circle"></i>
            <div>
              <p>Your subscription is cancelled and will end on {{ formatDate(subscriptionDetails.subscription.currentPeriodEnd) }}.</p>
              <Button label="Reactivate Subscription" size="small" @click="handleReactivateSubscription" :loading="actionLoading" />
            </div>
          </div>

          <!-- Management Actions -->
          <div v-else class="managementActions">
            <button class="actionBtn" @click="managementAction = 'addSeats'" v-if="currentPlanSeatLimits.current < currentPlanSeatLimits.max">
              <i class="pi pi-user-plus"></i>
              <div>
                <span class="actionTitle">Add Seats</span>
                <span class="actionDesc">Add team members immediately (prorated)</span>
              </div>
              <i class="pi pi-chevron-right"></i>
            </button>

            <button class="actionBtn" @click="managementAction = 'removeSeats'" v-if="currentPlanSeatLimits.current > 1">
              <i class="pi pi-user-minus"></i>
              <div>
                <span class="actionTitle">Remove Seats</span>
                <span class="actionDesc">Takes effect at end of billing period</span>
              </div>
              <i class="pi pi-chevron-right"></i>
            </button>

            <button class="actionBtn" @click="managementAction = 'upgrade'" v-if="availablePlansForChange.upgrades.length > 0">
              <i class="pi pi-arrow-up"></i>
              <div>
                <span class="actionTitle">Upgrade Plan</span>
                <span class="actionDesc">Immediate upgrade with prorated charge</span>
              </div>
              <i class="pi pi-chevron-right"></i>
            </button>

            <button class="actionBtn" @click="managementAction = 'downgrade'" v-if="availablePlansForChange.downgrades.length > 0">
              <i class="pi pi-arrow-down"></i>
              <div>
                <span class="actionTitle">Downgrade Plan</span>
                <span class="actionDesc">Takes effect at end of billing period</span>
              </div>
              <i class="pi pi-chevron-right"></i>
            </button>

            <button class="actionBtn danger" @click="managementAction = 'cancel'">
              <i class="pi pi-times-circle"></i>
              <div>
                <span class="actionTitle">Cancel Subscription</span>
                <span class="actionDesc">Access continues until period ends</span>
              </div>
              <i class="pi pi-chevron-right"></i>
            </button>
          </div>
        </template>

        <!-- Add Seats -->
        <template v-else-if="managementAction === 'addSeats'">
          <div class="actionHeader">
            <Button icon="pi pi-arrow-left" text rounded @click="managementAction = null" :disabled="actionLoading" />
            <h3>Add Seats</h3>
          </div>

          <div class="actionContent">
            <p>Add additional team member seats. You'll be charged a prorated amount for the remainder of your billing period.</p>

            <div class="seatControls">
              <Button icon="pi pi-minus" outlined rounded :disabled="seatsToChange <= 1 || actionLoading" @click="seatsToChange--" />
              <InputNumber v-model="seatsToChange" :min="1" :max="currentPlanSeatLimits.max - currentPlanSeatLimits.current" inputClass="seatInput" :disabled="actionLoading" />
              <Button icon="pi pi-plus" outlined rounded :disabled="seatsToChange >= (currentPlanSeatLimits.max - currentPlanSeatLimits.current) || actionLoading" @click="seatsToChange++" />
            </div>

            <p class="seatSummary">
              Current: {{ currentPlanSeatLimits.current }} seats → New: {{ currentPlanSeatLimits.current + seatsToChange }} seats
            </p>
            <p class="maxNote">Maximum {{ currentPlanSeatLimits.max }} seats for your plan</p>

            <Button label="Add Seats" class="w-full mt-3" @click="handleAddSeats" :loading="actionLoading" />
          </div>
        </template>

        <!-- Remove Seats -->
        <template v-else-if="managementAction === 'removeSeats'">
          <div class="actionHeader">
            <Button icon="pi pi-arrow-left" text rounded @click="managementAction = null" :disabled="actionLoading" />
            <h3>Remove Seats</h3>
          </div>

          <div class="actionContent">
            <p>Seat reductions take effect at the end of your current billing period. You must remove team members before reducing seats below your active count.</p>

            <div class="seatControls">
              <Button icon="pi pi-minus" outlined rounded :disabled="seatsToChange <= 1 || actionLoading" @click="seatsToChange--" />
              <InputNumber v-model="seatsToChange" :min="1" :max="currentPlanSeatLimits.current - currentPlanSeatLimits.active" inputClass="seatInput" :disabled="actionLoading" />
              <Button icon="pi pi-plus" outlined rounded :disabled="seatsToChange >= (currentPlanSeatLimits.current - currentPlanSeatLimits.active) || actionLoading" @click="seatsToChange++" />
            </div>

            <p class="seatSummary">
              Current: {{ currentPlanSeatLimits.current }} seats → New: {{ currentPlanSeatLimits.current - seatsToChange }} seats
            </p>
            <p class="activeNote" v-if="currentPlanSeatLimits.active > 1">
              <i class="pi pi-info-circle"></i>
              You have {{ currentPlanSeatLimits.active }} active seats (including yourself). Remove team members first if needed.
            </p>

            <Button label="Schedule Seat Reduction" class="w-full mt-3" @click="handleRemoveSeats" :loading="actionLoading" :disabled="currentPlanSeatLimits.current - seatsToChange < currentPlanSeatLimits.active" />
          </div>
        </template>

        <!-- Upgrade Plan -->
        <template v-else-if="managementAction === 'upgrade'">
          <div class="actionHeader">
            <Button icon="pi pi-arrow-left" text rounded @click="managementAction = null" :disabled="actionLoading" />
            <h3>Upgrade Plan</h3>
          </div>

          <div class="actionContent">
            <p>Upgrade takes effect immediately. You'll be charged a prorated amount for the price difference.</p>

            <div class="planSelection">
              <div
                v-for="planKey in availablePlansForChange.upgrades"
                :key="planKey"
                class="planChoice"
                :class="{ selected: targetPlan === planKey }"
                @click="targetPlan = planKey"
              >
                <RadioButton v-model="targetPlan" :value="planKey" :inputId="planKey" :disabled="actionLoading" />
                <label :for="planKey">
                  <span class="planChoiceName">{{ planInfo[planKey]?.name }}</span>
                  <span class="planChoicePrice">€{{ getPrice(planKey) }}/mo</span>
                </label>
              </div>
            </div>

            <Button label="Upgrade Now" class="w-full mt-3" @click="handlePlanUpgrade" :loading="actionLoading" :disabled="!targetPlan" />
          </div>
        </template>

        <!-- Downgrade Plan -->
        <template v-else-if="managementAction === 'downgrade'">
          <div class="actionHeader">
            <Button icon="pi pi-arrow-left" text rounded @click="managementAction = null" :disabled="actionLoading" />
            <h3>Downgrade Plan</h3>
          </div>

          <div class="actionContent">
            <p>Downgrade takes effect at the end of your current billing period. You'll continue to have access to your current plan until then.</p>

            <div class="planSelection">
              <div
                v-for="planKey in availablePlansForChange.downgrades"
                :key="planKey"
                class="planChoice"
                :class="{ selected: targetPlan === planKey }"
                @click="targetPlan = planKey"
              >
                <RadioButton v-model="targetPlan" :value="planKey" :inputId="'down_' + planKey" :disabled="actionLoading" />
                <label :for="'down_' + planKey">
                  <span class="planChoiceName">{{ planInfo[planKey]?.name }}</span>
                  <span class="planChoicePrice">{{ planKey === 'free' ? 'Free' : `€${getPrice(planKey)}/mo` }}</span>
                </label>
              </div>
            </div>

            <Button label="Schedule Downgrade" class="w-full mt-3" severity="warning" @click="handlePlanDowngrade" :loading="actionLoading" :disabled="!targetPlan" />
          </div>
        </template>

        <!-- Cancel Subscription -->
        <template v-else-if="managementAction === 'cancel'">
          <div class="actionHeader">
            <Button icon="pi pi-arrow-left" text rounded @click="managementAction = null" :disabled="actionLoading" />
            <h3>Cancel Subscription</h3>
          </div>

          <div class="actionContent">
            <div class="cancelWarning">
              <i class="pi pi-exclamation-triangle"></i>
              <div>
                <p>Are you sure you want to cancel?</p>
                <ul>
                  <li>You'll continue to have access until {{ formatDate(subscriptionDetails?.subscription?.currentPeriodEnd) }}</li>
                  <li>After that date, your account will return to the Free plan</li>
                  <li>You can reactivate anytime before the period ends</li>
                </ul>
              </div>
            </div>

            <div class="cancelActions">
              <Button label="Keep My Subscription" class="w-full" outlined @click="managementAction = null" :disabled="actionLoading" />
              <Button label="Yes, Cancel Subscription" class="w-full" severity="danger" @click="handleCancelSubscription" :loading="actionLoading" />
            </div>
          </div>
        </template>

        <!-- Loading state -->
        <template v-else-if="!subscriptionDetails">
          <div class="loadingState">
            <i class="pi pi-spin pi-spinner" style="font-size: 2rem"></i>
            <p>Loading subscription details...</p>
          </div>
        </template>
      </Dialog>

      <!-- Admin Section: Stripe Setup -->
      <div v-if="auth.isAdmin" class="row g-4 mt-2">
        <div class="col-12">
          <div class="apCard adminCard">
            <div class="adminHeader">
              <div>
                <h3>Admin: Stripe Configuration</h3>
                <p class="adminNote">Sync products and prices from your configuration to Stripe</p>
              </div>
              <Button
                :label="syncingStripe ? 'Syncing...' : 'Sync to Stripe'"
                :icon="syncingStripe ? 'pi pi-spin pi-spinner' : 'pi pi-sync'"
                :disabled="syncingStripe"
                @click="handleSyncStripe"
              />
            </div>

            <!-- Stripe Status -->
            <div v-if="stripeStatus" class="stripeStatus">
              <div v-if="stripeStatus.missing?.length" class="statusWarning">
                <i class="pi pi-exclamation-triangle"></i>
                Missing products: {{ stripeStatus.missing.join(', ') }}
              </div>

              <div class="statusGrid">
                <div v-for="(product, key) in stripeStatus.products" :key="key" class="statusItem">
                  <div class="statusName">
                    <i class="pi pi-check-circle" style="color: var(--ap-success)"></i>
                    {{ product.name || key }}
                  </div>
                  <div class="statusPrices">
                    <span v-if="stripeStatus.prices?.[key]?.monthly" class="priceTag">
                      Monthly: €{{ (stripeStatus.prices[key].monthly.amount / 100).toFixed(2) }}
                    </span>
                    <span v-if="stripeStatus.prices?.[key]?.annual" class="priceTag">
                      Annual: €{{ (stripeStatus.prices[key].annual.amount / 100).toFixed(2) }}
                    </span>
                    <span v-if="stripeStatus.prices?.[key]?.seat" class="priceTag">
                      Seat: €{{ (stripeStatus.prices[key].seat.amount / 100).toFixed(2) }}/mo
                    </span>
                    <span v-if="stripeStatus.prices?.[key]?.id" class="priceTag">
                      €{{ (stripeStatus.prices[key].amount / 100).toFixed(2) }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Env Updates (after sync) -->
              <div v-if="stripeStatus.env_updates" class="envUpdates">
                <h4>Generated Stripe IDs (for reference):</h4>
                <pre class="envCode">{{ Object.entries(stripeStatus.env_updates).map(([k, v]) => `${k}=${v}`).join('\n') }}</pre>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<style scoped>
.billingView {
  max-width: 1200px;
}

.currentPlanCard h3 {
  margin: 0;
  font-size: 1rem;
  color: var(--ap-text-muted);
}

.planHeader {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
}

.planName {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-top: 0.25rem;
}

.planTitle {
  font-size: 1.5rem;
  font-weight: 600;
  text-transform: capitalize;
}

.planActions {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  align-items: flex-end;
}

.usageSection h4,
.currentFeatures h4 {
  font-size: 0.875rem;
  color: var(--ap-text-muted);
  margin-bottom: 1rem;
}

.usageGrid {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.usageItem {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.usageHeader {
  display: flex;
  justify-content: space-between;
  font-size: 0.875rem;
}

.usageValue {
  font-family: var(--ap-font-mono);
  color: var(--ap-text-muted);
}

.currentFeatures {
  margin-top: 2rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--ap-border);
}

.featureList {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.5rem;
}

.featureList li {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: var(--ap-text-muted);
}

.featureList li .pi-check {
  color: var(--ap-success);
  font-size: 0.75rem;
}

/* Upgrade Card */
.upgradeCard {
  position: sticky;
  top: 1rem;
}

.upgradeHeader h3 {
  margin: 0 0 1rem;
  font-size: 1.125rem;
}

/* Billing Toggle */
.billingToggle {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
  padding: 0.75rem;
  background: var(--ap-surface-hover);
  border-radius: var(--ap-radius-sm);
}

.billingToggle > span {
  font-size: 0.875rem;
  color: var(--ap-text-muted);
  transition: color 0.2s;
}

.billingToggle > span.active {
  color: var(--ap-text);
  font-weight: 600;
}

.saveBadge {
  display: inline-block;
  background: rgba(34, 197, 94, 0.2);
  color: #22c55e;
  font-size: 0.625rem;
  padding: 0.125rem 0.375rem;
  border-radius: 4px;
  margin-left: 0.25rem;
  font-weight: 600;
}

.toggleSwitch {
  width: 44px;
  height: 24px;
  background: var(--ap-border);
  border: none;
  border-radius: 12px;
  cursor: pointer;
  position: relative;
  transition: background 0.2s;
}

.toggleSwitch.annual {
  background: var(--ap-accent);
}

.toggleKnob {
  position: absolute;
  top: 2px;
  left: 2px;
  width: 20px;
  height: 20px;
  background: white;
  border-radius: 50%;
  transition: transform 0.2s;
}

.toggleSwitch.annual .toggleKnob {
  transform: translateX(20px);
}

/* Plan Options */
.planOptions {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.planOption {
  padding: 1rem;
  background: var(--ap-surface-hover);
  border: 1px solid var(--ap-border);
  border-radius: var(--ap-radius-sm);
  position: relative;
}

.planOption.highlighted {
  border-color: var(--ap-accent);
  background: rgba(99, 102, 241, 0.05);
}

.popularTag {
  position: absolute;
  top: -8px;
  right: 12px;
  background: var(--ap-accent);
  color: white;
  font-size: 0.625rem;
  padding: 0.125rem 0.5rem;
  border-radius: 4px;
  font-weight: 600;
  text-transform: uppercase;
}

.planOptionHeader {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 0.5rem;
}

.planOptionName {
  font-weight: 600;
  font-size: 1rem;
}

.planOptionPrice {
  font-family: var(--ap-font-mono);
  color: var(--ap-accent);
  font-size: 1.125rem;
  font-weight: 600;
}

.planOptionPrice .perMonth {
  font-size: 0.75rem;
  color: var(--ap-text-muted);
  font-weight: 400;
}

.annualNote {
  font-size: 0.75rem;
  color: #22c55e;
  margin: 0 0 0.75rem;
}

.planOptionFeatures {
  list-style: none;
  padding: 0;
  margin: 0 0 1rem;
  font-size: 0.75rem;
  color: var(--ap-text-muted);
}

.planOptionFeatures li {
  padding: 0.25rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.planOptionFeatures li .pi-check {
  color: var(--ap-success);
  font-size: 0.625rem;
}

.planOptionFeatures .moreFeatures {
  color: var(--ap-accent);
  font-style: italic;
}

.noUpgrades {
  text-align: center;
  padding: 2rem;
  color: var(--ap-text-muted);
}

.noUpgrades .pi-check-circle {
  font-size: 2.5rem;
  color: var(--ap-success);
  margin-bottom: 1rem;
}

.noUpgrades p {
  margin: 0;
  font-weight: 600;
  color: var(--ap-text);
}

.noUpgrades span {
  font-size: 0.875rem;
}

/* Checkout Mode */
.checkoutHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.checkoutHeader h3 {
  margin: 0;
  font-size: 1.125rem;
}

.checkoutPlan {
  padding: 1rem;
  background: var(--ap-surface-hover);
  border-radius: var(--ap-radius-sm);
  margin-bottom: 1rem;
  text-align: center;
}

.checkoutPlanName {
  font-weight: 600;
  font-size: 1.25rem;
  margin-bottom: 0.25rem;
}

.checkoutPlanPrice {
  font-family: var(--ap-font-mono);
  color: var(--ap-accent);
  font-size: 1.5rem;
  font-weight: 700;
}

.checkoutPlanPrice .perSeat {
  font-size: 0.875rem;
  color: var(--ap-text-muted);
  font-weight: 400;
}

.billingNote {
  font-size: 0.75rem;
  color: #22c55e;
  margin: 0.5rem 0 0;
}

/* Package Details */
.packageDetails {
  margin-bottom: 1.5rem;
}

.packageDetails h4 {
  font-size: 0.875rem;
  color: var(--ap-text-muted);
  margin: 0 0 0.75rem;
}

.detailsList {
  list-style: none;
  padding: 0;
  margin: 0;
  background: var(--ap-surface-hover);
  border-radius: var(--ap-radius-sm);
  overflow: hidden;
}

.detailsList li {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0.75rem;
  font-size: 0.8125rem;
  border-bottom: 1px solid var(--ap-border);
}

.detailsList li:last-child {
  border-bottom: none;
}

.detailLabel {
  color: var(--ap-text-muted);
}

.detailValue {
  font-weight: 500;
  font-family: var(--ap-font-mono);
}

.detailValue.boolean {
  color: var(--ap-success);
}

.detailValue.boolean .pi-check {
  font-size: 0.75rem;
}

/* Seat Selection */
.seatSelection {
  margin-bottom: 1.5rem;
}

.seatSelection h4 {
  font-size: 0.875rem;
  color: var(--ap-text-muted);
  margin: 0 0 0.75rem;
}

.seatControls {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.seatControls :deep(.p-inputnumber) {
  width: 50px;
  flex-shrink: 0;
}

.seatControls :deep(.p-inputnumber-input) {
  width: 50px !important;
  min-width: 50px;
  text-align: center;
  font-weight: 600;
  font-size: 1rem;
  padding: 0.5rem 0.25rem;
}

.seatNote {
  font-size: 0.75rem;
  color: var(--ap-text-muted);
  text-align: center;
  margin: 0.5rem 0 0;
}

.seatLimit {
  font-size: 0.6875rem;
  color: var(--ap-text-muted);
  text-align: center;
  margin: 0.25rem 0 0;
  opacity: 0.7;
}

.includedSeatsNote {
  font-size: 0.75rem;
  color: var(--ap-text-muted);
  margin: 0.5rem 0 0;
}

/* Coupon Section */
.couponSection {
  margin-bottom: 1.5rem;
}

.couponSection label {
  display: block;
  font-size: 0.875rem;
  color: var(--ap-text-muted);
  margin-bottom: 0.5rem;
}

.couponInput .p-inputtext {
  width: 100%;
}

/* Price Summary */
.priceSummary {
  background: var(--ap-surface-hover);
  border-radius: var(--ap-radius-sm);
  padding: 1rem;
  margin-bottom: 1rem;
}

.priceRow {
  display: flex;
  justify-content: space-between;
  font-size: 0.875rem;
  padding: 0.25rem 0;
}

.priceRow.subtotal {
  color: var(--ap-text-muted);
  border-bottom: 1px solid var(--ap-border);
  padding-bottom: 0.5rem;
  margin-bottom: 0.5rem;
}

.priceRow.total {
  font-weight: 600;
  font-size: 1rem;
  padding-top: 0.25rem;
}

.totalAmount {
  color: var(--ap-accent);
  font-family: var(--ap-font-mono);
  font-size: 1.125rem;
}

.checkoutBtn {
  margin-bottom: 0.75rem;
}

.checkoutNote {
  text-align: center;
  font-size: 0.75rem;
  color: var(--ap-text-muted);
  margin: 0;
}

/* Admin Section */
.adminCard {
  border: 1px dashed var(--ap-border);
  background: rgba(99, 102, 241, 0.02);
}

.adminHeader {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  flex-wrap: wrap;
}

.adminHeader h3 {
  margin: 0;
  font-size: 1rem;
  color: var(--ap-accent);
}

.adminNote {
  margin: 0.25rem 0 0;
  font-size: 0.875rem;
  color: var(--ap-text-muted);
}

.stripeStatus {
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--ap-border);
}

.statusWarning {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  background: rgba(245, 158, 11, 0.1);
  color: #f59e0b;
  border-radius: var(--ap-radius-sm);
  margin-bottom: 1rem;
  font-size: 0.875rem;
}

.statusGrid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 0.75rem;
}

.statusItem {
  padding: 0.75rem;
  background: var(--ap-surface-hover);
  border-radius: var(--ap-radius-sm);
}

.statusName {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 500;
  font-size: 0.875rem;
  margin-bottom: 0.5rem;
}

.statusPrices {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.priceTag {
  font-size: 0.75rem;
  padding: 0.125rem 0.5rem;
  background: var(--ap-surface);
  border-radius: 4px;
  color: var(--ap-text-muted);
  font-family: var(--ap-font-mono);
}

.envUpdates {
  margin-top: 1.5rem;
}

.envUpdates h4 {
  font-size: 0.875rem;
  color: var(--ap-text-muted);
  margin: 0 0 0.5rem;
}

.envCode {
  background: var(--ap-surface);
  border: 1px solid var(--ap-border);
  border-radius: var(--ap-radius-sm);
  padding: 1rem;
  font-family: var(--ap-font-mono);
  font-size: 0.75rem;
  overflow-x: auto;
  white-space: pre-wrap;
  word-break: break-all;
  margin: 0;
}

/* Subscription Management Modal */
.subscriptionInfo {
  background: var(--ap-surface-hover);
  border-radius: var(--ap-radius-sm);
  padding: 1rem;
  margin-bottom: 1.5rem;
}

.subInfoRow {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--ap-border);
}

.subInfoRow:last-child {
  border-bottom: none;
}

.subInfoLabel {
  color: var(--ap-text-muted);
  font-size: 0.875rem;
}

.subInfoValue {
  font-weight: 500;
}

.subInfoValue.planBadge {
  background: var(--ap-accent);
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: var(--ap-radius-sm);
  font-size: 0.875rem;
  text-transform: capitalize;
}

.cancelledNotice {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  background: rgba(245, 158, 11, 0.1);
  border: 1px solid rgba(245, 158, 11, 0.3);
  border-radius: var(--ap-radius-sm);
  margin-bottom: 1rem;
}

.cancelledNotice > .pi {
  color: #f59e0b;
  font-size: 1.25rem;
  flex-shrink: 0;
  margin-top: 0.125rem;
}

.cancelledNotice p {
  margin: 0 0 0.75rem;
  font-size: 0.875rem;
}

.managementActions {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.actionBtn {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: var(--ap-surface-hover);
  border: 1px solid var(--ap-border);
  border-radius: var(--ap-radius-sm);
  cursor: pointer;
  transition: all 0.2s;
  text-align: left;
  width: 100%;
}

.actionBtn:hover {
  border-color: var(--ap-accent);
  background: rgba(99, 102, 241, 0.05);
}

.actionBtn.danger:hover {
  border-color: var(--ap-danger);
  background: rgba(239, 68, 68, 0.05);
}

.actionBtn > .pi:first-child {
  font-size: 1.25rem;
  color: var(--ap-accent);
  width: 24px;
  flex-shrink: 0;
}

.actionBtn.danger > .pi:first-child {
  color: var(--ap-danger);
}

.actionBtn > div {
  flex: 1;
}

.actionBtn .actionTitle {
  display: block;
  font-weight: 600;
  font-size: 0.9375rem;
  color: var(--ap-text);
}

.actionBtn .actionDesc {
  display: block;
  font-size: 0.75rem;
  color: var(--ap-text-muted);
  margin-top: 0.125rem;
}

.actionBtn > .pi-chevron-right {
  color: var(--ap-text-muted);
  font-size: 0.875rem;
}

/* Action Screens */
.actionHeader {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.actionHeader h3 {
  margin: 0;
  font-size: 1.125rem;
}

.actionContent p {
  font-size: 0.875rem;
  color: var(--ap-text-muted);
  margin: 0 0 1.5rem;
  line-height: 1.5;
}

.seatSummary {
  text-align: center;
  font-weight: 500;
  color: var(--ap-text);
  margin: 1rem 0 0.25rem !important;
}

.maxNote, .activeNote {
  text-align: center;
  font-size: 0.75rem !important;
  color: var(--ap-text-muted);
  margin: 0 !important;
}

.activeNote {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.75rem;
  background: rgba(245, 158, 11, 0.1);
  border-radius: var(--ap-radius-sm);
  margin-top: 1rem !important;
}

.activeNote .pi {
  color: #f59e0b;
}

/* Plan Selection */
.planSelection {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.planChoice {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem;
  background: var(--ap-surface-hover);
  border: 2px solid var(--ap-border);
  border-radius: var(--ap-radius-sm);
  cursor: pointer;
  transition: all 0.2s;
}

.planChoice:hover {
  border-color: var(--ap-accent);
}

.planChoice.selected {
  border-color: var(--ap-accent);
  background: rgba(99, 102, 241, 0.05);
}

.planChoice label {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex: 1;
  cursor: pointer;
}

.planChoiceName {
  font-weight: 600;
}

.planChoicePrice {
  font-family: var(--ap-font-mono);
  color: var(--ap-accent);
}

/* Cancel Warning */
.cancelWarning {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  background: rgba(239, 68, 68, 0.05);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: var(--ap-radius-sm);
  margin-bottom: 1.5rem;
}

.cancelWarning > .pi {
  color: var(--ap-danger);
  font-size: 1.5rem;
  flex-shrink: 0;
}

.cancelWarning p {
  margin: 0 0 0.75rem !important;
  font-weight: 600;
  color: var(--ap-text) !important;
}

.cancelWarning ul {
  margin: 0;
  padding-left: 1.25rem;
  font-size: 0.875rem;
  color: var(--ap-text-muted);
}

.cancelWarning li {
  margin-bottom: 0.25rem;
}

.cancelActions {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

/* Loading State */
.loadingState {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  color: var(--ap-text-muted);
}

.loadingState p {
  margin: 1rem 0 0;
}

@media (max-width: 992px) {
  .featureList {
    grid-template-columns: 1fr;
  }

  .adminHeader {
    flex-direction: column;
  }
}
</style>
