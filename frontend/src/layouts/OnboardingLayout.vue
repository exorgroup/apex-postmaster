<script setup>
import { computed } from 'vue'

const currentYear = computed(() => new Date().getFullYear())
</script>

<template>
  <div class="onboardingLayout">
    <div class="onboardingHeader">
      <div class="apLogo">
        <span>APEX</span> PostMaster
      </div>
    </div>
    <div class="onboardingContent">
      <slot />
    </div>
    <div class="onboardingFooter">
      <p>&copy; {{ currentYear }} APEX PostMaster</p>
    </div>
  </div>
</template>

<style scoped>
.onboardingLayout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: var(--ap-bg);
}

.onboardingHeader {
  padding: 1.5rem 2rem;
  border-bottom: 1px solid var(--ap-border);
}

.onboardingContent {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}

.onboardingFooter {
  padding: 1rem 2rem;
  text-align: center;
  font-size: 0.75rem;
  color: var(--ap-text-dim);
  border-top: 1px solid var(--ap-border);
}

.onboardingFooter p {
  margin: 0;
}
</style>
