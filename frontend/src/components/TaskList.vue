<template>
  <div>
    <div class="task-section" v-if="tasksStore.activeTasks.length">
      <div class="task-section-header">
        <span>Active Tasks</span>
        <span class="task-section-count">{{ tasksStore.activeTasks.length }}</span>
      </div>
      <div class="task-list">
        <TaskCard v-for="task in tasksStore.activeTasks" :key="task.id" :task="task" @select="t => $emit('select', t)" />
      </div>
    </div>

    <div class="task-section" v-if="tasksStore.completedTasks.length">
      <div class="task-section-header">
        <span>Completed</span>
        <span class="task-section-count">{{ tasksStore.completedTasks.length }}</span>
      </div>
      <div class="task-list">
        <TaskCard v-for="task in tasksStore.completedTasks" :key="task.id" :task="task" @select="t => $emit('select', t)" />
      </div>
    </div>

    <div v-if="!tasksStore.tasks.length && !tasksStore.loading" class="empty-state">
      <div class="empty-state-icon" v-html="icons.file"></div>
      <h3>No tasks yet</h3>
      <p>Chat with AI to add your tasks, or add them manually.</p>
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

const tasksStore = useTasksStore()
defineEmits(['select'])
</script>
