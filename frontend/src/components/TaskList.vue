<template>
  <div>
    <div class="task-section" v-if="tasksStore.activeTasks.length">
      <div class="task-section-header">
        <span>{{ t('activeTasks') }}</span>
        <span class="task-section-count">{{ tasksStore.activeTasks.length }}</span>
      </div>
      <div class="task-list">
        <TaskCard v-for="task in tasksStore.activeTasks" :key="task.id" :task="task" @select="t => $emit('select', t)" />
      </div>
    </div>

    <div class="task-section" v-if="tasksStore.completedTasks.length">
      <div class="task-section-header">
        <span>{{ t('completed') }}</span>
        <span class="task-section-count">{{ tasksStore.completedTasks.length }}</span>
      </div>
      <div class="task-list">
        <TaskCard v-for="task in tasksStore.completedTasks" :key="task.id" :task="task" @select="t => $emit('select', t)" />
      </div>
    </div>

    <div v-if="!tasksStore.tasks.length && !tasksStore.loading" class="empty-state">
      <div class="empty-state-icon" v-html="icons.file"></div>
      <h3>{{ t('emptyTasks') }}</h3>
      <p>{{ t('emptyTasksDesc') }}</p>
    </div>

    <div v-if="tasksStore.loading" style="display:flex;justify-content:center;padding:40px;">
      <div class="spinner"></div>
    </div>
  </div>
</template>

<script setup>
import { useTasksStore } from '../stores/tasks.js'
import { icons } from './icons.js'
import TaskCard from './TaskCard.vue'
import { t } from '../i18n.js'

const tasksStore = useTasksStore()
defineEmits(['select'])
</script>
