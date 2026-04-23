<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { usePostsStore } from '@/stores/posts'
import { usePlatformLimits } from '@/composables/usePlatformLimits'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import Skeleton from 'primevue/skeleton'

const router = useRouter()
const postsStore = usePostsStore()
const { getPlatform } = usePlatformLimits()

const currentDate = ref(new Date())
const selectedDate = ref(null)
const showDayDialog = ref(false)
const loading = ref(true)

const calendarPosts = ref([])

const currentMonth = computed(() => currentDate.value.getMonth())
const currentYear = computed(() => currentDate.value.getFullYear())

const monthName = computed(() => {
  return currentDate.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
})

const daysInMonth = computed(() => {
  return new Date(currentYear.value, currentMonth.value + 1, 0).getDate()
})

const firstDayOfMonth = computed(() => {
  const day = new Date(currentYear.value, currentMonth.value, 1).getDay()
  return day === 0 ? 6 : day - 1
})

const calendarDays = computed(() => {
  const days = []
  const prevMonth = new Date(currentYear.value, currentMonth.value, 0)
  const prevMonthDays = prevMonth.getDate()

  for (let i = firstDayOfMonth.value - 1; i >= 0; i--) {
    days.push({
      day: prevMonthDays - i,
      currentMonth: false,
      date: new Date(currentYear.value, currentMonth.value - 1, prevMonthDays - i)
    })
  }

  for (let i = 1; i <= daysInMonth.value; i++) {
    days.push({
      day: i,
      currentMonth: true,
      date: new Date(currentYear.value, currentMonth.value, i)
    })
  }

  const remaining = 42 - days.length
  for (let i = 1; i <= remaining; i++) {
    days.push({
      day: i,
      currentMonth: false,
      date: new Date(currentYear.value, currentMonth.value + 1, i)
    })
  }

  return days
})

const isToday = (date) => {
  const today = new Date()
  return date.toDateString() === today.toDateString()
}

const getPostsForDate = (date) => {
  const dateStr = date.toISOString().split('T')[0]
  return calendarPosts.value.filter(p => {
    if (!p.scheduledAt) return false
    return p.scheduledAt.startsWith(dateStr)
  })
}

const getPlatformColor = (platformId) => {
  const platform = getPlatform(platformId)
  return platform?.color || '#6366f1'
}

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

const prevMonth = () => {
  currentDate.value = new Date(currentYear.value, currentMonth.value - 1, 1)
  fetchCalendarData()
}

const nextMonth = () => {
  currentDate.value = new Date(currentYear.value, currentMonth.value + 1, 1)
  fetchCalendarData()
}

const goToToday = () => {
  currentDate.value = new Date()
  fetchCalendarData()
}

const openDayDialog = (day) => {
  selectedDate.value = day.date
  showDayDialog.value = true
}

const selectedDatePosts = computed(() => {
  if (!selectedDate.value) return []
  return getPostsForDate(selectedDate.value)
})

const formatSelectedDate = computed(() => {
  if (!selectedDate.value) return ''
  return selectedDate.value.toLocaleDateString('en-US', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })
})

const formatTime = (dateStr) => {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
}

const fetchCalendarData = async () => {
  loading.value = true
  const result = await postsStore.fetchCalendar(currentYear.value, currentMonth.value + 1)
  if (result.success) {
    calendarPosts.value = result.data || []
  }
  loading.value = false
}

onMounted(() => {
  fetchCalendarData()
})
</script>

<template>
  <DashboardLayout>
    <div class="calendarView apFadeIn">
      <div class="apPageHeader">
        <h1 class="apPageTitle">Calendar</h1>
        <Button
          label="New Post"
          icon="pi pi-plus"
          @click="router.push('/compose')"
        />
      </div>

      <div class="apCard calendarCard">
        <!-- Calendar Header -->
        <div class="calendarHeader">
          <div class="calendarNav">
            <Button
              icon="pi pi-chevron-left"
              text
              rounded
              @click="prevMonth"
            />
            <h2 class="monthTitle">{{ monthName }}</h2>
            <Button
              icon="pi pi-chevron-right"
              text
              rounded
              @click="nextMonth"
            />
          </div>
          <Button
            label="Today"
            outlined
            size="small"
            @click="goToToday"
          />
        </div>

        <!-- Calendar Grid -->
        <div class="calendarGrid">
          <!-- Day Headers -->
          <div class="dayHeader" v-for="day in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']" :key="day">
            {{ day }}
          </div>

          <!-- Calendar Days -->
          <template v-if="loading">
            <div v-for="i in 42" :key="i" class="calendarDay skeleton">
              <Skeleton width="100%" height="100%" />
            </div>
          </template>
          <template v-else>
            <div
              v-for="(day, index) in calendarDays"
              :key="index"
              class="calendarDay"
              :class="{
                otherMonth: !day.currentMonth,
                today: isToday(day.date),
                hasPosts: getPostsForDate(day.date).length > 0
              }"
              @click="openDayDialog(day)"
            >
              <span class="dayNumber">{{ day.day }}</span>
              <div class="dayPosts">
                <div
                  v-for="post in getPostsForDate(day.date).slice(0, 3)"
                  :key="post.id"
                  class="postPill"
                >
                  <span
                    v-for="platform in (post.platforms || []).slice(0, 2)"
                    :key="platform"
                    class="platformDot"
                    :style="{ background: getPlatformColor(platform) }"
                  ></span>
                  <span class="postContent">{{ post.content?.substring(0, 20) }}</span>
                </div>
                <span
                  v-if="getPostsForDate(day.date).length > 3"
                  class="morePosts"
                >
                  +{{ getPostsForDate(day.date).length - 3 }} more
                </span>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- Day Detail Dialog -->
      <Dialog
        v-model:visible="showDayDialog"
        :header="formatSelectedDate"
        :modal="true"
        :closable="true"
        :style="{ width: '500px', maxWidth: '90vw' }"
      >
        <div class="dayDetail">
          <div v-if="selectedDatePosts.length === 0" class="apEmpty">
            <i class="pi pi-calendar"></i>
            <h3>No posts scheduled</h3>
            <p>Create a post for this day</p>
          </div>
          <div v-else class="dayPostsList">
            <div
              v-for="post in selectedDatePosts"
              :key="post.id"
              class="dayPostItem"
              @click="router.push(`/compose/${post.id}`)"
            >
              <div class="postTime">{{ formatTime(post.scheduledAt) }}</div>
              <div class="postBody">
                <div class="postPlatforms">
                  <span
                    v-for="platform in (post.platforms || [])"
                    :key="platform"
                    class="platformDot"
                    :style="{ background: getPlatformColor(platform) }"
                  ></span>
                </div>
                <p class="postContent">{{ post.content }}</p>
                <Tag :value="post.status" :severity="getStatusSeverity(post.status)" size="small" />
              </div>
            </div>
          </div>
        </div>
        <template #footer>
          <Button
            label="Add Post"
            icon="pi pi-plus"
            @click="router.push('/compose'); showDayDialog = false"
          />
        </template>
      </Dialog>
    </div>
  </DashboardLayout>
</template>

<style scoped>
.calendarView {
  max-width: 1200px;
}

.calendarCard {
  padding: 1rem;
}

.calendarHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 0.5rem 1rem;
  border-bottom: 1px solid var(--ap-border);
  margin-bottom: 1rem;
}

.calendarNav {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.monthTitle {
  margin: 0;
  min-width: 200px;
  text-align: center;
  font-size: 1.25rem;
}

.calendarGrid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1px;
  background: var(--ap-border);
  border: 1px solid var(--ap-border);
  border-radius: var(--ap-radius-sm);
  overflow: hidden;
}

.dayHeader {
  background: var(--ap-surface);
  padding: 0.75rem;
  text-align: center;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--ap-text-muted);
  text-transform: uppercase;
}

.calendarDay {
  background: var(--ap-surface);
  min-height: 100px;
  padding: 0.5rem;
  cursor: pointer;
  transition: background var(--ap-transition);
}

.calendarDay:hover {
  background: var(--ap-surface-hover);
}

.calendarDay.otherMonth {
  opacity: 0.4;
}

.calendarDay.today {
  background: var(--ap-accent-subtle);
}

.calendarDay.today .dayNumber {
  background: var(--ap-accent);
  color: white;
  border-radius: 50%;
  width: 1.75rem;
  height: 1.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.calendarDay.skeleton {
  padding: 0;
}

.dayNumber {
  font-size: 0.875rem;
  font-weight: 500;
  margin-bottom: 0.25rem;
}

.dayPosts {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.postPill {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.625rem;
  padding: 0.125rem 0.25rem;
  background: var(--ap-surface-hover);
  border-radius: 4px;
  overflow: hidden;
}

.platformDot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  flex-shrink: 0;
}

.postContent {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: var(--ap-text-muted);
}

.morePosts {
  font-size: 0.625rem;
  color: var(--ap-text-muted);
  padding-left: 0.25rem;
}

.dayDetail {
  min-height: 200px;
}

.dayPostsList {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.dayPostItem {
  display: flex;
  gap: 1rem;
  padding: 0.75rem;
  background: var(--ap-surface-hover);
  border-radius: var(--ap-radius-sm);
  cursor: pointer;
  transition: background var(--ap-transition);
}

.dayPostItem:hover {
  background: var(--ap-border);
}

.postTime {
  font-family: var(--ap-font-mono);
  font-size: 0.75rem;
  color: var(--ap-text-muted);
  min-width: 50px;
}

.postBody {
  flex: 1;
}

.postPlatforms {
  display: flex;
  gap: 0.25rem;
  margin-bottom: 0.25rem;
}

.postBody .postContent {
  margin: 0 0 0.5rem;
  font-size: 0.875rem;
  color: var(--ap-text);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

@media (max-width: 767px) {
  .calendarDay {
    min-height: 60px;
    padding: 0.25rem;
  }

  .dayNumber {
    font-size: 0.75rem;
  }

  .postPill {
    display: none;
  }

  .calendarDay.hasPosts::after {
    content: '';
    display: block;
    width: 6px;
    height: 6px;
    background: var(--ap-accent);
    border-radius: 50%;
    margin-top: 0.25rem;
  }

  .morePosts {
    display: none;
  }
}
</style>
