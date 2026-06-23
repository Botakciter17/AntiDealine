<template>
  <div class="chat-layout">
    <!-- Sidebar for Sessions -->
    <div class="chat-sidebar" :class="{ 'open': showSidebar }">
      <div class="chat-sidebar-header">
        <button class="btn btn-primary" @click="startNewChat" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;">
          <span v-html="icons.plus"></span> Chat Baru
        </button>
        <button class="btn-icon mobile-close-sidebar" @click="showSidebar = false" v-html="icons.close"></button>
      </div>
      <div class="chat-sidebar-list">
        <div v-if="sessions.length === 0" class="empty-sessions">Belum ada chat</div>
        <div v-for="session in sessions" :key="session.id" 
             class="session-item" :class="{ 'active': currentSessionId === session.id }"
             @click="loadSession(session.id)">
          <span class="session-icon" v-html="icons.chat"></span>
          <span class="session-title">{{ session.title }}</span>
          <button class="btn-icon delete-session-btn" @click.stop="deleteSession(session.id)" title="Hapus">
            <span v-html="icons.trash"></span>
          </button>
        </div>
      </div>
    </div>

    <!-- Main Chat Area -->
    <div class="chat-main">
      <div class="chat-topbar">
        <button class="btn-icon mobile-menu-btn" @click="showSidebar = true" v-html="icons.menu"></button>
        <span class="chat-topbar-title">{{ currentSessionTitle }}</span>
      </div>

      <div class="chat-panel">
        <div class="chat-messages" ref="chatContainer">
          <div v-if="!messages.length" class="chat-empty">
            <div class="chat-empty-icon" v-html="icons.robot"></div>
            <h3>AI Task Assistant</h3>
            <p>Ceritakan tugas-tugas kamu, deadline-nya kapan, dan seberapa susah. AI akan membuatkan todo list otomatis!</p>
            <div class="chat-suggestions">
              <button class="chat-suggestion" v-for="s in suggestions" :key="s" @click="useSuggestion(s)">{{ s }}</button>
            </div>
          </div>

          <div v-for="(msg, i) in messages" :key="i" class="chat-bubble" :class="msg.role">
            <div class="chat-avatar" v-html="msg.role === 'user' ? icons.user : icons.robot"></div>
            <div>
              <div v-if="msg.imageUrl" class="chat-attached-image">
                <img :src="msg.imageUrl" alt="Attached" />
              </div>
              <div class="chat-content" v-html="formatMessage(msg.content)"></div>
              <div v-if="msg.tasksCreated?.length" class="chat-tasks-created">
                <div style="font-weight:600;margin-bottom:6px;display:flex;align-items:center;gap:6px;">
                  <span v-html="icons.check"></span> Tasks added:
                </div>
                <div v-for="task in msg.tasksCreated" :key="task.id" class="task-created-item">
                  <span>•</span>
                  <span>{{ task.title }}</span>
                  <span class="task-difficulty" :class="task.difficulty" style="font-size:10px;">{{ task.difficulty }}</span>
                </div>
              </div>
            </div>
          </div>

          <div v-if="sending" class="chat-bubble assistant">
            <div class="chat-avatar" v-html="icons.robot"></div>
            <div class="chat-content">
              <div class="typing-indicator"><span></span><span></span><span></span></div>
            </div>
          </div>
        </div>

        <!-- File Preview Bar -->
        <div v-if="attachedFile" class="chat-file-preview">
          <div class="file-preview-inner">
            <img v-if="filePreviewUrl" :src="filePreviewUrl" class="file-preview-thumb" alt="preview" />
            <span v-else class="file-preview-icon" v-html="icons.file"></span>
            <span class="file-preview-name">{{ attachedFile.name }}</span>
            <button class="btn-icon file-preview-remove" @click="removeFile" v-html="icons.close"></button>
          </div>
        </div>

        <div class="chat-input-area">
          <div class="chat-input-wrapper">
            <div class="attach-wrapper">
              <button class="chat-attach-btn" @click="toggleAttachMenu" :disabled="sending" title="Lampirkan file">
                <span v-html="showAttachMenu ? icons.close : icons.attach"></span>
              </button>

              <Transition name="attach-menu">
                <div v-if="showAttachMenu" class="attach-menu">
                  <button class="attach-menu-item" @click="pickFile('image')">
                    <span class="attach-menu-icon image-icon" v-html="icons.image"></span>
                    <span>Foto / Gambar</span>
                  </button>
                  <button class="attach-menu-item" @click="pickFile('document')">
                    <span class="attach-menu-icon doc-icon" v-html="icons.file"></span>
                    <span>Dokumen</span>
                  </button>
                </div>
              </Transition>
            </div>

            <input type="file" ref="fileInput" @change="onFileSelected" :accept="fileAccept" style="display:none" />
            <textarea v-model="input" class="input" placeholder="Ceritakan tugas kamu..." @keydown.enter.exact.prevent="sendMessage" rows="1" ref="chatInput"></textarea>
            <button class="chat-send-btn" @click="sendMessage" :disabled="(!input.trim() && !attachedFile) || sending">
              <span v-html="icons.send"></span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted } from 'vue'
import { api, apiUpload } from '../api.js'
import { useTasksStore } from '../stores/tasks.js'
import { icons } from './icons.js'
import { marked } from 'marked'

const tasksStore = useTasksStore()
const messages = ref([])
const sessions = ref([])
const currentSessionId = ref(null)
const input = ref('')
const sending = ref(false)
const showSidebar = ref(false)

const chatContainer = ref(null)
const chatInput = ref(null)
const fileInput = ref(null)
const attachedFile = ref(null)
const filePreviewUrl = ref(null)
const showAttachMenu = ref(false)
const fileAccept = ref('image/*,.pdf,.txt,.doc,.docx')

const suggestions = [
  'Aku punya tugas game dev deadline Sabtu',
  'Ada presentasi besok dan belum bikin slides',
  'Tugas matematika deadline 3 hari lagi',
]

marked.setOptions({ breaks: true, gfm: true })

const currentSessionTitle = computed(() => {
  if (!currentSessionId.value) return 'Chat Baru'
  const session = sessions.value.find(s => s.id === currentSessionId.value)
  return session ? session.title : 'Chat Baru'
})

function formatMessage(content) {
  let cleaned = content.replace(/```json\s*\{[\s\S]*?\}\s*```/g, '')
  // Render [📎 filename] as a nice badge
  cleaned = cleaned.replace(/\[📎 (.*?)\]/g, '<span class="chat-file-badge"><span class="icon">📎</span> $1</span>')
  return marked.parse(cleaned)
}

function useSuggestion(text) { input.value = text; chatInput.value?.focus() }

function toggleAttachMenu() { showAttachMenu.value = !showAttachMenu.value }

function pickFile(type) {
  fileAccept.value = type === 'image' ? 'image/*' : '.pdf,.txt,.doc,.docx'
  showAttachMenu.value = false
  nextTick(() => fileInput.value?.click())
}

function onFileSelected(e) {
  const file = e.target.files[0]
  if (!file) return
  attachedFile.value = file
  if (file.type.startsWith('image/')) {
    const reader = new FileReader()
    reader.onload = (ev) => { filePreviewUrl.value = ev.target.result }
    reader.readAsDataURL(file)
  } else {
    filePreviewUrl.value = null
  }
}

function removeFile() {
  attachedFile.value = null
  filePreviewUrl.value = null
  if (fileInput.value) fileInput.value.value = ''
}

async function fetchSessions() {
  try {
    sessions.value = await api('/chat/sessions')
  } catch (e) {
    console.error('Failed to load sessions', e)
  }
}

async function loadSession(id) {
  currentSessionId.value = id
  showSidebar.value = false
  messages.value = []
  try {
    const history = await api(`/chat/history?session_id=${id}`)
    messages.value = history || []
    await scrollToBottom()
  } catch (e) {
    console.error('Failed to load history', e)
  }
}

function startNewChat() {
  currentSessionId.value = null
  messages.value = []
  showSidebar.value = false
  chatInput.value?.focus()
}

async function deleteSession(id) {
  if (!confirm('Hapus percakapan ini?')) return
  try {
    await api('/chat/sessions', 'DELETE', { id })
    sessions.value = sessions.value.filter(s => s.id !== id)
    if (currentSessionId.value === id) {
      startNewChat()
    }
  } catch (e) {
    alert('Gagal menghapus percakapan')
  }
}

async function sendMessage() {
  const text = input.value.trim()
  const file = attachedFile.value
  if ((!text && !file) || sending.value) return
  
  const userMsg = { role: 'user', content: text || '(file terlampir)' }
  if (filePreviewUrl.value) userMsg.imageUrl = filePreviewUrl.value
  messages.value.push(userMsg)
  
  input.value = ''
  const currentFile = file
  removeFile()
  sending.value = true
  await scrollToBottom()
  
  try {
    let res
    if (currentFile) {
      const formData = new FormData()
      formData.append('message', text)
      formData.append('file', currentFile)
      if (currentSessionId.value) formData.append('session_id', currentSessionId.value)
      res = await apiUpload('/chat', formData)
    } else {
      const payload = { message: text }
      if (currentSessionId.value) payload.session_id = currentSessionId.value
      res = await api('/chat', 'POST', payload)
    }
    
    messages.value.push({ role: 'assistant', content: res.message, tasksCreated: res.tasks_created || [] })
    
    // If it's a new session, update session id and refresh sessions list
    if (!currentSessionId.value && res.session_id) {
      currentSessionId.value = res.session_id
      await fetchSessions()
    }
    
    if (res.tasks_created?.length) tasksStore.addTasksFromChat(res.tasks_created)
  } catch (e) {
    messages.value.push({ role: 'assistant', content: `Error: ${e.message}` })
  } finally {
    sending.value = false
    await scrollToBottom()
  }
}

async function scrollToBottom() {
  await nextTick()
  if (chatContainer.value) chatContainer.value.scrollTop = chatContainer.value.scrollHeight
}

onMounted(async () => {
  await fetchSessions()
  if (sessions.value.length > 0) {
    await loadSession(sessions.value[0].id)
  }
  chatInput.value?.focus()
})
</script>
