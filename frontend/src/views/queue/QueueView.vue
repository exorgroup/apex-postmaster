<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { usePostsStore } from '@/stores/posts'
import { usePlatformLimits } from '@/composables/usePlatformLimits'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import SelectButton from 'primevue/selectbutton'
import MultiSelect from 'primevue/multiselect'
import Calendar from 'primevue/calendar'
import Tag from 'primevue/tag'
import Menu from 'primevue/menu'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

const router = useRouter()
const postsStore = usePostsStore()
const confirm = useConfirm()
const toast = useToast()
const { getPlatform, getMvpPlatforms } = usePlatformLimits()

const statusOptions = ref([
  { label: 'All', value: null },
  { label: 'Scheduled', value: 'scheduled' },
  { label: 'Published', value: 'published' },
  { label: 'Drafts', value: 'draft' },
  { label: 'Failed', value: 'failed' }
])

const selectedStatus = ref(null)
const selectedPlatforms = ref([])
const dateRange = ref(null)
const actionsMenu = ref()
const selectedPost = ref(null)

const platformOptions = computed(() => {
  return getMvpPlatforms().map(p => ({
    label: p.name,
    value: p.id,
    icon: p.icon
  }))
})

const actionsMenuItems = computed(() => [
  {
    label: 'Edit',
    icon: 'pi pi-pencil',
    command: () => editPost(selectedPost.value)
  },
  {
    label: 'Duplicate',
    icon: 'pi pi-copy',
    command: () => duplicatePost(selectedPost.value)
  },
  {
    label: 'Reschedule',
    icon: 'pi pi-clock',
    command: () => reschedulePost(selectedPost.value),
    visible: selectedPost.value?.status === 'scheduled'
  },
  { separator: true },
  {
    label: 'Delete',
    icon: 'pi pi-trash',
    class: 'text-danger',
    command: () => confirmDelete(selectedPost.value)
  }
])

const getStatusSeverity = (status) => {
  const map = {
    draft: 'secondary',
    scheduled: 'info',
    publishing: 'warning',
    published: 'success',
    failed: 'danger'
  }
  return map[status] || 'secondary'
}

const getPlatformIcon = (platformId) => {
  const platform = getPlatform(platformId)
  return platform?.icon || 'pi pi-share-alt'
}

const getPlatformColor = (platformId) => {
  const platform = getPlatform(platformId)
  return platform?.color || '#6366f1'
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  return date.toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const truncateContent = (content, maxLength = 80) => {
  if (!content) return ''
  if (content.length <= maxLength) return content
  return content.substring(0, maxLength) + '...'
}

const openActionsMenu = (event, post) => {
  selectedPost.value = post
  actionsMenu.value.toggle(event)
}

const editPost = (post) => {
  if (post) {
    router.push(`/compose/${post.id}`)
  }
}

const duplicatePost = async (post) => {
  if (!post) return
  const result = await postsStore.duplicatePost(post.id)
  if (result.success) {
    toast.add({
      severity: 'success',
      summary: 'Post duplicated',
      detail: 'A copy has been saved as draft',
      life: 3000
    })
  }
}

const reschedulePost = (post) => {
  if (post) {
    router.push(`/compose/${post.id}`)
  }
}

const confirmDelete = (post) => {
  if (!post) return
  confirm.require({
    message: 'Are you sure you want to delete this post?',
    header: 'Delete Post',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      const result = await postsStore.deletePost(post.id)
      if (result.success) {
        toast.add({
          severity: 'success',
          summary: 'Post deleted',
          life: 3000
        })
      }
    }
  })
}

const applyFilters = () => {
  postsStore.setFilters({
    status: selectedStatus.value,
    platform: selectedPlatforms.value.length > 0 ? selectedPlatforms.value : null,
    dateFrom: dateRange.value?.[0]?.toISOString(),
    dateTo: dateRange.value?.[1]?.toISOString()
  })
  postsStore.fetchPosts()
}

const clearFilters = () => {
  selectedStatus.value = null
  selectedPlatforms.value = []
  dateRange.value = null
  applyFilters()
}

onMounted(() => {
  postsStore.fetchPosts()
})
</script>

<template>
  <DashboardLayout>
    <div class="queueView apFadeIn">
      <div class="apPageHeader">
        <h1 class="apPageTitle">Queue</h1>
        <Button
          label="New Post"
          icon="pi pi-plus"
          @click="router.push('/compose')"
        />
      </div>

      <!-- Filters -->
      <div class="apCard filtersCard mb-4">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-4">
            <label class="apLabel">Status</label>
            <SelectButton
              v-model="selectedStatus"
              :options="statusOptions"
              optionLabel="label"
              optionValue="value"
              @change="applyFilters"
              class="w-full statusFilter"
            />
          </div>
          <div class="col-12 col-md-3">
            <label class="apLabel">Platforms</label>
            <MultiSelect
              v-model="selectedPlatforms"
              :options="platformOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="All platforms"
              @change="applyFilters"
              class="w-full"
            >
              <template #option="{ option }">
                <div class="platformOption">
                  <i :class="option.icon"></i>
                  <span>{{ option.label }}</span>
                </div>
              </template>
            </MultiSelect>
          </div>
          <div class="col-12 col-md-3">
            <label class="apLabel">Date Range</label>
            <Calendar
              v-model="dateRange"
              selectionMode="range"
              dateFormat="dd M yy"
              placeholder="Select dates"
              @date-select="applyFilters"
              class="w-full"
            />
          </div>
          <div class="col-12 col-md-2">
            <Button
              label="Clear"
              icon="pi pi-filter-slash"
              outlined
              @click="clearFilters"
              class="w-full"
            />
          </div>
        </div>
      </div>

      <!-- Posts Table -->
      <div class="apCard tableCard">
        <template v-if="postsStore.loading">
          <div v-for="i in 5" :key="i" class="skeletonRow">
            <Skeleton width="100%" height="60px" />
          </div>
        </template>
        <template v-else-if="postsStore.posts.length === 0">
          <div class="apEmpty">
            <i class="pi pi-inbox"></i>
            <h3>No posts found</h3>
            <p>Create your first post or adjust your filters</p>
            <Button
              label="Create Post"
              icon="pi pi-plus"
              @click="router.push('/compose')"
            />
          </div>
        </template>
        <template v-else>
          <DataTable
            :value="postsStore.posts"
            :paginator="true"
            :rows="20"
            :rowsPerPageOptions="[10, 20, 50]"
            stripedRows
            class="postsTable"
          >
            <Column field="status" header="Status" style="width: 100px">
              <template #body="{ data }">
                <Tag :value="data.status" :severity="getStatusSeverity(data.status)" />
              </template>
            </Column>
            <Column field="content" header="Content" style="min-width: 300px">
              <template #body="{ data }">
                <div class="contentCell">
                  <p class="contentText">{{ truncateContent(data.content) }}</p>
                </div>
              </template>
            </Column>
            <Column field="platforms" header="Platforms" style="width: 150px">
              <template #body="{ data }">
                <div class="platformIcons">
                  <i
                    v-for="platform in (data.platforms || [])"
                    :key="platform"
                    :class="getPlatformIcon(platform)"
                    :style="{ color: getPlatformColor(platform) }"
                    v-tooltip.top="getPlatform(platform)?.name"
                  ></i>
                </div>
              </template>
            </Column>
            <Column field="scheduledAt" header="Scheduled" style="width: 180px">
              <template #body="{ data }">
                <span class="scheduleDate">{{ formatDate(data.scheduledAt) }}</span>
              </template>
            </Column>
            <Column header="Actions" style="width: 80px">
              <template #body="{ data }">
                <Button
                  icon="pi pi-ellipsis-v"
                  text
                  rounded
                  @click="openActionsMenu($event, data)"
                />
              </template>
            </Column>
          </DataTable>
        </template>
      </div>

      <Menu ref="actionsMenu" :model="actionsMenuItems" :popup="true" />
    </div>
  </DashboardLayout>
</template>

<style scoped>
.queueView {
  max-width: 1400px;
}

.filtersCard {
  padding: 1rem 1.5rem;
}

.statusFilter {
  display: flex;
  flex-wrap: wrap;
}

.statusFilter :deep(.p-button) {
  flex: 1;
  min-width: auto;
  font-size: 0.75rem;
  padding: 0.5rem;
}

.platformOption {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.skeletonRow {
  margin-bottom: 0.5rem;
}

.contentCell {
  max-width: 400px;
}

.contentText {
  margin: 0;
  line-height: 1.4;
}

.platformIcons {
  display: flex;
  gap: 0.5rem;
  font-size: 1.125rem;
}

.scheduleDate {
  font-size: 0.875rem;
  color: var(--ap-text-muted);
}

:deep(.p-datatable) {
  background: transparent;
}

:deep(.p-datatable .p-datatable-thead > tr > th) {
  background: transparent;
  border-color: var(--ap-border);
  color: var(--ap-text-muted);
  font-weight: 600;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

:deep(.p-datatable .p-datatable-tbody > tr) {
  background: transparent;
  border-color: var(--ap-border);
}

:deep(.p-datatable .p-datatable-tbody > tr:hover) {
  background: var(--ap-surface-hover);
}

:deep(.p-datatable .p-datatable-tbody > tr > td) {
  border-color: var(--ap-border);
  padding: 1rem;
}

:deep(.p-paginator) {
  background: transparent;
  border: none;
  padding: 1rem 0 0;
}

@media (max-width: 767px) {
  .statusFilter :deep(.p-button) {
    font-size: 0.625rem;
    padding: 0.375rem;
  }
}
</style>
