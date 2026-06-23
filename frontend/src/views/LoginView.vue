<template>
  <div class="auth-page">
    <div v-if="showOnboarding" class="onboarding-screen">
      <div class="onboarding-left">
        <div class="onboarding-logo">
          <span class="logo-icon" v-html="icons.bolt"></span>
          AntiDeadline
        </div>
        <div class="onboarding-image-container">
          <img :src="onboardingStep === 0 ? '/illustration-2.png' : '/illustration-1.png'" alt="Illustration" class="onboarding-img" />
        </div>
      </div>

      <div class="onboarding-right">
        <div class="onboarding-card">
          <h1 v-if="onboardingStep === 0" v-html="t('onboarding1Title')"></h1>
          <h1 v-if="onboardingStep === 1" v-html="t('onboarding2Title')"></h1>
          
          <p v-if="onboardingStep === 0">{{ t('onboarding1Desc') }}</p>
          <p v-if="onboardingStep === 1">{{ t('onboarding2Desc') }}</p>
          
          <div class="onboarding-footer">
            <div class="onboarding-dots">
              <span :class="{ active: onboardingStep === 0 }"></span>
              <span :class="{ active: onboardingStep === 1 }"></span>
            </div>
            <button class="btn-next" @click="nextStep">
              <span v-if="onboardingStep === 0">{{ t('next') }}</span>
              <span v-else>{{ t('getStarted') }}</span>
              <span class="next-icon" v-html="icons.chevronRight"></span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="auth-card card">
      <div class="auth-logo">
        <h1>
          <span class="logo-icon" v-html="icons.bolt"></span>
          AntiDeadline
        </h1>
        <p>{{ t('subtitleLogin') }}</p>
      </div>
      <div v-if="error" class="auth-error">{{ error }}</div>
      <form class="auth-form" @submit.prevent="handleSubmit">
        <div class="form-group">
          <label class="form-label">{{ t('username') }}</label>
          <input v-model="username" type="text" class="input" placeholder="..." required autocomplete="username" />
        </div>
        <div class="form-group">
          <label class="form-label">{{ t('password') }}</label>
          <input v-model="password" type="password" class="input" placeholder="..." required autocomplete="current-password" />
        </div>
        <button type="submit" class="btn btn-primary" :disabled="loading" style="width: 100%;">
          <span v-if="loading" class="spinner"></span>
          <span v-else>{{ isLogin ? t('signIn') : t('signUp') }}</span>
        </button>
      </form>
      <div class="auth-footer">
        <span v-if="isLogin">{{ t('noAccount') }} <button @click="isLogin = false">{{ t('signUp') }}</button></span>
        <span v-else>{{ t('haveAccount') }} <button @click="isLogin = true">{{ t('signIn') }}</button></span>
      </div>
    </div>

    <!-- Language Toggle Button -->
    <button 
      @click="toggleLang" 
      class="btn-icon" 
      style="position: absolute; top: 20px; right: 20px; z-index: 100; font-weight: 600; font-size: 14px; background: var(--bg-card); border-radius: 50%; width: 40px; height: 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);"
    >
      {{ currentLanguage === 'id' ? 'ID' : 'EN' }}
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import { icons } from '../components/icons.js'
import { t, currentLanguage } from '../i18n.js'

const router = useRouter()
const auth = useAuthStore()

const showOnboarding = ref(true)
const onboardingStep = ref(0)
const isLogin = ref(true)
const username = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

function toggleLang() {
  currentLanguage.value = currentLanguage.value === 'id' ? 'en' : 'id'
}

function nextStep() {
  if (onboardingStep.value === 0) {
    onboardingStep.value = 1
  } else {
    showOnboarding.value = false
  }
}

async function handleSubmit() {
  error.value = ''
  loading.value = true
  try {
    if (isLogin.value) await auth.login(username.value, password.value)
    else await auth.register(username.value, password.value)
    router.push('/')
  } catch (e) { error.value = e.message } finally { loading.value = false }
}
</script>
