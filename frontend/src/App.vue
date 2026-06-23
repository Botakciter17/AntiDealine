<template>
  <router-view />
</template>

<script setup>
import { onMounted } from 'vue'

onMounted(() => {
  const savedTheme = localStorage.getItem('theme') || 'dark'
  document.documentElement.setAttribute('data-theme', savedTheme)
  
  // Also initialize language fallback if needed
  if (!localStorage.getItem('language')) {
    localStorage.setItem('language', 'id')
  }
})
</script>
