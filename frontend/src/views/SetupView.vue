<template>
  <div class="setup-page">
    <div class="setup-card card">
      <div class="modal-header" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
        <h1 style="font-size: 20px; display: flex; align-items: center; gap: 8px;">
          <span class="logo-icon" v-html="icons.settings"></span>
          Settings
        </h1>
        <button class="btn-icon" @click="router.push('/')" v-html="icons.close"></button>
      </div>

      <div v-if="success" class="auth-error" style="background: var(--accent-dim); color: var(--accent);">
        {{ success }}
      </div>

      <div class="form-group" style="margin-bottom: 24px;">
        <label class="form-label">Language / Bahasa</label>
        <div style="display: flex; gap: 10px;">
          <button class="btn" :class="language === 'id' ? 'btn-primary' : 'btn-ghost'" style="flex:1" @click="setLanguage('id')">Indonesian</button>
          <button class="btn" :class="language === 'en' ? 'btn-primary' : 'btn-ghost'" style="flex:1" @click="setLanguage('en')">English</button>
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 24px;">
        <label class="form-label">Theme</label>
        <div style="display: flex; gap: 10px;">
          <button class="btn" :class="theme === 'dark' ? 'btn-primary' : 'btn-ghost'" style="flex:1" @click="setTheme('dark')">Dark Mode</button>
          <button class="btn" :class="theme === 'light' ? 'btn-primary' : 'btn-ghost'" style="flex:1" @click="setTheme('light')">Light Mode</button>
        </div>
      </div>

      <div class="form-group" style="margin-bottom: 24px;">
        <button class="btn btn-primary" style="width: 100%; padding: 12px; margin-bottom: 12px;" @click="saveSettings">
          <span v-html="icons.check"></span> Save Settings
        </button>

        <label class="form-label" style="color: var(--danger)">Danger Zone</label>
        <button class="btn btn-danger" style="width: 100%; padding: 12px;" @click="handleLogout">
          <span v-html="icons.logout"></span> Logout
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { icons } from '../components/icons.js'
import { useAuthStore } from '../stores/auth.js'
import { currentLanguage } from '../i18n.js'

const router = useRouter()
const auth = useAuthStore()

const success = ref('')
const language = ref('id')
const theme = ref('dark')

onMounted(() => {
  language.value = localStorage.getItem('language') || 'id'
  theme.value = localStorage.getItem('theme') || 'dark'
})

function setLanguage(lang) {
  language.value = lang
}

function setTheme(t) {
  theme.value = t
}

function saveSettings() {
  currentLanguage.value = language.value
  localStorage.setItem('theme', theme.value)
  document.documentElement.setAttribute('data-theme', theme.value)
  success.value = 'Settings saved successfully!'
  setTimeout(() => router.push('/'), 800)
}

function handleLogout() {
  auth.logout()
  router.push('/login')
}
</script>
