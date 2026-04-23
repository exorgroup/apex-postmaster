<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Button from 'primevue/button'
import Avatar from 'primevue/avatar'
import { useToast } from 'primevue/usetoast'

const auth = useAuthStore()
const toast = useToast()

const name = ref('')
const email = ref('')
const timezone = ref('')
const language = ref('en')
const loading = ref(false)

const timezones = [
  'UTC', 'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles',
  'Europe/London', 'Europe/Paris', 'Europe/Berlin', 'Asia/Tokyo', 'Asia/Shanghai', 'Australia/Sydney'
]

const languages = [
  { label: 'English', value: 'en' },
  { label: 'Spanish', value: 'es' },
  { label: 'French', value: 'fr' },
  { label: 'German', value: 'de' }
]

const handleSave = async () => {
  loading.value = true
  const result = await auth.updateProfile({
    name: name.value,
    timezone: timezone.value,
    language: language.value
  })
  loading.value = false

  if (result.success) {
    toast.add({ severity: 'success', summary: 'Profile updated', life: 3000 })
  } else {
    toast.add({ severity: 'error', summary: 'Error', detail: result.error, life: 5000 })
  }
}

onMounted(() => {
  name.value = auth.user?.name || ''
  email.value = auth.user?.email || ''
  timezone.value = auth.user?.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone
  language.value = auth.user?.language || 'en'
})
</script>

<template>
  <DashboardLayout>
    <div class="profileView apFadeIn">
      <div class="apPageHeader">
        <h1 class="apPageTitle">Profile Settings</h1>
      </div>

      <div class="row g-4">
        <div class="col-12 col-lg-8">
          <div class="apCard">
            <h3 class="cardTitle">Personal Information</h3>

            <div class="avatarSection">
              <Avatar
                :image="auth.user?.avatar"
                :label="auth.user?.avatar ? undefined : (auth.user?.name?.[0] || 'U')"
                shape="circle"
                size="xlarge"
                class="profileAvatar"
              />
              <div>
                <p class="avatarHint">Profile picture from your OAuth provider</p>
              </div>
            </div>

            <form @submit.prevent="handleSave" class="profileForm">
              <div class="formGroup">
                <label class="apLabel">Full Name</label>
                <InputText v-model="name" class="w-full" />
              </div>

              <div class="formGroup">
                <label class="apLabel">Email</label>
                <InputText v-model="email" class="w-full" disabled />
                <small class="formHint">Email cannot be changed</small>
              </div>

              <div class="formGroup">
                <label class="apLabel">Timezone</label>
                <Dropdown v-model="timezone" :options="timezones" class="w-full" />
              </div>

              <div class="formGroup">
                <label class="apLabel">Language</label>
                <Dropdown v-model="language" :options="languages" optionLabel="label" optionValue="value" class="w-full" />
              </div>

              <div class="formActions">
                <Button type="submit" label="Save Changes" :loading="loading" />
              </div>
            </form>
          </div>
        </div>

        <div class="col-12 col-lg-4">
          <div class="apCard">
            <h3 class="cardTitle">Account</h3>
            <div class="accountInfo">
              <div class="infoRow">
                <span class="infoLabel">Plan</span>
                <span class="infoValue capitalize">{{ auth.currentPlan }}</span>
              </div>
              <div class="infoRow">
                <span class="infoLabel">Member since</span>
                <span class="infoValue">{{ new Date(auth.user?.createdAt || Date.now()).toLocaleDateString() }}</span>
              </div>
            </div>
            <Button label="Manage Subscription" outlined class="w-full mt-3" @click="$router.push('/settings/billing')" />
          </div>

          <div class="apCard dangerZone mt-4">
            <h3 class="cardTitle">Danger Zone</h3>
            <p class="dangerText">Once you delete your account, there is no going back.</p>
            <Button label="Delete Account" severity="danger" outlined class="w-full" />
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<style scoped>
.profileView {
  max-width: 1000px;
}

.cardTitle {
  margin: 0 0 1.5rem;
  font-size: 1rem;
}

.avatarSection {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  margin-bottom: 2rem;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid var(--ap-border);
}

.profileAvatar {
  background: var(--ap-accent);
  color: white;
  font-size: 1.5rem;
}

.avatarHint {
  margin: 0;
  font-size: 0.875rem;
  color: var(--ap-text-muted);
}

.profileForm {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.formGroup {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.formHint {
  color: var(--ap-text-dim);
  font-size: 0.75rem;
}

.formActions {
  padding-top: 1rem;
}

.accountInfo {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.infoRow {
  display: flex;
  justify-content: space-between;
}

.infoLabel {
  color: var(--ap-text-muted);
}

.infoValue {
  font-weight: 500;
}

.capitalize {
  text-transform: capitalize;
}

.dangerZone {
  border-color: var(--ap-danger);
}

.dangerText {
  color: var(--ap-text-muted);
  font-size: 0.875rem;
  margin-bottom: 1rem;
}
</style>
