<script setup>
import { useRouter } from 'vue-router'
import Button from 'primevue/button'

const props = defineProps({
  title: {
    type: String,
    required: true
  },
  description: {
    type: String,
    default: 'We are working hard to bring you this feature. Stay tuned for updates!'
  }
})

const router = useRouter()
const goBack = () => router.push('/')
</script>

<template>
  <div class="placeholderPage">
    <!-- Navigation -->
    <nav class="navbar">
      <div class="navContainer">
        <div class="navBrand" @click="goBack" style="cursor: pointer">
          <span class="brandIcon">▲</span>
          <span class="brandText">APEX PostMaster</span>
        </div>
        <Button label="Back to Home" text @click="goBack" />
      </div>
    </nav>

    <!-- Content Section -->
    <section class="contentSection">
      <div class="sectionContainer">
        <div class="iconContainer">
          <i class="pi pi-wrench"></i>
        </div>
        <h1>{{ title }}</h1>
        <p class="description">{{ description }}</p>
        <Button label="Return to Home" @click="goBack" class="homeBtn" />
      </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
      <p>&copy; {{ new Date().getFullYear() }} APEX PostMaster. All rights reserved.</p>
    </footer>
  </div>
</template>

<style scoped>
.placeholderPage {
  --landing-bg: #0a0b0f;
  --landing-surface: #13141a;
  --landing-border: #2a2b35;
  --landing-text: #f0f0f5;
  --landing-text-muted: #9ca3af;
  --landing-accent: #6366f1;
  --landing-gradient: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);

  background: var(--landing-bg);
  color: var(--landing-text);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.navbar {
  border-bottom: 1px solid var(--landing-border);
  background: rgba(10, 11, 15, 0.9);
  backdrop-filter: blur(12px);
}

.navContainer {
  max-width: 1280px;
  margin: 0 auto;
  padding: 1rem 2rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.navBrand {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-weight: 700;
  font-size: 1.25rem;
}

.brandIcon {
  color: var(--landing-accent);
  font-size: 1.5rem;
}

.contentSection {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
}

.sectionContainer {
  text-align: center;
  max-width: 600px;
}

.iconContainer {
  margin-bottom: 2rem;
}

.iconContainer i {
  font-size: 4rem;
  color: var(--landing-accent);
}

h1 {
  font-size: 2.5rem;
  font-weight: 700;
  margin-bottom: 1rem;
}

.description {
  color: var(--landing-text-muted);
  font-size: 1.125rem;
  line-height: 1.6;
  margin-bottom: 2rem;
}

.homeBtn {
  background: var(--landing-gradient) !important;
  border: none !important;
  padding: 0.875rem 2rem !important;
}

.footer {
  border-top: 1px solid var(--landing-border);
  padding: 2rem;
  text-align: center;
  color: var(--landing-text-muted);
  font-size: 0.875rem;
}

@media (max-width: 768px) {
  h1 {
    font-size: 2rem;
  }

  .iconContainer i {
    font-size: 3rem;
  }
}
</style>
