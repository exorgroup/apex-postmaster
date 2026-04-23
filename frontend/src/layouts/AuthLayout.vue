<script setup>
import { computed } from 'vue'

const currentYear = computed(() => new Date().getFullYear())
</script>

<template>
  <div class="authLayout">
    <div class="authContainer">
      <div class="authLogo">
        <span>APEX</span> PostMaster
      </div>
      <slot />
      <div class="authFooter">
        <p>&copy; {{ currentYear }} APEX PostMaster. All rights reserved.</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.authLayout {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
  background: var(--ap-bg);
}

.authContainer {
  width: 100%;
  max-width: 400px;
}

.authLogo {
  font-size: 1.5rem;
  font-weight: 700;
  text-align: center;
  margin-bottom: 2rem;
  color: var(--ap-text);
}

.authLogo span {
  color: var(--ap-accent);
}

.authFooter {
  margin-top: 2rem;
  text-align: center;
  font-size: 0.75rem;
  color: var(--ap-text-dim);
}

.authFooter p {
  margin: 0;
}
</style>
