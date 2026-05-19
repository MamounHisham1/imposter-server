<script setup>
import { ref, nextTick, onMounted, onUnmounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useToast } from '../Composables/useToast';
import AvatarDisplay from './AvatarDisplay.vue';

const { t } = useI18n();
const { error: toastError } = useToast();

const props = defineProps({
    roomId: {
        type: [Number, String],
        required: true,
    },
    roomCode: {
        type: String,
        required: true,
    },
    playerId: {
        type: [Number, String],
        required: true,
    },
    messages: {
        type: Array,
        default: () => [],
    },
});

const isOpen = ref(false);
const messageInput = ref('');
const localMessages = ref([...(props.messages || [])]);
const messagesContainer = ref(null);
const isSending = ref(false);

watch(() => props.messages, (newVal) => {
    if (newVal && newVal.length > 0) {
        localMessages.value = [...newVal];
        scrollToBottom();
    }
}, { deep: true });

function scrollToBottom() {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
}

function toggleChat() {
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        scrollToBottom();
    }
}

function sendMessage() {
    const trimmed = messageInput.value.trim();
    if (!trimmed || isSending.value) return;

    isSending.value = true;

    router.post(
        '/game/' + props.roomCode + '/chat',
        { message: trimmed, player_id: props.playerId, room_id: props.roomId },
        {
            preserveScroll: true,
            onSuccess: () => {
                messageInput.value = '';
            },
            onError: (errors) => {
                const msg = Object.values(errors)[0];
                if (msg) toastError(msg);
            },
            onFinish: () => {
                isSending.value = false;
            },
        }
    );
}

function formatTime(isoString) {
    if (!isoString) return '';
    const date = new Date(isoString);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// Listen for real-time chat messages via WebSocket
onMounted(() => {
    if (window.Echo) {
        window.Echo.channel('room.' + props.roomId)
            .listen('.game.event', (e) => {
                if (e.type === 'chat_message') {
                    localMessages.value.push({
                        id: e.id,
                        player: e.player,
                        message: e.message,
                        created_at: e.created_at,
                    });
                    // Keep only last 50 messages locally
                    if (localMessages.value.length > 50) {
                        localMessages.value = localMessages.value.slice(-50);
                    }
                    scrollToBottom();
                }
            });
    }
});

onUnmounted(() => {
    // Don't leave the channel here - the parent component manages it
});
</script>

<template>
    <div class="game-chat-container">
        <!-- Chat Toggle Button -->
        <button
            @click="toggleChat"
            class="chat-toggle-btn"
            :class="{ 'active': isOpen }"
            :title="isOpen ? t('close_chat') : t('open_chat')"
        >
            <svg v-if="!isOpen" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
        </button>

        <!-- Chat Panel -->
        <Transition name="chat-slide">
            <div v-if="isOpen" class="chat-panel">
                <div class="chat-header">
                    <span class="wanted-text text-lg">{{ t('chat_title') }}</span>
                </div>

                <!-- Messages Area -->
                <div ref="messagesContainer" class="chat-messages scrollbar-western">
                    <div v-if="localMessages.length === 0" class="chat-empty">
                        {{ t('no_messages_yet') }}
                    </div>
                    <div v-for="msg in localMessages" :key="msg.id" class="chat-message">
                        <div class="chat-message-avatar">
                            <AvatarDisplay :avatar="msg.player?.avatar" :size="28" />
                        </div>
                        <div class="chat-message-content">
                            <div class="chat-message-header">
                                <span class="chat-message-nickname">{{ msg.player?.nickname }}</span>
                                <span class="chat-message-time">{{ formatTime(msg.created_at) }}</span>
                            </div>
                            <div class="chat-message-text">{{ msg.message }}</div>
                        </div>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="chat-input-area">
                    <input
                        v-model="messageInput"
                        @keyup.enter="sendMessage"
                        type="text"
                        class="chat-input"
                        :placeholder="t('type_message')"
                        maxlength="500"
                    />
                    <button
                        @click="sendMessage"
                        :disabled="!messageInput.trim() || isSending"
                        class="chat-send-btn"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13" />
                            <polygon points="22 2 15 22 11 13 2 9 22 2" />
                        </svg>
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.game-chat-container {
    position: fixed;
    bottom: 16px;
    right: 16px;
    z-index: 40;
}

.chat-toggle-btn {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background-color: #8b2500;
    color: #e8dcc4;
    border: 3px solid #4a1500;
    box-shadow: 2px 2px 0px #3a1000;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
    position: relative;
    z-index: 41;
}
.chat-toggle-btn:hover {
    transform: scale(1.1);
}
.chat-toggle-btn:active {
    box-shadow: 0px 0px 0px #3a1000;
    transform: translate(2px, 2px);
}

.chat-panel {
    position: absolute;
    bottom: 56px;
    right: 0;
    width: 340px;
    max-height: 480px;
    background-color: #e8dcc4;
    border: 3px solid #8b4513;
    box-shadow: inset 0 0 20px rgba(139, 69, 19, 0.15), 4px 4px 0px rgba(0,0,0,0.4);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

@media (min-width: 768px) {
    .chat-panel {
        width: 380px;
        max-height: 520px;
    }
}

.chat-header {
    padding: 10px 14px;
    background-color: #8b4513;
    color: #e8dcc4;
    border-bottom: 2px solid #5c3a21;
    text-align: center;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
    min-height: 200px;
    max-height: 360px;
}

.chat-empty {
    text-align: center;
    color: #8b4513;
    opacity: 0.6;
    padding: 20px 0;
    font-size: 14px;
}

.chat-message {
    display: flex;
    gap: 8px;
    padding: 6px 0;
    border-bottom: 1px dashed rgba(139, 69, 19, 0.2);
}
.chat-message:last-child {
    border-bottom: none;
}

.chat-message-avatar {
    flex-shrink: 0;
}

.chat-message-content {
    flex: 1;
    min-width: 0;
}

.chat-message-header {
    display: flex;
    align-items: baseline;
    gap: 6px;
    margin-bottom: 2px;
}

.chat-message-nickname {
    font-weight: bold;
    color: #8b2500;
    font-size: 13px;
}

.chat-message-time {
    color: #8b4513;
    opacity: 0.5;
    font-size: 11px;
}

.chat-message-text {
    color: #4a2511;
    font-size: 14px;
    word-break: break-word;
    line-height: 1.4;
}

.chat-input-area {
    display: flex;
    gap: 6px;
    padding: 8px 10px;
    border-top: 2px solid #8b4513;
    background-color: #d3bfa1;
}

.chat-input {
    flex: 1;
    background: transparent;
    border: none;
    border-bottom: 2px dashed #8b4513;
    color: #4a2511;
    font-size: 14px;
    padding: 4px 2px;
    font-family: inherit;
}
.chat-input:focus {
    outline: none;
    border-bottom-style: solid;
}
.chat-input::placeholder {
    color: #8b4513;
    opacity: 0.5;
}

.chat-send-btn {
    background-color: #8b2500;
    color: #e8dcc4;
    border: 2px solid #4a1500;
    padding: 4px 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.1s;
}
.chat-send-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.chat-send-btn:active:not(:disabled) {
    transform: translate(1px, 1px);
}

.wanted-text { color: #4a2511; text-shadow: 1px 1px 0px rgba(255,255,255,0.8); }

.scrollbar-western::-webkit-scrollbar { width: 6px; }
.scrollbar-western::-webkit-scrollbar-track { background: transparent; }
.scrollbar-western::-webkit-scrollbar-thumb { background: #8b4513; border-radius: 4px; }

/* Chat slide transition */
.chat-slide-enter-active { transition: all 0.25s ease-out; }
.chat-slide-leave-active { transition: all 0.15s ease-in; }
.chat-slide-enter-from { opacity: 0; transform: translateY(20px) scale(0.95); }
.chat-slide-leave-to { opacity: 0; transform: translateY(10px) scale(0.95); }
</style>
