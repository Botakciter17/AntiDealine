<template>
  <div class="task-card" :style="{ backgroundColor: cardBgColor }" :class="{ completed: task.completed, 'group-task': !!task.group_id }" @click="$emit('select', task)">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
      <div class="task-title" style="font-size: 16px; font-weight: 700; color: #18181b;">
        <span v-if="task.group_id" class="group-task-badge">
          <span v-html="icons.users"></span>
        </span>
        {{ task.title }}
      </div>
      <div class="task-difficulty" :style="{ backgroundColor: badgeBgColor, color: '#18181b', fontWeight: 700, padding: '4px 12px', borderRadius: '12px', fontSize: '12px' }">
        {{ t(task.difficulty).toUpperCase() }}
      </div>
    </div>
    
    <div class="task-meta" style="color: #18181b; opacity: 0.8; margin-top: 0; margin-bottom: 12px;">
      <span class="task-deadline">
        <span v-html="icons.calendar"></span>
        {{ formatDeadline(task.deadline) }}
      </span>
    </div>

    <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; color: #18181b; margin-bottom: 8px;">
      <span>{{ t('normal') }}</span>
      <span>{{ t('doItNow') }}</span>
    </div>

    <div v-if="!task.completed" class="health-bar-wrapper" style="width: 100%; display: block;">
      <div class="health-bar" style="background: rgba(0,0,0,0.1); height: 16px; border-radius: 8px; border: none;">
        <div class="health-bar-fill" :style="{ width: task.health_percent + '%', backgroundColor: healthColor }"></div>
      </div>
      <div style="text-align: right; font-size: 11px; color: #18181b; font-weight: 700; margin-top: 4px;">{{ Math.round(task.health_percent) }}%</div>
    </div>

    <div v-if="task.completed" style="display:flex;align-items:center;color:#18181b; font-weight: bold;">
      <span v-html="icons.check" style="margin-right: 4px;"></span> {{ t('completed') }}
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { icons } from './icons.js'
import { t, currentLanguage } from '../i18n.js'

const props = defineProps({ task: { type: Object, required: true } })
defineEmits(['select'])

const isOverdue = computed(() => new Date(props.task.deadline) < new Date())

const cardBgColor = computed(() => {
  if (props.task.difficulty === 'hard') return 'var(--health-red)'
  if (props.task.difficulty === 'medium') return 'var(--health-yellow)'
  return '#c1f287' // easy
})

const badgeBgColor = computed(() => {
  if (props.task.difficulty === 'hard') return 'rgba(0,0,0,0.15)'
  if (props.task.difficulty === 'medium') return 'rgba(0,0,0,0.1)'
  return 'rgba(0,0,0,0.1)'
})

const healthColor = computed(() => {
  return '#4ade80' // Using a strong green for the bar itself, or whatever fits
})

function formatDeadline(deadline) {
  const d = new Date(deadline)
  return d.toLocaleDateString(currentLanguage.value === 'id' ? 'id-ID' : 'en-US', { weekday: 'long', day: 'numeric', month: 'short' })
}
</script>

<style scoped>
.task-card {
  padding: 20px;
  border-radius: 24px;
  border: none;
  color: #18181b;
}
.group-task {
  border: 2px solid rgba(0,0,0,0.2) !important;
}
</style>
