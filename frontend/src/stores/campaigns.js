import { ref } from 'vue'
import { defineStore } from 'pinia'
import { axiosInstance } from '@/composables/useApi'

export const useCampaignsStore = defineStore('campaigns', () => {
  const campaigns = ref([])
  const chats = ref([])
  const activeChat = ref(null)
  const activeChatMessages = ref([])
  const loading = ref(false)
  const isSending = ref(false)
  const error = ref(null)

  const fetchCampaigns = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.get('/campaigns')
      campaigns.value = response.data
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch campaigns'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const createCampaign = async (data) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/campaigns', data)
      campaigns.value.unshift(response.data)
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create campaign'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const deployCampaign = async (id) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post(`/campaigns/${id}/deploy`)
      const index = campaigns.value.findIndex(c => c.id === id)
      if (index !== -1) {
        campaigns.value[index] = response.data
      }
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to deploy campaign'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const fetchChats = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.get('/campaigns/chats')
      chats.value = response.data
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch chats'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const createChat = async () => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.post('/campaigns/chats')
      chats.value.unshift(response.data)
      activeChat.value = response.data
      activeChatMessages.value = []
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to create chat'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const fetchChatMessages = async (chatId) => {
    loading.value = true
    error.value = null

    try {
      const response = await axiosInstance.get(`/campaigns/chats/${chatId}`)
      activeChat.value = response.data.chat || { id: chatId }
      activeChatMessages.value = response.data.messages || []
      return { success: true }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to fetch messages'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  const sendMessage = async (chatId, content) => {
    isSending.value = true
    error.value = null

    activeChatMessages.value.push({
      id: Date.now(),
      role: 'user',
      content,
      createdAt: new Date().toISOString()
    })

    try {
      const response = await axiosInstance.post(`/campaigns/chats/${chatId}/message`, { content })

      activeChatMessages.value.push({
        id: Date.now() + 1,
        role: 'assistant',
        content: response.data.message,
        costCents: response.data.costCents,
        createdAt: new Date().toISOString()
      })

      return {
        success: true,
        data: response.data
      }
    } catch (err) {
      activeChatMessages.value.pop()
      error.value = err.response?.data?.error || 'Failed to send message'
      return { success: false, error: error.value }
    } finally {
      isSending.value = false
    }
  }

  const generatePlan = async (chatId) => {
    isSending.value = true
    error.value = null

    try {
      const response = await axiosInstance.post(`/campaigns/chats/${chatId}/generate-plan`)
      return { success: true, data: response.data }
    } catch (err) {
      error.value = err.response?.data?.error || 'Failed to generate plan'
      return { success: false, error: error.value }
    } finally {
      isSending.value = false
    }
  }

  const setActiveChat = (chat) => {
    activeChat.value = chat
    if (chat) {
      fetchChatMessages(chat.id)
    } else {
      activeChatMessages.value = []
    }
  }

  return {
    campaigns,
    chats,
    activeChat,
    activeChatMessages,
    loading,
    isSending,
    error,
    fetchCampaigns,
    createCampaign,
    deployCampaign,
    fetchChats,
    createChat,
    fetchChatMessages,
    sendMessage,
    generatePlan,
    setActiveChat
  }
})
