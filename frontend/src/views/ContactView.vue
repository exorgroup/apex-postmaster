<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import { axiosInstance } from '@/composables/useApi'

const router = useRouter()

const form = ref({
  name: '',
  surname: '',
  email: '',
  phone: '',
  company: '',
  message: ''
})

const loading = ref(false)
const success = ref(false)
const error = ref('')

const submitForm = async () => {
  // Validate required fields
  if (!form.value.name || !form.value.surname || !form.value.email || !form.value.phone || !form.value.message) {
    error.value = 'Please fill in all required fields'
    return
  }

  // Basic email validation
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  if (!emailRegex.test(form.value.email)) {
    error.value = 'Please enter a valid email address'
    return
  }

  loading.value = true
  error.value = ''
  success.value = false

  try {
    await axiosInstance.post('/contact', form.value)
    success.value = true
    form.value = {
      name: '',
      surname: '',
      email: '',
      phone: '',
      company: '',
      message: ''
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to send message. Please try again.'
  } finally {
    loading.value = false
  }
}

const goBack = () => router.push('/')
</script>

<template>
  <div class="contactPage">
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

    <!-- Contact Form Section -->
    <section class="contactSection">
      <div class="sectionContainer">
        <h1>Contact Us</h1>
        <p class="subtitle">Get in touch with our team. We'll get back to you within 24 hours.</p>

        <div class="formContainer">
          <form @submit.prevent="submitForm">
            <div class="formRow">
              <div class="formField">
                <label for="name">First Name *</label>
                <InputText
                  id="name"
                  v-model="form.name"
                  placeholder="Enter your first name"
                  :disabled="loading"
                />
              </div>
              <div class="formField">
                <label for="surname">Last Name *</label>
                <InputText
                  id="surname"
                  v-model="form.surname"
                  placeholder="Enter your last name"
                  :disabled="loading"
                />
              </div>
            </div>

            <div class="formRow">
              <div class="formField">
                <label for="email">Email *</label>
                <InputText
                  id="email"
                  v-model="form.email"
                  type="email"
                  placeholder="your.email@example.com"
                  :disabled="loading"
                />
              </div>
              <div class="formField">
                <label for="phone">Phone *</label>
                <InputText
                  id="phone"
                  v-model="form.phone"
                  type="tel"
                  placeholder="+356 1234 5678"
                  :disabled="loading"
                />
              </div>
            </div>

            <div class="formField">
              <label for="company">Company (Optional)</label>
              <InputText
                id="company"
                v-model="form.company"
                placeholder="Your company name"
                :disabled="loading"
              />
            </div>

            <div class="formField">
              <label for="message">Message *</label>
              <Textarea
                id="message"
                v-model="form.message"
                rows="6"
                placeholder="How can we help you?"
                :disabled="loading"
              />
            </div>

            <div v-if="error" class="errorMessage">
              <i class="pi pi-exclamation-circle"></i>
              {{ error }}
            </div>

            <div v-if="success" class="successMessage">
              <i class="pi pi-check-circle"></i>
              Thank you for contacting us! We'll get back to you soon.
            </div>

            <Button
              type="submit"
              label="Send Message"
              :loading="loading"
              :disabled="loading"
              class="submitBtn"
            />
          </form>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
      <p>&copy; {{ new Date().getFullYear() }} APEX PostMaster. All rights reserved.</p>
    </footer>
  </div>
</template>

<style scoped>
.contactPage {
  --landing-bg: #0a0b0f;
  --landing-surface: #13141a;
  --landing-surface-alt: #1a1b23;
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

.contactSection {
  flex: 1;
  padding: 4rem 2rem;
}

.sectionContainer {
  max-width: 800px;
  margin: 0 auto;
}

h1 {
  font-size: 2.5rem;
  font-weight: 700;
  text-align: center;
  margin-bottom: 0.5rem;
}

.subtitle {
  text-align: center;
  color: var(--landing-text-muted);
  font-size: 1.125rem;
  margin-bottom: 3rem;
}

.formContainer {
  background: var(--landing-surface);
  border: 1px solid var(--landing-border);
  border-radius: 12px;
  padding: 2rem;
}

.formRow {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.formField {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
}

.formField label {
  font-weight: 600;
  font-size: 0.9375rem;
  color: var(--landing-text);
}

.errorMessage,
.successMessage {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 1rem;
  border-radius: 8px;
  margin-bottom: 1.5rem;
  font-size: 0.9375rem;
}

.errorMessage {
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #fca5a5;
}

.successMessage {
  background: rgba(34, 197, 94, 0.1);
  border: 1px solid rgba(34, 197, 94, 0.3);
  color: #86efac;
}

.submitBtn {
  width: 100%;
  background: var(--landing-gradient) !important;
  border: none !important;
  padding: 0.875rem !important;
  font-size: 1rem !important;
  font-weight: 600 !important;
}

.footer {
  border-top: 1px solid var(--landing-border);
  padding: 2rem;
  text-align: center;
  color: var(--landing-text-muted);
  font-size: 0.875rem;
}

@media (max-width: 768px) {
  .formRow {
    grid-template-columns: 1fr;
    gap: 0;
  }

  h1 {
    font-size: 2rem;
  }

  .formContainer {
    padding: 1.5rem;
  }
}
</style>
