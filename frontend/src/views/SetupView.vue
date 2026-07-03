<template>
  <div class="setup-page">
    <div class="setup-card card" style="max-width: 480px; padding: 0; overflow: hidden; border: 1px solid var(--border); background: var(--bg-card); box-shadow: 0 16px 40px rgba(0,0,0,0.1);">
      <!-- Standard Header -->
      <div style="padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; background: var(--bg-card); border-bottom: 1px solid var(--border-light);">
        <button class="btn-icon" @click="router.push('/')" v-html="icons.chevronRight" style="transform: rotate(180deg); margin-left: -8px; color: var(--text-secondary);"></button>
        <h1 style="font-size: 18px; font-weight: 600; margin: 0; letter-spacing: 0.5px; color: var(--text-primary);">{{ t('profileAccount') }}</h1>
        <div style="width: 32px;"></div> <!-- Spacer -->
      </div>

      <!-- Profile Header (Avatar) -->
      <div style="text-align: center; padding: 32px 24px 24px 24px; display: flex; flex-direction: column; align-items: center; position: relative;">
        <div style="position: relative; margin-bottom: 16px;">
          <img v-if="avatarPreview || avatarUrl" :src="avatarPreview || avatarUrl" style="width: 112px; height: 112px; border-radius: 50%; object-fit: cover; border: 4px solid var(--bg-card); box-shadow: 0 8px 24px rgba(0,0,0,0.15);" @error="avatarUrl = ''" />
          <div v-else class="user-avatar" style="width: 112px; height: 112px; border-radius: 50%; background: var(--accent); display: flex; align-items: center; justify-content: center; font-weight: 700; color: #18181b; border: 4px solid var(--bg-card); box-shadow: 0 8px 24px rgba(0,0,0,0.15); font-size: 44px;">
            {{ auth.user?.display_name ? auth.user.display_name.charAt(0).toUpperCase() : auth.user?.username?.charAt(0).toUpperCase() }}
          </div>
          <button @click="triggerAvatarUpload" style="position: absolute; bottom: 4px; right: 4px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 50%; width: 32px; height: 32px; display: flex; justify-content: center; align-items: center; color: var(--text-primary); cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 4px 10px rgba(0,0,0,0.15);" onmouseover="this.style.transform='scale(1.1)'; this.style.boxShadow='0 6px 14px rgba(0,0,0,0.2)';" onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 4px 10px rgba(0,0,0,0.15)';">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          </button>
          <input type="file" ref="avatarInput" @change="handleAvatarChange" accept="image/jpeg,image/png,image/webp,image/gif" style="display: none;" />
        </div>
        <h2 style="font-size: 24px; font-weight: 800; margin: 0 0 4px 0; color: var(--text-primary); letter-spacing: -0.5px;">{{ auth.user?.display_name || auth.user?.username }}</h2>
        <p style="color: var(--text-secondary); margin: 0; font-size: 15px; font-weight: 500;">@{{ auth.user?.username }}</p>
      </div>

      <!-- Quick Stats -->
      <div style="display: flex; gap: 12px; padding: 0 24px 32px 24px; justify-content: center;">
        <div style="background: var(--accent); color: #18181b; padding: 16px 20px; border-radius: 12px; flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; border: none; box-shadow: 0 4px 12px var(--accent-dim);">
          <span style="font-size: 16px; font-weight: 700; margin-bottom: 4px;">{{ tasks.activeTasks.length }}</span>
          <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">{{ t('activeTasks') }}</span>
        </div>
        <div style="background: #3f3f46; color: var(--accent); padding: 16px 20px; border-radius: 12px; flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; border: none;">
          <span style="font-size: 16px; font-weight: 700; margin-bottom: 4px;">{{ tasks.completedTasks.length }}</span>
          <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">{{ t('completed') }}</span>
        </div>
      </div>

      <!-- Informasi Pribadi Section -->
      <div style="padding: 0 24px 32px 24px;">
        <div style="background: var(--bg-elevated); border-radius: 20px; padding: 24px; border: 1px solid var(--border-light); box-shadow: 0 4px 24px rgba(0,0,0,0.04);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--text-primary); letter-spacing: -0.3px;">{{ t('personalInfo') }}</h3>
            <button v-if="!isEditing" @click="startEditing" class="btn-icon" style="color: var(--accent); background: var(--accent-dim); border-radius: 50%; padding: 6px; width: 32px; height: 32px; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button v-else @click="saveSettings" class="btn btn-primary" style="padding: 6px 16px; font-size: 13px; height: auto; border-radius: 10px; box-shadow: 0 4px 12px var(--accent-dim);" :disabled="saving">
              <span v-if="saving" class="spinner"></span><span v-else style="font-weight: 600;">{{ t('save') }}</span>
            </button>
          </div>

          <div v-if="success" style="color: var(--health-green); font-size: 13px; margin-bottom: 20px; background: rgba(76, 175, 80, 0.1); padding: 10px 14px; border-radius: 10px; border: 1px solid rgba(76, 175, 80, 0.2); font-weight: 500; display: flex; align-items: center; gap: 8px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            {{ success }}
          </div>

          <!-- Nama -->
          <div style="display: flex; align-items: flex-start; gap: 16px; margin-bottom: 20px;">
            <div style="color: var(--accent); width: 36px; height: 36px; border-radius: 10px; background: var(--bg); display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-light);">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <div style="flex: 1; padding-top: 2px;">
              <div style="font-size: 11px; color: var(--text-secondary); margin-bottom: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">{{ t('displayName') }}</div>
              <div v-if="!isEditing" style="font-size: 14px; font-weight: 600; color: var(--text-primary);">{{ auth.user?.display_name || auth.user?.username }}</div>
              <input v-else v-model="displayName" type="text" class="input" style="padding: 10px 14px; font-size: 14px; border-radius: 10px; border: 1px solid var(--border); width: 100%; background: var(--bg);" />
            </div>
          </div>

          <div style="height: 1px; background: var(--border-light); margin-bottom: 20px; margin-left: 52px;"></div>

          <!-- Username -->
          <div style="display: flex; align-items: flex-start; gap: 16px; margin-bottom: 20px;">
            <div style="color: var(--accent); width: 36px; height: 36px; border-radius: 10px; background: var(--bg); display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-light);">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            </div>
            <div style="flex: 1; padding-top: 2px; min-width: 0;">
              <div style="font-size: 11px; color: var(--text-secondary); margin-bottom: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Username</div>
              <div style="font-size: 14px; font-weight: 500; color: var(--text-primary); word-break: break-all;">@{{ auth.user?.username }}</div>
              <div v-if="isEditing" style="font-size: 11px; color: var(--accent); margin-top: 8px; font-weight: 500; background: var(--accent-dim); padding: 6px 10px; border-radius: 6px; display: inline-block;">
                {{ t('changeUsernameLimit') }}
              </div>
            </div>
          </div>

          <div style="height: 1px; background: var(--border-light); margin-bottom: 20px; margin-left: 52px;"></div>

          <!-- Email -->
          <div style="display: flex; align-items: flex-start; gap: 16px;">
            <div style="color: var(--accent); width: 36px; height: 36px; border-radius: 10px; background: var(--bg); display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-light);">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            </div>
            <div style="flex: 1; padding-top: 2px; min-width: 0;">
              <div style="font-size: 11px; color: var(--text-secondary); margin-bottom: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Email</div>
              <div style="font-size: 14px; font-weight: 500; color: var(--text-primary); word-break: break-all;">{{ auth.user?.email || t('notSet') }}</div>
              <div v-if="isEditing" style="font-size: 11px; color: var(--accent); margin-top: 8px; font-weight: 500; background: var(--accent-dim); padding: 6px 10px; border-radius: 6px; display: inline-block;">
                {{ t('cannotChange') }}
              </div>
            </div>
          </div>
        </div>
        
        <!-- WhatsApp Alert Section -->
        <div style="background: var(--bg-elevated); border-radius: 20px; padding: 24px; border: 1px solid var(--border-light); box-shadow: 0 4px 24px rgba(0,0,0,0.04); margin-top: 24px;">
          <h3 style="margin: 0 0 16px 0; font-size: 16px; font-weight: 700; color: var(--text-primary); letter-spacing: -0.3px;">{{ t('waAlertTitle') }}</h3>
          <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 20px; line-height: 1.5;">{{ t('waAlertDesc') }}</p>
          
          <div v-if="auth.user?.whatsapp_verified" style="display: flex; align-items: center; gap: 12px; background: rgba(76, 175, 80, 0.1); padding: 12px 16px; border-radius: 12px; border: 1px solid rgba(76, 175, 80, 0.2);">
            <div style="color: var(--health-green); font-size: 20px;">✓</div>
            <div>
              <div style="font-size: 14px; font-weight: 600; color: var(--text-primary);">{{ t('verified') }}</div>
              <div style="font-size: 13px; color: var(--health-green);">{{ t('alertWillBeSentTo') }} {{ auth.user?.whatsapp_number }}</div>
            </div>
            <button @click="resetWhatsapp" class="btn" style="margin-left: auto; font-size: 12px; padding: 6px 12px;">{{ t('remove') }}</button>
          </div>
          
          <div v-else>
            <div v-if="!otpSent" style="display: flex; flex-direction: column; gap: 12px;">
              <input v-model="waNumber" type="text" class="input" :placeholder="t('waNumberPlaceholder')" style="padding: 12px; border-radius: 10px;" />
              <button @click="sendWaOtp" class="btn btn-primary" :disabled="sendingOtp" style="border-radius: 10px; justify-content: center;">
                <span v-if="sendingOtp" class="spinner"></span>
                <span v-else>{{ t('sendOtp') }}</span>
              </button>
            </div>
            <div v-else style="display: flex; flex-direction: column; gap: 12px;">
              <div style="font-size: 13px; color: var(--accent);">{{ t('otpSentTo') }} {{ waNumber }}.</div>
              <input v-model="waOtp" type="text" class="input" :placeholder="t('otpPlaceholder')" style="padding: 12px; border-radius: 10px; text-align: center; letter-spacing: 2px; font-weight: bold;" />
              <div style="display: flex; gap: 8px;">
                <button @click="otpSent = false" class="btn" style="flex: 1; border-radius: 10px; justify-content: center;">{{ t('cancel') }}</button>
                <button @click="verifyWaOtp" class="btn btn-primary" :disabled="verifyingOtp" style="flex: 2; border-radius: 10px; justify-content: center;">
                  <span v-if="verifyingOtp" class="spinner"></span>
                  <span v-else>{{ t('verify') }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>
        
        <div style="margin-top: 24px;">
          <button class="btn" style="width: 100%; padding: 14px; color: var(--danger); background: var(--danger-dim); border: 1px solid transparent; border-radius: 16px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s;" onmouseover="this.style.borderColor='var(--danger)'; this.style.boxShadow='0 4px 12px var(--danger-dim)';" onmouseout="this.style.borderColor='transparent'; this.style.boxShadow='none';" @click="handleLogout">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            {{ t('logout') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { icons } from '../components/icons.js'
import { useAuthStore } from '../stores/auth.js'
import { useTasksStore } from '../stores/tasks.js'
import { t } from '../i18n.js'

const router = useRouter()
const auth = useAuthStore()
const tasks = useTasksStore()

const success = ref('')
const displayName = ref('')
const avatarUrl = ref('')
const avatarFile = ref(null)
const avatarPreview = ref(null)
const avatarInput = ref(null)
const isEditing = ref(false)
const saving = ref(false)

const waNumber = ref('')
const waOtp = ref('')
const otpSent = ref(false)
const sendingOtp = ref(false)
const verifyingOtp = ref(false)

function triggerAvatarUpload() {
  avatarInput.value.click()
}

function handleAvatarChange(e) {
  const file = e.target.files[0]
  if (file) {
    avatarFile.value = file
    const reader = new FileReader()
    reader.onload = (ev) => {
      avatarPreview.value = ev.target.result
    }
    reader.readAsDataURL(file)
    // auto save avatar if not editing details
    if (!isEditing.value) {
      saveSettings()
    }
  }
}

async function sendWaOtp() {
  if (!waNumber.value) return alert('Masukkan nomor WhatsApp')
  sendingOtp.value = true
  try {
    const res = await fetch('http://localhost:3001/send-otp', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ number: waNumber.value, user_id: auth.user.id })
    })
    const data = await res.json()
    if (!res.ok) throw new Error(data.error || 'Gagal mengirim OTP')
    
    otpSent.value = true
    success.value = 'Kode OTP berhasil dikirim ke WhatsApp!'
  } catch (e) {
    alert(e.message)
  } finally {
    sendingOtp.value = false
  }
}

async function verifyWaOtp() {
  if (!waOtp.value) return alert('Masukkan kode OTP')
  verifyingOtp.value = true
  try {
    const res = await fetch('http://localhost:3001/verify-otp', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ user_id: auth.user.id, otp: waOtp.value })
    })
    const data = await res.json()
    if (!res.ok) throw new Error(data.error || 'Gagal verifikasi')
    
    success.value = 'WhatsApp berhasil diverifikasi!'
    auth.user = { ...auth.user, whatsapp_verified: 1, whatsapp_number: waNumber.value }
    localStorage.setItem('ad_user', JSON.stringify(auth.user))
    otpSent.value = false
  } catch (e) {
    alert(e.message)
  } finally {
    verifyingOtp.value = false
  }
}

async function resetWhatsapp() {
  // Reset via backend profile update mock
  // Or just manual query for this MVP
  alert(t('contactAdmin'))
}

onMounted(() => {
  if (auth.user) {
    displayName.value = auth.user.display_name || auth.user.username
    avatarUrl.value = auth.user.avatar || ''
  }
})

function startEditing() {
  isEditing.value = true
  success.value = ''
}

async function saveSettings() {
  saving.value = true
  success.value = ''
  try {
    await auth.updateProfile(displayName.value, avatarFile.value)
    success.value = 'Profil berhasil diperbarui!'
    isEditing.value = false
    avatarFile.value = null // reset so it doesn't re-upload same file again
  } catch (err) {
    console.error(err)
  } finally {
    saving.value = false
  }
}

function handleLogout() {
  auth.logout()
  router.push('/login')
}
</script>
