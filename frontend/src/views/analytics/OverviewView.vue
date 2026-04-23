<script setup>
import { ref, onMounted } from 'vue'
import { useAnalyticsStore } from '@/stores/analytics'
import { usePlatformLimits } from '@/composables/usePlatformLimits'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import Chart from 'primevue/chart'
import Dropdown from 'primevue/dropdown'
import Skeleton from 'primevue/skeleton'

const analyticsStore = useAnalyticsStore()
const { getPlatform, getMvpPlatforms } = usePlatformLimits()

const dateRange = ref('7d')
const dateRangeOptions = [
  { label: 'Last 7 days', value: '7d' },
  { label: 'Last 30 days', value: '30d' },
  { label: 'Last 90 days', value: '90d' }
]

const metrics = ref([
  { label: 'Total Impressions', value: '24.5K', change: '+12%', positive: true },
  { label: 'Total Engagements', value: '3.2K', change: '+8%', positive: true },
  { label: 'Posts Published', value: '47', change: '+5', positive: true },
  { label: 'Avg. Engagement Rate', value: '4.2%', change: '-0.3%', positive: false }
])

const engagementChartData = ref({
  labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
  datasets: [
    {
      label: 'Likes',
      data: [120, 190, 150, 220, 280, 310, 240],
      backgroundColor: '#6366f1'
    },
    {
      label: 'Comments',
      data: [45, 67, 52, 89, 102, 95, 78],
      backgroundColor: '#22c55e'
    },
    {
      label: 'Shares',
      data: [23, 34, 28, 45, 56, 48, 39],
      backgroundColor: '#f59e0b'
    }
  ]
})

const engagementChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom',
      labels: { color: '#7a7f8e' }
    }
  },
  scales: {
    x: {
      stacked: true,
      grid: { color: 'rgba(255,255,255,0.05)' },
      ticks: { color: '#7a7f8e' }
    },
    y: {
      stacked: true,
      grid: { color: 'rgba(255,255,255,0.05)' },
      ticks: { color: '#7a7f8e' }
    }
  }
}

const platformData = ref([
  { platform: 'facebook', impressions: 12500, engagements: 1850, rate: '4.8%' },
  { platform: 'instagram', impressions: 8200, engagements: 920, rate: '3.6%' },
  { platform: 'youtube', impressions: 3800, engagements: 430, rate: '3.2%' }
])

const topPosts = ref([
  { id: 1, content: 'Our biggest product launch yet!', platform: 'facebook', engagements: 456, impressions: 8900 },
  { id: 2, content: 'Behind the scenes look at our office...', platform: 'instagram', engagements: 312, impressions: 5600 },
  { id: 3, content: 'New tutorial video is live!', platform: 'youtube', engagements: 234, impressions: 4200 }
])

onMounted(() => {
  analyticsStore.fetchOverview()
})
</script>

<template>
  <DashboardLayout>
    <div class="analyticsView apFadeIn">
      <div class="apPageHeader">
        <h1 class="apPageTitle">Analytics</h1>
        <Dropdown
          v-model="dateRange"
          :options="dateRangeOptions"
          optionLabel="label"
          optionValue="value"
        />
      </div>

      <!-- Metrics Grid -->
      <div class="row g-3 mb-4">
        <div v-for="metric in metrics" :key="metric.label" class="col-6 col-lg-3">
          <div class="apCard metricCard">
            <span class="apLabel">{{ metric.label }}</span>
            <div class="metricValue">
              <span class="apMetric">{{ metric.value }}</span>
              <span class="metricChange" :class="{ positive: metric.positive }">
                {{ metric.change }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-4">
        <!-- Engagement Chart -->
        <div class="col-12 col-lg-8">
          <div class="apCard">
            <h3 class="cardTitle">Engagement Over Time</h3>
            <div class="chartContainer">
              <Chart type="bar" :data="engagementChartData" :options="engagementChartOptions" />
            </div>
          </div>
        </div>

        <!-- Platform Breakdown -->
        <div class="col-12 col-lg-4">
          <div class="apCard">
            <h3 class="cardTitle">Platform Breakdown</h3>
            <div class="platformList">
              <div v-for="item in platformData" :key="item.platform" class="platformItem">
                <div class="platformInfo">
                  <i :class="getPlatform(item.platform)?.icon" :style="{ color: getPlatform(item.platform)?.color }"></i>
                  <span>{{ getPlatform(item.platform)?.name }}</span>
                </div>
                <div class="platformStats">
                  <span class="statValue">{{ item.engagements.toLocaleString() }}</span>
                  <span class="statLabel">engagements</span>
                </div>
                <span class="engagementRate">{{ item.rate }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Top Performing Posts -->
        <div class="col-12">
          <div class="apCard">
            <h3 class="cardTitle">Top Performing Posts</h3>
            <div class="topPostsList">
              <div v-for="post in topPosts" :key="post.id" class="topPostItem">
                <div class="postPlatform">
                  <i :class="getPlatform(post.platform)?.icon" :style="{ color: getPlatform(post.platform)?.color }"></i>
                </div>
                <div class="postContent">
                  <p>{{ post.content }}</p>
                </div>
                <div class="postMetrics">
                  <div class="postMetric">
                    <span class="metricValue">{{ post.impressions.toLocaleString() }}</span>
                    <span class="metricLabel">impressions</span>
                  </div>
                  <div class="postMetric">
                    <span class="metricValue">{{ post.engagements.toLocaleString() }}</span>
                    <span class="metricLabel">engagements</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<style scoped>
.analyticsView {
  max-width: 1400px;
}

.metricCard {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.metricValue {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
}

.metricChange {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--ap-danger);
}

.metricChange.positive {
  color: var(--ap-success);
}

.cardTitle {
  margin: 0 0 1rem;
  font-size: 1rem;
}

.chartContainer {
  height: 300px;
}

.platformList {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.platformItem {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem;
  background: var(--ap-surface-hover);
  border-radius: var(--ap-radius-sm);
}

.platformInfo {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-weight: 500;
}

.platformInfo i {
  font-size: 1.25rem;
}

.platformStats {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.statValue {
  font-family: var(--ap-font-mono);
  font-weight: 600;
}

.statLabel {
  font-size: 0.625rem;
  color: var(--ap-text-muted);
}

.engagementRate {
  font-family: var(--ap-font-mono);
  font-size: 0.875rem;
  color: var(--ap-success);
  min-width: 50px;
  text-align: right;
}

.topPostsList {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.topPostItem {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: var(--ap-surface-hover);
  border-radius: var(--ap-radius-sm);
}

.postPlatform i {
  font-size: 1.5rem;
}

.postContent {
  flex: 1;
}

.postContent p {
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.postMetrics {
  display: flex;
  gap: 1.5rem;
}

.postMetric {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.postMetric .metricValue {
  font-family: var(--ap-font-mono);
  font-weight: 600;
}

.postMetric .metricLabel {
  font-size: 0.625rem;
  color: var(--ap-text-muted);
}

@media (max-width: 767px) {
  .postMetrics {
    flex-direction: column;
    gap: 0.5rem;
  }
}
</style>
