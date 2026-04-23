<script setup>
import { ref, onMounted } from 'vue'
import { useBillingStore } from '@/stores/billing'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Dropdown from 'primevue/dropdown'
import Calendar from 'primevue/calendar'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'

const billing = useBillingStore()
const toast = useToast()

const showCreateDialog = ref(false)
const newCoupon = ref({ code: '', discountType: 'percentage', discountValue: 10, duration: 'once', maxRedemptions: null, expiresAt: null })

const discountTypes = [{ label: 'Percentage', value: 'percentage' }, { label: 'Fixed Amount', value: 'fixed' }]
const durations = [{ label: 'Once', value: 'once' }, { label: 'Repeating', value: 'repeating' }, { label: 'Forever', value: 'forever' }]

const handleCreate = async () => {
  const result = await billing.createCoupon(newCoupon.value)
  if (result.success) {
    toast.add({ severity: 'success', summary: 'Coupon created', life: 3000 })
    showCreateDialog.value = false
    newCoupon.value = { code: '', discountType: 'percentage', discountValue: 10, duration: 'once', maxRedemptions: null, expiresAt: null }
  } else {
    toast.add({ severity: 'error', summary: 'Error', detail: result.error, life: 5000 })
  }
}

const handleDeactivate = async (id) => {
  const result = await billing.deactivateCoupon(id)
  if (result.success) {
    toast.add({ severity: 'success', summary: 'Coupon deactivated', life: 3000 })
  }
}

const generateCode = () => {
  newCoupon.value.code = 'APEX' + Math.random().toString(36).substring(2, 8).toUpperCase()
}

onMounted(() => {
  billing.fetchCoupons()
})
</script>

<template>
  <DashboardLayout>
    <div class="couponsView apFadeIn">
      <div class="apPageHeader">
        <h1 class="apPageTitle">Coupon Management</h1>
        <Button label="Create Coupon" icon="pi pi-plus" @click="showCreateDialog = true" />
      </div>

      <div class="apCard">
        <DataTable :value="billing.coupons" :paginator="true" :rows="10" stripedRows :loading="billing.loading">
          <Column field="code" header="Code">
            <template #body="{ data }">
              <code class="couponCode">{{ data.code }}</code>
            </template>
          </Column>
          <Column field="discountType" header="Discount">
            <template #body="{ data }">
              {{ data.discountType === 'percentage' ? `${data.discountValue}%` : `€${data.discountValue}` }}
            </template>
          </Column>
          <Column field="timesRedeemed" header="Usage">
            <template #body="{ data }">
              {{ data.timesRedeemed }} / {{ data.maxRedemptions || '∞' }}
            </template>
          </Column>
          <Column field="isActive" header="Status">
            <template #body="{ data }">
              <Tag :value="data.isActive ? 'Active' : 'Inactive'" :severity="data.isActive ? 'success' : 'secondary'" />
            </template>
          </Column>
          <Column header="Actions">
            <template #body="{ data }">
              <Button v-if="data.isActive" icon="pi pi-ban" text rounded severity="danger" @click="handleDeactivate(data.id)" />
            </template>
          </Column>
        </DataTable>
      </div>

      <Dialog v-model:visible="showCreateDialog" header="Create Coupon" modal :style="{ width: '450px' }">
        <div class="createForm">
          <div class="formGroup">
            <label class="apLabel">Code</label>
            <div class="codeInput">
              <InputText v-model="newCoupon.code" placeholder="COUPON123" />
              <Button icon="pi pi-refresh" outlined @click="generateCode" />
            </div>
          </div>
          <div class="formGroup">
            <label class="apLabel">Discount Type</label>
            <Dropdown v-model="newCoupon.discountType" :options="discountTypes" optionLabel="label" optionValue="value" class="w-full" />
          </div>
          <div class="formGroup">
            <label class="apLabel">Value</label>
            <InputNumber v-model="newCoupon.discountValue" class="w-full" />
          </div>
          <div class="formGroup">
            <label class="apLabel">Duration</label>
            <Dropdown v-model="newCoupon.duration" :options="durations" optionLabel="label" optionValue="value" class="w-full" />
          </div>
          <div class="formGroup">
            <label class="apLabel">Max Redemptions (optional)</label>
            <InputNumber v-model="newCoupon.maxRedemptions" class="w-full" placeholder="Unlimited" />
          </div>
          <div class="formGroup">
            <label class="apLabel">Expires At (optional)</label>
            <Calendar v-model="newCoupon.expiresAt" dateFormat="dd M yy" class="w-full" />
          </div>
        </div>
        <template #footer>
          <Button label="Cancel" text @click="showCreateDialog = false" />
          <Button label="Create" @click="handleCreate" :loading="billing.loading" />
        </template>
      </Dialog>
    </div>
  </DashboardLayout>
</template>

<style scoped>
.couponsView {
  max-width: 1000px;
}

.couponCode {
  background: var(--ap-surface-hover);
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-family: var(--ap-font-mono);
}

.createForm {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.formGroup {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.codeInput {
  display: flex;
  gap: 0.5rem;
}

.codeInput .p-inputtext {
  flex: 1;
}
</style>
