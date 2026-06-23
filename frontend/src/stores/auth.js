import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '../api.js'

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

  async function register(username, password) {
    const res = await api('/auth/register', 'POST', { username, password })
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

  function logout() {
    token.value = ''
    user.value = null
    localStorage.removeItem('ad_token')
    localStorage.removeItem('ad_user')
  }

  return { token, user, isLoggedIn, hasApiKey, login, register, updateApiKey, logout }
})
