<script setup>
import { RouterView } from 'vue-router'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import CookieConsent from '@/components/CookieConsent.vue'
</script>

<template>
  <div class="app-dark">
    <Toast position="top-right" />
    <ConfirmDialog />
    <RouterView />
    <CookieConsent />
  </div>
</template>

<style>
.app-dark {
  min-height: 100vh;
}
</style>
