import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '../api.js'

export const useTasksStore = defineStore('tasks', () => {
  const tasks = ref([])
  const loading = ref(false)

  // Sort: incomplete first, then by deadline (closest first)
  const sortedTasks = computed(() => {
    return [...tasks.value].sort((a, b) => {
      // Completed tasks go to bottom
      if (a.completed !== b.completed) return a.completed - b.completed
      // Sort by deadline (closest first)
      return new Date(a.deadline) - new Date(b.deadline)
    })
  })

  const activeTasks = computed(() => sortedTasks.value.filter(t => !t.completed))
  const completedTasks = computed(() => sortedTasks.value.filter(t => t.completed))

  async function fetchTasks() {
    loading.value = true
    try {
      const res = await api('/tasks', 'GET')
      tasks.value = res.tasks
    } finally {
      loading.value = false
    }
  }

  async function createTask(taskData) {
    const res = await api('/tasks', 'POST', taskData)
    tasks.value.push(res.task)
    return res.task
  }

  async function updateTask(taskData) {
    const res = await api('/tasks', 'PUT', taskData)
    const idx = tasks.value.findIndex(t => t.id === res.task.id)
    if (idx !== -1) tasks.value[idx] = res.task
    return res.task
  }

  async function deleteTask(id) {
    await api('/tasks', 'DELETE', { id })
    tasks.value = tasks.value.filter(t => t.id !== id)
  }

  async function toggleComplete(id) {
    const task = tasks.value.find(t => t.id === id)
    if (task) {
      return await updateTask({ id, completed: task.completed ? 0 : 1 })
    }
  }

  function addTasksFromChat(newTasks) {
    for (const task of newTasks) {
      if (!tasks.value.find(t => t.id === task.id)) {
        task.health_percent = 100
        tasks.value.push(task)
      }
    }
  }

  return {
    tasks, loading, sortedTasks, activeTasks, completedTasks,
    fetchTasks, createTask, updateTask, deleteTask, toggleComplete, addTasksFromChat
  }
})
