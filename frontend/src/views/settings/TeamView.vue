<script setup>
import { ref } from 'vue'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Avatar from 'primevue/avatar'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'

const toast = useToast()

const showInviteDialog = ref(false)
const inviteEmail = ref('')
const inviteRole = ref('editor')

const roles = [
  { label: 'Admin', value: 'admin' },
  { label: 'Editor', value: 'editor' },
  { label: 'Viewer', value: 'viewer' }
]

const members = ref([
  { id: 1, name: 'John Doe', email: 'john@example.com', role: 'owner', status: 'active' },
  { id: 2, name: 'Jane Smith', email: 'jane@example.com', role: 'admin', status: 'active' },
  { id: 3, name: 'Bob Johnson', email: 'bob@example.com', role: 'editor', status: 'pending' }
])

const handleInvite = () => {
  if (!inviteEmail.value) return
  toast.add({ severity: 'success', summary: 'Invitation sent', detail: `Invited ${inviteEmail.value}`, life: 3000 })
  showInviteDialog.value = false
  inviteEmail.value = ''
}

const handleRemove = (member) => {
  members.value = members.value.filter(m => m.id !== member.id)
  toast.add({ severity: 'success', summary: 'Member removed', life: 3000 })
}

const getRoleSeverity = (role) => {
  if (role === 'owner') return 'warning'
  if (role === 'admin') return 'info'
  return 'secondary'
}
</script>

<template>
  <DashboardLayout>
    <div class="teamView apFadeIn">
      <div class="apPageHeader">
        <h1 class="apPageTitle">Team</h1>
        <Button label="Invite Member" icon="pi pi-user-plus" @click="showInviteDialog = true" />
      </div>

      <div class="apCard">
        <DataTable :value="members" stripedRows>
          <Column header="Member">
            <template #body="{ data }">
              <div class="memberInfo">
                <Avatar :label="data.name[0]" shape="circle" />
                <div>
                  <span class="memberName">{{ data.name }}</span>
                  <span class="memberEmail">{{ data.email }}</span>
                </div>
              </div>
            </template>
          </Column>
          <Column field="role" header="Role">
            <template #body="{ data }">
              <Tag :value="data.role" :severity="getRoleSeverity(data.role)" />
            </template>
          </Column>
          <Column field="status" header="Status">
            <template #body="{ data }">
              <Tag :value="data.status" :severity="data.status === 'active' ? 'success' : 'warning'" />
            </template>
          </Column>
          <Column header="Actions">
            <template #body="{ data }">
              <Button v-if="data.role !== 'owner'" icon="pi pi-trash" text rounded severity="danger" @click="handleRemove(data)" />
            </template>
          </Column>
        </DataTable>
      </div>

      <Dialog v-model:visible="showInviteDialog" header="Invite Team Member" modal :style="{ width: '400px' }">
        <div class="inviteForm">
          <div class="formGroup">
            <label class="apLabel">Email</label>
            <InputText v-model="inviteEmail" type="email" placeholder="colleague@example.com" class="w-full" />
          </div>
          <div class="formGroup">
            <label class="apLabel">Role</label>
            <Dropdown v-model="inviteRole" :options="roles" optionLabel="label" optionValue="value" class="w-full" />
          </div>
        </div>
        <template #footer>
          <Button label="Cancel" text @click="showInviteDialog = false" />
          <Button label="Send Invite" @click="handleInvite" :disabled="!inviteEmail" />
        </template>
      </Dialog>
    </div>
  </DashboardLayout>
</template>

<style scoped>
.teamView {
  max-width: 800px;
}

.memberInfo {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.memberName {
  display: block;
  font-weight: 500;
}

.memberEmail {
  display: block;
  font-size: 0.75rem;
  color: var(--ap-text-muted);
}

.inviteForm {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.formGroup {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
</style>
