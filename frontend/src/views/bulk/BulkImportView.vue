<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { usePostsStore } from '@/stores/posts'
import { usePlatformsStore } from '@/stores/platforms'
import { usePermissions } from '@/composables/usePermissions'
import { usePlatformLimits } from '@/composables/usePlatformLimits'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import FileUpload from 'primevue/fileupload'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dropdown from 'primevue/dropdown'
import Tag from 'primevue/tag'
import Checkbox from 'primevue/checkbox'
import { useToast } from 'primevue/usetoast'

const router = useRouter()
const posts = usePostsStore()
const platformsStore = usePlatformsStore()
const { canUseBulkImport } = usePermissions()
const { getMvpPlatforms, getPlatformIcon } = usePlatformLimits()
const toast = useToast()

const step = ref(1)
const loading = ref(false)
const importedPosts = ref([])
const selectedPosts = ref([])
const defaultPlatforms = ref([])

const platforms = getMvpPlatforms()

const connectedPlatforms = computed(() => {
  return platformsStore.accounts.filter(a => a.isActive)
})

const platformOptions = computed(() => {
  return connectedPlatforms.value.map(p => ({
    label: p.platformDisplayName || p.platform,
    value: p.platform
  }))
})

const validPosts = computed(() => {
  return importedPosts.value.filter(p => p.content && p.content.trim())
})

const handleFileUpload = async (event) => {
  const file = event.files[0]
  if (!file) return

  loading.value = true

  try {
    const text = await file.text()
    const rows = parseCSV(text)

    if (rows.length === 0) {
      toast.add({ severity: 'warn', summary: 'Empty file', detail: 'No posts found in the CSV', life: 5000 })
      return
    }

    importedPosts.value = rows.map((row, index) => ({
      id: index + 1,
      content: row.content || row.text || row.post || '',
      scheduledAt: row.scheduled_at || row.date || row.scheduledAt || null,
      platforms: defaultPlatforms.value,
      status: 'pending',
      errors: []
    }))

    // Validate posts
    importedPosts.value.forEach(post => {
      if (!post.content) {
        post.errors.push('Content is required')
        post.status = 'error'
      }
      if (post.content && post.content.length > 2000) {
        post.errors.push('Content exceeds 2000 characters')
        post.status = 'error'
      }
    })

    step.value = 2
    toast.add({ severity: 'success', summary: 'File parsed', detail: `Found ${rows.length} posts`, life: 3000 })
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Parse error', detail: 'Failed to parse CSV file', life: 5000 })
  } finally {
    loading.value = false
  }
}

const parseCSV = (text) => {
  const lines = text.split('\n').filter(line => line.trim())
  if (lines.length < 2) return []

  const headers = lines[0].split(',').map(h => h.trim().toLowerCase().replace(/['"]/g, ''))
  const rows = []

  for (let i = 1; i < lines.length; i++) {
    const values = parseCSVLine(lines[i])
    const row = {}
    headers.forEach((header, index) => {
      row[header] = values[index] || ''
    })
    rows.push(row)
  }

  return rows
}

const parseCSVLine = (line) => {
  const values = []
  let current = ''
  let inQuotes = false

  for (let i = 0; i < line.length; i++) {
    const char = line[i]
    if (char === '"') {
      inQuotes = !inQuotes
    } else if (char === ',' && !inQuotes) {
      values.push(current.trim())
      current = ''
    } else {
      current += char
    }
  }
  values.push(current.trim())

  return values
}

const updatePostPlatforms = (post, platforms) => {
  post.platforms = platforms
}

const handleSelectAll = (checked) => {
  if (checked) {
    selectedPosts.value = validPosts.value.map(p => p.id)
  } else {
    selectedPosts.value = []
  }
}

const handleImport = async () => {
  const postsToImport = importedPosts.value.filter(
    p => selectedPosts.value.includes(p.id) && p.status !== 'error'
  )

  if (postsToImport.length === 0) {
    toast.add({ severity: 'warn', summary: 'No posts selected', detail: 'Please select valid posts to import', life: 3000 })
    return
  }

  loading.value = true
  let successCount = 0
  let errorCount = 0

  for (const post of postsToImport) {
    try {
      const result = await posts.createPost({
        content: post.content,
        platforms: post.platforms.length ? post.platforms : defaultPlatforms.value,
        scheduledAt: post.scheduledAt,
        status: post.scheduledAt ? 'scheduled' : 'draft'
      })

      if (result.success) {
        post.status = 'imported'
        successCount++
      } else {
        post.status = 'error'
        post.errors.push(result.error)
        errorCount++
      }
    } catch (error) {
      post.status = 'error'
      post.errors.push('Import failed')
      errorCount++
    }
  }

  loading.value = false

  if (successCount > 0) {
    toast.add({
      severity: 'success',
      summary: 'Import complete',
      detail: `${successCount} posts imported${errorCount > 0 ? `, ${errorCount} failed` : ''}`,
      life: 5000
    })
  }

  if (errorCount > 0 && successCount === 0) {
    toast.add({ severity: 'error', summary: 'Import failed', detail: 'All posts failed to import', life: 5000 })
  }

  step.value = 3
}

const getStatusSeverity = (status) => {
  const map = {
    pending: 'secondary',
    imported: 'success',
    error: 'danger'
  }
  return map[status] || 'secondary'
}

const downloadTemplate = () => {
  const template = 'content,scheduled_at\n"Your post content here","2024-12-25 10:00:00"\n"Another post",""\n'
  const blob = new Blob([template], { type: 'text/csv' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'apex_postmaster_import_template.csv'
  a.click()
  URL.revokeObjectURL(url)
}

const resetImport = () => {
  step.value = 1
  importedPosts.value = []
  selectedPosts.value = []
}
</script>

<template>
  <DashboardLayout>
    <div class="bulkImportView apFadeIn">
      <div class="pageHeader">
        <div>
          <h1>Bulk Import</h1>
          <p class="pageDescription">Import multiple posts from a CSV file</p>
        </div>
      </div>

      <div v-if="!canUseBulkImport" class="upgradePrompt">
        <i class="pi pi-lock"></i>
        <h3>Bulk Import is a Pro feature</h3>
        <p>Upgrade to Pro to import multiple posts at once from CSV files.</p>
        <router-link to="/settings/billing">
          <Button label="Upgrade to Pro" />
        </router-link>
      </div>

      <template v-else>
        <!-- Step 1: Upload -->
        <div v-if="step === 1" class="uploadStep">
          <div class="uploadCard">
            <h3>Upload CSV File</h3>
            <p>Your CSV should have columns: <code>content</code>, <code>scheduled_at</code> (optional)</p>

            <div class="defaultPlatforms">
              <label class="apLabel">Default Platforms</label>
              <div class="platformCheckboxes">
                <div v-for="platform in platformOptions" :key="platform.value" class="platformCheckbox">
                  <Checkbox
                    v-model="defaultPlatforms"
                    :inputId="platform.value"
                    :value="platform.value"
                  />
                  <label :for="platform.value">{{ platform.label }}</label>
                </div>
              </div>
            </div>

            <FileUpload
              mode="basic"
              accept=".csv"
              :maxFileSize="5000000"
              chooseLabel="Select CSV File"
              @select="handleFileUpload"
              :auto="true"
              class="uploadButton"
            />

            <div class="templateLink">
              <Button label="Download Template" icon="pi pi-download" text size="small" @click="downloadTemplate" />
            </div>
          </div>
        </div>

        <!-- Step 2: Review -->
        <div v-if="step === 2" class="reviewStep">
          <div class="reviewHeader">
            <h3>Review Posts</h3>
            <div class="reviewActions">
              <Checkbox
                :modelValue="selectedPosts.length === validPosts.length && validPosts.length > 0"
                @update:modelValue="handleSelectAll"
                inputId="selectAll"
                binary
              />
              <label for="selectAll">Select All Valid ({{ validPosts.length }})</label>
            </div>
          </div>

          <DataTable
            :value="importedPosts"
            v-model:selection="selectedPosts"
            dataKey="id"
            :paginator="importedPosts.length > 10"
            :rows="10"
            class="postsTable"
          >
            <Column selectionMode="multiple" headerStyle="width: 3rem" />
            <Column field="content" header="Content">
              <template #body="{ data }">
                <div class="contentPreview">
                  {{ data.content?.substring(0, 100) }}{{ data.content?.length > 100 ? '...' : '' }}
                </div>
              </template>
            </Column>
            <Column field="platforms" header="Platforms">
              <template #body="{ data }">
                <Dropdown
                  v-model="data.platforms"
                  :options="platformOptions"
                  optionLabel="label"
                  optionValue="value"
                  multiple
                  placeholder="Select platforms"
                  class="platformDropdown"
                  @change="updatePostPlatforms(data, $event.value)"
                />
              </template>
            </Column>
            <Column field="scheduledAt" header="Scheduled">
              <template #body="{ data }">
                {{ data.scheduledAt || 'Draft' }}
              </template>
            </Column>
            <Column field="status" header="Status">
              <template #body="{ data }">
                <Tag :value="data.status" :severity="getStatusSeverity(data.status)" />
                <div v-if="data.errors.length" class="errorList">
                  <small v-for="error in data.errors" :key="error" class="errorText">{{ error }}</small>
                </div>
              </template>
            </Column>
          </DataTable>

          <div class="reviewFooter">
            <Button label="Back" text @click="step = 1" />
            <Button
              :label="`Import ${selectedPosts.length} Posts`"
              icon="pi pi-upload"
              @click="handleImport"
              :loading="loading"
              :disabled="selectedPosts.length === 0"
            />
          </div>
        </div>

        <!-- Step 3: Complete -->
        <div v-if="step === 3" class="completeStep">
          <div class="completeCard">
            <i class="pi pi-check-circle"></i>
            <h3>Import Complete</h3>
            <p>{{ importedPosts.filter(p => p.status === 'imported').length }} posts have been imported successfully.</p>

            <div class="completeActions">
              <Button label="Import More" outlined @click="resetImport" />
              <Button label="View Queue" @click="router.push('/queue')" />
            </div>
          </div>
        </div>
      </template>
    </div>
  </DashboardLayout>
</template>

<style scoped>
.bulkImportView {
  padding: 0;
}

.pageHeader {
  margin-bottom: 2rem;
}

.pageHeader h1 {
  margin-bottom: 0.25rem;
}

.pageDescription {
  color: var(--ap-text-muted);
  margin: 0;
}

.upgradePrompt {
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

.upgradePrompt i {
  font-size: 3rem;
  color: var(--ap-text-muted);
  margin-bottom: 1rem;
}

.upgradePrompt h3 {
  margin-bottom: 0.5rem;
}

.upgradePrompt p {
  color: var(--ap-text-muted);
  margin-bottom: 1.5rem;
  max-width: 400px;
}

.uploadStep {
  display: flex;
  justify-content: center;
}

.uploadCard {
  background: var(--ap-surface);
  border: 1px solid var(--ap-border);
  border-radius: var(--ap-radius);
  padding: 2rem;
  text-align: center;
  max-width: 500px;
  width: 100%;
}

.uploadCard h3 {
  margin-bottom: 0.5rem;
}

.uploadCard p {
  color: var(--ap-text-muted);
  margin-bottom: 1.5rem;
}

.uploadCard code {
  background: var(--ap-bg);
  padding: 0.125rem 0.375rem;
  border-radius: 4px;
  font-family: var(--ap-font-mono);
  font-size: 0.875rem;
}

.defaultPlatforms {
  text-align: left;
  margin-bottom: 1.5rem;
}

.defaultPlatforms label {
  display: block;
  margin-bottom: 0.5rem;
}

.platformCheckboxes {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
}

.platformCheckbox {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.uploadButton {
  margin-bottom: 1rem;
}

.templateLink {
  margin-top: 1rem;
}

.reviewStep {
  background: var(--ap-surface);
  border: 1px solid var(--ap-border);
  border-radius: var(--ap-radius);
  padding: 1.5rem;
}

.reviewHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.reviewHeader h3 {
  margin: 0;
}

.reviewActions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.postsTable {
  margin-bottom: 1rem;
}

.contentPreview {
  font-size: 0.875rem;
  max-width: 300px;
}

.platformDropdown {
  min-width: 150px;
}

.errorList {
  margin-top: 0.25rem;
}

.errorText {
  display: block;
  color: var(--ap-danger);
}

.reviewFooter {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  padding-top: 1rem;
  border-top: 1px solid var(--ap-border);
}

.completeStep {
  display: flex;
  justify-content: center;
}

.completeCard {
  background: var(--ap-surface);
  border: 1px solid var(--ap-border);
  border-radius: var(--ap-radius);
  padding: 3rem 2rem;
  text-align: center;
  max-width: 400px;
}

.completeCard i {
  font-size: 4rem;
  color: var(--ap-success);
  margin-bottom: 1rem;
}

.completeCard h3 {
  margin-bottom: 0.5rem;
}

.completeCard p {
  color: var(--ap-text-muted);
  margin-bottom: 1.5rem;
}

.completeActions {
  display: flex;
  justify-content: center;
  gap: 0.75rem;
}

:deep(.p-datatable) {
  background: transparent;
}

:deep(.p-datatable .p-datatable-thead > tr > th) {
  background: var(--ap-bg);
  border-color: var(--ap-border);
}

:deep(.p-datatable .p-datatable-tbody > tr) {
  background: var(--ap-surface);
}

:deep(.p-datatable .p-datatable-tbody > tr > td) {
  border-color: var(--ap-border);
}
</style>
