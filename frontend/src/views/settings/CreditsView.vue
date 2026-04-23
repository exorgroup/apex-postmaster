<script setup>
import { ref, onMounted } from 'vue'
import { useCreditsStore } from '@/stores/credits'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'

const credits = useCreditsStore()

const packages = ref([
  { id: 1, name: 'Starter', price: 10, credits: 10, bonus: 0 },
  { id: 2, name: 'Builder', price: 25, credits: 27, bonus: 2 },
  { id: 3, name: 'Growth', price: 50, credits: 55, bonus: 5 },
  { id: 4, name: 'Scale', price: 100, credits: 115, bonus: 15 }
])

const transactions = ref([
  { id: 1, type: 'topup', amount: 2500, description: 'Builder package', createdAt: '2024-01-15T10:30:00' },
  { id: 2, type: 'deduction', amount: -23, description: 'Campaign chat', createdAt: '2024-01-14T14:22:00' },
  { id: 3, type: 'deduction', amount: -18, description: 'Campaign chat', createdAt: '2024-01-14T14:20:00' }
])

const handlePurchase = async (packageId) => {
  await credits.purchasePackage(packageId)
}

const formatDate = (dateStr) => {
  return new Date(dateStr).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}

onMounted(() => {
  credits.fetchBalance()
  credits.fetchTransactions()
  credits.fetchPackages()
})
</script>

<template>
  <DashboardLayout>
    <div class="creditsView apFadeIn">
      <div class="apPageHeader">
        <h1 class="apPageTitle">Credits</h1>
      </div>

      <!-- Balance Card -->
      <div class="apCard balanceCard mb-4">
        <div class="balanceInfo">
          <span class="apLabel">Current Balance</span>
          <span class="balanceAmount">€{{ credits.balanceFormatted }}</span>
        </div>
        <p class="balanceHint">Credits are used for AI Campaign Planner conversations</p>
      </div>

      <!-- Packages -->
      <h2 class="sectionTitle">Top Up</h2>
      <div class="row g-3 mb-4">
        <div v-for="pkg in packages" :key="pkg.id" class="col-6 col-lg-3">
          <div class="apCard packageCard">
            <h3 class="packageName">{{ pkg.name }}</h3>
            <div class="packagePrice">€{{ pkg.price }}</div>
            <div class="packageCredits">
              <span class="creditAmount">€{{ pkg.credits }}</span>
              <span v-if="pkg.bonus > 0" class="creditBonus">+€{{ pkg.bonus }} bonus</span>
            </div>
            <Button label="Buy" class="w-full" outlined @click="handlePurchase(pkg.id)" />
          </div>
        </div>
      </div>

      <!-- Transaction History -->
      <h2 class="sectionTitle">Transaction History</h2>
      <div class="apCard">
        <DataTable :value="transactions" :paginator="true" :rows="10" stripedRows>
          <Column field="type" header="Type">
            <template #body="{ data }">
              <Tag :value="data.type" :severity="data.type === 'topup' ? 'success' : 'secondary'" />
            </template>
          </Column>
          <Column field="amount" header="Amount">
            <template #body="{ data }">
              <span class="amount" :class="{ positive: data.amount > 0, negative: data.amount < 0 }">
                {{ data.amount > 0 ? '+' : '' }}€{{ (Math.abs(data.amount) / 100).toFixed(2) }}
              </span>
            </template>
          </Column>
          <Column field="description" header="Description" />
          <Column field="createdAt" header="Date">
            <template #body="{ data }">
              {{ formatDate(data.createdAt) }}
            </template>
          </Column>
        </DataTable>
      </div>
    </div>
  </DashboardLayout>
</template>

<style scoped>
.creditsView {
  max-width: 1000px;
}

.balanceCard {
  text-align: center;
  padding: 2rem;
}

.balanceAmount {
  display: block;
  font-family: var(--ap-font-mono);
  font-size: 3rem;
  font-weight: 700;
  color: var(--ap-accent);
  margin: 0.5rem 0;
}

.balanceHint {
  color: var(--ap-text-muted);
  font-size: 0.875rem;
  margin: 0;
}

.sectionTitle {
  font-size: 1.125rem;
  margin-bottom: 1rem;
}

.packageCard {
  text-align: center;
}

.packageName {
  font-size: 1rem;
  margin: 0 0 0.5rem;
}

.packagePrice {
  font-size: 1.5rem;
  font-weight: 700;
  font-family: var(--ap-font-mono);
  margin-bottom: 0.5rem;
}

.packageCredits {
  margin-bottom: 1rem;
  font-size: 0.875rem;
  color: var(--ap-text-muted);
}

.creditAmount {
  font-family: var(--ap-font-mono);
}

.creditBonus {
  color: var(--ap-success);
  margin-left: 0.5rem;
}

.amount {
  font-family: var(--ap-font-mono);
}

.amount.positive {
  color: var(--ap-success);
}

.amount.negative {
  color: var(--ap-text-muted);
}
</style>
