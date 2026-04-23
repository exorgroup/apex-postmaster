<script setup>
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { axiosInstance } from '@/composables/useApi'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

onMounted(async () => {
  const code = route.query.code
  const provider = route.params.provider

  if (!code || !provider) {
    router.push('/login?error=invalid_oauth')
    return
  }

  try {
    const response = await axiosInstance.post('/auth/oauth', {
      provider,
      code
    })

    const { accessToken, refreshToken, user } = response.data.data
    auth.setTokens(accessToken, refreshToken)
    auth.user = user

    // Redirect based on onboarding status
    if (user.onboardingCompleted) {
      router.push('/dashboard')
    } else {
      router.push('/onboarding')
    }
  } catch (err) {
    console.error('OAuth error:', err)
    router.push('/login?error=oauth_failed')
  }
})
</script>

<template>
  <div class="oauth-callback">
    <div class="loading">
      <i class="pi pi-spin pi-spinner" style="font-size: 2rem;"></i>
      <p>Completing sign in...</p>
    </div>
  </div>
</template>

<style scoped>
.oauth-callback {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--ap-bg);
}

.loading {
  text-align: center;
  color: var(--ap-text);
}

.loading i {
  color: var(--ap-accent);
  margin-bottom: 1rem;
}

.loading p {
  color: var(--ap-text-muted);
}
</style>
