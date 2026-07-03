<template>
  <div style="height: 100%;">
    <!-- Global App Settings Button -->
    <button 
      v-if="$route.path === '/'"
      class="btn-icon global-settings-btn" 
      @click="showAppSettings = true"
      style="position: fixed; top: 16px; right: 16px; z-index: 9000; background: transparent; border: none; box-shadow: none; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; color: var(--text-secondary); cursor: pointer;"
      onmouseover="this.style.color='var(--text)'"
      onmouseout="this.style.color='var(--text-secondary)'"
    >
      <span v-html="icons.moreVertical"></span>
    </button>

    <!-- Global App Settings Modal -->
    <div v-if="showAppSettings" class="modal-overlay" style="z-index: 10000; backdrop-filter: blur(8px); background: rgba(0,0,0,0.6);" @click.self="showAppSettings = false">
      <div class="modal card" style="max-width: 400px; padding: 24px; border-radius: 24px; box-shadow: 0 24px 48px rgba(0,0,0,0.2); border: 1px solid var(--border-light); position: relative;">
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
          <h2 style="margin: 0; font-size: 20px; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--accent);"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            {{ t('settings') }}
          </h2>
          <button class="btn-icon" @click="showAppSettings = false" style="color: var(--text-secondary); background: var(--bg-elevated); border-radius: 50%; padding: 6px; width: 32px; height: 32px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
          </button>
        </div>

        <!-- Language Segmented Control -->
        <div style="margin-bottom: 24px;">
          <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">{{ t('language') }}</label>
          <div style="display: flex; gap: 8px; background: var(--bg-elevated); padding: 6px; border-radius: 16px; border: 1px solid var(--border-light);">
            <button @click="currentLanguage = 'id'" :style="[currentLanguage === 'id' ? 'background: var(--bg-card); box-shadow: 0 4px 12px rgba(0,0,0,0.1); color: var(--text-primary); border: 1px solid var(--border);' : 'background: transparent; color: var(--text-secondary); border: 1px solid transparent;']" style="flex: 1; padding: 12px; border-radius: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
              {{ t('indonesian') }}
            </button>
            <button @click="currentLanguage = 'en'" :style="[currentLanguage === 'en' ? 'background: var(--bg-card); box-shadow: 0 4px 12px rgba(0,0,0,0.1); color: var(--text-primary); border: 1px solid var(--border);' : 'background: transparent; color: var(--text-secondary); border: 1px solid transparent;']" style="flex: 1; padding: 12px; border-radius: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
              {{ t('english') }}
            </button>
          </div>
        </div>

        <!-- Theme Segmented Control -->
        <div style="margin-bottom: 32px;">
          <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">{{ t('theme') }}</label>
          <div style="display: flex; gap: 8px; background: var(--bg-elevated); padding: 6px; border-radius: 16px; border: 1px solid var(--border-light);">
            <button @click="setTheme('light')" :style="[currentTheme === 'light' ? 'background: var(--bg-card); box-shadow: 0 4px 12px rgba(0,0,0,0.1); color: var(--text-primary); border: 1px solid var(--border);' : 'background: transparent; color: var(--text-secondary); border: 1px solid transparent;']" style="flex: 1; padding: 12px; border-radius: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
              {{ t('lightTheme') }}
            </button>
            <button @click="setTheme('dark')" :style="[currentTheme === 'dark' ? 'background: var(--bg-card); box-shadow: 0 4px 12px rgba(0,0,0,0.1); color: var(--text-primary); border: 1px solid var(--border);' : 'background: transparent; color: var(--text-secondary); border: 1px solid transparent;']" style="flex: 1; padding: 12px; border-radius: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
              {{ t('darkTheme') }}
            </button>
          </div>
        </div>

        <div style="text-align: right;">
          <button class="btn btn-primary" @click="showAppSettings = false" style="width: 100%; padding: 14px; border-radius: 16px; font-size: 15px; font-weight: 700;">{{ t('close') }}</button>
        </div>
      </div>
    </div>

    <router-view />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { icons } from './components/icons.js'
import { currentLanguage, t } from './i18n.js'

const showAppSettings = ref(false)
const currentTheme = ref(localStorage.getItem('ad_theme') || 'dark')

function setTheme(theme) {
  currentTheme.value = theme
  document.documentElement.setAttribute('data-theme', theme)
  localStorage.setItem('ad_theme', theme)
}

onMounted(() => {
  const savedTheme = localStorage.getItem('ad_theme') || localStorage.getItem('theme') || 'dark'
  document.documentElement.setAttribute('data-theme', savedTheme)
  currentTheme.value = savedTheme
  
  if (!localStorage.getItem('language')) {
    localStorage.setItem('language', 'id')
  }
})
</script>
