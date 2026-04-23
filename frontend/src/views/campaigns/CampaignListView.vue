<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useCampaignsStore } from '@/stores/campaigns'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

const router = useRouter()
const campaigns = useCampaignsStore()

const campaignsList = ref([
  { id: 1, name: 'Q1 Product Launch', status: 'active', postsPlanned: 24, postsPublished: 12, startDate: '2024-01-15', endDate: '2024-02-28' },
  { id: 2, name: 'Holiday Sale 2024', status: 'planning', postsPlanned: 18, postsPublished: 0, startDate: '2024-03-01', endDate: '2024-03-31' },
  { id: 3, name: 'Brand Awareness', status: 'completed', postsPlanned: 30, postsPublished: 30, startDate: '2024-01-01', endDate: '2024-01-31' }
])

const getStatusSeverity = (status) => {
  if (status === 'active') return 'success'
  if (status === 'planning') return 'info'
  if (status === 'completed') return 'secondary'
  return 'warning'
}

onMounted(() => {
  campaigns.fetchCampaigns()
})
</script>

<template>
  <DashboardLayout>
    <div class="campaignsView apFadeIn">
      <div class="apPageHeader">
        <h1 class="apPageTitle">Campaigns</h1>
        <Button label="Plan with AI" icon="pi pi-sparkles" @click="router.push('/campaigns/planner')" />
      </div>

      <div v-if="campaignsList.length === 0" class="apCard apEmpty">
        <i class="pi pi-megaphone"></i>
        <h3>No campaigns yet</h3>
        <p>Create your first campaign using our AI planner</p>
        <Button label="Start Planning" icon="pi pi-sparkles" @click="router.push('/campaigns/planner')" />
      </div>

      <div v-else class="row g-3">
        <div v-for="campaign in campaignsList" :key="campaign.id" class="col-12 col-md-6 col-lg-4">
          <div class="apCard campaignCard" @click="router.push(`/campaigns/${campaign.id}`)">
            <div class="campaignHeader">
              <h3>{{ campaign.name }}</h3>
              <Tag :value="campaign.status" :severity="getStatusSeverity(campaign.status)" />
            </div>
            <div class="campaignProgress">
              <div class="progressBar">
                <div class="progressFill" :style="{ width: (campaign.postsPublished / campaign.postsPlanned * 100) + '%' }"></div>
              </div>
              <span class="progressText">{{ campaign.postsPublished }}/{{ campaign.postsPlanned }} posts</span>
            </div>
            <div class="campaignDates">
              <span>{{ campaign.startDate }} - {{ campaign.endDate }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<style scoped>
.campaignsView {
  max-width: 1200px;
}

.campaignCard {
  cursor: pointer;
  transition: all var(--ap-transition);
}

.campaignCard:hover {
  transform: translateY(-2px);
  box-shadow: var(--ap-shadow-lg);
}

.campaignHeader {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.campaignHeader h3 {
  margin: 0;
  font-size: 1rem;
}

.campaignProgress {
  margin-bottom: 1rem;
}

.progressBar {
  height: 6px;
  background: var(--ap-border);
  border-radius: 3px;
  overflow: hidden;
  margin-bottom: 0.5rem;
}

.progressFill {
  height: 100%;
  background: var(--ap-accent);
  border-radius: 3px;
  transition: width 0.3s ease;
}

.progressText {
  font-size: 0.75rem;
  color: var(--ap-text-muted);
}

.campaignDates {
  font-size: 0.75rem;
  color: var(--ap-text-dim);
}
</style>
