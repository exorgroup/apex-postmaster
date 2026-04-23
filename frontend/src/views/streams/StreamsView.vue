<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { usePlatformsStore } from '@/stores/platforms'
import { usePermissions } from '@/composables/usePermissions'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import Button from 'primevue/button'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'

const platformsStore = usePlatformsStore()
const { canUseStreams } = usePermissions()
const toast = useToast()

const streams = ref([])
const loading = ref(false)
const addStreamDialog = ref(false)
const refreshInterval = ref(null)

const newStream = ref({
  name: '',
  type: 'mentions',
  platform: '',
  keywords: ''
})

const streamTypes = [
  { label: 'Mentions', value: 'mentions' },
  { label: 'Hashtag', value: 'hashtag' },
  { label: 'Keywords', value: 'keywords' },
  { label: 'Scheduled Posts', value: 'scheduled' }
]

const connectedPlatforms = computed(() => {
  return platformsStore.accounts
    .filter(a => a.isActive)
    .map(a => ({ label: a.platformDisplayName || a.platform, value: a.platform }))
})

const fetchStreams = async () => {
  loading.value = true
  try {
    const response = await fetch('/api/streams')
    if (response.ok) {
      streams.value = await response.json()
    }
  } catch (error) {
    console.error('Failed to fetch streams:', error)
  } finally {
    loading.value = false
  }
}

const refreshStreamData = async () => {
  for (const stream of streams.value) {
    try {
      const response = await fetch(`/api/streams/${stream.id}/items`)
      if (response.ok) {
        stream.items = await response.json()
      }
    } catch (error) {
      console.error(`Failed to refresh stream ${stream.id}:`, error)
    }
  }
}

const handleAddStream = async () => {
  if (!newStream.value.name || !newStream.value.platform) {
    toast.add({ severity: 'warn', summary: 'Missing info', detail: 'Please fill in all required fields', life: 3000 })
    return
  }

  loading.value = true
  try {
    const response = await fetch('/api/streams', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newStream.value)
    })

    if (response.ok) {
      const created = await response.json()
      streams.value.push(created)
      addStreamDialog.value = false
      newStream.value = { name: '', type: 'mentions', platform: '', keywords: '' }
      toast.add({ severity: 'success', summary: 'Stream created', life: 3000 })
    }
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to create stream', life: 5000 })
  } finally {
    loading.value = false
  }
}

const handleDeleteStream = async (streamId) => {
  try {
    const response = await fetch(`/api/streams/${streamId}`, { method: 'DELETE' })
    if (response.ok) {
      streams.value = streams.value.filter(s => s.id !== streamId)
      toast.add({ severity: 'success', summary: 'Stream deleted', life: 3000 })
    }
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete stream', life: 5000 })
  }
}

const getStreamIcon = (type) => {
  const icons = {
    mentions: 'pi pi-at',
    hashtag: 'pi pi-hashtag',
    keywords: 'pi pi-search',
    scheduled: 'pi pi-clock'
  }
  return icons[type] || 'pi pi-list'
}

const formatTime = (dateString) => {
  const date = new Date(dateString)
  const now = new Date()
  const diff = now - date
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(diff / 3600000)

  if (minutes < 1) return 'Just now'
  if (minutes < 60) return `${minutes}m ago`
  if (hours < 24) return `${hours}h ago`
  return date.toLocaleDateString()
}

onMounted(async () => {
  await platformsStore.fetchAccounts()
  await fetchStreams()

  // Auto-refresh every 60 seconds
  refreshInterval.value = setInterval(refreshStreamData, 60000)
})

onUnmounted(() => {
  if (refreshInterval.value) {
    clearInterval(refreshInterval.value)
  }
})
</script>

<template>
  <DashboardLayout>
    <div class="streamsView apFadeIn">
      <div class="pageHeader">
        <div>
          <h1>Streams</h1>
          <p class="pageDescription">Monitor mentions, hashtags, and keywords in real-time</p>
        </div>
        <Button
          v-if="canUseStreams"
          icon="pi pi-plus"
          label="Add Stream"
          @click="addStreamDialog = true"
        />
      </div>

      <div v-if="!canUseStreams" class="upgradePrompt">
        <i class="pi pi-lock"></i>
        <h3>Streams is a Pro feature</h3>
        <p>Upgrade to Pro to monitor mentions, hashtags, and keywords across your social platforms.</p>
        <router-link to="/settings/billing">
          <Button label="Upgrade to Pro" />
        </router-link>
      </div>

      <div v-else-if="streams.length === 0" class="emptyState">
        <i class="pi pi-inbox"></i>
        <h3>No streams yet</h3>
        <p>Create your first stream to start monitoring social activity.</p>
        <Button label="Create Stream" icon="pi pi-plus" @click="addStreamDialog = true" />
      </div>

      <div v-else class="streamsGrid">
        <div v-for="stream in streams" :key="stream.id" class="streamColumn">
          <div class="streamHeader">
            <div class="streamInfo">
              <i :class="getStreamIcon(stream.type)"></i>
              <span class="streamName">{{ stream.name }}</span>
            </div>
            <div class="streamActions">
              <Button icon="pi pi-refresh" text rounded size="small" @click="refreshStreamData" />
              <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="handleDeleteStream(stream.id)" />
            </div>
          </div>

          <div class="streamItems">
            <div v-if="!stream.items || stream.items.length === 0" class="emptyStream">
              <p>No items yet</p>
            </div>
            <div v-for="item in stream.items" :key="item.id" class="streamItem">
              <div class="itemHeader">
                <img v-if="item.authorAvatar" :src="item.authorAvatar" :alt="item.authorName" class="authorAvatar" />
                <div v-else class="authorAvatarPlaceholder">{{ item.authorName?.charAt(0) || '?' }}</div>
                <div class="authorInfo">
                  <span class="authorName">{{ item.authorName }}</span>
                  <span class="itemTime">{{ formatTime(item.createdAt) }}</span>
                </div>
              </div>
              <p class="itemContent">{{ item.content }}</p>
              <div class="itemMeta">
                <Tag :value="item.platform" size="small" />
                <div class="itemStats">
                  <span v-if="item.likes"><i class="pi pi-heart"></i> {{ item.likes }}</span>
                  <span v-if="item.comments"><i class="pi pi-comment"></i> {{ item.comments }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Add Stream Dialog -->
      <Dialog v-model:visible="addStreamDialog" header="Add Stream" modal :style="{ width: '450px' }">
        <div class="formGroup">
          <label class="apLabel">Stream Name</label>
          <InputText v-model="newStream.name" placeholder="My Stream" class="w-full" />
        </div>

        <div class="formGroup">
          <label class="apLabel">Type</label>
          <Dropdown
            v-model="newStream.type"
            :options="streamTypes"
            optionLabel="label"
            optionValue="value"
            placeholder="Select type"
            class="w-full"
          />
        </div>

        <div class="formGroup">
          <label class="apLabel">Platform</label>
          <Dropdown
            v-model="newStream.platform"
            :options="connectedPlatforms"
            optionLabel="label"
            optionValue="value"
            placeholder="Select platform"
            class="w-full"
          />
        </div>

        <div v-if="newStream.type === 'hashtag' || newStream.type === 'keywords'" class="formGroup">
          <label class="apLabel">{{ newStream.type === 'hashtag' ? 'Hashtag' : 'Keywords' }}</label>
          <InputText
            v-model="newStream.keywords"
            :placeholder="newStream.type === 'hashtag' ? '#trending' : 'keyword1, keyword2'"
            class="w-full"
          />
        </div>

        <template #footer>
          <Button label="Cancel" text @click="addStreamDialog = false" />
          <Button label="Create" @click="handleAddStream" :loading="loading" />
        </template>
      </Dialog>
    </div>
  </DashboardLayout>
</template>

<style scoped>
.streamsView {
  padding: 0;
}

.pageHeader {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
}

.pageHeader h1 {
  margin-bottom: 0.25rem;
}

.pageDescription {
  color: var(--ap-text-muted);
  margin: 0;
}

.upgradePrompt,
.emptyState {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  text-align: center;
  background: var(--ap-surface);
  border: 1px solid var(--ap-border);
  border-radius: var(--ap-radius);
}

.upgradePrompt i,
.emptyState i {
  font-size: 3rem;
  color: var(--ap-text-muted);
  margin-bottom: 1rem;
}

.upgradePrompt h3,
.emptyState h3 {
  margin-bottom: 0.5rem;
}

.upgradePrompt p,
.emptyState p {
  color: var(--ap-text-muted);
  margin-bottom: 1.5rem;
  max-width: 400px;
}

.streamsGrid {
  display: flex;
  gap: 1rem;
  overflow-x: auto;
  padding-bottom: 1rem;
}

.streamColumn {
  flex: 0 0 350px;
  background: var(--ap-surface);
  border: 1px solid var(--ap-border);
  border-radius: var(--ap-radius);
  display: flex;
  flex-direction: column;
  max-height: calc(100vh - 200px);
}

.streamHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border-bottom: 1px solid var(--ap-border);
}

.streamInfo {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.streamInfo i {
  color: var(--ap-accent);
}

.streamName {
  font-weight: 600;
}

.streamActions {
  display: flex;
  gap: 0.25rem;
}

.streamItems {
  flex: 1;
  overflow-y: auto;
  padding: 0.5rem;
}

.emptyStream {
  padding: 2rem;
  text-align: center;
  color: var(--ap-text-muted);
}

.streamItem {
  padding: 0.75rem;
  border-radius: var(--ap-radius-sm);
  margin-bottom: 0.5rem;
  background: var(--ap-bg);
  border: 1px solid var(--ap-border);
}

.itemHeader {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
}

.authorAvatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  object-fit: cover;
}

.authorAvatarPlaceholder {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--ap-accent);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 0.875rem;
}

.authorInfo {
  display: flex;
  flex-direction: column;
}

.authorName {
  font-weight: 500;
  font-size: 0.875rem;
}

.itemTime {
  font-size: 0.75rem;
  color: var(--ap-text-muted);
}

.itemContent {
  font-size: 0.875rem;
  line-height: 1.5;
  margin-bottom: 0.5rem;
}

.itemMeta {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.itemStats {
  display: flex;
  gap: 0.75rem;
  font-size: 0.75rem;
  color: var(--ap-text-muted);
}

.itemStats span {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.formGroup {
  margin-bottom: 1rem;
}

.formGroup label {
  display: block;
  margin-bottom: 0.5rem;
}

@media (max-width: 767px) {
  .streamColumn {
    flex: 0 0 300px;
  }
}
</style>
