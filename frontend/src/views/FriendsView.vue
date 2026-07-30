<template>
  <div class="view-container social-view" @click="closeContextMenu">
    <!-- Sidebar: List of Chats -->
    <div class="social-sidebar" :class="{ 'mobile-hidden': activeChat }">
      <div class="header" style="margin-bottom: 16px; padding: 0 16px;">
        <h2 style="font-size: 22px; font-weight: 700;">Chats</h2>
      </div>

      <!-- Tabs -->
      <div class="social-tabs">
        <button :class="{active: tab === 'chats'}" @click="tab = 'chats'">{{ t('allChats') }}</button>
        <button :class="{active: tab === 'contacts'}" @click="tab = 'contacts'">{{ t('contactsGroups') }}</button>
      </div>

      <!-- Tab Chats (Unified) -->
      <div v-if="tab === 'chats'" class="social-list">
        <div v-if="recentChats.length === 0" class="empty-state">{{ t('noChats') }}</div>
        <div v-for="chat in recentChats" :key="chat.type + chat.id" class="friend-item" :class="{active: activeChat?.type === chat.type && activeChat?.id === chat.id}" @click="openChat(chat)">
          <div class="friend-info">
            <img v-if="chat.type === 'group' && getGroupAvatar(chat.id)" :src="getGroupAvatar(chat.id)" class="friend-avatar" style="object-fit: cover; border: none; padding: 0;" />
            <img v-else-if="chat.type === 'dm' && chat.avatar" :src="chat.avatar" class="friend-avatar" style="object-fit: cover; border: none; padding: 0;" />
            <div v-else class="friend-avatar" :style="chat.type === 'dm' ? 'font-weight: bold; font-size: 14px;' : ''" v-html="chat.type === 'dm' ? chat.name.charAt(0).toUpperCase() : icons.users"></div>
            <div class="chat-item-content">
              <div class="chat-item-header">
                <span class="friend-name">{{ chat.name }}</span>
                <span class="chat-item-time">{{ formatTime(chat.last_message_time) }}</span>
              </div>
              <div class="chat-item-last-msg">{{ chat.last_message || t('noMessages') }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab Kontak (Friends & Groups) -->
      <div v-if="tab === 'contacts'" class="social-list">
        <!-- Tambah Teman -->
        <div style="padding: 16px; border-bottom: 1px solid var(--border);">
          <div style="display: flex; gap: 8px;">
            <input v-model="addUsername" type="text" class="input" :placeholder="t('usernamePlaceholder')" @keydown.enter="sendRequest" style="flex: 1;" />
            <button class="btn btn-primary" @click="sendRequest" :disabled="loadingAdd">
              <span v-html="icons.plus"></span>
            </button>
          </div>
          <div v-if="addMessage" :class="{'text-success': addSuccess, 'text-danger': !addSuccess}" style="margin-top: 8px; font-size: 12px;">{{ addMessage }}</div>
        </div>

        <!-- Buat Grup -->
        <div style="padding: 16px; border-bottom: 1px solid var(--border);">
          <button v-if="!showCreateGroup" class="btn btn-ghost" style="width: 100%; justify-content: flex-start;" @click="showCreateGroup = true">
            <span v-html="icons.plus"></span> {{ t('createGroup') }}
          </button>
          <div v-else style="display: flex; gap: 8px; flex-direction: column;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <div style="font-size: 13px; font-weight: bold;">{{ t('createGroup') }}</div>
              <button class="btn-icon" @click="showCreateGroup = false"><span v-html="icons.close"></span></button>
            </div>
            <input v-model="newGroupName" type="text" class="input" :placeholder="t('newGroupName')" />
            <div style="font-size: 12px; color: var(--text-secondary);">{{ t('selectMembers') }}</div>
            <div class="members-select">
              <label v-for="friend in friends" :key="friend.id" class="member-checkbox">
                <input type="checkbox" :value="friend.id" v-model="newGroupMembers" />
                {{ friend.display_name || friend.username }}
              </label>
            </div>
            <button class="btn btn-primary" @click="createGroup" :disabled="!newGroupName || loadingAdd">{{ t('createGroupBtn') }}</button>
          </div>
        </div>

        <!-- Permintaan Masuk -->
        <div v-if="pendingRequests.length > 0" style="padding: 16px; border-bottom: 1px solid var(--border);">
          <div style="font-size: 13px; font-weight: bold; color: var(--text-secondary); margin-bottom: 12px;">{{ t('incomingRequests') }}</div>
          <div v-for="req in pendingRequests" :key="req.request_id" class="friend-item">
            <div style="display: flex; align-items: center; gap: 8px;">
              <img v-if="req.avatar" :src="req.avatar" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;" />
              <span v-else style="width: 24px; height: 24px; border-radius: 50%; background: var(--bg-elevated); display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: bold; color: var(--accent);">{{ req.display_name ? req.display_name.charAt(0).toUpperCase() : req.username.charAt(0).toUpperCase() }}</span>
              <span class="friend-name">{{ req.display_name || req.username }}</span>
            </div>
            <div style="display: flex; gap: 4px;">
              <button class="btn-icon" style="color: var(--success);" @click="respondRequest(req.request_id, 'accept')"><span v-html="icons.check"></span></button>
              <button class="btn-icon" style="color: var(--danger);" @click="respondRequest(req.request_id, 'reject')"><span v-html="icons.close"></span></button>
            </div>
          </div>
        </div>

        <div style="padding: 16px;">
          <div style="font-size: 13px; font-weight: bold; color: var(--text-secondary); margin-bottom: 12px;">{{ t('contactsList') }}</div>
          <div v-if="friends.length === 0" class="empty-state" style="padding: 20px;">{{ t('noContacts') }}</div>
          <div v-for="friend in friends" :key="friend.id" class="friend-item" @click="openChat({type: 'dm', id: friend.id, name: friend.display_name || friend.username, avatar: friend.avatar})">
            <div class="friend-info">
              <img v-if="friend.avatar" :src="friend.avatar" class="friend-avatar" style="object-fit: cover; border: none; padding: 0;" />
              <div v-else class="friend-avatar" style="font-weight: bold; font-size: 14px;">{{ friend.display_name ? friend.display_name.charAt(0).toUpperCase() : friend.username.charAt(0).toUpperCase() }}</div>
              <span class="friend-name">{{ friend.display_name || friend.username }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Chat Area -->
    <div class="social-main" :class="{ 'mobile-active': activeChat }">
      <div class="chat-bg-pattern"></div>
      
      <div v-if="!activeChat" class="empty-chat-state">
        <span v-html="icons.chat" style="font-size: 48px; opacity: 0.2; margin-bottom: 16px;"></span>
        {{ t('selectChatToStart') }}
      </div>

      <div v-else class="chat-container">
        <!-- Chat Header -->
        <div class="chat-header">
          <button class="btn-icon mobile-only" @click="activeChat = null" v-html="icons.arrowLeft" style="margin-right: 8px;"></button>
          <img v-if="activeChat.type === 'group' && getGroupAvatar(activeChat.id)" :src="getGroupAvatar(activeChat.id)" class="friend-avatar" style="object-fit: cover; border: none; padding: 0;" />
          <img v-else-if="activeChat.type === 'dm' && activeChat.avatar" :src="activeChat.avatar" class="friend-avatar" style="object-fit: cover; border: none; padding: 0;" />
          <div v-else class="friend-avatar" :style="activeChat.type === 'dm' ? 'font-weight: bold; font-size: 14px;' : ''" v-html="activeChat.type === 'dm' ? activeChat.name.charAt(0).toUpperCase() : icons.users"></div>
          <div style="flex: 1;">
            <div class="chat-title">{{ activeChat.name }}</div>
            <div class="chat-subtitle">
              {{ activeChat.type === 'dm' ? t('online') : t('groupChat') }}
              <span v-if="activeChat.type === 'group'" style="margin-left: 8px; color: var(--accent);">
                • {{ t('progress') }}: {{ getGroupProgress(activeChat.id) }}%
              </span>
            </div>
            
            <div v-if="activeChat.type === 'group'" class="group-progress-bar">
              <div class="group-progress-fill" :style="{ width: getGroupProgress(activeChat.id) + '%' }"></div>
            </div>
          </div>
          
          <!-- Group Options Menu -->
          <div v-if="activeChat.type === 'group'" class="group-options">
            <button class="btn-icon" @click="showGroupOptions = !showGroupOptions" v-html="icons.moreVertical"></button>
            <div v-if="showGroupOptions" class="dropdown-menu">
              <button v-if="isGroupAdmin(activeChat.id)" class="dropdown-item" @click="openGroupTaskAssign(activeChat.id)">
                <span v-html="icons.plus" style="margin-right:8px;"></span> {{ t('assignGroupTask') }}
              </button>
              <button v-if="isGroupAdmin(activeChat.id)" class="dropdown-item" @click="openGroupSettings(activeChat.id)">
                <span v-html="icons.settings" style="margin-right:8px;"></span> {{ t('groupSettings') }}
              </button>
              
              <button v-if="isGroupAdmin(activeChat.id) && getGroupProgress(activeChat.id) === 100" class="dropdown-item" @click="resetGroupProgress(activeChat.id)" style="color: var(--success); font-weight: bold;">
                <span v-html="icons.refresh" style="margin-right:8px;"></span> {{ t('startNewProject') }}
              </button>
              <button v-if="isGroupAdmin(activeChat.id) && getGroupMemberCount(activeChat.id) <= 1" class="dropdown-item text-danger" @click="deleteGroup(activeChat.id)">
                <span v-html="icons.trash" style="margin-right:8px;"></span> {{ t('deleteGroup') }}
              </button>
              <button class="dropdown-item text-danger" @click="leaveGroup(activeChat.id)">
                <span v-html="icons.logout" style="margin-right:8px;"></span> {{ t('leaveGroup') }}
              </button>
            </div>
          </div>
        </div>

        <!-- Chat Messages -->
        <div class="chat-messages" ref="messagesContainer">
          <div v-for="msg in chatMessages" :key="msg.id" class="msg-wrapper" :class="{'msg-self': isSelf(msg.sender_id), 'msg-system': msg.is_system && !msg.content.includes('AI Assistant:')}">
            <!-- Pure System Notifications -->
            <div v-if="msg.is_system && !msg.content.includes('AI Assistant:')" class="msg-system-text">{{ msg.content }}</div>
            
            <!-- AI Bubble Message -->
            <div v-else-if="msg.is_system && msg.content.includes('AI Assistant:')" class="msg-bubble ai-bubble">
              <div class="msg-sender" style="color: var(--accent); display: flex; align-items: center; gap: 4px;">
                <span v-html="icons.bot"></span> {{ t('aiModerator') }}
              </div>
              <div class="msg-content" style="white-space: pre-wrap;">{{ msg.content.replace('🤖 AI Assistant: ', '') }}</div>
              <span class="msg-time">{{ formatTime(msg.created_at) }}</span>
            </div>
            
            <!-- Normal User Message -->
            <div v-else class="msg-bubble" :class="isSelf(msg.sender_id) ? 'chat-out' : 'chat-in'" @contextmenu.prevent="onContextMenu($event, msg)">
              <div v-if="activeChat.type === 'group' && !isSelf(msg.sender_id)" style="display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                <img v-if="msg.sender_avatar" :src="msg.sender_avatar" style="width: 16px; height: 16px; border-radius: 50%; object-fit: cover;" />
                <span v-else style="width: 16px; height: 16px; border-radius: 50%; background: var(--bg-primary); display: flex; align-items: center; justify-content: center; font-size: 8px; font-weight: bold; color: var(--accent);">{{ msg.sender_display_name ? msg.sender_display_name.charAt(0).toUpperCase() : msg.sender_name.charAt(0).toUpperCase() }}</span>
                <div class="msg-sender" style="color: rgba(255, 255, 255, 0.9); margin-bottom: 0;">{{ msg.sender_display_name || msg.sender_name }}</div>
              </div>
              
              <!-- Normal Message -->
              <div v-if="msg.msg_type !== 'progress_report'" class="msg-content" style="white-space: pre-wrap;">{{ msg.content }}</div>
              
              <!-- Progress Report Message -->
              <div v-else class="progress-report-msg">
                <div class="progress-badge">
                  <span v-html="icons.target" style="vertical-align: middle; margin-right: 4px;"></span> {{ t('progressReport') }}
                </div>
                <div class="msg-content" style="margin-bottom: 8px;">{{ msg.content }}</div>
                
                <!-- Image attachment -->
                <div v-if="msg.attachment && isImage(msg.attachment)" class="image-attachment-wrapper">
                  <img :src="msg.attachment" class="progress-img" />
                  <a :href="msg.attachment" :download="msg.original_filename || 'image'" target="_blank" class="image-download-btn">
                    <span v-html="icons.download"></span> {{ t('download') }}
                  </a>
                </div>
                
                <!-- Non-image file attachment -->
                <a v-else-if="msg.attachment" :href="msg.attachment" :download="msg.original_filename || 'file'" target="_blank" class="file-attachment">
                  <div class="file-info">
                    <span v-html="icons.file" class="file-icon"></span>
                    <span class="file-name">{{ msg.original_filename || t('attachmentFile') }}</span>
                  </div>
                  <span v-html="icons.download" class="file-dl"></span>
                </a>
                
                <div class="progress-actions">
                  <span class="approval-count" :class="{'approved': msg.approvals?.length > 0}">
                    <span v-html="icons.check" class="check-icon"></span> 
                    {{ msg.approvals?.length || 0 }} Approve
                  </span>
                  <button 
                    v-if="!isSelf(msg.sender_id) && (!msg.approvals || !msg.approvals.includes(userId.toString()))" 
                    class="btn btn-primary btn-sm" 
                    @click="openApprovalModal(msg.id)"
                    style="font-size: 12px; padding: 6px 12px; display:flex; align-items:center; gap:4px;"
                  >
                    <span v-html="icons.check"></span> {{ t('approve') }}
                  </button>
                </div>
              </div>
              <div class="msg-time-wrapper">
                <span class="msg-time">{{ formatTime(msg.created_at) }}</span>
                <span v-if="isSelf(msg.sender_id)" v-html="isMessageReadByAll(msg.id) ? icons.checkAll : icons.check" class="read-receipt" :class="{'read': isMessageReadByAll(msg.id)}"></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Chat Input -->
        <div class="chat-input-area">
          <button v-if="activeChat.type === 'group'" class="btn-icon" @click="triggerProgressUpload" title="Lapor Progress" style="color: var(--text-secondary);">
            <span v-html="icons.paperclip"></span>
          </button>
          <input type="file" ref="progressFileInput" @change="handleProgressUpload" style="display: none;" />
          
          <input v-model="messageInput" type="text" class="input chat-input" :placeholder="t('typeMessage')" @keydown.enter="sendMessage" />
          
          <button class="chat-mic-btn btn-icon" :class="{ listening: isListening }" @click="toggleVoice" :disabled="sendingMsg" title="Voice Input" style="width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-light); background: var(--bg-elevated); color: var(--text-secondary);">
            <span v-html="icons.mic"></span>
          </button>

          <button class="btn-send" @click="sendMessage" :disabled="sendingMsg">
            <span v-html="icons.send"></span>
          </button>
        </div>
      </div>
    </div>

    <!-- Group Task Assign Modal -->
    <div v-if="taskAssignModal.show" class="modal-overlay" @click.self="taskAssignModal.show = false">
      <div class="approval-modal">
        <div class="modal-header">
          <h3>{{ t('assignGroupTask') }}</h3>
          <button class="btn-icon" @click="taskAssignModal.show = false" v-html="icons.close"></button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>{{ t('taskReceiver') }}</label>
            <select v-model="taskAssignModal.targetUserId" class="input">
              <option disabled value="">{{ t('selectMember') }}</option>
              <option v-for="member in taskAssignModal.members" :key="member.id" :value="member.id">
                {{ member.display_name || member.username }} ({{ member.role }})
              </option>
            </select>
          </div>
          <div class="form-group">
            <label>{{ t('taskTitle') }}</label>
            <input v-model="taskAssignModal.title" type="text" class="input" :placeholder="t('taskTitlePlaceholder')" />
          </div>
          <div class="form-group">
            <label>{{ t('deadline') }}</label>
            <input v-model="taskAssignModal.deadline" type="datetime-local" class="input" />
          </div>
          <div class="form-group">
            <label>{{ t('difficultyLevel') }}</label>
            <select v-model="taskAssignModal.difficulty" class="input">
              <option value="easy">{{ t('easyDiff') }}</option>
              <option value="medium">{{ t('mediumDiff') }}</option>
              <option value="hard">{{ t('hardDiff') }}</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn" @click="taskAssignModal.show = false">{{ t('cancel') }}</button>
          <button class="btn btn-primary" @click="submitGroupTaskAssign" :disabled="sendingMsg">{{ t('shareTask') }}</button>
        </div>
      </div>
    </div>

    <!-- Approval Modal -->
    <div v-if="approvalModal.show" class="modal-overlay" @click.self="approvalModal.show = false">
      <div class="approval-modal">
        <div class="approval-modal-header">
          <h3>{{ t('verifyProgress') }}</h3>
          <button class="btn-icon" @click="approvalModal.show = false" v-html="icons.close"></button>
        </div>
        <div class="approval-modal-body">
          <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 16px;">
            {{ t('verifyProgressDesc') }}
          </p>
          <div class="current-progress-info">
            <span>{{ t('currentProgress') }}</span>
            <span style="color: var(--accent); font-weight: bold;">{{ currentGroupProgress }}%</span>
          </div>
          <div class="progress-slider-group">
            <input type="range" v-model.number="approvalModal.percent" min="1" max="100" class="progress-slider" />
            <div class="progress-slider-value">+{{ approvalModal.percent }}%</div>
          </div>
        </div>
        <div class="approval-modal-footer">
          <button class="btn btn-ghost" @click="approvalModal.show = false">{{ t('cancel') }}</button>
          <button class="btn btn-primary" @click="submitApproval" :disabled="sendingMsg">Approve</button>
        </div>
      </div>
    </div>
    <!-- Group Settings Modal -->
    <div v-if="showGroupSettingsModal" class="modal-overlay" @click.self="showGroupSettingsModal = false">
      <div class="approval-modal">
        <div style="padding: 16px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
          <h3 style="font-size: 16px; margin: 0;">{{ t('groupSettings') }}</h3>
          <button class="btn-icon" @click="showGroupSettingsModal = false" v-html="icons.close"></button>
        </div>
        <div style="padding: 16px;">
          <div class="form-group">
            <label class="form-label">{{ t('groupName') }}</label>
            <input v-model="editingGroup.name" type="text" class="input" placeholder="Nama grup..." />
          </div>
          <div class="form-group" style="margin-top: 12px;">
            <label class="form-label">{{ t('groupProfilePic') }}</label>
            <div style="display: flex; gap: 12px; align-items: center;">
              <img v-if="editingGroup.avatarPreview || editingGroup.avatar" :src="editingGroup.avatarPreview || editingGroup.avatar" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent-dim);" />
              <div v-else class="user-avatar" style="width: 48px; height: 48px; border-radius: 50%; background: var(--bg-elevated); display: flex; align-items: center; justify-content: center; font-weight: bold; color: var(--accent); border: 2px solid var(--accent-dim); font-size: 18px;">
                {{ editingGroup.name ? editingGroup.name.charAt(0).toUpperCase() : 'G' }}
              </div>
              <button class="btn btn-ghost" @click="triggerGroupAvatarUpload" style="font-size: 13px; padding: 6px 12px;">{{ t('changePic') }}</button>
              <input type="file" ref="groupAvatarInput" @change="handleGroupAvatarChange" accept="image/jpeg,image/png,image/webp,image/gif" style="display: none;" />
            </div>
          </div>
          
          <div class="form-group" style="margin-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <label class="form-label" style="margin: 0;">{{ t('groupMembers') }} ({{ groupMembersList.length }})</label>
              <button v-if="isGroupAdmin(editingGroup.id)" class="btn btn-ghost" @click="showAddMember = !showAddMember" style="font-size: 12px; padding: 4px 8px; color: var(--accent);">{{ t('addMember') }}</button>
            </div>
            
            <div v-if="showAddMember" style="margin-bottom: 12px; display: flex; gap: 8px; padding: 8px; background: rgba(255,255,255,0.05); border-radius: 8px;">
              <select v-model="selectedNewMember" class="input" style="flex: 1; padding: 8px;">
                <option value="">{{ t('selectFriend') }}</option>
                <option v-for="friend in availableFriendsToAdd" :key="friend.id" :value="friend.id">
                  {{ friend.display_name || friend.username }}
                </option>
              </select>
              <button class="btn btn-primary" @click="addNewMember" :disabled="!selectedNewMember" style="padding: 8px 16px;">{{ t('add') }}</button>
            </div>

            <div class="members-select" style="max-height: 200px;">
              <div v-for="member in groupMembersList" :key="member.id" class="friend-item" style="padding: 8px;">
                <img v-if="member.avatar" :src="member.avatar" class="user-avatar" />
                <div v-else class="user-avatar" style="background: var(--bg-elevated); display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; color: var(--accent);">
                  {{ member.display_name ? member.display_name.charAt(0).toUpperCase() : member.username.charAt(0).toUpperCase() }}
                </div>
                <div class="friend-info">
                  <div class="friend-name">{{ member.display_name || member.username }} <span v-if="member.role === 'admin'" style="font-size: 10px; padding: 2px 6px; background: var(--accent-dim); color: var(--accent); border-radius: 10px; margin-left: 4px;">Admin</span></div>
                  <div class="friend-username" style="font-size: 11px;">@{{ member.username }}</div>
                </div>
                <div class="member-actions" v-if="member.id !== userId">
                  <button v-if="member.role !== 'admin'" class="btn btn-ghost" @click="changeMemberRole(member.id, 'admin')" style="font-size: 11px; padding: 4px 8px;" :title="t('makeAdmin')">{{ t('makeAdmin') }}</button>
                  <button v-if="member.role === 'admin'" class="btn btn-ghost" @click="changeMemberRole(member.id, 'member')" style="font-size: 11px; padding: 4px 8px;" :title="t('makeMember')">{{ t('makeMember') }}</button>
                  <button class="btn btn-ghost text-danger" @click="kickMember(member.id)" style="font-size: 11px; padding: 4px 8px;" :title="t('kick')">{{ t('kick') }}</button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div style="padding: 16px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 8px;">
          <button class="btn btn-ghost" @click="showGroupSettingsModal = false">{{ t('cancel') }}</button>
          <button class="btn btn-primary" @click="saveGroupSettings" :disabled="savingGroup">
            {{ savingGroup ? t('saving') : t('save') }}
          </button>
        </div>
      </div>
    </div>
    <!-- Context Menu -->
    <div v-if="contextMenu.show" class="msg-context-menu" :style="{top: contextMenu.y + 'px', left: contextMenu.x + 'px'}">
      <div class="context-item text-danger" @click="handleDeleteContext">
        <span v-html="icons.trash"></span> {{ t('deleteMessage') }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { t } from '../i18n.js'
import { ref, onMounted, onUnmounted, nextTick, watch, computed } from 'vue'
import { api, apiUpload } from '../api.js'
import { icons } from '../components/icons.js'
import { useAuthStore } from '../stores/auth.js'

const auth = useAuthStore()
const tab = ref('chats')

// Friends & Groups Data
const friends = ref([])
const pendingRequests = ref([])
const groups = ref([])
const recentChats = ref([]) // Unified list

const addUsername = ref('')
const addMessage = ref('')
const addSuccess = ref(false)
const loadingAdd = ref(false)

const showCreateGroup = ref(false)
const newGroupName = ref('')
const newGroupMembers = ref([])

const showGroupSettingsModal = ref(false)
const groupAvatarInput = ref(null)
const editingGroup = ref({ id: null, name: '', avatar: '', avatarFile: null, avatarPreview: null })

function triggerGroupAvatarUpload() {
  groupAvatarInput.value.click()
}

function handleGroupAvatarChange(e) {
  const file = e.target.files[0]
  if (file) {
    editingGroup.value.avatarFile = file
    const reader = new FileReader()
    reader.onload = (ev) => {
      editingGroup.value.avatarPreview = ev.target.result
    }
    reader.readAsDataURL(file)
  }
}
const savingGroup = ref(false)

// Chat Data
const activeChat = ref(null) // { type: 'dm'|'group', id: Number, name: String }
const chatMessages = ref([])
const messageInput = ref('')
const messagesContainer = ref(null)
const sendingMsg = ref(false)
const isListening = ref(false)
let recognition = null
const progressFileInput = ref(null)
const showGroupOptions = ref(false)
const groupProgress = ref(0)
const taskAssignModal = ref({
  show: false,
  groupId: null,
  members: [],
  targetUserId: '',
  title: '',
  deadline: '',
  difficulty: 'medium'
})

const activeReadUpTo = ref(0)

// Context Menu
const contextMenu = ref({ show: false, x: 0, y: 0, msg: null })

// Approval Modal
const approvalModal = ref({ show: false, messageId: null, percent: 10 })

const userId = computed(() => auth.user?.id)
const currentGroupProgress = computed(() => groupProgress.value)

// User Colors for Groups
const userColors = ['#e53935', '#d81b60', '#8e24aa', '#3949ab', '#039be5', '#00897b', '#43a047', '#f4511e']
function getColorForUser(id) {
  if (!id) return '#fff'
  return userColors[id % userColors.length]
}

function isSelf(senderId) { return senderId == userId.value }

function formatTime(datetime) {
  if (!datetime) return ''
  const date = new Date(datetime + ' UTC')
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

function canDelete(datetime) {
  if (!datetime) return false
  const msgDate = new Date(datetime + ' UTC')
  const now = new Date()
  const diffMs = now - msgDate
  return diffMs <= 5 * 60 * 1000 // 5 minutes
}

function onContextMenu(e, msg) {
  if (isSelf(msg.sender_id) && canDelete(msg.created_at)) {
    contextMenu.value = { show: true, x: e.clientX, y: e.clientY, msg }
  }
}

function closeContextMenu() {
  contextMenu.value.show = false
}

async function handleDeleteContext() {
  const msg = contextMenu.value.msg
  closeContextMenu()
  if (msg) await deleteMessage(msg.id)
}

async function deleteMessage(messageId) {
  if (!confirm('Hapus pesan ini?')) return
  try {
    const endpoint = activeChat.value.type === 'group' ? '/groups/messages' : '/dm'
    await api(`${endpoint}?id=${messageId}`, 'DELETE')
    await loadMessages()
  } catch (e) {
    alert(e.message)
  }
}

function isMessageReadByAll(msgId) {
  return activeChat.value.type === 'group' && msgId <= activeReadUpTo.value
}

function getGroupProgress(groupId) {
  const g = groups.value.find(x => x.id === groupId)
  return g ? (g.progress || 0) : 0
}

function getGroupAvatar(groupId) {
  const g = groups.value.find(x => x.id === groupId)
  return g && g.avatar ? g.avatar : null
}

function getGroupMemberCount(groupId) {
  // Currently we don't fetch all members explicitly on frontend. 
  // Let's assume if they want to delete, we let them try and backend will reject if > 1.
  // But we can hide the delete button by default and just rely on backend error if we don't know the count.
  // Actually, we can fetch group members later. For now let's just return 1 so the button appears and backend handles validation.
  return 1;
}

function isGroupAdmin(groupId) {
  const g = groups.value.find(x => x.id === groupId)
  return g ? g.role === 'admin' : false
}

function isImage(path) {
  if (!path) return false
  const ext = path.split('.').pop().toLowerCase()
  return ['jpg','jpeg','png','gif','webp','bmp','svg'].includes(ext)
}

// Data Fetching
async function fetchRecentChats() {
  try {
    const res = await api('/chat/recent')
    recentChats.value = res.chats || []
  } catch (e) { console.error(e) }
}

async function fetchFriends() {
  try {
    const res = await api('/friends')
    friends.value = res.friends || []
    pendingRequests.value = res.pending_requests || []
  } catch (e) { console.error(e) }
}

async function fetchGroups() {
  try {
    const res = await api('/groups')
    groups.value = res.groups || []
  } catch (e) { console.error(e) }
}

// Friend Actions
async function sendRequest() {
  if (!addUsername.value.trim()) return
  loadingAdd.value = true
  try {
    const res = await api('/friends/request', 'POST', { username: addUsername.value.trim() })
    addSuccess.value = true; addMessage.value = res.message; addUsername.value = ''
  } catch (e) {
    addSuccess.value = false; addMessage.value = e.message
  } finally {
    loadingAdd.value = false; setTimeout(() => { addMessage.value = '' }, 3000)
  }
}

async function respondRequest(requestId, action) {
  try {
    await api('/friends/respond', 'POST', { request_id: requestId, action })
    await fetchFriends()
  } catch (e) { alert(e.message) }
}

// Group Actions
async function createGroup() {
  if (!newGroupName.value.trim()) return
  loadingAdd.value = true
  try {
    await api('/groups', 'POST', { name: newGroupName.value.trim(), members: newGroupMembers.value })
    newGroupName.value = ''; newGroupMembers.value = []
    await fetchGroups()
    await fetchRecentChats()
    tab.value = 'chats'
  } catch (e) { alert(e.message) } finally { loadingAdd.value = false }
}

async function deleteGroup(id) {
  if (!confirm('Hapus grup ini permanen?')) return
  try {
    await api(`/groups?group_id=${id}`, 'DELETE')
    showGroupOptions.value = false
    activeChat.value = null
    await fetchGroups()
    await fetchRecentChats()
  } catch (e) {
    alert(e.message)
  }
}

const groupMembersList = ref([])
const showAddMember = ref(false)
const selectedNewMember = ref('')

const availableFriendsToAdd = computed(() => {
  return friends.value.filter(f => !groupMembersList.value.some(m => m.id === f.id))
})

async function fetchGroupMembers(groupId) {
  try {
    const res = await api(`/groups/members?group_id=${groupId}`)
    groupMembersList.value = res.members || []
  } catch (e) {
    console.error(e)
  }
}

async function addNewMember() {
  if (!selectedNewMember.value) return
  try {
    await api('/groups/members/add', 'POST', { group_id: editingGroup.value.id, user_id: selectedNewMember.value })
    selectedNewMember.value = ''
    showAddMember.value = false
    await fetchGroupMembers(editingGroup.value.id)
  } catch (e) {
    alert(e.message)
  }
}

async function openGroupSettings(id) {
  const g = groups.value.find(x => x.id === id)
  if (!g) return
  editingGroup.value = { id: g.id, name: g.name, avatar: g.avatar || '', avatarFile: null, avatarPreview: null }
  showGroupOptions.value = false
  showAddMember.value = false
  selectedNewMember.value = ''
  await fetchGroupMembers(id)
  showGroupSettingsModal.value = true
}

async function kickMember(userId) {
  if (!confirm('Yakin ingin mengeluarkan anggota ini?')) return
  try {
    await api('/groups/members/kick', 'POST', { group_id: editingGroup.value.id, user_id: userId })
    await fetchGroupMembers(editingGroup.value.id)
  } catch (e) {
    alert(e.message)
  }
}

async function changeMemberRole(userId, newRole) {
  const roleText = newRole === 'admin' ? t('makeAdmin') : t('makeMember')
  if (!confirm(`Yakin ingin ${roleText}?`)) return
  try {
    await api('/groups/members/role', 'POST', { group_id: editingGroup.value.id, user_id: userId, role: newRole })
    await fetchGroupMembers(editingGroup.value.id)
  } catch (e) {
    alert(e.message)
  }
}

async function saveGroupSettings() {
  if (!editingGroup.value.name.trim()) return
  savingGroup.value = true
  try {
    const formData = new FormData()
    formData.append('id', editingGroup.value.id)
    formData.append('name', editingGroup.value.name)
    if (editingGroup.value.avatarFile) {
      formData.append('avatar', editingGroup.value.avatarFile)
    }

    await apiUpload('/groups/update', formData)
    
    // Update local state
    if (activeChat.value && activeChat.value.id === editingGroup.value.id) {
      activeChat.value.name = editingGroup.value.name
    }
    
    showGroupSettingsModal.value = false
    await fetchGroups()
    await fetchRecentChats()
  } catch (e) {
    alert(e.message)
  } finally {
    savingGroup.value = false
  }
}

async function leaveGroup(groupId) {
  if (!confirm('Yakin ingin keluar dari grup ini?')) return
  try {
    await api(`/groups/members?group_id=${groupId}`, 'DELETE')
    activeChat.value = null
    showGroupOptions.value = false
    await fetchGroups()
    await fetchRecentChats()
  } catch (e) { alert(e.message) }
}

// Chat Actions
async function openChat(chatItem) {
  activeChat.value = { type: chatItem.type, id: chatItem.id, name: chatItem.name }
  await loadMessages()
}

async function loadMessages() {
  if (!activeChat.value) return
  try {
    let endpoint = activeChat.value.type === 'dm' 
      ? `/dm?user_id=${activeChat.value.id}` 
      : `/groups/messages?group_id=${activeChat.value.id}`
    const res = await api(endpoint)
    chatMessages.value = res.messages || []
    if (res.current_progress !== undefined) {
      groupProgress.value = res.current_progress
    }
    if (res.read_up_to !== undefined) {
      activeReadUpTo.value = res.read_up_to
    }
    await nextTick()
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  } catch (e) { console.error(e) }
}

async function sendMessage() {
  if (!messageInput.value.trim() || !activeChat.value) return
  const content = messageInput.value.trim()
  messageInput.value = ''
  
  chatMessages.value.push({ id: Date.now(), sender_id: userId.value, content, is_system: 0, sender_name: auth.user.username, msg_type: 'text', created_at: new Date().toISOString() })
  await nextTick(); if (messagesContainer.value) messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
  
  sendingMsg.value = true
  try {
    if (activeChat.value.type === 'dm') {
      await api('/dm', 'POST', { receiver_id: activeChat.value.id, content })
    } else {
      await api('/groups/messages', 'POST', { group_id: activeChat.value.id, content, msg_type: 'text' })
    }
    await loadMessages()
    await fetchRecentChats()
  } catch (e) { alert(e.message); await loadMessages() } finally { sendingMsg.value = false }
}

function triggerProgressUpload() {
  if (progressFileInput.value) progressFileInput.value.click()
}

async function handleProgressUpload(event) {
  const file = event.target.files[0]
  if (!file) return
  
  const desc = prompt('Deskripsi laporan progress ini:')
  if (desc === null) return
  
  sendingMsg.value = true
  const formData = new FormData()
  formData.append('group_id', activeChat.value.id)
  formData.append('content', desc || 'Mengirim laporan progress')
  formData.append('msg_type', 'progress_report')
  formData.append('file', file)
  
  try {
    await apiUpload('/groups/messages', formData)
    await loadMessages()
    await fetchRecentChats()
  } catch (e) { alert('Upload gagal: ' + e.message) }
  finally { sendingMsg.value = false; event.target.value = '' }
}

function openApprovalModal(messageId) {
  approvalModal.value = { show: true, messageId, percent: 10 }
}

async function submitApproval() {
  sendingMsg.value = true
  try {
    await api('/groups/messages/approve', 'POST', { 
      message_id: approvalModal.value.messageId, 
      progress_percent: approvalModal.value.percent 
    })
    approvalModal.value.show = false
    await loadMessages()
    await fetchGroups()
  } catch (e) { alert(e.message) } finally { sendingMsg.value = false }
}

async function resetGroupProgress(groupId) {
  if (!confirm('Apakah kamu yakin ingin me-reset progress grup ini kembali ke 0%? Semua riwayat chat akan dipertahankan, dan proyek baru akan dimulai.')) return
  try {
    await api('/groups/reset', 'POST', { group_id: groupId })
    showGroupOptions.value = false
    await loadMessages()
    await fetchGroups()
  } catch (e) {
    alert(e.message)
  }
}

onMounted(() => {
  fetchRecentChats()
  fetchFriends()
  fetchGroups()
  setInterval(() => {
    if (activeChat.value) loadMessages()
    fetchRecentChats()
    fetchFriends()
    fetchGroups()
  }, 5000)

  const tomorrow = new Date()
  tomorrow.setDate(tomorrow.getDate() + 1)
  tomorrow.setHours(23, 59, 0, 0)
  taskAssignModal.value.deadline = tomorrow.toISOString().slice(0, 16)

  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition
  if (SpeechRecognition) {
    recognition = new SpeechRecognition()
    recognition.lang = 'id-ID'
    recognition.continuous = false
    recognition.interimResults = false
    
    recognition.onstart = () => { isListening.value = true }
    recognition.onresult = (event) => {
      const transcript = event.results[0][0].transcript
      messageInput.value = (messageInput.value + ' ' + transcript).trim()
    }
    recognition.onerror = (event) => {
      console.error('Speech recognition error', event.error)
      isListening.value = false
    }
    recognition.onend = () => { isListening.value = false }
  }
})

function toggleVoice() {
  if (!recognition) {
    alert('Browser kamu tidak mendukung fitur voice recognition.')
    return
  }
  if (isListening.value) {
    recognition.stop()
  } else {
    recognition.start()
  }
}

async function openGroupTaskAssign(groupId) {
  showGroupOptions.value = false
  try {
    const res = await api(`/groups/members?group_id=${groupId}`)
    taskAssignModal.value.members = res.members || []
    taskAssignModal.value.groupId = groupId
    taskAssignModal.value.show = true
  } catch (e) {
    alert('Gagal mengambil daftar anggota')
  }
}

async function submitGroupTaskAssign() {
  if (!taskAssignModal.value.targetUserId || !taskAssignModal.value.title || !taskAssignModal.value.deadline) {
    alert('Harap isi semua kolom tugas')
    return
  }
  
  sendingMsg.value = true
  try {
    await api('/groups/tasks', 'POST', {
      group_id: taskAssignModal.value.groupId,
      target_user_id: taskAssignModal.value.targetUserId,
      title: taskAssignModal.value.title,
      deadline: taskAssignModal.value.deadline,
      difficulty: taskAssignModal.value.difficulty
    })
    
    taskAssignModal.value.show = false
    taskAssignModal.value.title = ''
    alert('Tugas kelompok berhasil dibagikan!')
    await loadMessages()
  } catch (e) {
    alert('Error: ' + e.message)
  } finally {
    sendingMsg.value = false
  }
}
</script>

<style scoped>
/* AntiDeadline Native Styles */
.view-container {
  display: flex;
  height: 100%;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  background: var(--bg-primary);
  position: relative;
}

.social-sidebar {
  width: 320px;
  min-width: 320px;
  display: flex;
  flex-direction: column;
  border-right: 1px solid var(--border);
  background: var(--bg-primary);
}

.social-tabs {
  display: flex;
  border-bottom: 1px solid var(--border);
}

.social-tabs button {
  flex: 1;
  padding: 12px;
  background: transparent;
  border: none;
  border-bottom: 2px solid transparent;
  color: var(--text-secondary);
  cursor: pointer;
  font-weight: 600;
  transition: all 0.2s;
}

.social-tabs button.active {
  color: var(--accent);
  border-bottom-color: var(--accent);
}

.social-list {
  flex: 1;
  overflow-y: auto;
}

/* Chat Item in List */
.friend-item {
  padding: 12px 16px;
  cursor: pointer;
  transition: background 0.15s;
}
.friend-item:hover, .friend-item.active {
  background: rgba(255,255,255,0.05);
}

.friend-info {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
}

.friend-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: var(--bg-card);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  color: var(--text-secondary);
  flex-shrink: 0;
}

.chat-item-content {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
  border-bottom: 1px solid var(--border-light);
  padding-bottom: 12px;
}

.friend-item:last-child .chat-item-content {
  border-bottom: none;
}

.chat-item-header {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 2px;
}

.friend-name {
  font-weight: 500;
  font-size: 16px;
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.chat-item-time {
  font-size: 12px;
  color: var(--text-tertiary);
}

.chat-item-last-msg {
  font-size: 13px;
  color: var(--text-secondary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.social-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  position: relative;
  background: var(--bg-primary);
}

.empty-chat-state {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: var(--text-tertiary);
  font-size: 15px;
  z-index: 1;
}

.chat-container {
  display: flex;
  flex-direction: column;
  height: 100%;
  position: relative;
  z-index: 1;
}

/* Chat Header */
.chat-header {
  padding: 10px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  background: var(--bg-primary);
  border-bottom: 1px solid var(--border);
  z-index: 2;
}

.chat-title {
  font-size: 16px;
  font-weight: 500;
  color: var(--text-primary);
}

.chat-subtitle {
  font-size: 13px;
  color: var(--text-tertiary);
}

.group-progress-bar {
  height: 3px;
  background: rgba(255,255,255,0.1);
  border-radius: 2px;
  margin-top: 4px;
  width: 100%;
  max-width: 200px;
}
.group-progress-fill {
  height: 100%;
  background: var(--accent);
  border-radius: 2px;
  transition: width 0.3s;
}

/* Chat Messages */
.chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 20px 5%;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.msg-wrapper {
  display: flex;
  width: 100%;
  margin-bottom: 2px;
}

.msg-wrapper.msg-self {
  justify-content: flex-end;
}

.msg-system-text {
  background: var(--bg-card);
  color: var(--text-secondary);
  padding: 6px 12px;
  border-radius: 12px;
  font-size: 12px;
  margin: 12px auto;
  text-align: center;
  max-width: 80%;
  border: 1px solid var(--border-light);
}

.msg-bubble {
  max-width: 75%;
  padding: 10px 16px;
  border-radius: 18px;
  font-size: 14.5px;
  position: relative;
  color: var(--text-primary);
  display: flex;
  flex-direction: column;
  line-height: 1.5;
  box-shadow: 0 4px 15px rgba(0,0,0,0.05);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.msg-bubble:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(0,0,0,0.08);
}

.chat-in {
  background: var(--bg-elevated);
  border: 1px solid var(--border);
  color: var(--text-primary);
  border-bottom-left-radius: 4px;
}

.chat-out {
  background: var(--accent);
  border: none;
  color: var(--accent-text);
  border-bottom-right-radius: 4px;
}

.msg-sender {
  font-size: 12.5px;
  font-weight: 600;
  margin-bottom: 2px;
}

.msg-time-wrapper {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 4px;
  margin-top: 4px;
}

.msg-time {
  font-size: 11px;
  color: var(--text-secondary);
}

.chat-in .msg-time {
  color: var(--text-secondary);
  opacity: 0.8;
}

.chat-out .msg-time {
  color: rgba(255, 255, 255, 0.8);
}

.read-receipt {
  color: rgba(255, 255, 255, 0.7);
  font-size: 14px;
  display: flex;
}
.read-receipt.read {
  color: #fff;
  opacity: 1;
}

/* Context Menu */
.msg-context-menu {
  position: fixed;
  background: var(--bg-elevated);
  border: 1px solid var(--border-color);
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
  padding: 4px;
  z-index: 9999;
  min-width: 150px;
}
.context-item {
  padding: 8px 12px;
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  border-radius: 4px;
  font-size: 13px;
  transition: background 0.2s;
}
.context-item:hover {
  background: var(--bg-hover);
}
.context-item.text-danger {
  color: #ff4d4f;
}

/* Input Area */
.chat-input-area {
  padding: 12px 20px 20px;
  background: transparent;
  display: flex;
  align-items: center;
  gap: 12px;
  position: relative;
  z-index: 10;
}

.chat-input {
  flex: 1;
  background: var(--bg-elevated);
  border: 1px solid var(--border-light);
  border-radius: 24px;
  padding: 14px 20px;
  color: var(--text-primary);
  font-size: 14.5px;
  outline: none;
  transition: all 0.2s ease;
  box-shadow: 0 4px 12px rgba(0,0,0,0.03);
}

.chat-input:focus {
  border-color: var(--accent);
  box-shadow: 0 4px 15px var(--accent-dim);
  background: var(--bg-card);
}

.btn-send {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: var(--accent);
  color: var(--accent-text);
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
  box-shadow: 0 4px 12px var(--accent-dim);
}

.btn-send:hover:not(:disabled) {
  transform: scale(1.08) translateY(-2px);
  box-shadow: 0 6px 16px var(--accent-dim);
}

.btn-send:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
  background: var(--border);
  color: var(--text-tertiary);
}

/* Group Options */
.group-options {
  position: relative;
}
.dropdown-menu {
  position: absolute;
  top: 100%;
  right: 0;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  padding: 8px 0;
  min-width: 150px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.4);
  z-index: 100;
}
.dropdown-item {
  display: flex;
  align-items: center;
  width: 100%;
  padding: 10px 16px;
  background: none;
  border: none;
  color: var(--text-primary);
  cursor: pointer;
  text-align: left;
  font-size: 14px;
}
.dropdown-item:hover { background: rgba(255,255,255,0.05); }

/* File Attachment */
.file-attachment {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  background: rgba(0,0,0,0.2);
  border-radius: 8px;
  text-decoration: none;
  color: var(--text-primary);
  margin: 4px 0 8px;
  border: 1px solid var(--border-light);
}
.chat-out .file-attachment {
  background: rgba(0,0,0,0.1);
  border-color: var(--accent);
  color: var(--text-primary);
}
.chat-out .file-icon { color: var(--accent); }

.file-icon { color: var(--accent); flex-shrink: 0; }
.file-name { flex: 1; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Approval Modal */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(11, 20, 26, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}
.approval-modal {
  background: var(--bg-card);
  border-radius: 20px;
  width: 100%;
  max-width: 360px;
  overflow: hidden;
  box-shadow: 0 24px 48px rgba(0,0,0,0.15);
  border: 1px solid var(--border-light);
  color: var(--text-primary);
}
.approval-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-light);
}
.approval-modal-header h3 { font-size: 18px; font-weight: 500; }
.approval-modal-body { padding: 20px; }
.current-progress-info {
  display: flex;
  justify-content: space-between;
  padding: 12px;
  background: var(--bg-card);
  border-radius: 8px;
  font-size: 14px;
  margin-bottom: 20px;
  border: 1px solid var(--border-light);
}
.progress-slider-group {
  display: flex;
  align-items: center;
  gap: 16px;
  background: var(--bg-elevated);
  padding: 16px;
  border-radius: 12px;
  border: 1px solid var(--border-light);
}
.progress-slider {
  flex: 1;
  -webkit-appearance: none;
  appearance: none;
  height: 6px;
  border-radius: 3px;
  background: var(--bg-card);
  outline: none;
}
.progress-slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: var(--accent);
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(0,139,255,0.4);
  border: 2px solid #fff;
}
.progress-slider-value {
  font-size: 16px;
  font-weight: 700;
  color: var(--accent);
  min-width: 44px;
  text-align: right;
}
.approval-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 20px;
  border-top: 1px solid var(--border-light);
}

/* Members Select */
.members-select {
  max-height: 150px;
  overflow-y: auto;
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  padding: 8px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.member-checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  cursor: pointer;
  padding: 4px;
}

/* Progress Report Box */
.progress-report-msg {
  background: rgba(255,255,255,0.15);
  padding: 12px;
  border-radius: 12px;
  margin-top: 6px;
  border: 1px solid rgba(255,255,255,0.2);
}
.chat-in .progress-report-msg {
  background: var(--bg-elevated);
  border: 1px solid var(--border-light);
}

.progress-badge {
  font-size: 11.5px;
  font-weight: 700;
  color: inherit;
  margin-bottom: 10px;
  letter-spacing: 0.5px;
  display: flex;
  align-items: center;
  gap: 4px;
}
.chat-in .progress-badge { color: var(--accent); }

.progress-img {
  max-width: 100%;
  border-radius: 8px;
  margin-bottom: 8px;
  border: 1px solid rgba(255,255,255,0.1);
}
.chat-in .progress-img { border-color: var(--border-light); }

.image-attachment-wrapper {
  position: relative;
  display: block;
  margin-bottom: 12px;
}
.image-download-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  padding: 6px 12px;
  background: var(--bg-elevated);
  border: 1px solid var(--border-color);
  color: var(--text-primary);
  border-radius: 20px;
  text-decoration: none;
  font-weight: 500;
  transition: all 0.2s;
  cursor: pointer;
  box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}
.image-download-btn:hover {
  background: var(--accent);
  color: #fff;
  border-color: var(--accent);
}

.file-attachment {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  text-decoration: none;
  color: inherit;
  transition: all 0.2s;
  margin-bottom: 10px;
}
.file-attachment:hover {
  background: rgba(255, 255, 255, 0.1);
  border-color: rgba(255, 255, 255, 0.2);
}
.chat-in .file-attachment {
  background: var(--bg-card);
  border-color: var(--border-light);
}
.chat-in .file-attachment:hover {
  background: var(--bg-hover);
}
.file-info {
  display: flex;
  align-items: center;
  gap: 8px;
  overflow: hidden;
}
.file-icon {
  font-size: 16px;
  color: var(--accent);
}
.file-name {
  font-size: 13px;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.file-dl {
  font-size: 16px;
  opacity: 0.7;
  transition: opacity 0.2s;
}
.file-attachment:hover .file-dl {
  opacity: 1;
  color: var(--accent);
}

.progress-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 10px;
  gap: 12px;
}

.approval-count {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12.5px;
  font-weight: 600;
  color: inherit;
  opacity: 0.8;
}
.approval-count.approved {
  opacity: 1;
}
.chat-out .approval-count {
  color: #fff;
}
.chat-out .check-icon {
  color: #fff;
}
.chat-in .check-icon {
  color: var(--accent);
}

/* AI Bubble */
.ai-bubble {
  background: rgba(0, 139, 255, 0.05);
  border: 1px solid rgba(0, 139, 255, 0.2);
  border-top-left-radius: 4px;
}

/* ===== MOBILE ===== */
  .social-view {
    flex: 1;
    height: 100%;
    border: none;
    border-radius: 0;
  }
  .social-sidebar {
    width: 100%;
    min-width: 100%;
  }
  .social-main {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 10;
    display: none;
  }
  .social-main.mobile-active {
    display: flex;
  }
  .social-sidebar.mobile-hidden {
    display: none;
  }
  .mobile-only {
    display: flex;
  }
  .msg-wrapper { width: 100%; }
  .msg-bubble { max-width: 88%; padding: 6px 12px; font-size: 13.5px; }
  .chat-input { padding: 10px 14px; font-size: 14px; }
  .btn-send { width: 40px; height: 40px; }
  .chat-input-area { position: relative; bottom: 0; padding-bottom: 24px; }

.progress-slider {
  -webkit-appearance: none;
  width: 100%;
  height: 6px;
  background: var(--border);
  border-radius: 4px;
  outline: none;
}
.progress-slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: var(--accent);
  cursor: pointer;
  box-shadow: 0 0 10px var(--accent-dim);
}
.progress-slider::-moz-range-thumb {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: var(--accent);
  cursor: pointer;
  box-shadow: 0 0 10px var(--accent-dim);
  border: none;
}
</style>
