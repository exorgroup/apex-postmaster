<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Button from 'primevue/button'

const router = useRouter()
const showBanner = ref(false)

onMounted(() => {
  const consent = localStorage.getItem('cookieConsent')
  if (!consent) {
    showBanner.value = true
  }
})

const acceptAll = () => {
  localStorage.setItem('cookieConsent', JSON.stringify({
    necessary: true,
    functional: true,
    analytics: true,
    marketing: true,
    timestamp: new Date().toISOString()
  }))
  showBanner.value = false
}

const acceptNecessary = () => {
  localStorage.setItem('cookieConsent', JSON.stringify({
    necessary: true,
    functional: false,
    analytics: false,
    marketing: false,
    timestamp: new Date().toISOString()
  }))
  showBanner.value = false
}

const goToPolicy = () => {
  router.push('/cookie-policy')
}
</script>

<template>
  <Transition name="slide-up">
    <div v-if="showBanner" class="cookieBanner">
      <div class="cookieContent">
        <div class="cookieText">
          <h3><i class="pi pi-info-circle"></i> Cookie Notice</h3>
          <p>
            We use cookies to enhance your browsing experience, serve personalized content, and analyze our traffic.
            By clicking "Accept All", you consent to our use of cookies.
            <a @click="goToPolicy" class="policyLink">Learn more</a>
          </p>
        </div>
        <div class="cookieActions">
          <Button
            label="Accept Necessary Only"
            @click="acceptNecessary"
            outlined
            class="necessaryBtn"
          />
          <Button
            label="Accept All"
            @click="acceptAll"
            class="acceptBtn"
          />
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.cookieBanner {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background: rgba(19, 20, 26, 0.98);
  border-top: 1px solid var(--ap-border, #2a2b35);
  padding: 1.5rem 2rem;
  z-index: 9999;
  backdrop-filter: blur(12px);
}

.cookieContent {
  max-width: 1280px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 2rem;
}

.cookieText {
  flex: 1;
}

.cookieText h3 {
  font-size: 1rem;
  font-weight: 600;
  margin: 0 0 0.5rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--ap-text, #f0f0f5);
}

.cookieText h3 i {
  color: var(--ap-accent, #6366f1);
}

.cookieText p {
  margin: 0;
  font-size: 0.9375rem;
  line-height: 1.5;
  color: var(--ap-text-muted, #9ca3af);
}

.policyLink {
  color: var(--ap-accent, #6366f1);
  text-decoration: underline;
  cursor: pointer;
}

.policyLink:hover {
  opacity: 0.8;
}

.cookieActions {
  display: flex;
  gap: 1rem;
  flex-shrink: 0;
}

.necessaryBtn {
  border-color: var(--ap-border, #2a2b35) !important;
  color: var(--ap-text, #f0f0f5) !important;
}

.acceptBtn {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important;
  border: none !important;
  color: white !important;
}

.slide-up-enter-active,
.slide-up-leave-active {
  transition: transform 0.3s ease-out;
}

.slide-up-enter-from,
.slide-up-leave-to {
  transform: translateY(100%);
}

@media (max-width: 768px) {
  .cookieBanner {
    padding: 1rem;
  }

  .cookieContent {
    flex-direction: column;
    align-items: stretch;
    gap: 1rem;
  }

  .cookieActions {
    flex-direction: column;
  }

  .cookieActions button {
    width: 100%;
  }
}
</style>
