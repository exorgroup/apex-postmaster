<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AuthLayout from '@/layouts/AuthLayout.vue'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Message from 'primevue/message'

const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const localError = ref('')

const handleLogin = async () => {
  localError.value = ''

  if (!email.value || !password.value) {
    localError.value = 'Please enter your email and password'
    return
  }

  const result = await auth.login(email.value, password.value)
  if (!result.success) {
    localError.value = result.error
  }
}

const loginWithGoogle = () => {
  auth.loginWithProvider('google')
}

const loginWithFacebook = () => {
  auth.loginWithProvider('facebook')
}
</script>

<template>
  <AuthLayout>
    <div class="authCard apCard">
      <h2 class="authTitle">Welcome back</h2>
      <p class="authSubtitle">Sign in to your account to continue</p>

      <div class="socialButtons">
        <Button
          class="googleBtn"
          @click="loginWithGoogle"
        >
          <svg class="googleIcon" viewBox="0 0 24 24" width="20" height="20">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
          </svg>
          Continue with Google
        </Button>
        <Button
          class="facebookBtn"
          @click="loginWithFacebook"
        >
          <i class="pi pi-facebook"></i>
          Continue with Facebook
        </Button>
      </div>

      <div class="apDivider">or</div>

      <form @submit.prevent="handleLogin" class="authForm">
        <Message v-if="localError" severity="error" :closable="false" class="formError">
          {{ localError }}
        </Message>

        <div class="formGroup">
          <label for="email">Email</label>
          <InputText
            id="email"
            v-model="email"
            type="email"
            placeholder="you@example.com"
            class="w-full"
          />
        </div>

        <div class="formGroup">
          <label for="password">Password</label>
          <Password
            id="password"
            v-model="password"
            :feedback="false"
            toggleMask
            placeholder="Enter your password"
            class="w-full"
            inputClass="w-full"
          />
        </div>

        <div class="formActions">
          <router-link to="/forgot-password" class="forgotLink">
            Forgot password?
          </router-link>
        </div>

        <Button
          type="submit"
          label="Sign in"
          class="w-full submitBtn"
          :loading="auth.loading"
        />
      </form>

      <p class="authSwitch">
        Don't have an account?
        <router-link to="/register">Sign up</router-link>
      </p>
    </div>
  </AuthLayout>
</template>

<style scoped>
.authCard {
  padding: 2rem;
}

.authTitle {
  text-align: center;
  margin-bottom: 0.5rem;
  font-size: 1.5rem;
}

.authSubtitle {
  text-align: center;
  color: var(--ap-text-muted);
  margin-bottom: 1.5rem;
}

.socialButtons {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-bottom: 1.5rem;
}

.googleBtn {
  background: white !important;
  color: #1f1f1f !important;
  border: 1px solid var(--ap-border) !important;
  justify-content: center;
  gap: 0.75rem;
}

.googleBtn:hover {
  background: #f8f8f8 !important;
}

.googleIcon {
  flex-shrink: 0;
}

.facebookBtn {
  background: #1877f2 !important;
  border-color: #1877f2 !important;
  justify-content: center;
  gap: 0.75rem;
}

.facebookBtn:hover {
  background: #166fe5 !important;
}

.authForm {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.formGroup {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.formGroup label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--ap-text);
}

.formActions {
  display: flex;
  justify-content: flex-end;
}

.forgotLink {
  font-size: 0.875rem;
  color: var(--ap-text-muted);
}

.forgotLink:hover {
  color: var(--ap-accent);
}

.submitBtn {
  margin-top: 0.5rem;
}

.formError {
  margin-bottom: 0.5rem;
}

.authSwitch {
  text-align: center;
  margin-top: 1.5rem;
  font-size: 0.875rem;
  color: var(--ap-text-muted);
}

.authSwitch a {
  color: var(--ap-accent);
  font-weight: 500;
}

:deep(.p-password) {
  width: 100%;
}

:deep(.p-inputtext) {
  width: 100%;
}
</style>
