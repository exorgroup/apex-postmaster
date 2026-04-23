<script setup>
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import AuthLayout from '@/layouts/AuthLayout.vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Message from 'primevue/message'

const auth = useAuthStore()

const email = ref('')
const localError = ref('')
const submitted = ref(false)

const handleSubmit = async () => {
  localError.value = ''

  if (!email.value) {
    localError.value = 'Please enter your email address'
    return
  }

  const result = await auth.forgotPassword(email.value)
  if (result.success) {
    submitted.value = true
  } else {
    localError.value = result.error
  }
}
</script>

<template>
  <AuthLayout>
    <div class="authCard apCard">
      <template v-if="!submitted">
        <h2 class="authTitle">Reset your password</h2>
        <p class="authSubtitle">
          Enter your email address and we'll send you a link to reset your password.
        </p>

        <form @submit.prevent="handleSubmit" class="authForm">
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

          <Button
            type="submit"
            label="Send reset link"
            class="w-full submitBtn"
            :loading="auth.loading"
          />
        </form>

        <p class="authSwitch">
          Remember your password?
          <router-link to="/login">Sign in</router-link>
        </p>
      </template>

      <template v-else>
        <div class="successState">
          <i class="pi pi-check-circle successIcon"></i>
          <h2 class="authTitle">Check your email</h2>
          <p class="authSubtitle">
            We've sent a password reset link to <strong>{{ email }}</strong>
          </p>
          <p class="authSubtitle">
            If you don't see it, check your spam folder.
          </p>
          <Button
            label="Back to sign in"
            class="w-full"
            outlined
            @click="$router.push('/login')"
          />
        </div>
      </template>
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

.successState {
  text-align: center;
}

.successIcon {
  font-size: 3rem;
  color: var(--ap-success);
  margin-bottom: 1rem;
}

.successState strong {
  color: var(--ap-text);
}

:deep(.p-inputtext) {
  width: 100%;
}
</style>
