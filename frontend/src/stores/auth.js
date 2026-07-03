import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api, apiUpload } from '../api.js'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('ad_token') || '')
  const user = ref(JSON.parse(localStorage.getItem('ad_user') || 'null'))

  const isLoggedIn = computed(() => !!token.value)
  const hasApiKey = computed(() => !!user.value?.api_key)

  async function login(username, password) {
    const res = await api('/auth/login', 'POST', { username, password })
    token.value = res.token
    user.value = res.user
    localStorage.setItem('ad_token', res.token)
    localStorage.setItem('ad_user', JSON.stringify(res.user))
    return res
  }

  async function register(email, username, password) {
    const res = await api('/auth/register', 'POST', { email, username, password })
    token.value = res.token
    user.value = res.user
    localStorage.setItem('ad_token', res.token)
    localStorage.setItem('ad_user', JSON.stringify(res.user))
    return res
  }

  async function updateApiKey(apiKey) {
    await api('/user/apikey', 'PUT', { api_key: apiKey })
    user.value = { ...user.value, api_key: '••••••' + apiKey.slice(-4) }
    localStorage.setItem('ad_user', JSON.stringify(user.value))
  }

  async function updateProfile(displayName, avatarFile) {
    const formData = new FormData()
    formData.append('display_name', displayName)
    if (avatarFile) {
      formData.append('avatar', avatarFile)
    }
    const res = await apiUpload('/user/profile', formData)
    user.value = { ...user.value, display_name: res.display_name, avatar: res.avatar }
    localStorage.setItem('ad_user', JSON.stringify(user.value))
    return res
  }

  async function setUsername(newUsername) {
    const res = await api('/user/username', 'POST', { username: newUsername })
    user.value = { ...user.value, username: res.username }
    localStorage.setItem('ad_user', JSON.stringify(user.value))
    return res
  }

  function logout() {
    token.value = ''
    user.value = null
    localStorage.removeItem('ad_token')
    localStorage.removeItem('ad_user')
  }

  async function googleLogin(credential) {
    const res = await api('/auth/google', 'POST', { credential })
    token.value = res.token
    user.value = res.user
    localStorage.setItem('ad_token', res.token)
    localStorage.setItem('ad_user', JSON.stringify(res.user))
    return res
  }

  return { token, user, isLoggedIn, hasApiKey, login, register, googleLogin, updateApiKey, updateProfile, setUsername, logout }
})
