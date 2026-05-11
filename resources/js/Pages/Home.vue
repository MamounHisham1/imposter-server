<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useToast } from '../Composables/useToast';

const { t } = useI18n();
const { error: toastError } = useToast();

const props = defineProps({
    rooms: {
        type: Array,
        default: () => [],
    },
});

const activePanel = ref(null); // 'create' | 'join' | null
const localRooms = ref([...props.rooms]);

onMounted(() => {
    if (window.Echo) {
        window.Echo.channel('public-rooms')
            .listen('.rooms.event', (e) => {
                switch (e.action) {
                    case 'created':
                        if (!localRooms.value.find((r) => r.id === e.room.id)) {
                            localRooms.value.unshift(e.room);
                        }
                        break;
                    case 'updated':
                        const idx = localRooms.value.findIndex((r) => r.id === e.room.id);
                        if (idx !== -1) {
                            localRooms.value[idx] = e.room;
                        } else {
                            localRooms.value.unshift(e.room);
                        }
                        break;
                    case 'removed':
                        localRooms.value = localRooms.value.filter((r) => r.id !== e.room.id);
                        break;
                }
            });
    }
});

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leaveChannel('public-rooms');
    }
});

const createForm = useForm({
    nickname: '',
    type: 'public',
    max_players: 6,
    rounds_per_game: 3,
    language: 'en',
});

const joinForm = useForm({
    code: '',
    nickname: '',
});

function showCreate() {
    activePanel.value = activePanel.value === 'create' ? null : 'create';
}

function showJoin() {
    activePanel.value = activePanel.value === 'join' ? null : 'join';
}

function submitCreate() {
    createForm.post('/room', {
        onError: (errors) => {
            const msg = Object.values(errors)[0];
            if (msg) toastError(msg);
        },
    });
}

function submitJoin() {
    joinForm.post('/room/join', {
        onError: (errors) => {
            const msg = Object.values(errors)[0];
            if (msg) toastError(msg);
        },
    });
}
</script>

<template>
    <div class="min-h-screen bg-[#000a00] text-[#33ff66] flex flex-col items-center px-4 py-8">
        <Toast />
        <!-- Title -->
        <h1
            class="text-5xl sm:text-7xl font-extrabold tracking-[0.4em] mb-2 text-center"
            style="
                background: linear-gradient(180deg, #00ff41 0%, #33ff66 50%, #00ff41 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                text-shadow: none;
            "
        >
            {{ t('title') }}
        </h1>

        <!-- Decorative line -->
        <div class="w-48 h-[2px] bg-gradient-to-r from-transparent via-[#00ff41] to-transparent mb-10"></div>

        <!-- Main buttons -->
        <div class="flex flex-col sm:flex-row gap-4 mb-8 w-full max-w-md">
            <button
                @click="showCreate"
                class="flex-1 relative group"
            >
                <div
                    class="absolute inset-0 bg-[#00ff41]/10 group-hover:bg-[#00ff41]/20 transition-all duration-300"
                    style="clip-path: polygon(8px 0, 100% 0, calc(100% - 8px) 100%, 0 100%);"
                ></div>
                <div
                    class="relative border border-[#00ff41]/60 px-6 py-4 font-bold text-lg tracking-wider text-[#00ff41] group-hover:text-[#33ff66] transition-colors"
                    style="clip-path: polygon(8px 0, 100% 0, calc(100% - 8px) 100%, 0 100%);"
                >
                    <!-- Plus SVG icon -->
                    <span class="flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 5v14M5 12h14" stroke-linecap="round" />
                        </svg>
                        {{ t('create_room') }}
                    </span>
                </div>
            </button>

            <button
                @click="showJoin"
                class="flex-1 relative group"
            >
                <div
                    class="absolute inset-0 bg-[#00ff41]/10 group-hover:bg-[#00ff41]/20 transition-all duration-300"
                    style="clip-path: polygon(8px 0, 100% 0, calc(100% - 8px) 100%, 0 100%);"
                ></div>
                <div
                    class="relative border border-[#00ff41]/60 px-6 py-4 font-bold text-lg tracking-wider text-[#00ff41] group-hover:text-[#33ff66] transition-colors"
                    style="clip-path: polygon(8px 0, 100% 0, calc(100% - 8px) 100%, 0 100%);"
                >
                    <!-- Enter SVG icon -->
                    <span class="flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4" stroke-linecap="round" stroke-linejoin="round" />
                            <polyline points="10 17 15 12 10 7" stroke-linecap="round" stroke-linejoin="round" />
                            <line x1="15" y1="12" x2="3" y2="12" stroke-linecap="round" />
                        </svg>
                        {{ t('join_room') }}
                    </span>
                </div>
            </button>
        </div>

        <!-- Create Room Panel -->
        <transition name="slide">
            <div
                v-if="activePanel === 'create'"
                class="w-full max-w-md border border-[#00ff41]/30 bg-[#001200]/80 p-6 mb-6"
                style="clip-path: polygon(0 0, 100% 0, 100% calc(100% - 12px), calc(100% - 12px) 100%, 0 100%);"
            >
                <form @submit.prevent="submitCreate" class="space-y-5">
                    <!-- Nickname -->
                    <div>
                        <label class="block text-xs font-bold tracking-wider mb-1 text-[#00ff41]/70 uppercase">
                            {{ t('nickname') }}
                        </label>
                        <input
                            v-model="createForm.nickname"
                            type="text"
                            maxlength="20"
                            required
                            class="w-full bg-[#000a00] border border-[#00ff41]/30 px-4 py-2 text-[#33ff66] font-mono focus:border-[#00ff41] focus:outline-none focus:ring-1 focus:ring-[#00ff41]/50 transition-colors"
                            :placeholder="t('nickname')"
                        />
                    </div>

                    <!-- Room Type Toggle -->
                    <div>
                        <label class="block text-xs font-bold tracking-wider mb-2 text-[#00ff41]/70 uppercase">
                            {{ t('public') }} / {{ t('private') }}
                        </label>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                @click="createForm.type = 'public'"
                                :class="[
                                    'flex-1 py-2 text-sm font-bold tracking-wider border transition-all',
                                    createForm.type === 'public'
                                        ? 'border-[#00ff41] bg-[#00ff41]/15 text-[#00ff41]'
                                        : 'border-[#00ff41]/20 bg-transparent text-[#00ff41]/40 hover:border-[#00ff41]/40',
                                ]"
                                style="clip-path: polygon(6px 0, 100% 0, calc(100% - 6px) 100%, 0 100%);"
                            >
                                <!-- Globe SVG -->
                                <span class="flex items-center justify-center gap-1">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10" />
                                        <line x1="2" y1="12" x2="22" y2="12" />
                                        <path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10A15.3 15.3 0 0112 2z" />
                                    </svg>
                                    {{ t('public') }}
                                </span>
                            </button>
                            <button
                                type="button"
                                @click="createForm.type = 'private'"
                                :class="[
                                    'flex-1 py-2 text-sm font-bold tracking-wider border transition-all',
                                    createForm.type === 'private'
                                        ? 'border-[#00ff41] bg-[#00ff41]/15 text-[#00ff41]'
                                        : 'border-[#00ff41]/20 bg-transparent text-[#00ff41]/40 hover:border-[#00ff41]/40',
                                ]"
                                style="clip-path: polygon(6px 0, 100% 0, calc(100% - 6px) 100%, 0 100%);"
                            >
                                <!-- Lock SVG -->
                                <span class="flex items-center justify-center gap-1">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                                        <path d="M7 11V7a5 5 0 0110 0v4" />
                                    </svg>
                                    {{ t('private') }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Language Toggle -->
                    <div>
                        <label class="block text-xs font-bold tracking-wider mb-2 text-[#00ff41]/70 uppercase">
                            {{ t('language') }}
                        </label>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                @click="createForm.language = 'en'"
                                :class="[
                                    'flex-1 py-2 text-sm font-bold tracking-wider border transition-all',
                                    createForm.language === 'en'
                                        ? 'border-[#00ff41] bg-[#00ff41]/15 text-[#00ff41]'
                                        : 'border-[#00ff41]/20 bg-transparent text-[#00ff41]/40 hover:border-[#00ff41]/40',
                                ]"
                                style="clip-path: polygon(6px 0, 100% 0, calc(100% - 6px) 100%, 0 100%);"
                            >
                                {{ t('english') }}
                            </button>
                            <button
                                type="button"
                                @click="createForm.language = 'ar'"
                                :class="[
                                    'flex-1 py-2 text-sm font-bold tracking-wider border transition-all',
                                    createForm.language === 'ar'
                                        ? 'border-[#00ff41] bg-[#00ff41]/15 text-[#00ff41]'
                                        : 'border-[#00ff41]/20 bg-transparent text-[#00ff41]/40 hover:border-[#00ff41]/40',
                                ]"
                                style="clip-path: polygon(6px 0, 100% 0, calc(100% - 6px) 100%, 0 100%);"
                            >
                                {{ t('arabic') }}
                            </button>
                        </div>
                    </div>

                    <!-- Max Players Slider -->
                    <div>
                        <label class="block text-xs font-bold tracking-wider mb-1 text-[#00ff41]/70 uppercase">
                            {{ t('max_players') }}: <span class="text-[#00ff41]">{{ createForm.max_players }}</span>
                        </label>
                        <input
                            v-model.number="createForm.max_players"
                            type="range"
                            min="3"
                            max="10"
                            class="w-full accent-[#00ff41] h-2 bg-[#00ff41]/20 rounded-lg appearance-none cursor-pointer"
                        />
                        <div class="flex justify-between text-[10px] text-[#00ff41]/40 font-mono mt-1">
                            <span>3</span>
                            <span>10</span>
                        </div>
                    </div>

                    <!-- Rounds Slider -->
                    <div>
                        <label class="block text-xs font-bold tracking-wider mb-1 text-[#00ff41]/70 uppercase">
                            {{ t('rounds') }}: <span class="text-[#00ff41]">{{ createForm.rounds_per_game }}</span>
                        </label>
                        <input
                            v-model.number="createForm.rounds_per_game"
                            type="range"
                            min="1"
                            max="10"
                            class="w-full accent-[#00ff41] h-2 bg-[#00ff41]/20 rounded-lg appearance-none cursor-pointer"
                        />
                        <div class="flex justify-between text-[10px] text-[#00ff41]/40 font-mono mt-1">
                            <span>1</span>
                            <span>10</span>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="createForm.processing"
                        class="w-full py-3 font-bold text-lg tracking-wider bg-[#00ff41]/20 border border-[#00ff41] text-[#00ff41] hover:bg-[#00ff41]/30 transition-all disabled:opacity-40"
                        style="clip-path: polygon(10px 0, 100% 0, calc(100% - 10px) 100%, 0 100%);"
                    >
                        {{ t('create_room') }}
                    </button>
                </form>
            </div>
        </transition>

        <!-- Join Room Panel -->
        <transition name="slide">
            <div
                v-if="activePanel === 'join'"
                class="w-full max-w-md border border-[#00ff41]/30 bg-[#001200]/80 p-6 mb-6"
                style="clip-path: polygon(0 0, 100% 0, 100% calc(100% - 12px), calc(100% - 12px) 100%, 0 100%);"
            >
                <form @submit.prevent="submitJoin" class="space-y-5">
                    <!-- Room Code -->
                    <div>
                        <label class="block text-xs font-bold tracking-wider mb-1 text-[#00ff41]/70 uppercase">
                            {{ t('room_code') }}
                        </label>
                        <input
                            v-model="joinForm.code"
                            type="text"
                            maxlength="6"
                            required
                            class="w-full bg-[#000a00] border border-[#00ff41]/30 px-4 py-2 text-[#33ff66] font-mono text-center text-2xl tracking-[0.5em] uppercase focus:border-[#00ff41] focus:outline-none focus:ring-1 focus:ring-[#00ff41]/50 transition-colors"
                            placeholder="------"
                        />
                    </div>

                    <!-- Nickname -->
                    <div>
                        <label class="block text-xs font-bold tracking-wider mb-1 text-[#00ff41]/70 uppercase">
                            {{ t('nickname') }}
                        </label>
                        <input
                            v-model="joinForm.nickname"
                            type="text"
                            maxlength="20"
                            required
                            class="w-full bg-[#000a00] border border-[#00ff41]/30 px-4 py-2 text-[#33ff66] font-mono focus:border-[#00ff41] focus:outline-none focus:ring-1 focus:ring-[#00ff41]/50 transition-colors"
                            :placeholder="t('nickname')"
                        />
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="joinForm.processing"
                        class="w-full py-3 font-bold text-lg tracking-wider bg-[#00ff41]/20 border border-[#00ff41] text-[#00ff41] hover:bg-[#00ff41]/30 transition-all disabled:opacity-40"
                        style="clip-path: polygon(10px 0, 100% 0, calc(100% - 10px) 100%, 0 100%);"
                    >
                        {{ t('join_room') }}
                    </button>
                </form>
            </div>
        </transition>

        <!-- Public Rooms -->
        <div class="w-full max-w-md mt-4">
            <h2 class="text-sm font-bold tracking-[0.3em] text-[#00ff41]/60 uppercase mb-3 flex items-center gap-2">
                <!-- List SVG -->
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="8" y1="6" x2="21" y2="6" />
                    <line x1="8" y1="12" x2="21" y2="12" />
                    <line x1="8" y1="18" x2="21" y2="18" />
                    <line x1="3" y1="6" x2="3.01" y2="6" />
                    <line x1="3" y1="12" x2="3.01" y2="12" />
                    <line x1="3" y1="18" x2="3.01" y2="18" />
                </svg>
                {{ t('public_rooms') }}
            </h2>

            <div v-if="localRooms.length === 0" class="text-center text-[#00ff41]/30 text-sm py-8 font-mono">
                {{ t('no_rooms') }}
            </div>

            <div v-else class="space-y-2">
                <button
                    v-for="room in localRooms"
                    :key="room.id"
                    @click="activePanel = 'join'; joinForm.code = room.code"
                    class="w-full flex items-center justify-between bg-[#001200]/60 border border-[#00ff41]/20 px-4 py-3 hover:border-[#00ff41]/50 transition-colors"
                    style="clip-path: polygon(6px 0, 100% 0, calc(100% - 6px) 100%, 0 100%);"
                >
                    <span class="font-mono tracking-wider text-[#00ff41]">{{ room.code }}</span>
                    <span class="text-xs text-[#00ff41]/50">
                        {{ room.players_count || 0 }}/{{ room.max_players || 6 }}
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.slide-enter-active,
.slide-leave-active {
    transition: all 0.3s ease;
}
.slide-enter-from,
.slide-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
