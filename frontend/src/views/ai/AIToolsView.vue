<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAIStore } from '@/stores/ai'
import { usePlatformLimits } from '@/composables/usePlatformLimits'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import Textarea from 'primevue/textarea'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Button from 'primevue/button'
import Chip from 'primevue/chip'
import Chart from 'primevue/chart'
import Message from 'primevue/message'
import { useToast } from 'primevue/usetoast'

const router = useRouter()
const aiStore = useAIStore()
const toast = useToast()
const { getMvpPlatforms } = usePlatformLimits()

const platforms = getMvpPlatforms().map(p => ({ label: p.name, value: p.id }))
const tones = [
  { label: 'Professional', value: 'professional' },
  { label: 'Casual', value: 'casual' },
  { label: 'Friendly', value: 'friendly' },
  { label: 'Humorous', value: 'humorous' },
  { label: 'Inspiring', value: 'inspiring' }
]

const createTopic = ref('')
const createPlatform = ref('facebook')
const createTone = ref('professional')
const createResult = ref('')

const optimiseContent = ref('')
const optimisePlatform = ref('facebook')
const optimiseResult = ref('')

const repurposeContent = ref('')
const repurposeFrom = ref('facebook')
const repurposeTo = ref(['instagram', 'youtube'])
const repurposeResults = ref([])

const hashtagContent = ref('')
const hashtagPlatform = ref('instagram')
const hashtagResults = ref({ primary: [], secondary: [], niche: [] })

const threadContent = ref('')
const threadResults = ref([])

const scoreContent = ref('')
const scorePlatform = ref('facebook')
const scoreResults = ref(null)

const scoreChartData = computed(() => {
  if (!scoreResults.value) return null
  return {
    labels: ['Hook', 'Clarity', 'Engagement', 'Hashtags', 'CTA'],
    datasets: [{
      label: 'Score',
      data: [
        scoreResults.value.hook || 0,
        scoreResults.value.clarity || 0,
        scoreResults.value.engagement || 0,
        scoreResults.value.hashtags || 0,
        scoreResults.value.cta || 0
      ],
      backgroundColor: 'rgba(99, 102, 241, 0.2)',
      borderColor: '#6366f1',
      pointBackgroundColor: '#6366f1'
    }]
  }
})

const scoreChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  scales: {
    r: {
      beginAtZero: true,
      max: 10,
      ticks: { display: false },
      grid: { color: 'rgba(255, 255, 255, 0.1)' },
      pointLabels: { color: '#7a7f8e' }
    }
  },
  plugins: { legend: { display: false } }
}

const handleCreate = async () => {
  if (!createTopic.value) return
  const result = await aiStore.createPost(createTopic.value, createPlatform.value, createTone.value)
  if (result.success) {
    createResult.value = result.data.content || result.data.output
    toast.add({ severity: 'success', summary: 'Content generated', life: 3000 })
  } else {
    toast.add({ severity: 'error', summary: 'Error', detail: result.error, life: 5000 })
  }
}

const handleOptimise = async () => {
  if (!optimiseContent.value) return
  const result = await aiStore.optimise(optimiseContent.value, optimisePlatform.value)
  if (result.success) {
    optimiseResult.value = result.data.content || result.data.output
    toast.add({ severity: 'success', summary: 'Content optimised', life: 3000 })
  } else {
    toast.add({ severity: 'error', summary: 'Error', detail: result.error, life: 5000 })
  }
}

const handleRepurpose = async () => {
  if (!repurposeContent.value) return
  const result = await aiStore.repurpose(repurposeContent.value, repurposeFrom.value, repurposeTo.value)
  if (result.success) {
    repurposeResults.value = result.data.versions || []
    toast.add({ severity: 'success', summary: 'Content repurposed', life: 3000 })
  } else {
    toast.add({ severity: 'error', summary: 'Error', detail: result.error, life: 5000 })
  }
}

const handleHashtags = async () => {
  if (!hashtagContent.value) return
  const result = await aiStore.suggestHashtags(hashtagContent.value, hashtagPlatform.value)
  if (result.success) {
    hashtagResults.value = result.data || { primary: [], secondary: [], niche: [] }
    toast.add({ severity: 'success', summary: 'Hashtags generated', life: 3000 })
  } else {
    toast.add({ severity: 'error', summary: 'Error', detail: result.error, life: 5000 })
  }
}

const handleThread = async () => {
  if (!threadContent.value) return
  const result = await aiStore.generateThread(threadContent.value)
  if (result.success) {
    threadResults.value = result.data.tweets || result.data.thread || []
    toast.add({ severity: 'success', summary: 'Thread generated', life: 3000 })
  } else {
    toast.add({ severity: 'error', summary: 'Error', detail: result.error, life: 5000 })
  }
}

const handleScore = async () => {
  if (!scoreContent.value) return
  const result = await aiStore.scorePost(scoreContent.value, scorePlatform.value)
  if (result.success) {
    scoreResults.value = result.data
    toast.add({ severity: 'success', summary: 'Post scored', life: 3000 })
  } else {
    toast.add({ severity: 'error', summary: 'Error', detail: result.error, life: 5000 })
  }
}

const copyToClipboard = (text) => {
  navigator.clipboard.writeText(text)
  toast.add({ severity: 'success', summary: 'Copied to clipboard', life: 2000 })
}

const useInComposer = (content) => {
  router.push({ path: '/compose', query: { content: encodeURIComponent(content) } })
}
</script>

<template>
  <DashboardLayout>
    <div class="aiToolsView apFadeIn">
      <div class="apPageHeader">
        <div>
          <h1 class="apPageTitle">AI Tools</h1>
          <p class="apTextMuted">{{ aiStore.remainingGenerations }} generations remaining this month</p>
        </div>
      </div>

      <div class="apCard">
        <TabView>
          <!-- Create Tab -->
          <TabPanel header="Create">
            <div class="toolSection">
              <p class="toolDescription">Generate a post from a topic or idea.</p>
              <div class="row g-3">
                <div class="col-12">
                  <label class="apLabel">Topic or idea</label>
                  <InputText
                    v-model="createTopic"
                    placeholder="e.g., New product launch, company milestone, industry tips..."
                    class="w-full"
                  />
                </div>
                <div class="col-12 col-md-6">
                  <label class="apLabel">Platform</label>
                  <Dropdown
                    v-model="createPlatform"
                    :options="platforms"
                    optionLabel="label"
                    optionValue="value"
                    class="w-full"
                  />
                </div>
                <div class="col-12 col-md-6">
                  <label class="apLabel">Tone</label>
                  <Dropdown
                    v-model="createTone"
                    :options="tones"
                    optionLabel="label"
                    optionValue="value"
                    class="w-full"
                  />
                </div>
                <div class="col-12">
                  <Button
                    label="Generate"
                    icon="pi pi-sparkles"
                    @click="handleCreate"
                    :loading="aiStore.loading"
                    :disabled="!createTopic"
                  />
                </div>
              </div>

              <div v-if="createResult" class="resultSection">
                <label class="apLabel">Generated Content</label>
                <div class="resultBox">
                  <p>{{ createResult }}</p>
                </div>
                <div class="resultActions">
                  <Button label="Copy" icon="pi pi-copy" outlined size="small" @click="copyToClipboard(createResult)" />
                  <Button label="Use in Composer" icon="pi pi-pencil" size="small" @click="useInComposer(createResult)" />
                </div>
              </div>
            </div>
          </TabPanel>

          <!-- Optimise Tab -->
          <TabPanel header="Optimise">
            <div class="toolSection">
              <p class="toolDescription">Improve your existing content for better engagement.</p>
              <div class="row g-3">
                <div class="col-12">
                  <label class="apLabel">Your content</label>
                  <Textarea
                    v-model="optimiseContent"
                    rows="4"
                    placeholder="Paste your content here..."
                    class="w-full"
                  />
                </div>
                <div class="col-12 col-md-6">
                  <label class="apLabel">Platform</label>
                  <Dropdown
                    v-model="optimisePlatform"
                    :options="platforms"
                    optionLabel="label"
                    optionValue="value"
                    class="w-full"
                  />
                </div>
                <div class="col-12">
                  <Button
                    label="Optimise"
                    icon="pi pi-bolt"
                    @click="handleOptimise"
                    :loading="aiStore.loading"
                    :disabled="!optimiseContent"
                  />
                </div>
              </div>

              <div v-if="optimiseResult" class="resultSection">
                <div class="row g-3">
                  <div class="col-12 col-md-6">
                    <label class="apLabel">Original</label>
                    <div class="resultBox original">
                      <p>{{ optimiseContent }}</p>
                    </div>
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="apLabel">Optimised</label>
                    <div class="resultBox optimised">
                      <p>{{ optimiseResult }}</p>
                    </div>
                  </div>
                </div>
                <div class="resultActions">
                  <Button label="Copy" icon="pi pi-copy" outlined size="small" @click="copyToClipboard(optimiseResult)" />
                  <Button label="Use in Composer" icon="pi pi-pencil" size="small" @click="useInComposer(optimiseResult)" />
                </div>
              </div>
            </div>
          </TabPanel>

          <!-- Hashtags Tab -->
          <TabPanel header="Hashtags">
            <div class="toolSection">
              <p class="toolDescription">Get relevant hashtag suggestions for your content.</p>
              <div class="row g-3">
                <div class="col-12">
                  <label class="apLabel">Your content</label>
                  <Textarea
                    v-model="hashtagContent"
                    rows="4"
                    placeholder="Paste your content here..."
                    class="w-full"
                  />
                </div>
                <div class="col-12 col-md-6">
                  <label class="apLabel">Platform</label>
                  <Dropdown
                    v-model="hashtagPlatform"
                    :options="platforms"
                    optionLabel="label"
                    optionValue="value"
                    class="w-full"
                  />
                </div>
                <div class="col-12">
                  <Button
                    label="Suggest Hashtags"
                    icon="pi pi-hashtag"
                    @click="handleHashtags"
                    :loading="aiStore.loading"
                    :disabled="!hashtagContent"
                  />
                </div>
              </div>

              <div v-if="hashtagResults.primary?.length > 0" class="resultSection">
                <div class="hashtagGroup">
                  <label class="apLabel">Primary (High reach)</label>
                  <div class="hashtagChips">
                    <Chip
                      v-for="tag in hashtagResults.primary"
                      :key="tag"
                      :label="tag"
                      class="hashtagChip primary"
                      @click="copyToClipboard(tag)"
                    />
                  </div>
                </div>
                <div v-if="hashtagResults.secondary?.length > 0" class="hashtagGroup">
                  <label class="apLabel">Secondary (Medium reach)</label>
                  <div class="hashtagChips">
                    <Chip
                      v-for="tag in hashtagResults.secondary"
                      :key="tag"
                      :label="tag"
                      class="hashtagChip secondary"
                      @click="copyToClipboard(tag)"
                    />
                  </div>
                </div>
                <div v-if="hashtagResults.niche?.length > 0" class="hashtagGroup">
                  <label class="apLabel">Niche (Targeted)</label>
                  <div class="hashtagChips">
                    <Chip
                      v-for="tag in hashtagResults.niche"
                      :key="tag"
                      :label="tag"
                      class="hashtagChip niche"
                      @click="copyToClipboard(tag)"
                    />
                  </div>
                </div>
              </div>
            </div>
          </TabPanel>

          <!-- Score Tab -->
          <TabPanel header="Score">
            <div class="toolSection">
              <p class="toolDescription">Get an engagement prediction score for your post.</p>
              <div class="row g-3">
                <div class="col-12">
                  <label class="apLabel">Your content</label>
                  <Textarea
                    v-model="scoreContent"
                    rows="4"
                    placeholder="Paste your content here..."
                    class="w-full"
                  />
                </div>
                <div class="col-12 col-md-6">
                  <label class="apLabel">Platform</label>
                  <Dropdown
                    v-model="scorePlatform"
                    :options="platforms"
                    optionLabel="label"
                    optionValue="value"
                    class="w-full"
                  />
                </div>
                <div class="col-12">
                  <Button
                    label="Score Post"
                    icon="pi pi-chart-bar"
                    @click="handleScore"
                    :loading="aiStore.loading"
                    :disabled="!scoreContent"
                  />
                </div>
              </div>

              <div v-if="scoreResults" class="resultSection">
                <div class="row g-3">
                  <div class="col-12 col-md-6">
                    <div class="scoreChart">
                      <Chart type="radar" :data="scoreChartData" :options="scoreChartOptions" />
                    </div>
                  </div>
                  <div class="col-12 col-md-6">
                    <div class="scoreDetails">
                      <div class="overallScore">
                        <span class="apMetric">{{ scoreResults.overall || 0 }}</span>
                        <span class="apLabel">/10 Overall</span>
                      </div>
                      <div v-if="scoreResults.tips?.length > 0" class="scoreTips">
                        <label class="apLabel">Tips to improve</label>
                        <ul>
                          <li v-for="(tip, i) in scoreResults.tips" :key="i">{{ tip }}</li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </TabPanel>
        </TabView>
      </div>
    </div>
  </DashboardLayout>
</template>

<style scoped>
.aiToolsView {
  max-width: 1000px;
}

.aiToolsView :deep(.p-textarea) {
  width: 100% !important;
  min-height: 250px !important;
}

.toolSection {
  padding: 1rem 0;
}

.toolDescription {
  color: var(--ap-text-muted);
  margin-bottom: 1.5rem;
}

.resultSection {
  margin-top: 2rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--ap-border);
}

.resultBox {
  background: var(--ap-surface-hover);
  border-radius: var(--ap-radius-sm);
  padding: 1rem;
  margin-top: 0.5rem;
}

.resultBox p {
  margin: 0;
  white-space: pre-wrap;
}

.resultBox.original {
  opacity: 0.7;
}

.resultBox.optimised {
  border-left: 3px solid var(--ap-success);
}

.resultActions {
  display: flex;
  gap: 0.75rem;
  margin-top: 1rem;
}

.hashtagGroup {
  margin-bottom: 1rem;
}

.hashtagChips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.hashtagChip {
  cursor: pointer;
  transition: all var(--ap-transition);
}

.hashtagChip:hover {
  transform: scale(1.05);
}

.hashtagChip.primary {
  background: var(--ap-accent-subtle);
  color: var(--ap-accent);
}

.hashtagChip.secondary {
  background: rgba(34, 197, 94, 0.1);
  color: var(--ap-success);
}

.hashtagChip.niche {
  background: rgba(245, 158, 11, 0.1);
  color: var(--ap-warning);
}

.scoreChart {
  height: 250px;
}

.scoreDetails {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.overallScore {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
}

.scoreTips ul {
  margin: 0.5rem 0 0;
  padding-left: 1.25rem;
}

.scoreTips li {
  margin-bottom: 0.5rem;
  color: var(--ap-text-muted);
  font-size: 0.875rem;
}

:deep(.p-tabview-nav) {
  border-color: var(--ap-border);
}

:deep(.p-tabview-nav-link) {
  color: var(--ap-text-muted) !important;
}

:deep(.p-tabview-nav li.p-highlight .p-tabview-nav-link) {
  color: var(--ap-text) !important;
  border-color: var(--ap-accent) !important;
}
</style>
