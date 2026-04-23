<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { usePlatformsStore } from '@/stores/platforms'
import { usePlatformLimits } from '@/composables/usePlatformLimits'
import OnboardingLayout from '@/layouts/OnboardingLayout.vue'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'

const router = useRouter()
const auth = useAuthStore()
const platformsStore = usePlatformsStore()
const { getMvpPlatforms } = usePlatformLimits()
const toast = useToast()

const activeStep = ref(0)
const loading = ref(false)

const steps = [
  { label: 'Welcome', icon: 'pi-user' },
  { label: 'Connect', icon: 'pi-link' },
  { label: 'Plan', icon: 'pi-credit-card' },
  { label: 'First Post', icon: 'pi-pencil' }
]

const profileName = ref(auth.user?.name || '')
const profileTimezone = ref(Intl.DateTimeFormat().resolvedOptions().timeZone)
const profileIndustry = ref('')

const timezones = [
  'UTC',
  'America/New_York',
  'America/Chicago',
  'America/Denver',
  'America/Los_Angeles',
  'Europe/London',
  'Europe/Paris',
  'Europe/Berlin',
  'Asia/Tokyo',
  'Asia/Shanghai',
  'Australia/Sydney'
]

const industries = [
  { label: 'Technology', value: 'technology' },
  { label: 'Marketing', value: 'marketing' },
  { label: 'E-commerce', value: 'ecommerce' },
  { label: 'Lifestyle', value: 'lifestyle' },
  { label: 'Education', value: 'education' },
  { label: 'Health & Fitness', value: 'health' },
  { label: 'Finance', value: 'finance' },
  { label: 'Creative', value: 'creative' },
  { label: 'Other', value: 'other' }
]

const platforms = getMvpPlatforms()
const selectedPlan = ref('free')

const plans = [
  {
    id: 'free',
    name: 'Free',
    price: 0,
    features: ['15 posts/month', '2 social accounts', '10 AI generations', '7 days analytics']
  },
  {
    id: 'pro',
    name: 'Pro',
    price: 15,
    popular: true,
    features: ['Unlimited posts', '5 social accounts', '100 AI generations', '90 days analytics', 'Bulk import', 'Streams']
  },
  {
    id: 'team',
    name: 'Team',
    price: 39,
    features: ['Everything in Pro', '15 social accounts', '500 AI generations', '1 year analytics', 'Up to 10 members', 'Approval workflows']
  }
]

const firstPostContent = ref('')
const firstPostPlatform = ref('')

const connectedPlatforms = computed(() => {
  return platformsStore.accounts.filter(a => a.isActive)
})

const canProceedStep1 = computed(() => {
  return profileName.value && profileTimezone.value
})

const handleProfileSave = async () => {
  loading.value = true
  const result = await auth.updateProfile({
    name: profileName.value,
    timezone: profileTimezone.value,
    industry: profileIndustry.value
  })
  loading.value = false

  if (result.success) {
    activeStep.value = 1
  } else {
    toast.add({ severity: 'error', summary: 'Error', detail: result.error, life: 5000 })
  }
}

const handleConnectPlatform = (platformId) => {
  platformsStore.connectPlatform(platformId)
}

const isPlatformConnected = (platformId) => {
  return platformsStore.accounts.some(a => a.platform === platformId && a.isActive)
}

const handlePlanSelect = async (planId) => {
  selectedPlan.value = planId

  if (planId === 'free') {
    activeStep.value = 3
  } else {
    loading.value = true
    const { useBillingStore } = await import('@/stores/billing')
    const billing = useBillingStore()
    await billing.createCheckout(planId)
    loading.value = false
  }
}

const handleSkipFirstPost = () => {
  completeOnboarding()
}

const handlePublishFirstPost = async () => {
  if (!firstPostContent.value) {
    completeOnboarding()
    return
  }

  loading.value = true
  const { usePostsStore } = await import('@/stores/posts')
  const posts = usePostsStore()

  const result = await posts.createPost({
    content: firstPostContent.value,
    platforms: firstPostPlatform.value ? [firstPostPlatform.value] : [],
    status: 'draft'
  })

  loading.value = false

  if (result.success) {
    toast.add({ severity: 'success', summary: 'Post created!', detail: 'Your first post has been saved as a draft', life: 3000 })
  }

  completeOnboarding()
}

const completeOnboarding = async () => {
  loading.value = true
  await auth.updateProfile({ onboardingCompleted: true })
  loading.value = false
  router.push('/dashboard')
}

onMounted(async () => {
  await platformsStore.fetchAccounts()
})
</script>

<template>
  <OnboardingLayout>
    <div class="onboardingView">
      <!-- Custom Stepper Header -->
      <div class="stepperHeader">
        <div
          v-for="(step, index) in steps"
          :key="index"
          class="stepItem"
          :class="{ active: activeStep === index, completed: activeStep > index }"
        >
          <div class="stepNumber">
            <i v-if="activeStep > index" class="pi pi-check"></i>
            <span v-else>{{ index + 1 }}</span>
          </div>
          <span class="stepLabel">{{ step.label }}</span>
        </div>
      </div>

      <!-- Step Content -->
      <div class="stepContent">
        <!-- Step 0: Welcome / Profile -->
        <div v-if="activeStep === 0" class="stepPanel">
          <h2>Welcome to APEX PostMaster</h2>
          <p class="stepDescription">Let's set up your profile to personalize your experience.</p>

          <div class="formGroup">
            <label class="apLabel">Your name</label>
            <InputText v-model="profileName" placeholder="John Doe" class="w-full" />
          </div>

          <div class="formGroup">
            <label class="apLabel">Timezone</label>
            <Dropdown
              v-model="profileTimezone"
              :options="timezones"
              placeholder="Select timezone"
              class="w-full"
            />
          </div>

          <div class="formGroup">
            <label class="apLabel">Industry</label>
            <Dropdown
              v-model="profileIndustry"
              :options="industries"
              optionLabel="label"
              optionValue="value"
              placeholder="Select your industry"
              class="w-full"
            />
          </div>

          <div class="stepActions">
            <Button
              label="Continue"
              icon="pi pi-arrow-right"
              iconPos="right"
              @click="handleProfileSave"
              :disabled="!canProceedStep1"
              :loading="loading"
            />
          </div>
        </div>

        <!-- Step 1: Connect Platforms -->
        <div v-if="activeStep === 1" class="stepPanel">
          <h2>Connect your accounts</h2>
          <p class="stepDescription">Link your social media accounts to start scheduling posts.</p>

          <div class="platformsGrid">
            <div
              v-for="platform in platforms"
              :key="platform.id"
              class="platformCard"
              :class="{ connected: isPlatformConnected(platform.id) }"
              :style="{ '--platform-color': platform.color }"
            >
              <i :class="platform.icon" class="platformIcon"></i>
              <span class="platformName">{{ platform.name }}</span>
              <Tag v-if="isPlatformConnected(platform.id)" value="Connected" severity="success" />
              <Button
                v-else
                label="Connect"
                size="small"
                @click="handleConnectPlatform(platform.id)"
              />
            </div>
          </div>

          <div class="stepActions">
            <Button
              label="Skip for now"
              text
              @click="activeStep = 2"
            />
            <Button
              label="Continue"
              icon="pi pi-arrow-right"
              iconPos="right"
              @click="activeStep = 2"
            />
          </div>
        </div>

        <!-- Step 2: Choose Plan -->
        <div v-if="activeStep === 2" class="stepPanel">
          <h2>Choose your plan</h2>
          <p class="stepDescription">Start free and upgrade anytime.</p>

          <div class="plansGrid">
            <Card
              v-for="plan in plans"
              :key="plan.id"
              class="planCard"
              :class="{ popular: plan.popular, selected: selectedPlan === plan.id }"
            >
              <template #header>
                <Tag v-if="plan.popular" value="Most Popular" class="popularTag" />
              </template>
              <template #title>
                <span class="planName">{{ plan.name }}</span>
              </template>
              <template #subtitle>
                <div class="planPrice">
                  <span class="priceAmount">{{ plan.price === 0 ? 'Free' : `€${plan.price}` }}</span>
                  <span v-if="plan.price > 0" class="pricePeriod">/month</span>
                </div>
              </template>
              <template #content>
                <ul class="planFeatures">
                  <li v-for="feature in plan.features" :key="feature">
                    <i class="pi pi-check"></i>
                    {{ feature }}
                  </li>
                </ul>
              </template>
              <template #footer>
                <Button
                  :label="plan.price === 0 ? 'Start Free' : 'Get Started'"
                  class="w-full"
                  :outlined="!plan.popular"
                  @click="handlePlanSelect(plan.id)"
                  :loading="loading && selectedPlan === plan.id"
                />
              </template>
            </Card>
          </div>
        </div>

        <!-- Step 3: First Post -->
        <div v-if="activeStep === 3" class="stepPanel">
          <h2>Create your first post</h2>
          <p class="stepDescription">Let's create something awesome together! (Optional)</p>

          <div class="formGroup">
            <label class="apLabel">What would you like to share?</label>
            <Textarea
              v-model="firstPostContent"
              rows="4"
              placeholder="Write your first post..."
              class="w-full"
            />
          </div>

          <div v-if="connectedPlatforms.length > 0" class="formGroup">
            <label class="apLabel">Platform</label>
            <Dropdown
              v-model="firstPostPlatform"
              :options="connectedPlatforms.map(p => ({ label: p.platformDisplayName || p.platform, value: p.platform }))"
              optionLabel="label"
              optionValue="value"
              placeholder="Select platform"
              class="w-full"
            />
          </div>

          <div class="stepActions">
            <Button
              label="Skip & Finish"
              text
              @click="handleSkipFirstPost"
            />
            <Button
              label="Save & Finish"
              icon="pi pi-check"
              @click="handlePublishFirstPost"
              :loading="loading"
            />
          </div>
        </div>
      </div>
    </div>
  </OnboardingLayout>
</template>

<style scoped>
.onboardingView {
  width: 100%;
  max-width: 800px;
}

.stepperHeader {
  display: flex;
  justify-content: center;
  gap: 0.5rem;
  margin-bottom: 2rem;
  padding: 1rem;
  flex-wrap: wrap;
}

.stepItem {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border-radius: 50px;
  background: var(--ap-surface);
  border: 1px solid var(--ap-border);
  opacity: 0.5;
  transition: all 0.3s;
}

.stepItem.active {
  opacity: 1;
  border-color: var(--ap-accent);
  background: rgba(99, 102, 241, 0.1);
}

.stepItem.completed {
  opacity: 1;
  border-color: var(--ap-success);
  background: rgba(34, 197, 94, 0.1);
}

.stepNumber {
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: var(--ap-border);
  font-size: 0.75rem;
  font-weight: 600;
}

.stepItem.active .stepNumber {
  background: var(--ap-accent);
  color: white;
}

.stepItem.completed .stepNumber {
  background: var(--ap-success);
  color: white;
}

.stepLabel {
  font-size: 0.875rem;
  font-weight: 500;
}

.stepContent {
  background: var(--ap-surface);
  border: 1px solid var(--ap-border);
  border-radius: var(--ap-radius);
  padding: 2rem;
}

.stepPanel {
  text-align: center;
}

.stepPanel h2 {
  margin-bottom: 0.5rem;
}

.stepDescription {
  color: var(--ap-text-muted);
  margin-bottom: 2rem;
}

.formGroup {
  text-align: left;
  margin-bottom: 1.5rem;
  max-width: 400px;
  margin-left: auto;
  margin-right: auto;
}

.formGroup label {
  display: block;
  margin-bottom: 0.5rem;
}

.stepActions {
  display: flex;
  justify-content: center;
  gap: 1rem;
  margin-top: 2rem;
}

.platformsGrid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 1rem;
  max-width: 600px;
  margin: 0 auto 2rem;
}

.platformCard {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  padding: 1.5rem;
  background: var(--ap-bg);
  border: 1px solid var(--ap-border);
  border-radius: var(--ap-radius);
  transition: all 0.3s;
}

.platformCard:hover {
  border-color: var(--ap-accent);
}

.platformCard.connected {
  border-color: var(--ap-success);
  background: rgba(34, 197, 94, 0.05);
}

.platformIcon {
  font-size: 2rem;
  color: var(--platform-color);
}

.platformName {
  font-weight: 500;
}

.plansGrid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1.5rem;
  max-width: 900px;
  margin: 0 auto;
}

.planCard {
  text-align: left;
  position: relative;
}

.planCard.popular {
  border-color: var(--ap-accent);
}

.planCard.selected {
  border-color: var(--ap-accent);
  box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
}

.popularTag {
  position: absolute;
  top: -0.75rem;
  left: 50%;
  transform: translateX(-50%);
}

.planName {
  font-size: 1.25rem;
  font-weight: 600;
}

.planPrice {
  margin: 0.5rem 0 1rem;
}

.priceAmount {
  font-size: 2rem;
  font-weight: 700;
  font-family: var(--ap-font-mono);
}

.pricePeriod {
  color: var(--ap-text-muted);
  font-size: 0.875rem;
}

.planFeatures {
  list-style: none;
  padding: 0;
  margin: 0;
}

.planFeatures li {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  margin-bottom: 0.5rem;
  color: var(--ap-text-muted);
}

.planFeatures li i {
  color: var(--ap-success);
}

:deep(.p-card) {
  background: var(--ap-bg);
  border: 1px solid var(--ap-border);
}

@media (max-width: 575px) {
  .stepperHeader {
    gap: 0.25rem;
  }

  .stepItem {
    padding: 0.375rem 0.75rem;
  }

  .stepLabel {
    display: none;
  }

  .plansGrid {
    grid-template-columns: 1fr;
  }

  .stepActions {
    flex-direction: column;
  }
}
</style>
