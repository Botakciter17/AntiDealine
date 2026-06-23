<template>
  <div class="task-detail-overlay" @click.self="$emit('close')">
    <div class="task-detail-card">
      <!-- Header -->
      <div class="task-detail-header">
        <span class="task-difficulty" :class="task.difficulty">{{ task.difficulty }}</span>
        <button class="btn-icon" @click="$emit('close')" v-html="icons.close"></button>
      </div>

      <!-- Body -->
      <div class="task-detail-body">
        <div class="task-detail-title">{{ task.title }}</div>
        <div v-if="task.description" class="task-detail-desc">{{ task.description }}</div>

        <div class="task-detail-meta">
          <div class="task-detail-row">
            <span class="label"><span v-html="icons.calendar"></span> Deadline</span>
            <span class="value" :style="{ color: isOverdue ? 'var(--danger)' : '' }">
              {{ formatDate(task.deadline) }}
            </span>
          </div>
          <div class="task-detail-row">
            <span class="label"><span v-html="icons.clock"></span> Time Left</span>
            <span class="value">{{ timeLeft }}</span>
          </div>
          <div class="task-detail-row" v-if="task.estimated_time">
            <span class="label"><span v-html="icons.clock"></span> Estimasi</span>
            <span class="value" style="color: var(--accent);">{{ task.estimated_time }}</span>
          </div>
        </div>

        <!-- Health Bar -->
        <div class="task-detail-health" v-if="!task.completed">
          <div class="health-bar">
            <div class="health-bar-fill" :style="{ width: task.health_percent + '%', backgroundColor: healthColor }"></div>
          </div>
          <div class="health-info">
            <span>Health</span>
            <span :style="{ color: healthColor, fontWeight: 600 }">{{ Math.round(task.health_percent) }}%</span>
          </div>
        </div>
      </div>

      <!-- Slide to Complete -->
      <div class="slide-to-complete" v-if="!task.completed">
        <div v-if="!justCompleted" class="slide-track" ref="trackEl">
          <div class="slide-track-text">
            {{ t('swipeToUnlock') }}
          </div>
          <div
            class="slide-handle"
            ref="handleEl"
            :style="{ left: handleLeft + 'px' }"
            @mousedown="startDrag"
            @touchstart.prevent="startDrag"
          >
            <span v-html="icons.lock"></span>
          </div>
        </div>
        <div v-else class="slide-completed">
          <span v-html="icons.check"></span>
          Completed!
        </div>
      </div>

      <div v-if="task.completed" class="slide-to-complete">
        <div class="slide-completed" style="background: var(--text-tertiary);">
          <span v-html="icons.check"></span>
          Already Completed
        </div>
      </div>

      <!-- Footer -->
      <div class="task-detail-footer">
        <button class="btn btn-danger" @click="showDeleteConfirm = true" style="width:100%; padding: 14px 20px; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 8px;">
          <span v-html="icons.trash"></span> Delete Task
        </button>
      </div>
    </div>

    <!-- Custom Delete Confirm Dialog -->
    <Transition name="confirm">
      <div v-if="showDeleteConfirm" class="confirm-overlay" @click.self="showDeleteConfirm = false">
        <div class="confirm-dialog">
          <div class="confirm-icon">
            <span v-html="icons.alert"></span>
          </div>
          <h3>Hapus Tugas?</h3>
          <p>Tugas <strong>{{ task.title }}</strong> akan dihapus secara permanen dan tidak bisa dikembalikan.</p>
          <div class="confirm-actions">
            <button class="btn btn-ghost" @click="showDeleteConfirm = false" style="flex:1; padding: 12px;">Batal</button>
            <button class="btn btn-danger" @click="confirmDelete" style="flex:1; padding: 12px;">Hapus</button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import { icons } from './icons.js'
import { t } from '../i18n.js'
import { useTasksStore } from '../stores/tasks.js'

const props = defineProps({ task: { type: Object, required: true } })
const emit = defineEmits(['close'])
const tasksStore = useTasksStore()

const trackEl = ref(null)
const handleEl = ref(null)
const handleLeft = ref(4)
const justCompleted = ref(false)
const showDeleteConfirm = ref(false)
let isDragging = false

const isOverdue = computed(() => new Date(props.task.deadline) < new Date())

const healthColor = computed(() => {
  const hp = props.task.health_percent
  if (hp >= 70) return '#4CAF50'
  if (hp >= 45) return '#FFC107'
  if (hp >= 20) return '#FF9800'
  return '#F44336'
})

const timeLeft = computed(() => {
  const diff = new Date(props.task.deadline) - new Date()
  if (diff < 0) return 'Overdue!'
  const d = Math.floor(diff / 86400000)
  const h = Math.floor((diff % 86400000) / 3600000)
  const m = Math.floor((diff % 3600000) / 60000)
  if (d > 0) return `${d}d ${h}h`
  if (h > 0) return `${h}h ${m}m`
  return `${m}m`
})

function formatDate(d) {
  return new Date(d).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function startDrag(e) {
  isDragging = true
  const startX = e.type === 'mousedown' ? e.clientX : e.touches[0].clientX
  const startLeft = handleLeft.value

  function onMove(ev) {
    if (!isDragging) return
    const x = ev.type === 'mousemove' ? ev.clientX : ev.touches[0].clientX
    const delta = x - startX
    const trackW = trackEl.value?.offsetWidth || 300
    const maxLeft = trackW - 48
    handleLeft.value = Math.max(4, Math.min(maxLeft, startLeft + delta))
  }

  function onEnd() {
    isDragging = false
    const trackW = trackEl.value?.offsetWidth || 300
    const maxLeft = trackW - 48
    if (handleLeft.value >= maxLeft - 10) {
      completeTask()
    } else {
      handleLeft.value = 4
    }
    document.removeEventListener('mousemove', onMove)
    document.removeEventListener('mouseup', onEnd)
    document.removeEventListener('touchmove', onMove)
    document.removeEventListener('touchend', onEnd)
  }

  document.addEventListener('mousemove', onMove)
  document.addEventListener('mouseup', onEnd)
  document.addEventListener('touchmove', onMove)
  document.addEventListener('touchend', onEnd)
}

async function completeTask() {
  justCompleted.value = true
  await tasksStore.toggleComplete(props.task.id)
  setTimeout(() => emit('close'), 800)
}

async function handleDelete() {
  showDeleteConfirm.value = true
}

async function confirmDelete() {
  await tasksStore.deleteTask(props.task.id)
  emit('close')
}

onBeforeUnmount(() => {
  isDragging = false
})
</script>
