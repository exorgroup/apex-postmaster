<script setup>
import { ref, onMounted } from 'vue'
import { useAnalyticsStore } from '@/stores/analytics'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Tag from 'primevue/tag'

const analyticsStore = useAnalyticsStore()
const searchQuery = ref('')

const hashtags = ref([
  { tag: '#marketing', postCount: 1250000, avgEngagement: 4.2, growth: 12.5, sentiment: 0.75 },
  { tag: '#socialmedia', postCount: 890000, avgEngagement: 3.8, growth: 8.2, sentiment: 0.68 },
  { tag: '#contentcreator', postCount: 650000, avgEngagement: 5.1, growth: 15.3, sentiment: 0.82 },
  { tag: '#digitalmarketing', postCount: 520000, avgEngagement: 3.5, growth: 6.8, sentiment: 0.71 },
  { tag: '#growthhacking', postCount: 320000, avgEngagement: 4.8, growth: 22.1, sentiment: 0.79 }
])

const formatNumber = (num) => {
  if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M'
  if (num >= 1000) return (num / 1000).toFixed(0) + 'K'
  return num.toString()
}

const getSentimentSeverity = (score) => {
  if (score >= 0.7) return 'success'
  if (score >= 0.4) return 'warning'
  return 'danger'
}

onMounted(() => {
  analyticsStore.fetchHashtags()
})
</script>

<template>
  <DashboardLayout>
    <div class="hashtagView apFadeIn">
      <div class="apPageHeader">
        <h1 class="apPageTitle">Hashtag Analytics</h1>
      </div>

      <div class="apCard">
        <div class="tableHeader mb-3">
          <InputText v-model="searchQuery" placeholder="Search hashtags..." class="searchInput" />
        </div>

        <DataTable :value="hashtags" :paginator="true" :rows="10" stripedRows>
          <Column field="tag" header="Hashtag" sortable>
            <template #body="{ data }">
              <span class="hashtagName">{{ data.tag }}</span>
            </template>
          </Column>
          <Column field="postCount" header="Post Count" sortable>
            <template #body="{ data }">
              <span class="apMono">{{ formatNumber(data.postCount) }}</span>
            </template>
          </Column>
          <Column field="avgEngagement" header="Avg. Engagement" sortable>
            <template #body="{ data }">
              <span class="apMono">{{ data.avgEngagement }}%</span>
            </template>
          </Column>
          <Column field="growth" header="Growth" sortable>
            <template #body="{ data }">
              <span class="growthValue" :class="{ positive: data.growth > 0 }">
                {{ data.growth > 0 ? '+' : '' }}{{ data.growth }}%
              </span>
            </template>
          </Column>
          <Column field="sentiment" header="Sentiment" sortable>
            <template #body="{ data }">
              <Tag :value="(data.sentiment * 100).toFixed(0) + '%'" :severity="getSentimentSeverity(data.sentiment)" />
            </template>
          </Column>
        </DataTable>
      </div>
    </div>
  </DashboardLayout>
</template>

<style scoped>
.hashtagView {
  max-width: 1200px;
}

.tableHeader {
  display: flex;
  justify-content: flex-end;
}

.searchInput {
  max-width: 300px;
}

.hashtagName {
  font-weight: 500;
  color: var(--ap-accent);
}

.growthValue {
  color: var(--ap-danger);
}

.growthValue.positive {
  color: var(--ap-success);
}
</style>
