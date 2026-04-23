<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePlatformsStore } from '@/stores/platforms'
import { usePlatformLimits, MVP_PLATFORMS } from '@/composables/usePlatformLimits'
import { usePermissions } from '@/composables/usePermissions'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

const route = useRoute()
const router = useRouter()
const platformsStore = usePlatformsStore()
const { getPlatform, getMvpPlatforms } = usePlatformLimits()
const { canConnectAccount, remainingAccounts } = usePermissions()
const confirm = useConfirm()
const toast = useToast()

const availablePlatforms = getMvpPlatforms()

const getConnectedAccounts = (platformId) => {
  return platformsStore.accounts.filter(a => a.platform === platformId && a.isActive)
}

const handleConnect = (platformId) => {
  if (!canConnectAccount.value) {
    toast.add({
      severity: 'warn',
      summary: 'Limit reached',
      detail: 'Upgrade your plan to connect more accounts',
      life: 5000
    })
    return
  }
  platformsStore.connectPlatform(platformId)
}

const handleDisconnect = (account) => {
  confirm.require({
    message: `Are you sure you want to disconnect ${account.platformDisplayName || account.platformUsername}?`,
    header: 'Disconnect Account',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      const result = await platformsStore.disconnectPlatform(account.id)
      if (result.success) {
        toast.add({
          severity: 'success',
          summary: 'Account disconnected',
          life: 3000
        })
      } else {
        toast.add({
          severity: 'error',
          summary: 'Error',
          detail: result.error,
          life: 5000
        })
      }
    }
  })
}

const handleRefresh = async (account) => {
  const result = await platformsStore.refreshToken(account.id)
  if (result.success) {
    toast.add({
      severity: 'success',
      summary: 'Token refreshed',
      life: 3000
    })
  } else {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: result.error,
      life: 5000
    })
  }
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}

onMounted(() => {
  platformsStore.fetchAccounts()

  // Check URL params for messages from OAuth callback
  const error = route.query.error
  const success = route.query.success
  const warning = route.query.warning

  if (error) {
    toast.add({
      severity: 'error',
      summary: 'Connection Failed',
      detail: decodeURIComponent(error),
      life: 6000
    })
  } else if (warning) {
    toast.add({
      severity: 'warn',
      summary: 'Partial Success',
      detail: decodeURIComponent(warning),
      life: 6000
    })
  } else if (success === 'connected') {
    toast.add({
      severity: 'success',
      summary: 'Connected',
      detail: 'Account connected successfully',
      life: 3000
    })
  }

  // Clean up URL params
  if (error || success || warning) {
    router.replace({ path: route.path })
  }
})
</script>

<template>
  <DashboardLayout>
    <div class="platformsView apFadeIn">
      <div class="apPageHeader">
        <div>
          <h1 class="apPageTitle">Connected Platforms</h1>
          <p class="apTextMuted">{{ platformsStore.activeAccounts.length }} connected · {{ remainingAccounts }} remaining</p>
        </div>
      </div>

      <div class="row g-4">
        <div v-for="platform in availablePlatforms" :key="platform.id" class="col-12 col-md-6 col-lg-4">
          <div
            class="apCard platformCard"
            :style="{ '--platform-color': platform.color }"
          >
            <template v-if="platformsStore.loading">
              <div class="platformHeader">
                <Skeleton shape="circle" width="48px" height="48px" />
                <div class="platformInfo">
                  <Skeleton width="100px" height="1.25rem" />
                  <Skeleton width="60px" height="0.75rem" class="mt-2" />
                </div>
              </div>
            </template>
            <template v-else>
              <div class="platformHeader">
                <div class="platformIcon">
                  <i :class="platform.icon"></i>
                </div>
                <div class="platformInfo">
                  <h3 class="platformName">{{ platform.name }}</h3>
                  <template v-if="getConnectedAccounts(platform.id).length > 0">
                    <Tag :value="`${getConnectedAccounts(platform.id).length} connected`" severity="success" />
                  </template>
                  <template v-else>
                    <Tag value="Not connected" severity="secondary" />
                  </template>
                </div>
              </div>

              <!-- Connected accounts list -->
              <div v-if="getConnectedAccounts(platform.id).length > 0" class="connectedAccountsList">
                <div
                  v-for="account in getConnectedAccounts(platform.id)"
                  :key="account.id"
                  class="connectedAccount"
                >
                  <div class="accountInfo">
                    <div class="apAvatar apAvatarSm">
                      <img
                        v-if="account.platformAvatar && !account.avatarError"
                        :src="account.platformAvatar"
                        :alt="account.platformDisplayName"
                        @error="account.avatarError = true"
                      />
                      <span v-else class="avatarInitial">{{ (account.platformDisplayName || account.platformUsername || '?')[0].toUpperCase() }}</span>
                    </div>
                    <div>
                      <p class="accountName">{{ account.platformDisplayName || account.platformUsername }}</p>
                      <p class="accountMeta">Connected {{ formatDate(account.createdAt) }}</p>
                    </div>
                  </div>
                  <div class="accountActions">
                    <Button
                      icon="pi pi-refresh"
                      text
                      rounded
                      size="small"
                      v-tooltip.top="'Refresh token'"
                      @click="handleRefresh(account)"
                    />
                    <Button
                      icon="pi pi-times"
                      text
                      rounded
                      size="small"
                      severity="danger"
                      v-tooltip.top="'Disconnect'"
                      @click="handleDisconnect(account)"
                    />
                  </div>
                </div>
              </div>

              <!-- Add another / Connect button -->
              <div class="connectSection">
                <p v-if="getConnectedAccounts(platform.id).length === 0" class="platformDescription">
                  Connect your {{ platform.name }} account to schedule and publish posts.
                </p>
                <Button
                  :label="getConnectedAccounts(platform.id).length > 0 ? 'Add Another Account' : `Connect ${platform.name}`"
                  :icon="getConnectedAccounts(platform.id).length > 0 ? 'pi pi-plus' : platform.icon"
                  :class="['w-full', getConnectedAccounts(platform.id).length > 0 ? 'addAnotherBtn' : 'connectBtn']"
                  :outlined="getConnectedAccounts(platform.id).length > 0"
                  :style="getConnectedAccounts(platform.id).length === 0 ? { background: platform.color, borderColor: platform.color } : {}"
                  @click="handleConnect(platform.id)"
                />
              </div>
            </template>
          </div>
        </div>
      </div>

      <!-- Coming Soon Platforms -->
      <div class="comingSoonSection mt-4">
        <h2 class="sectionTitle">Coming Soon</h2>
        <div class="row g-3">
          <div v-for="platformId in ['twitter', 'linkedin', 'threads', 'tiktok']" :key="platformId" class="col-6 col-md-3">
            <div class="comingSoonCard apCardFlat">
              <i :class="getPlatform(platformId)?.icon" :style="{ color: getPlatform(platformId)?.color }"></i>
              <span>{{ getPlatform(platformId)?.name }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<style scoped>
.platformsView {
  max-width: 1200px;
}

.platformCard {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.platformHeader {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.platformIcon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: var(--ap-surface-hover);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  color: var(--platform-color);
}

.platformInfo {
  flex: 1;
}

.platformName {
  margin: 0 0 0.25rem;
  font-size: 1.125rem;
}

.connectedAccount {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem;
  background: var(--ap-surface-hover);
  border-radius: var(--ap-radius-sm);
}

.accountInfo {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.accountName {
  margin: 0;
  font-weight: 500;
  font-size: 0.875rem;
}

.accountMeta {
  margin: 0;
  font-size: 0.75rem;
  color: var(--ap-text-muted);
}

.accountActions {
  display: flex;
  gap: 0.25rem;
}

.connectSection {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.platformDescription {
  font-size: 0.875rem;
  color: var(--ap-text-muted);
  margin: 0;
}

.connectBtn {
  color: white !important;
}

.connectedAccountsList {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.addAnotherBtn {
  border-color: var(--ap-border) !important;
  color: var(--ap-text-muted) !important;
}

.avatarInitial {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  background: var(--ap-accent);
  color: white;
  font-weight: 600;
  font-size: 0.875rem;
  border-radius: 50%;
}

.comingSoonSection {
  padding-top: 1rem;
  border-top: 1px solid var(--ap-border);
}

.sectionTitle {
  font-size: 1rem;
  color: var(--ap-text-muted);
  margin-bottom: 1rem;
}

.comingSoonCard {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem;
  opacity: 0.5;
}

.comingSoonCard i {
  font-size: 1.25rem;
}

.comingSoonCard span {
  font-size: 0.875rem;
  color: var(--ap-text-muted);
}
</style>
