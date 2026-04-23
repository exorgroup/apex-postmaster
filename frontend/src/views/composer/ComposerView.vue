<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { usePostsStore } from '@/stores/posts'
import { usePlatformsStore } from '@/stores/platforms'
import { usePlatformLimits, MVP_PLATFORMS } from '@/composables/usePlatformLimits'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Calendar from 'primevue/calendar'
import FileUpload from 'primevue/fileupload'
import Chip from 'primevue/chip'
import ToggleButton from 'primevue/togglebutton'
import Message from 'primevue/message'
import { useToast } from 'primevue/usetoast'

const router = useRouter()
const route = useRoute()
const toast = useToast()
const postsStore = usePostsStore()
const platformsStore = usePlatformsStore()
const { getPlatform, getStrictestLimit, getCharPercentage, isOverLimit, getMvpPlatforms } = usePlatformLimits()

const content = ref('')
const selectedPlatforms = ref([])
const scheduledAt = ref(null)
const isScheduled = ref(false)
const mediaFiles = ref([])
const loading = ref(false)
const editMode = ref(false)
const editPostId = ref(null)

const availablePlatforms = computed(() => getMvpPlatforms())

const charLimit = computed(() => {
  if (selectedPlatforms.value.length === 0) return 5000
  return getStrictestLimit(selectedPlatforms.value)
})

const charCount = computed(() => content.value.length)

const charPercentage = computed(() => {
  return Math.min(100, (charCount.value / charLimit.value) * 100)
})

const charColor = computed(() => {
  if (charPercentage.value >= 90) return 'var(--ap-danger)'
  if (charPercentage.value >= 70) return 'var(--ap-warning)'
  return 'var(--ap-accent)'
})

const isOverCharLimit = computed(() => {
  return charCount.value > charLimit.value
})

const extractedHashtags = computed(() => {
  const regex = /#[\w]+/g
  const matches = content.value.match(regex) || []
  return [...new Set(matches)]
})

const platformWarnings = computed(() => {
  const warnings = []
  selectedPlatforms.value.forEach(platformId => {
    const platform = getPlatform(platformId)
    if (platform && content.value.length > platform.charLimit) {
      warnings.push(`Content exceeds ${platform.name} limit of ${platform.charLimit} characters`)
    }
  })
  return warnings
})

const canSubmit = computed(() => {
  return content.value.trim().length > 0 &&
    selectedPlatforms.value.length > 0 &&
    !isOverCharLimit.value &&
    (!isScheduled.value || scheduledAt.value)
})

const togglePlatform = (platformId) => {
  const index = selectedPlatforms.value.indexOf(platformId)
  if (index === -1) {
    selectedPlatforms.value.push(platformId)
  } else {
    selectedPlatforms.value.splice(index, 1)
  }
}

const isPlatformSelected = (platformId) => {
  return selectedPlatforms.value.includes(platformId)
}

const onMediaUpload = (event) => {
  mediaFiles.value = [...mediaFiles.value, ...event.files]
}

const removeMedia = (index) => {
  mediaFiles.value.splice(index, 1)
}

const handleSave = async (asDraft = false) => {
  if (!canSubmit.value && !asDraft) return

  loading.value = true

  const postData = {
    content: content.value,
    platforms: selectedPlatforms.value,
    status: asDraft ? 'draft' : (isScheduled.value ? 'scheduled' : 'draft'),
    scheduledAt: isScheduled.value ? scheduledAt.value?.toISOString() : null,
    hashtags: extractedHashtags.value
  }

  let result
  if (editMode.value && editPostId.value) {
    result = await postsStore.updatePost(editPostId.value, postData)
  } else {
    result = await postsStore.createPost(postData)
  }

  loading.value = false

  if (result.success) {
    toast.add({
      severity: 'success',
      summary: editMode.value ? 'Post updated' : 'Post created',
      detail: asDraft ? 'Saved as draft' : (isScheduled.value ? 'Post scheduled successfully' : 'Saved as draft'),
      life: 3000
    })
    router.push('/queue')
  } else {
    toast.add({
      severity: 'error',
      summary: 'Error',
      detail: result.error,
      life: 5000
    })
  }
}

const handleSchedule = () => {
  handleSave(false)
}

const handleSaveDraft = () => {
  handleSave(true)
}

onMounted(async () => {
  await platformsStore.fetchAccounts()

  if (route.params.id) {
    editMode.value = true
    editPostId.value = route.params.id
    const result = await postsStore.fetchPost(route.params.id)
    if (result.success && result.data) {
      content.value = result.data.content || ''
      selectedPlatforms.value = result.data.platforms || []
      if (result.data.scheduledAt) {
        scheduledAt.value = new Date(result.data.scheduledAt)
        isScheduled.value = true
      }
    }
  }
})
</script>

<template>
  <DashboardLayout>
    <div class="composerView apFadeIn">
      <div class="apPageHeader">
        <h1 class="apPageTitle">{{ editMode ? 'Edit Post' : 'Create Post' }}</h1>
        <div class="headerActions">
          <Button
            label="Save Draft"
            outlined
            @click="handleSaveDraft"
            :loading="loading"
          />
          <Button
            :label="isScheduled ? 'Schedule Post' : 'Save'"
            icon="pi pi-send"
            @click="handleSchedule"
            :disabled="!canSubmit"
            :loading="loading"
          />
        </div>
      </div>

      <div class="row g-4">
        <!-- Left Column: Editor + Previews -->
        <div class="col-12 col-lg-8">
          <!-- Content Editor -->
          <div class="apCard editorCard">
            <div class="editorHeader">
              <span class="apLabel">Content</span>
              <div class="charCounter" :style="{ '--progress': charPercentage + '%', '--color': charColor }">
                <svg viewBox="0 0 36 36" class="charRing">
                  <path
                    class="charRingBg"
                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                  />
                  <path
                    class="charRingProgress"
                    :stroke-dasharray="`${charPercentage}, 100`"
                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                  />
                </svg>
                <span class="charText" :class="{ over: isOverCharLimit }">
                  {{ charCount }}/{{ charLimit }}
                </span>
              </div>
            </div>
            <Textarea
              v-model="content"
              :autoResize="true"
              rows="6"
              placeholder="What would you like to share?"
              class="contentTextarea"
            />

            <!-- Extracted Hashtags -->
            <div v-if="extractedHashtags.length > 0" class="hashtagsPreview">
              <Chip
                v-for="tag in extractedHashtags"
                :key="tag"
                :label="tag"
                class="hashtagChip"
              />
            </div>

            <!-- Platform Warnings -->
            <Message
              v-for="(warning, index) in platformWarnings"
              :key="index"
              severity="warn"
              :closable="false"
              class="platformWarning"
            >
              {{ warning }}
            </Message>
          </div>

          <!-- Media Upload -->
          <div class="apCard mediaCard">
            <span class="apLabel">Media</span>
            <FileUpload
              mode="basic"
              name="media"
              accept="image/*,video/*"
              :maxFileSize="10000000"
              :auto="false"
              chooseLabel="Add Media"
              class="mediaUpload"
              @select="onMediaUpload"
            />
            <div v-if="mediaFiles.length > 0" class="mediaPreview">
              <div v-for="(file, index) in mediaFiles" :key="index" class="mediaItem">
                <img v-if="file.type?.startsWith('image/')" :src="file.objectURL" alt="Preview" />
                <div v-else class="videoPlaceholder">
                  <i class="pi pi-video"></i>
                  <span>{{ file.name }}</span>
                </div>
                <Button
                  icon="pi pi-times"
                  text
                  rounded
                  class="removeMedia"
                  @click="removeMedia(index)"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column: Platforms + Schedule -->
        <div class="col-12 col-lg-4">
          <!-- Platform Selector -->
          <div class="apCard platformsCard">
            <span class="apLabel">Platforms</span>
            <p class="platformsHint">Select where to publish</p>
            <div class="platformsList">
              <div
                v-for="platform in availablePlatforms"
                :key="platform.id"
                class="platformItem"
                :class="{ selected: isPlatformSelected(platform.id) }"
                :style="{ '--platform-color': platform.color }"
                @click="togglePlatform(platform.id)"
              >
                <i :class="platform.icon"></i>
                <div class="platformInfo">
                  <span class="platformName">{{ platform.name }}</span>
                  <span class="platformLimit">{{ platform.charLimit }} chars</span>
                </div>
                <i v-if="isPlatformSelected(platform.id)" class="pi pi-check checkIcon"></i>
              </div>
            </div>
          </div>

          <!-- Schedule -->
          <div class="apCard scheduleCard">
            <span class="apLabel">Schedule</span>
            <div class="scheduleToggle">
              <ToggleButton
                v-model="isScheduled"
                onLabel="Scheduled"
                offLabel="Save as Draft"
                onIcon="pi pi-clock"
                offIcon="pi pi-save"
                class="w-full"
              />
            </div>
            <div v-if="isScheduled" class="schedulePicker">
              <Calendar
                v-model="scheduledAt"
                showTime
                hourFormat="24"
                :minDate="new Date()"
                dateFormat="dd M yy"
                placeholder="Select date and time"
                class="w-full"
              />
            </div>
          </div>

          <!-- AI Assist (placeholder) -->
          <div class="apCard aiCard">
            <span class="apLabel">AI Assist</span>
            <p class="aiHint">Let AI help you create better content</p>
            <div class="aiActions">
              <Button
                label="Generate"
                icon="pi pi-sparkles"
                outlined
                size="small"
                class="w-full mb-2"
                @click="router.push('/ai')"
              />
              <Button
                label="Optimise"
                icon="pi pi-bolt"
                outlined
                size="small"
                class="w-full mb-2"
                :disabled="!content"
              />
              <Button
                label="Hashtags"
                icon="pi pi-hashtag"
                outlined
                size="small"
                class="w-full"
                :disabled="!content"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<style scoped>
.composerView {
  max-width: 1200px;
}

.headerActions {
  display: flex;
  gap: 0.75rem;
}

.editorCard {
  margin-bottom: 1rem;
}

.editorHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.charCounter {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.charRing {
  width: 32px;
  height: 32px;
  transform: rotate(-90deg);
}

.charRingBg {
  fill: none;
  stroke: var(--ap-border);
  stroke-width: 3;
}

.charRingProgress {
  fill: none;
  stroke: var(--color);
  stroke-width: 3;
  stroke-linecap: round;
  transition: stroke-dasharray 0.3s ease;
}

.charText {
  font-size: 0.75rem;
  font-family: var(--ap-font-mono);
  color: var(--ap-text-muted);
}

.charText.over {
  color: var(--ap-danger);
  font-weight: 600;
}

.contentTextarea {
  width: 100%;
  min-height: 150px;
  font-size: 1rem;
  line-height: 1.6;
}

.hashtagsPreview {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid var(--ap-border);
}

.hashtagChip {
  background: var(--ap-accent-subtle);
  color: var(--ap-accent);
}

.platformWarning {
  margin-top: 0.75rem;
}

.mediaCard {
  margin-bottom: 1rem;
}

.mediaUpload {
  margin-top: 0.75rem;
}

.mediaPreview {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-top: 1rem;
}

.mediaItem {
  position: relative;
  width: 100px;
  height: 100px;
  border-radius: var(--ap-radius-sm);
  overflow: hidden;
  background: var(--ap-surface-hover);
}

.mediaItem img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.videoPlaceholder {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  font-size: 0.625rem;
  color: var(--ap-text-muted);
  padding: 0.5rem;
  text-align: center;
}

.videoPlaceholder i {
  font-size: 1.5rem;
  margin-bottom: 0.25rem;
}

.removeMedia {
  position: absolute;
  top: 0.25rem;
  right: 0.25rem;
  background: rgba(0, 0, 0, 0.5) !important;
  color: white !important;
}

.platformsCard,
.scheduleCard,
.aiCard {
  margin-bottom: 1rem;
}

.platformsHint,
.aiHint {
  font-size: 0.75rem;
  color: var(--ap-text-muted);
  margin: 0.5rem 0 1rem;
}

.platformsList {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.platformItem {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem;
  background: var(--ap-surface-hover);
  border: 1px solid transparent;
  border-radius: var(--ap-radius-sm);
  cursor: pointer;
  transition: all var(--ap-transition);
}

.platformItem:hover {
  border-color: var(--ap-border-hover);
}

.platformItem.selected {
  border-color: var(--platform-color);
  background: rgba(var(--platform-color), 0.1);
}

.platformItem i:first-child {
  font-size: 1.25rem;
  color: var(--platform-color);
  width: 1.5rem;
  text-align: center;
}

.platformInfo {
  flex: 1;
}

.platformName {
  display: block;
  font-weight: 500;
  font-size: 0.875rem;
}

.platformLimit {
  display: block;
  font-size: 0.625rem;
  color: var(--ap-text-muted);
}

.checkIcon {
  color: var(--platform-color);
}

.scheduleToggle {
  margin-top: 0.75rem;
}

.schedulePicker {
  margin-top: 1rem;
}

.aiActions {
  margin-top: 0.75rem;
}

@media (max-width: 575px) {
  .headerActions {
    flex-direction: column;
    width: 100%;
  }

  .headerActions .p-button {
    width: 100%;
  }
}
</style>
