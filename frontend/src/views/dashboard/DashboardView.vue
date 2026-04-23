<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import Button from 'primevue/button'
import Chart from 'primevue/chart'
import Skeleton from 'primevue/skeleton'

const router = useRouter()
const loading = ref(true)

const metrics = ref([
  { label: 'Scheduled Posts', value: 12, icon: 'pi pi-clock', change: '+3', positive: true },
  { label: 'Published Today', value: 5, icon: 'pi pi-check-circle', change: '+2', positive: true },
  { label: 'Total Engagements', value: '2.4K', icon: 'pi pi-heart', change: '+15%', positive: true },
  { label: 'Followers Gained', value: 127, icon: 'pi pi-users', change: '+8%', positive: true }
])

const recentPosts = ref([
  { id: 1, content: 'Excited to announce our new product launch!', platform: 'facebook', status: 'published', scheduledAt: '2024-01-15 09:00', engagements: 234 },
  { id: 2, content: 'Check out our latest blog post about social media trends...', platform: 'instagram', status: 'scheduled', scheduledAt: '2024-01-15 14:00', engagements: 0 },
  { id: 3, content: 'Behind the scenes of our creative process', platform: 'youtube', status: 'draft', scheduledAt: null, engagements: 0 }
])

const chartData = ref({
  labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
  datasets: [
    {
      label: 'Engagements',
      data: [120, 190, 150, 220, 280, 310, 240],
      fill: true,
      backgroundColor: 'rgba(99, 102, 241, 0.1)',
      borderColor: '#6366f1',
      tension: 0.4
    }
  ]
})

const chartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    }
  },
  scales: {
    x: {
      grid: {
        color: 'rgba(255, 255, 255, 0.05)'
      },
      ticks: {
        color: '#7a7f8e'
      }
    },
    y: {
      grid: {
        color: 'rgba(255, 255, 255, 0.05)'
      },
      ticks: {
        color: '#7a7f8e'
      }
    }
  }
})

const getPlatformIcon = (platform) => {
  const icons = {
    facebook: 'pi pi-facebook',
    instagram: 'pi pi-instagram',
    youtube: 'pi pi-youtube',
    twitter: 'pi pi-twitter',
    linkedin: 'pi pi-linkedin'
  }
  return icons[platform] || 'pi pi-share-alt'
}

const getPlatformColor = (platform) => {
  const colors = {
    facebook: '#1877f2',
    instagram: '#e1306c',
    youtube: '#ff0000',
    twitter: '#1d9bf0',
    linkedin: '#0a66c2'
  }
  return colors[platform] || '#6366f1'
}

const getStatusClass = (status) => {
  return `status${status.charAt(0).toUpperCase() + status.slice(1)}`
}

onMounted(() => {
  setTimeout(() => {
    loading.value = false
  }, 500)
})
</script>

<template>
  <DashboardLayout>
    <div class="dashboardView apFadeIn">
      <div class="apPageHeader">
        <h1 class="apPageTitle">Dashboard</h1>
        <Button
          label="New Post"
          icon="pi pi-plus"
          @click="router.push('/compose')"
        />
      </div>

      <!-- Metrics Grid -->
      <div class="row g-3 mb-4">
        <div v-for="metric in metrics" :key="metric.label" class="col-12 col-sm-6 col-lg-3">
          <div class="apCard metricCard">
            <template v-if="loading">
              <Skeleton width="60%" height="1rem" class="mb-2" />
              <Skeleton width="40%" height="2rem" />
            </template>
            <template v-else>
              <div class="metricHeader">
                <span class="apLabel">{{ metric.label }}</span>
                <i :class="metric.icon" class="metricIcon"></i>
              </div>
              <div class="metricValue">
                <span class="apMetric">{{ metric.value }}</span>
                <span class="metricChange" :class="{ positive: metric.positive, negative: !metric.positive }">
                  {{ metric.change }}
                </span>
              </div>
            </template>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <!-- Engagement Chart -->
        <div class="col-12 col-lg-8">
          <div class="apCard chartCard">
            <div class="cardHeader">
              <h3>Weekly Engagements</h3>
            </div>
            <div class="chartContainer">
              <template v-if="loading">
                <Skeleton width="100%" height="250px" />
              </template>
              <template v-else>
                <Chart type="line" :data="chartData" :options="chartOptions" class="chart" />
              </template>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-12 col-lg-4">
          <div class="apCard quickActionsCard">
            <div class="cardHeader">
              <h3>Quick Actions</h3>
            </div>
            <div class="quickActions">
              <Button
                label="Create Post"
                icon="pi pi-pencil"
                class="w-full mb-2"
                @click="router.push('/compose')"
              />
              <Button
                label="View Calendar"
                icon="pi pi-calendar"
                class="w-full mb-2"
                outlined
                @click="router.push('/calendar')"
              />
              <Button
                label="AI Tools"
                icon="pi pi-sparkles"
                class="w-full mb-2"
                outlined
                @click="router.push('/ai')"
              />
              <Button
                label="Connect Platform"
                icon="pi pi-plus-circle"
                class="w-full"
                outlined
                @click="router.push('/settings/platforms')"
              />
            </div>
          </div>
        </div>

        <!-- Recent Posts -->
        <div class="col-12">
          <div class="apCard recentPostsCard">
            <div class="cardHeader">
              <h3>Recent Posts</h3>
              <Button
                label="View All"
                link
                @click="router.push('/queue')"
              />
            </div>
            <template v-if="loading">
              <div v-for="i in 3" :key="i" class="postItem">
                <Skeleton width="100%" height="60px" />
              </div>
            </template>
            <template v-else>
              <div v-if="recentPosts.length === 0" class="apEmpty">
                <i class="pi pi-inbox"></i>
                <h3>No posts yet</h3>
                <p>Create your first post to get started</p>
                <Button
                  label="Create Post"
                  icon="pi pi-plus"
                  @click="router.push('/compose')"
                />
              </div>
              <div v-else class="postsList">
                <div v-for="post in recentPosts" :key="post.id" class="postItem">
                  <div class="postPlatform">
                    <i :class="getPlatformIcon(post.platform)" :style="{ color: getPlatformColor(post.platform) }"></i>
                  </div>
                  <div class="postContent">
                    <p class="postText">{{ post.content }}</p>
                    <div class="postMeta">
                      <span :class="getStatusClass(post.status)" class="postStatus">{{ post.status }}</span>
                      <span v-if="post.scheduledAt" class="postTime">{{ post.scheduledAt }}</span>
                    </div>
                  </div>
                  <div class="postEngagements" v-if="post.status === 'published'">
                    <span class="apMono">{{ post.engagements }}</span>
                    <span class="apTextMuted">engagements</span>
                  </div>
                  <Button
                    icon="pi pi-ellipsis-v"
                    text
                    rounded
                    class="postActions"
                  />
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<style scoped>
.dashboardView {
  max-width: 1400px;
}

.metricCard {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.metricHeader {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.metricIcon {
  color: var(--ap-text-muted);
  font-size: 1.25rem;
}

.metricValue {
  display: flex;
  align-items: baseline;
  gap: 0.75rem;
}

.metricChange {
  font-size: 0.75rem;
  font-weight: 500;
  padding: 0.125rem 0.375rem;
  border-radius: 4px;
}

.metricChange.positive {
  color: var(--ap-success);
  background: rgba(34, 197, 94, 0.1);
}

.metricChange.negative {
  color: var(--ap-danger);
  background: rgba(239, 68, 68, 0.1);
}

.cardHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.cardHeader h3 {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
}

.chartContainer {
  height: 250px;
}

.chart {
  height: 100%;
}

.quickActions {
  display: flex;
  flex-direction: column;
}

.postsList {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.postItem {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: var(--ap-surface-hover);
  border-radius: var(--ap-radius-sm);
  transition: background var(--ap-transition);
}

.postItem:hover {
  background: var(--ap-border);
}

.postPlatform {
  font-size: 1.5rem;
  width: 2.5rem;
  text-align: center;
}

.postContent {
  flex: 1;
  min-width: 0;
}

.postText {
  margin: 0 0 0.25rem 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.postMeta {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-size: 0.75rem;
}

.postStatus {
  text-transform: capitalize;
  font-weight: 500;
}

.postTime {
  color: var(--ap-text-muted);
}

.postEngagements {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  font-size: 0.75rem;
}

.postActions {
  color: var(--ap-text-muted);
}

@media (max-width: 575px) {
  .postItem {
    flex-wrap: wrap;
  }

  .postEngagements {
    width: 100%;
    flex-direction: row;
    justify-content: flex-start;
    gap: 0.25rem;
    margin-top: 0.5rem;
    padding-left: 3.5rem;
  }
}
</style>
