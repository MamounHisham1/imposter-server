<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import GameLayout from '../layouts/GameLayout.vue';

const { t } = useI18n();

const props = defineProps({
    room: Object,
    player: Object,
    players: {
        type: Array,
        default: () => [],
    },
});

const localPlayers = ref([...props.players]);
const localRoom = ref({ ...props.room });

const isCreator = computed(() => {
    return props.player.id === props.room.creator_id;
});

const allReady = computed(() => {
    return (
        localPlayers.value.length >= 3 &&
        localPlayers.value.every((p) => p.is_ready)
    );
});

const readyForm = useForm({});

function toggleReady() {
    router.post('/room/' + props.room.code + '/ready', {}, {
        preserveScroll: true,
        onSuccess: (page) => {
            localPlayers.value = page.props.players || localPlayers.value;
        },
    });
}

function startGame() {
    router.post('/room/' + props.room.code + '/start', {}, {
        preserveScroll: true,
    });
}

const settingsForm = useForm({
    max_players: props.room.max_players,
    rounds_per_game: props.room.rounds_per_game,
});

function updateSettings() {
    settingsForm.put('/room/' + props.room.id + '/settings', {
        preserveScroll: true,
        onSuccess: (page) => {
            if (page.props.room) {
                localRoom.value = page.props.room;
            }
        },
    });
}

// Echo listeners
onMounted(() => {
    if (window.Echo) {
        window.Echo.channel('room.' + props.room.id)
            .listen('.game.event', (e) => {
                switch (e.type) {
                    case 'player_joined':
                        if (e.player && !localPlayers.value.find((p) => p.id === e.player.id)) {
                            localPlayers.value.push(e.player);
                        }
                        if (e.room) localRoom.value = e.room;
                        break;
                    case 'player_ready':
                        if (e.player) {
                            const idx = localPlayers.value.findIndex((p) => p.id === e.player.id);
                            if (idx !== -1) localPlayers.value[idx].is_ready = e.player.is_ready;
                        }
                        break;
                    case 'game_started':
                        router.visit('/game/' + props.room.code);
                        break;
                }
            });
    }
});

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leaveChannel('room.' + props.room.id);
    }
});
</script>

<template>
    <GameLayout :room-code="room.code">
        <div class="max-w-lg mx-auto space-y-6">
            <!-- Room Code Display -->
            <div class="text-center py-4">
                <p class="text-xs tracking-[0.3em] text-[#00ff41]/50 uppercase mb-1">{{ t('room_code') }}</p>
                <p
                    class="text-4xl sm:text-5xl font-mono font-bold tracking-[0.5em] text-[#00ff41]"
                    style="
                        text-shadow: 0 0 20px rgba(0, 255, 65, 0.4), 0 0 40px rgba(0, 255, 65, 0.1);
                    "
                >
                    {{ room.code }}
                </p>
            </div>

            <!-- Player List -->
            <div class="border border-[#00ff41]/20 bg-[#001200]/50 p-4">
                <h3 class="text-xs tracking-[0.3em] text-[#00ff41]/50 uppercase mb-3">
                    {{ t('waiting_for_players') }} ({{ localPlayers.length }})
                </h3>
                <div class="space-y-2">
                    <div
                        v-for="p in localPlayers"
                        :key="p.id"
                        class="flex items-center justify-between bg-[#000a00]/60 border border-[#00ff41]/10 px-4 py-2"
                    >
                        <div class="flex items-center gap-3">
                            <!-- Hexagonal avatar -->
                            <div
                                class="w-8 h-8 flex items-center justify-center text-xs font-bold"
                                :class="p.is_ready ? 'bg-[#00ff41]/20 text-[#00ff41]' : 'bg-[#00ff41]/5 text-[#00ff41]/30'"
                                style="clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);"
                            >
                                {{ p.nickname.charAt(0).toUpperCase() }}
                            </div>
                            <span class="font-mono text-sm" :class="p.is_ready ? 'text-[#33ff66]' : 'text-[#00ff41]/50'">
                                {{ p.nickname }}
                            </span>
                            <span
                                v-if="p.id === room.creator_id"
                                class="text-[10px] tracking-wider text-[#00ff41]/40 border border-[#00ff41]/20 px-1"
                            >
                                HOST
                            </span>
                        </div>
                        <!-- Ready status icon -->
                        <div v-if="p.is_ready" class="text-[#00ff41]">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <div v-else class="text-[#00ff41]/30">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="15" y1="9" x2="9" y2="15" stroke-linecap="round" />
                                <line x1="9" y1="9" x2="15" y2="15" stroke-linecap="round" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Room Settings (creator only) -->
            <div v-if="isCreator" class="border border-[#00ff41]/20 bg-[#001200]/50 p-4">
                <h3 class="text-xs tracking-[0.3em] text-[#00ff41]/50 uppercase mb-3">Settings</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs text-[#00ff41]/60 mb-1">
                            {{ t('max_players') }}: <span class="text-[#00ff41]">{{ settingsForm.max_players }}</span>
                        </label>
                        <input
                            v-model.number="settingsForm.max_players"
                            type="range"
                            min="3"
                            max="10"
                            class="w-full accent-[#00ff41]"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-[#00ff41]/60 mb-1">
                            {{ t('rounds') }}: <span class="text-[#00ff41]">{{ settingsForm.rounds_per_game }}</span>
                        </label>
                        <input
                            v-model.number="settingsForm.rounds_per_game"
                            type="range"
                            min="1"
                            max="10"
                            class="w-full accent-[#00ff41]"
                        />
                    </div>
                    <button
                        @click="updateSettings"
                        class="w-full py-2 text-sm font-bold tracking-wider border border-[#00ff41]/40 text-[#00ff41] hover:bg-[#00ff41]/10 transition-colors"
                        style="clip-path: polygon(6px 0, 100% 0, calc(100% - 6px) 100%, 0 100%);"
                    >
                        Save
                    </button>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="space-y-3">
                <!-- Ready Toggle -->
                <button
                    @click="toggleReady"
                    class="w-full py-3 font-bold text-lg tracking-wider border transition-all"
                    :class="
                        player.is_ready
                            ? 'border-[#ff3333]/60 bg-[#ff3333]/10 text-[#ff3333] hover:bg-[#ff3333]/20'
                            : 'border-[#00ff41] bg-[#00ff41]/15 text-[#00ff41] hover:bg-[#00ff41]/25'
                    "
                    style="clip-path: polygon(8px 0, 100% 0, calc(100% - 8px) 100%, 0 100%);"
                >
                    <span class="flex items-center justify-center gap-2">
                        <svg v-if="player.is_ready" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="15" y1="9" x2="9" y2="15" stroke-linecap="round" />
                            <line x1="9" y1="9" x2="15" y2="15" stroke-linecap="round" />
                        </svg>
                        <svg v-else class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        {{ player.is_ready ? t('not_ready') : t('ready') }}
                    </span>
                </button>

                <!-- Start Game (creator only) -->
                <button
                    v-if="isCreator"
                    @click="startGame"
                    :disabled="!allReady"
                    class="w-full py-4 font-bold text-xl tracking-[0.2em] border border-[#00ff41] bg-[#00ff41]/20 text-[#00ff41] hover:bg-[#00ff41]/30 transition-all disabled:opacity-30 disabled:cursor-not-allowed"
                    style="clip-path: polygon(12px 0, 100% 0, calc(100% - 12px) 100%, 0 100%);"
                >
                    <!-- Play SVG -->
                    <span class="flex items-center justify-center gap-2">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                            <polygon points="5 3 19 12 5 21 5 3" />
                        </svg>
                        {{ t('start_game') }}
                    </span>
                </button>

                <p v-if="isCreator && !allReady" class="text-center text-xs text-[#00ff41]/30 font-mono">
                    {{ t('waiting_for_players') }}
                </p>
            </div>
        </div>
    </GameLayout>
</template>
