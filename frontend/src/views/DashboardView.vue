<template>
  <div class="dashboard">
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="sidebar-brand">
          <span class="brand-icon" v-html="icons.bolt"></span>
          AntiDeadline
        </div>
      </div>

      <nav class="sidebar-nav">
        <button class="sidebar-nav-item" :class="{ active: activeTab === 'tasks' }" @click="activeTab = 'tasks'">
          <span class="nav-icon" v-html="icons.tasks"></span> {{ t('tasks') }}
        </button>
        <button class="sidebar-nav-item" :class="{ active: activeTab === 'chat' }" @click="activeTab = 'chat'">
          <span class="nav-icon" v-html="icons.chat"></span> {{ t('aiChat') }}
        </button>
        <button class="sidebar-nav-item" @click="showAddModal = true">
          <span class="nav-icon" v-html="icons.plus"></span> {{ t('addTask') }}
        </button>
      </nav>

      <div class="sidebar-stats" v-if="tasksStore.tasks.length">
        <div class="sidebar-stats-title">Overview</div>
        <div class="sidebar-stat">
          <span class="stat-label">Active</span>
          <span class="stat-value" style="color:var(--accent)">{{ tasksStore.activeTasks.length }}</span>
        </div>
        <div class="sidebar-stat">
          <span class="stat-label">Completed</span>
          <span class="stat-value">{{ tasksStore.completedTasks.length }}</span>
        </div>
        <div class="sidebar-stat">
          <span class="stat-label">Critical</span>
          <span class="stat-value" style="color:var(--danger)">{{ criticalCount }}</span>
        </div>
      </div>

      <div class="sidebar-footer">
        <button class="sidebar-nav-item" @click="router.push('/setup')">
          <span class="nav-icon" v-html="icons.settings"></span> {{ t('settings') }}
        </button>
        <button class="sidebar-nav-item" @click="handleLogout">
          <span class="nav-icon" v-html="icons.logout"></span> {{ t('logout') }}
        </button>
        <div class="user-info">
          <div class="user-avatar">{{ auth.user?.username?.charAt(0).toUpperCase() }}</div>
          <span class="user-name">{{ auth.user?.username }}</span>
        </div>
      </div>
    </aside>

    <main class="main-content">
      <template v-if="activeTab === 'tasks'">
        <div class="content-header">
          <h1>{{ t('hello') }}, <span style="color: var(--accent);">{{ auth.user?.username }}</span></h1>
          <p>{{ t('dashboardSubtitle') }}</p>
        </div>
        <div class="content-body">
          <TaskList @select="openTaskDetail" />
        </div>
      </template>
      <template v-if="activeTab === 'chat'">
        <ChatPanel />
      </template>
    </main>

    <!-- Task Detail Modal -->
    <TaskDetailModal v-if="selectedTask" :task="selectedTask" @close="selectedTask = null" />

    <!-- Add Task Modal -->
    <div v-if="showAddModal" class="modal-overlay" @click.self="showAddModal = false">
      <div class="modal">
        <div class="modal-header">
          <h2>Add Task</h2>
          <button class="btn-icon" @click="showAddModal = false" v-html="icons.close"></button>
        </div>
        <form @submit.prevent="handleAddTask">
          <div class="form-group">
            <label class="form-label">Title</label>
            <input v-model="newTask.title" type="text" class="input" placeholder="What needs to be done?" required />
          </div>
          <div class="form-group">
            <label class="form-label">Description</label>
            <textarea v-model="newTask.description" class="input" placeholder="Optional details..."></textarea>
          </div>
          <div style="display:flex;gap:12px;">
            <div class="form-group" style="flex:1;">
              <label class="form-label">Deadline</label>
              <input v-model="newTask.deadline" type="datetime-local" class="input" required />
            </div>
            <div class="form-group" style="width:140px;">
              <label class="form-label">Difficulty</label>
              <select v-model="newTask.difficulty" class="input">
                <option value="easy">Easy</option>
                <option value="medium">Medium</option>
                <option value="hard">Hard</option>
              </select>
            </div>
          </div>
          <div class="modal-actions">
            <button type="button" class="btn btn-ghost" @click="showAddModal = false">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="addingTask">
              <span v-if="addingTask" class="spinner"></span>
              <span v-else>Add Task</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import { useTasksStore } from '../stores/tasks.js'
import { icons } from '../components/icons.js'
import { t } from '../i18n.js'
import TaskList from '../components/TaskList.vue'
import ChatPanel from '../components/ChatPanel.vue'
import TaskDetailModal from '../components/TaskDetailModal.vue'

const router = useRouter()
const auth = useAuthStore()
const tasksStore = useTasksStore()

const activeTab = ref('tasks')
const showAddModal = ref(false)
const addingTask = ref(false)
const selectedTask = ref(null)
const newTask = ref({ title: '', description: '', deadline: '', difficulty: 'medium', progress: 0 })

const criticalCount = computed(() => tasksStore.activeTasks.filter(t => t.health_percent < 30).length)

function openTaskDetail(task) { selectedTask.value = { ...task } }
function handleLogout() { auth.logout(); router.push('/login') }

async function handleAddTask() {
  addingTask.value = true
  try {
    await tasksStore.createTask({ ...newTask.value, deadline: newTask.value.deadline.replace('T', ' ') + ':00' })
    showAddModal.value = false
    newTask.value = { title: '', description: '', deadline: '', difficulty: 'medium', progress: 0 }
  } catch (e) { alert(e.message) } finally { addingTask.value = false }
}

onMounted(() => {
  tasksStore.fetchTasks()
  setInterval(() => tasksStore.fetchTasks(), 60000)
})
</script>
