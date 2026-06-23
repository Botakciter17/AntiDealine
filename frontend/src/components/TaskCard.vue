<template>
  <div class="task-card" :class="{ completed: task.completed }" @click="$emit('select', task)">
    <div class="task-body">
      <div class="task-title">{{ task.title }}</div>
      <div class="task-meta">
        <span class="task-deadline">
          <span v-html="icons.calendar"></span>
          {{ formatDeadline(task.deadline) }}
        </span>
        <span class="task-difficulty" :class="task.difficulty">{{ task.difficulty }}</span>
        <span v-if="task.progress > 0" style="color: var(--text-tertiary); font-size: 11px;">
          {{ task.progress }}% done
        </span>
        <span v-if="isOverdue && !task.completed" class="task-overdue">
          <span v-html="icons.alert"></span> Overdue
        </span>
      </div>
    </div>

    <div v-if="!task.completed" class="health-bar-wrapper">
      <div class="health-bar">
        <div class="health-bar-fill" :style="{ width: task.health_percent + '%', backgroundColor: healthColor }"></div>
      </div>
      <span class="health-label" :style="{ color: healthColor }">{{ Math.round(task.health_percent) }}%</span>
    </div>

    <div v-if="task.completed" style="display:flex;align-items:center;color:var(--accent);">
      <span v-html="icons.check"></span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { icons } from './icons.js'

const props = defineProps({ task: { type: Object, required: true } })
defineEmits(['select'])

const isOverdue = computed(() => new Date(props.task.deadline) < new Date())
const healthColor = computed(() => {
  const hp = props.task.health_percent
  if (hp >= 70) return '#4CAF50'
  if (hp >= 45) return '#FFC107'
  if (hp >= 20) return '#FF9800'
  return '#F44336'
})

function formatDeadline(deadline) {
  const d = new Date(deadline)
  const diff = d - new Date()
  const days = Math.floor(diff / 86400000)
  const hours = Math.floor((diff % 86400000) / 3600000)
  if (diff < 0) return 'Overdue'
  if (days === 0 && hours <= 0) return 'Due now'
  if (days === 0) return `${hours}h left`
  if (days === 1) return 'Tomorrow'
  if (days < 7) return `${days} days left`
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>
