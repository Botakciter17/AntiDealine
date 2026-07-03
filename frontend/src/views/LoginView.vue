<template>
  <div class="auth-page">
    <div v-if="showOnboarding" class="onboarding-screen">
      <div class="onboarding-left">
        <div class="onboarding-logo">
          <img src="/tuntaz-logo.png" alt="Tuntaz Logo" style="width: 28px; height: 28px; object-fit: contain;" />
          Tuntaz
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

    <div v-else class="auth-card" style="background: #18181b; border-radius: 32px; padding: 40px 32px; border: 1px solid #27272a; max-width: 420px; width: 100%; margin: auto; box-shadow: 0 24px 48px rgba(0,0,0,0.4);">
      <div class="auth-logo" style="text-align: center; margin-bottom: 32px;">
        <div style="margin-bottom: 12px; display: inline-flex;">
          <img src="/tuntaz-logo.png" alt="Tuntaz Logo" style="width: 72px; height: 72px; object-fit: contain;" />
        </div>
        <h1 style="font-size: 32px; font-weight: 800; color: white; margin: 0 0 8px 0; letter-spacing: -1px;">Tuntaz</h1>
        <p style="color: #a1a1aa; font-size: 14px;">{{ t('subtitleLogin') }}</p>
      </div>

      <div v-if="error" class="auth-error" style="color: #ef4444; background: rgba(239,68,68,0.1); padding: 12px; border-radius: 12px; font-size: 13px; margin-bottom: 24px; text-align: center;">{{ error }}</div>
      
      <form class="auth-form" @submit.prevent="handleSubmit">
        <!-- Register Full Name -->
        <div class="form-group" v-if="!isLogin" style="margin-bottom: 24px;">
          <label style="display: block; font-size: 11px; font-weight: 700; color: #d4d4d8; letter-spacing: 1.5px; margin-bottom: 8px;">{{ t('fullNameLabel') }}</label>
          <div style="position: relative;">
            <input v-model="username" type="text" placeholder="Bagus Arya L" required style="width: 100%; background: #18181b; border: 1px solid #27272a; border-radius: 12px; padding: 14px 16px; color: white; font-size: 15px; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='#d4f279'" onblur="this.style.borderColor='#27272a'" />
            <svg style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #52525b; pointer-events: none;" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
        </div>

        <!-- Email -->
        <div class="form-group" style="margin-bottom: 24px;">
          <label style="display: block; font-size: 11px; font-weight: 700; color: #d4d4d8; letter-spacing: 1.5px; margin-bottom: 8px;">{{ t('emailLabel') }}</label>
          <div style="position: relative;">
            <input v-model="email" type="email" placeholder="contoh@email.com" required style="width: 100%; background: #18181b; border: 1px solid #27272a; border-radius: 12px; padding: 14px 16px; color: white; font-size: 15px; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='#d4f279'" onblur="this.style.borderColor='#27272a'" />
            <span style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #52525b; font-weight: 600; font-size: 18px; pointer-events: none;">@</span>
          </div>
        </div>

        <!-- Password -->
        <div class="form-group" style="margin-bottom: 32px;">
          <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px;">
            <label style="font-size: 11px; font-weight: 700; color: #d4d4d8; letter-spacing: 1.5px;">{{ t('passwordLabel') }}</label>
            <a v-if="isLogin" href="#" style="font-size: 11px; color: #d4f279; text-decoration: none; font-weight: 700;">{{ t('forgotPassword') }}</a>
          </div>
          <div style="position: relative;">
            <input v-model="password" type="password" placeholder="••••••••" required style="width: 100%; background: #18181b; border: 1px solid #27272a; border-radius: 12px; padding: 14px 16px; color: white; font-size: 15px; outline: none; letter-spacing: 2px; transition: border-color 0.2s;" onfocus="this.style.borderColor='#d4f279'" onblur="this.style.borderColor='#27272a'" />
            <svg style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #52525b; pointer-events: none;" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </div>
        </div>

        <!-- Submit Button -->
        <button type="submit" :disabled="loading" style="width: 100%; background: #d4f279; color: #18181b; border: none; border-radius: 16px; padding: 16px; font-size: 16px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 14px rgba(212,242,121,0.2); transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='scale(1.02)'; this.style.boxShadow='0 6px 20px rgba(212,242,121,0.3)';" onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 4px 14px rgba(212,242,121,0.2)';">
          <span v-if="loading" class="spinner"></span>
          <template v-else>
            <span v-if="isLogin">{{ t('signIn') }}</span>
            <svg v-if="isLogin" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            <span v-if="!isLogin">{{ t('createAccount') }}</span>
            <svg v-if="!isLogin" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
          </template>
        </button>
      </form>
      
      <div style="margin: 32px 0; display: flex; align-items: center; justify-content: center; gap: 16px;">
        <div style="flex: 1; height: 1px; background: #27272a;"></div>
        <span style="color: #52525b; font-size: 11px; font-weight: 700; letter-spacing: 1.5px;">{{ t('orContinueWith') }}</span>
        <div style="flex: 1; height: 1px; background: #27272a;"></div>
      </div>
      
      <!-- Container for Google Button -->
      <div style="display: flex; justify-content: center; margin-bottom: 40px; width: 100%;">
        <div id="googleSignInContainer" style="width: 100%; display: flex; justify-content: center;"></div>
      </div>
      
      <div style="text-align: center; font-size: 14px; color: #a1a1aa;">
        <span v-if="isLogin">{{ t('noAccount') }} <button @click="isLogin = false" style="background: none; border: none; color: #d4f279; font-weight: 700; cursor: pointer; padding: 0;">{{ t('register') }}</button></span>
        <span v-else>{{ t('haveAccount') }} <button @click="isLogin = true" style="background: none; border: none; color: #d4f279; font-weight: 700; cursor: pointer; padding: 0;">{{ t('signIn') }}</button></span>
      </div>
    </div>
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
const email = ref('')
const username = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

function nextStep() {
  if (onboardingStep.value === 0) {
    onboardingStep.value = 1
  } else {
    showOnboarding.value = false
    initGoogleLogin()
  }
}

function initGoogleLogin() {
  const checkGoogle = setInterval(() => {
    if (window.google && document.getElementById("googleSignInContainer")) {
      clearInterval(checkGoogle)
      window.google.accounts.id.initialize({
        client_id: "117711463661-kb9kehrd1csom0ls7gophcsfb00vobma.apps.googleusercontent.com",
        callback: handleGoogleCredentialResponse,
        context: "signin",
        ux_mode: "popup"
      })
      window.google.accounts.id.renderButton(
        document.getElementById("googleSignInContainer"),
        { theme: "filled_black", size: "large", type: "standard", shape: "rectangular", text: "continue_with", width: 350 }
      )
    }
  }, 100)
}

async function handleGoogleCredentialResponse(response) {
  error.value = ''
  loading.value = true
  try {
    await auth.googleLogin(response.credential)
    router.push('/')
  } catch (e) {
    error.value = 'Gagal login dengan Google: ' + e.message
  } finally {
    loading.value = false
  }
}

async function handleSubmit() {
  error.value = ''
  loading.value = true
  try {
    if (isLogin.value) await auth.login(email.value, password.value)
    else await auth.register(email.value, username.value, password.value)
    router.push('/')
  } catch (e) { error.value = e.message } finally { loading.value = false }
}
</script>
