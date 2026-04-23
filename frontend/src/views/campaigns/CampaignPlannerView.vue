<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCampaignsStore } from '@/stores/campaigns'
import { useCreditsStore } from '@/stores/credits'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'

const route = useRoute()
const router = useRouter()
const campaigns = useCampaignsStore()
const credits = useCreditsStore()
const toast = useToast()

const messageInput = ref('')
const messagesContainer = ref(null)

const currentBalance = computed(() => credits.balanceFormatted)
const sessionCost = computed(() => {
  const total = campaigns.activeChatMessages.reduce((sum, m) => sum + (m.costCents || 0), 0)
  return (total / 100).toFixed(2)
})

const scrollToBottom = () => {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}

const handleSendMessage = async () => {
  if (!messageInput.value.trim() || campaigns.isSending) return
  if (!credits.hasCredits) {
    toast.add({ severity: 'warn', summary: 'Insufficient credits', detail: 'Please top up to continue', life: 5000 })
    return
  }

  const content = messageInput.value
  messageInput.value = ''

  const chatId = campaigns.activeChat?.id
  if (!chatId) {
    const result = await campaigns.createChat()
    if (!result.success) {
      toast.add({ severity: 'error', summary: 'Error', detail: result.error, life: 5000 })
      return
    }
  }

  const result = await campaigns.sendMessage(campaigns.activeChat.id, content)
  if (!result.success) {
    toast.add({ severity: 'error', summary: 'Error', detail: result.error, life: 5000 })
  }

  scrollToBottom()
}

const handleNewChat = async () => {
  const result = await campaigns.createChat()
  if (result.success) {
    router.push(`/campaigns/planner/${result.data.id}`)
  }
}

const selectChat = (chat) => {
  campaigns.setActiveChat(chat)
  router.push(`/campaigns/planner/${chat.id}`)
}

onMounted(async () => {
  await credits.fetchBalance()
  await campaigns.fetchChats()

  if (route.params.chatId) {
    await campaigns.fetchChatMessages(route.params.chatId)
  }

  scrollToBottom()
})
</script>

<template>
  <DashboardLayout>
    <div class="plannerView apFadeIn">
      <div class="plannerLayout">
        <!-- Chat List -->
        <div class="chatList d-none d-lg-flex">
          <div class="chatListHeader">
            <h3>Chats</h3>
            <Button icon="pi pi-plus" text rounded @click="handleNewChat" />
          </div>
          <div class="chatListItems">
            <div
              v-for="chat in campaigns.chats"
              :key="chat.id"
              class="chatItem"
              :class="{ active: campaigns.activeChat?.id === chat.id }"
              @click="selectChat(chat)"
            >
              <span class="chatTitle">{{ chat.title || 'New Campaign' }}</span>
              <span class="chatDate">{{ new Date(chat.createdAt).toLocaleDateString() }}</span>
            </div>
            <div v-if="campaigns.chats.length === 0" class="emptyChatList">
              <p>No chats yet</p>
            </div>
          </div>
        </div>

        <!-- Chat Area -->
        <div class="chatArea">
          <div class="chatHeader">
            <div class="chatInfo">
              <h2>{{ campaigns.activeChat?.title || 'Campaign Planner' }}</h2>
              <span class="costInfo">
                Session: €{{ sessionCost }} · Balance: €{{ currentBalance }}
              </span>
            </div>
            <Button icon="pi pi-plus" label="New Chat" outlined size="small" class="d-lg-none" @click="handleNewChat" />
          </div>

          <div class="messagesContainer" ref="messagesContainer">
            <div v-if="campaigns.activeChatMessages.length === 0" class="emptyChat">
              <i class="pi pi-comments"></i>
              <h3>Start planning your campaign</h3>
              <p>Tell me about your campaign goals, target audience, and timeline.</p>
            </div>
            <div
              v-for="message in campaigns.activeChatMessages"
              :key="message.id"
              class="message"
              :class="message.role"
            >
              <div class="messageContent">{{ message.content }}</div>
              <div v-if="message.costCents" class="messageCost">€{{ (message.costCents / 100).toFixed(2) }}</div>
            </div>
            <div v-if="campaigns.isSending" class="message assistant typing">
              <div class="typingIndicator">
                <span></span><span></span><span></span>
              </div>
            </div>
          </div>

          <div class="chatInput">
            <Textarea
              v-model="messageInput"
              :autoResize="true"
              rows="1"
              placeholder="Describe your campaign..."
              @keydown.enter.exact.prevent="handleSendMessage"
            />
            <Button
              icon="pi pi-send"
              :disabled="!messageInput.trim() || campaigns.isSending"
              @click="handleSendMessage"
            />
          </div>

          <div v-if="!credits.hasCredits" class="lowBalanceWarning">
            <i class="pi pi-exclamation-triangle"></i>
            <span>Insufficient credits.</span>
            <router-link to="/settings/credits">Top up now</router-link>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<style scoped>
.plannerView {
  height: calc(100vh - 60px - 3rem);
}

.plannerLayout {
  display: flex;
  height: 100%;
  gap: 1rem;
}

.chatList {
  width: 280px;
  flex-shrink: 0;
  background: var(--ap-surface);
  border: 1px solid var(--ap-border);
  border-radius: var(--ap-radius);
  display: flex;
  flex-direction: column;
}

.chatListHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border-bottom: 1px solid var(--ap-border);
}

.chatListHeader h3 {
  margin: 0;
  font-size: 1rem;
}

.chatListItems {
  flex: 1;
  overflow-y: auto;
}

.chatItem {
  padding: 0.75rem 1rem;
  cursor: pointer;
  border-bottom: 1px solid var(--ap-border);
  transition: background var(--ap-transition);
}

.chatItem:hover {
  background: var(--ap-surface-hover);
}

.chatItem.active {
  background: var(--ap-accent-subtle);
}

.chatTitle {
  display: block;
  font-weight: 500;
  font-size: 0.875rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.chatDate {
  font-size: 0.625rem;
  color: var(--ap-text-muted);
}

.emptyChatList {
  padding: 2rem;
  text-align: center;
  color: var(--ap-text-muted);
}

.chatArea {
  flex: 1;
  display: flex;
  flex-direction: column;
  background: var(--ap-surface);
  border: 1px solid var(--ap-border);
  border-radius: var(--ap-radius);
  overflow: hidden;
}

.chatHeader {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border-bottom: 1px solid var(--ap-border);
}

.chatInfo h2 {
  margin: 0;
  font-size: 1rem;
}

.costInfo {
  font-size: 0.75rem;
  color: var(--ap-text-muted);
  font-family: var(--ap-font-mono);
}

.messagesContainer {
  flex: 1;
  overflow-y: auto;
  padding: 1rem;
}

.emptyChat {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  text-align: center;
  color: var(--ap-text-muted);
}

.emptyChat i {
  font-size: 3rem;
  margin-bottom: 1rem;
  opacity: 0.5;
}

.emptyChat h3 {
  color: var(--ap-text);
  margin-bottom: 0.5rem;
}

.message {
  max-width: 80%;
  margin-bottom: 1rem;
  padding: 0.75rem 1rem;
  border-radius: var(--ap-radius-sm);
}

.message.user {
  margin-left: auto;
  background: var(--ap-accent);
  color: white;
}

.message.assistant {
  background: var(--ap-surface-hover);
}

.messageContent {
  white-space: pre-wrap;
}

.messageCost {
  font-size: 0.625rem;
  color: var(--ap-text-muted);
  margin-top: 0.25rem;
  text-align: right;
}

.message.user .messageCost {
  color: rgba(255, 255, 255, 0.7);
}

.typingIndicator {
  display: flex;
  gap: 0.25rem;
}

.typingIndicator span {
  width: 8px;
  height: 8px;
  background: var(--ap-text-muted);
  border-radius: 50%;
  animation: typing 1.4s infinite;
}

.typingIndicator span:nth-child(2) { animation-delay: 0.2s; }
.typingIndicator span:nth-child(3) { animation-delay: 0.4s; }

@keyframes typing {
  0%, 60%, 100% { transform: translateY(0); }
  30% { transform: translateY(-4px); }
}

.chatInput {
  display: flex;
  gap: 0.75rem;
  padding: 1rem;
  border-top: 1px solid var(--ap-border);
}

.chatInput .p-inputtextarea {
  flex: 1;
  max-height: 150px;
}

.lowBalanceWarning {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.75rem;
  background: rgba(239, 68, 68, 0.1);
  color: var(--ap-danger);
  font-size: 0.875rem;
}

.lowBalanceWarning a {
  color: var(--ap-danger);
  text-decoration: underline;
}

@media (max-width: 991px) {
  .plannerView {
    height: auto;
    min-height: calc(100vh - 60px - 3rem - 56px);
  }
}
</style>
