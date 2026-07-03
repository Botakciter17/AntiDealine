<template>
  <div class="dashboard">
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="sidebar-brand">
          <img src="/tuntaz-logo.png" alt="Tuntaz Logo" style="width: 28px; height: 28px; object-fit: contain; margin-right: 8px;" />
          Tuntaz
        </div>
      </div>

      <nav class="sidebar-nav">
        <button class="sidebar-nav-item" :class="{ active: activeTab === 'tasks' }" @click="activeTab = 'tasks'">
          <span class="nav-icon" v-html="icons.check"></span>
        </button>
        <button class="sidebar-nav-item" :class="{ active: activeTab === 'chat' }" @click="activeTab = 'chat'">
          <span class="nav-icon" v-html="icons.robot"></span>
        </button>
        <button class="sidebar-nav-item" @click="showAddModal = true">
          <span class="nav-icon" v-html="icons.plus"></span>
        </button>
        <button class="sidebar-nav-item" :class="{ active: activeTab === 'friends' }" @click="activeTab = 'friends'">
          <span class="nav-icon" v-html="icons.users"></span>
        </button>
        <button class="sidebar-nav-item" @click="router.push('/setup')">
          <span class="nav-icon" v-html="icons.user"></span>
        </button>
      </nav>

      <div class="sidebar-stats" v-if="tasksStore.tasks.length">
        <div class="sidebar-stats-title">{{ t('overview') }}</div>
        <div class="sidebar-stat">
          <span class="stat-label">{{ t('active') }}</span>
          <span class="stat-value" style="color:var(--accent)">{{ tasksStore.activeTasks.length }}</span>
        </div>
        <div class="sidebar-stat">
          <span class="stat-label">{{ t('completed') }}</span>
          <span class="stat-value">{{ tasksStore.completedTasks.length }}</span>
        </div>
        <div class="sidebar-stat">
          <span class="stat-label">{{ t('critical') }}</span>
          <span class="stat-value" style="color:var(--danger)">{{ criticalCount }}</span>
        </div>
      </div>

      <div class="sidebar-footer">
        <div class="user-info">
          <img v-if="auth.user?.avatar" :src="auth.user.avatar" class="user-avatar" style="object-fit: cover; border: none; padding: 0;" @error="auth.user.avatar = ''" />
          <div v-else class="user-avatar">{{ auth.user?.display_name ? auth.user?.display_name.charAt(0).toUpperCase() : auth.user?.username?.charAt(0).toUpperCase() }}</div>
          <span class="user-name">{{ auth.user?.display_name || auth.user?.username }}</span>
        </div>
      </div>
    </aside>

    <main class="main-content">
      <template v-if="activeTab === 'tasks'">
        <div class="content-header" style="display: flex; align-items: center; justify-content: space-between; border-bottom: none; padding-bottom: 8px;">
          <div style="display: flex; align-items: center; gap: 14px;">
            <img v-if="auth.user?.avatar" :src="auth.user.avatar" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-light);" @error="auth.user.avatar = ''" />
            <div v-else style="width: 48px; height: 48px; border-radius: 50%; background: var(--bg-elevated); color: var(--accent); border: 2px solid var(--border-light); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 20px;">
              {{ auth.user?.display_name ? auth.user.display_name.charAt(0).toUpperCase() : auth.user?.username?.charAt(0).toUpperCase() }}
            </div>
            <div>
              <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 2px;">{{ t('hello') }}!</div>
              <div style="font-size: 17px; font-weight: 700; color: var(--text-primary); letter-spacing: -0.3px;">{{ auth.user?.display_name || auth.user?.username }}</div>
            </div>
          </div>
        </div>
        <div class="content-body" style="padding-top: 10px;">
          
          <div class="summary-wrapper" style="position: relative; margin-bottom: 30px;">
            <!-- Summary Card -->
            <div class="summary-card" style="background: var(--bg-card); color: var(--text-primary); border-radius: var(--radius-xl); padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); position: relative; overflow: hidden; border: 1px solid var(--border);">
              <!-- Spotlight aura -->
              <div class="mascot-aura" style="background: var(--accent); opacity: 0.2; width: 200px; height: 200px; right: -50px; top: -50px;"></div>
              
              <!-- Mascot inside the card -->
              <img src="/mascotLight1.png" class="card-mascot mascot-light" alt="AI Mascot" style="right: -10px; bottom: -20px; width: 140px;" />
              <img src="/mascotDark1.png" class="card-mascot mascot-dark" alt="AI Mascot" style="right: -10px; bottom: -20px; width: 140px;" />
              
              <div style="position: relative; z-index: 1; max-width: 60%;">
                <div style="font-size: 16px; opacity: 0.9; margin-bottom: 8px; font-weight: 500;">{{ t('overviewTitle') }}</div>
                <div style="font-size: 48px; font-weight: 700; line-height: 1.1; margin-bottom: 24px; letter-spacing: -1px;">
                  {{ tasksStore.activeTasks.length }} <span style="font-size: 16px; font-weight: 500; opacity: 0.9; letter-spacing: 0;">{{ t('activeTasksSubtitle') }}</span>
                </div>
                <div style="display: flex; gap: 12px; font-size: 12px; font-weight: 500;">
                  <div style="display: flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.05); padding: 6px 12px; border-radius: 20px; border: 1px solid var(--border);">
                    <span v-html="icons.check" style="width: 14px; opacity: 0.8;"></span>
                    <span>{{ tasksStore.completedTasks.length }} {{ t('completed') }}</span>
                  </div>
                  <div style="display: flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.05); padding: 6px 12px; border-radius: 20px; border: 1px solid var(--border);">
                    <span v-html="icons.alert" style="width: 14px; color: var(--danger);"></span>
                    <span>{{ criticalCount }} {{ t('critical') }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <TaskList @select="openTaskDetail" />
        </div>
      </template>
      <template v-if="activeTab === 'chat'">
        <ChatPanel />
      </template>

      <template v-if="activeTab === 'friends'">
        <FriendsView />
      </template>
    </main>

    <!-- Task Detail Modal -->
    <TaskDetailModal v-if="selectedTask" :task="selectedTask" @close="selectedTask = null" />

    <!-- Add Task Modal -->
    <div v-if="showAddModal" class="modal-overlay" @click.self="showAddModal = false">
      <div class="modal" style="background: #18181b; padding: 32px; border-radius: 12px; border: none; max-width: 360px; box-shadow: 0 20px 40px rgba(0,0,0,0.5);">
        <h2 style="color: white; margin-bottom: 24px; font-size: 20px;">{{ t('addTaskTitle') }}</h2>
        <form @submit.prevent="handleAddTask">
          <div class="form-group" style="margin-bottom: 16px;">
            <label class="form-label" style="color: white; margin-bottom: 8px;">{{ t('titleLabel') }}</label>
            <input v-model="newTask.title" type="text" class="input" :placeholder="t('placeholderTitle')" required style="background: #3f3f46; border: none; padding: 12px 16px; border-radius: 8px; color: white;" />
          </div>
          <div class="form-group" style="margin-bottom: 16px;">
            <label class="form-label" style="color: white; margin-bottom: 8px;">{{ t('descLabel') }}</label>
            <textarea v-model="newTask.description" class="input" :placeholder="t('placeholderDesc')" style="background: #3f3f46; border: none; padding: 12px 16px; border-radius: 8px; min-height: 100px; color: white;"></textarea>
          </div>
          <div class="form-group" style="margin-bottom: 16px;">
            <label class="form-label" style="color: white; margin-bottom: 8px;">{{ t('deadlineLabel') }}</label>
            <input v-model="newTask.deadline" type="datetime-local" class="input" required style="background: #3f3f46; border: none; padding: 12px 16px; border-radius: 8px; color: white; width: 100%; box-sizing: border-box;" />
          </div>
          <div class="form-group" style="margin-bottom: 32px;">
            <label class="form-label" style="color: white; margin-bottom: 8px;">{{ t('diffLabel') }}</label>
            <select v-model="newTask.difficulty" class="input" style="background: #3f3f46; border: none; padding: 12px 16px; border-radius: 8px; color: white; width: 100%; box-sizing: border-box;">
              <option value="easy">{{ t('easy') }}</option>
              <option value="medium">{{ t('medium') }}</option>
              <option value="hard">{{ t('hard') }}</option>
            </select>
          </div>
          <div class="modal-actions" style="display: flex; gap: 12px; justify-content: space-between;">
            <button type="button" class="btn btn-ghost" @click="showAddModal = false" style="flex: 1; border: 1px solid #c1f287; color: white; padding: 14px; border-radius: 8px; font-weight: 600;">{{ t('cancel') }}</button>
            <button type="submit" class="btn btn-primary" :disabled="addingTask" style="flex: 1; background: #c1f287; color: #18181b; padding: 14px; border-radius: 8px; font-weight: 600; border: none;">
              <span v-if="addingTask" class="spinner"></span>
              <span v-else>{{ t('addTaskTitle') }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Setup Username Modal -->
    <div v-if="showUsernameSetup" class="modal-overlay" style="z-index: 9999;">
      <div class="modal card" style="max-width: 400px; text-align: center;">
        <h2 style="margin-bottom: 8px;">{{ t('welcome') }}</h2>
        <p style="color: var(--text-secondary); margin-bottom: 24px; font-size: 14px;">{{ t('setupUsernameDesc') }}</p>
        
        <form @submit.prevent="saveUsername">
          <div class="form-group" style="text-align: left;">
            <label class="form-label">{{ t('username') }}</label>
            <input v-model="newUsernameInput" type="text" class="input" :placeholder="t('usernamePlaceholder')" required />
            <p v-if="usernameError" style="color: var(--danger); font-size: 12px; margin-top: 4px;">{{ usernameError }}</p>
          </div>
          <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 16px;" :disabled="savingUsername">
            <span v-if="savingUsername" class="spinner"></span>
            <span v-else>{{ t('saveUsername') }}</span>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import { useTasksStore } from '../stores/tasks.js'
import { icons } from '../components/icons.js'
import { t } from '../i18n.js'
import TaskList from '../components/TaskList.vue'
import ChatPanel from '../components/ChatPanel.vue'
import FriendsView from './FriendsView.vue'
import TaskDetailModal from '../components/TaskDetailModal.vue'

const router = useRouter()
const auth = useAuthStore()
const tasksStore = useTasksStore()

const activeTab = ref('tasks')
const showAddModal = ref(false)
const addingTask = ref(false)
const selectedTask = ref(null)
const newTask = ref({ title: '', description: '', deadline: '', difficulty: 'medium', progress: 0 })

watch(activeTab, (newTab) => {
  if (newTab === 'friends') {
    document.body.classList.add('hide-global-settings')
  } else {
    document.body.classList.remove('hide-global-settings')
  }
}, { immediate: true })

// Setup Username state
const showUsernameSetup = computed(() => {
  if (!auth.user || !auth.user.username) return false;
  const uname = auth.user.username;
  return uname.startsWith('_new_google_user_') || /^[a-zA-Z0-9.]+?\d{4}$/.test(uname);
})
const newUsernameInput = ref('')
const usernameError = ref('')
const savingUsername = ref(false)

async function saveUsername() {
  usernameError.value = ''
  savingUsername.value = true
  try {
    await auth.setUsername(newUsernameInput.value)
  } catch (e) {
    usernameError.value = e.message
  } finally {
    savingUsername.value = false
  }
}

const criticalCount = computed(() => tasksStore.activeTasks.filter(t => t.health_percent < 30).length)

function openTaskDetail(task) { selectedTask.value = { ...task } }

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

watch(activeTab, (newVal) => {
  if (newVal === 'tasks') {
    tasksStore.fetchTasks()
  }
})
</script>
