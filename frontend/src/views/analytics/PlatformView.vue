<script setup>
import { ref, onMounted } from 'vue'
import { useAnalyticsStore } from '@/stores/analytics'
import { usePlatformLimits } from '@/composables/usePlatformLimits'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import Chart from 'primevue/chart'

const analyticsStore = useAnalyticsStore()
const { getPlatform, getMvpPlatforms } = usePlatformLimits()

const platforms = getMvpPlatforms()

const platformStats = ref([
  { platform: 'facebook', followers: 12500, posts: 45, impressions: 125000, engagements: 8500, rate: 6.8 },
  { platform: 'instagram', followers: 8200, posts: 38, impressions: 82000, engagements: 5200, rate: 6.3 },
  { platform: 'youtube', followers: 3800, posts: 12, impressions: 45000, engagements: 2100, rate: 4.7 }
])

const comparisonChartData = ref({
  labels: ['Facebook', 'Instagram', 'YouTube'],
  datasets: [
    {
      label: 'Engagement Rate %',
      data: [6.8, 6.3, 4.7],
      backgroundColor: ['#1877f2', '#e1306c', '#ff0000']
    }
  ]
})

const comparisonChartOptions = {
  indexAxis: 'y',
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false }
  },
  scales: {
    x: {
      grid: { color: 'rgba(255,255,255,0.05)' },
      ticks: { color: '#7a7f8e' }
    },
    y: {
      grid: { display: false },
      ticks: { color: '#7a7f8e' }
    }
  }
}

const formatNumber = (num) => {
  if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M'
  if (num >= 1000) return (num / 1000).toFixed(1) + 'K'
  return num.toString()
}

onMounted(() => {
  analyticsStore.fetchPlatformComparison()
})
</script>

<template>
  <DashboardLayout>
    <div class="platformView apFadeIn">
      <div class="apPageHeader">
        <h1 class="apPageTitle">Platform Comparison</h1>
      </div>

      <div class="row g-4">
        <!-- Platform Cards -->
        <div v-for="stat in platformStats" :key="stat.platform" class="col-12 col-md-4">
          <div class="apCard platformCard" :style="{ '--platform-color': getPlatform(stat.platform)?.color }">
            <div class="platformHeader">
              <i :class="getPlatform(stat.platform)?.icon"></i>
              <h3>{{ getPlatform(stat.platform)?.name }}</h3>
            </div>
            <div class="platformMetrics">
              <div class="metric">
                <span class="metricValue">{{ formatNumber(stat.followers) }}</span>
                <span class="metricLabel">Followers</span>
              </div>
              <div class="metric">
                <span class="metricValue">{{ stat.posts }}</span>
                <span class="metricLabel">Posts</span>
              </div>
              <div class="metric">
                <span class="metricValue">{{ formatNumber(stat.impressions) }}</span>
                <span class="metricLabel">Impressions</span>
              </div>
              <div class="metric">
                <span class="metricValue">{{ stat.rate }}%</span>
                <span class="metricLabel">Eng. Rate</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Comparison Chart -->
        <div class="col-12">
          <div class="apCard">
            <h3 class="cardTitle">Engagement Rate Comparison</h3>
            <div class="chartContainer">
              <Chart type="bar" :data="comparisonChartData" :options="comparisonChartOptions" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<style scoped>
.platformView {
  max-width: 1200px;
}

.platformCard {
  border-top: 3px solid var(--platform-color);
}

.platformHeader {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
}

.platformHeader i {
  font-size: 1.5rem;
  color: var(--platform-color);
}

.platformHeader h3 {
  margin: 0;
}

.platformMetrics {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

.metric {
  display: flex;
  flex-direction: column;
}

.metricValue {
  font-family: var(--ap-font-mono);
  font-size: 1.25rem;
  font-weight: 600;
}

.metricLabel {
  font-size: 0.75rem;
  color: var(--ap-text-muted);
}

.cardTitle {
  margin: 0 0 1rem;
}

.chartContainer {
  height: 200px;
}
</style>
