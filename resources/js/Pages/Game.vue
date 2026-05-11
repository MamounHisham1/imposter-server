<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
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
    round: Object,
    hints: {
        type: Array,
        default: () => [],
    },
    currentTurn: {
        type: Object,
        default: null,
    },
});

const localHints = ref([...(props.hints || [])]);
const localCurrentTurn = ref(props.currentTurn);
const localRound = ref(props.round);
const hintInput = ref('');

const isMyTurn = computed(() => {
    return localCurrentTurn.value && localCurrentTurn.value.player_id === props.player.id;
});

const wordLabel = computed(() => {
    return props.player.is_imposter ? t('your_hint') : t('your_word');
});

const wordValue = computed(() => {
    if (props.player.is_imposter) {
        return localRound.value?.imposter_hint || '???';
    }
    return localRound.value?.word || '';
});

function submitHint() {
    if (!hintInput.value.trim()) return;

    router.post(
        '/game/' + props.room.code + '/hint',
        { content: hintInput.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                hintInput.value = '';
            },
        }
    );
}

// Echo listeners
onMounted(() => {
    if (window.Echo) {
        window.Echo.channel('room.' + props.room.id)
            .listen('.game.event', (e) => {
                switch (e.type) {
                    case 'hint_submitted':
                        if (e.hints) {
                            localHints.value = e.hints;
                        }
                        break;
                    case 'round_complete':
                        if (e.hints) localHints.value = e.hints;
                        break;
                    case 'voting_started':
                        router.visit('/game/' + props.room.code + '/vote');
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
        <div class="max-w-lg mx-auto space-y-5">
            <!-- Round Badge -->
            <div class="flex items-center justify-center gap-3">
                <div
                    class="px-4 py-1 text-xs font-bold tracking-[0.3em] border border-[#00ff41]/40 bg-[#00ff41]/10 text-[#00ff41]"
                    style="clip-path: polygon(8px 0, 100% 0, calc(100% - 8px) 100%, 0 100%);"
                >
                    ROUND {{ round?.number || 1 }} / {{ room.rounds_per_game }}
                </div>
            </div>

            <!-- Timer placeholder -->
            <div class="flex justify-center">
                <div
                    class="w-20 h-20 rounded-full border-2 border-[#00ff41]/30 flex items-center justify-center"
                    style="box-shadow: 0 0 15px rgba(0, 255, 65, 0.1);"
                >
                    <!-- Clock SVG -->
                    <svg class="w-8 h-8 text-[#00ff41]/50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="12" cy="12" r="10" />
                        <polyline points="12 6 12 12 16 14" stroke-linecap="round" />
                    </svg>
                </div>
            </div>

            <!-- Word Card -->
            <div
                class="border border-[#00ff41]/30 bg-[#001200]/80 p-6 text-center"
                style="clip-path: polygon(0 0, 100% 0, 100% calc(100% - 10px), calc(100% - 10px) 100%, 0 100%);"
            >
                <p class="text-xs tracking-[0.3em] text-[#00ff41]/50 uppercase mb-2">
                    {{ wordLabel }}
                </p>
                <p
                    class="text-2xl sm:text-3xl font-bold font-mono tracking-wider"
                    :class="player.is_imposter ? 'text-[#ff3333]' : 'text-[#00ff41]'"
                    style="text-shadow: 0 0 20px rgba(0, 255, 65, 0.3);"
                >
                    {{ wordValue }}
                </p>
                <p v-if="player.is_imposter" class="text-[10px] text-[#ff3333]/50 mt-2 tracking-wider uppercase">
                    (You are the imposter)
                </p>
            </div>

            <!-- Player List with turn indicators -->
            <div class="border border-[#00ff41]/15 bg-[#001200]/30 p-3">
                <div class="flex flex-wrap gap-2 justify-center">
                    <div
                        v-for="p in players"
                        :key="p.id"
                        class="flex flex-col items-center gap-1"
                    >
                        <div
                            class="w-10 h-10 flex items-center justify-center text-xs font-bold transition-all"
                            :class="[
                                localCurrentTurn && localCurrentTurn.player_id === p.id
                                    ? 'bg-[#00ff41]/30 text-[#00ff41] scale-110'
                                    : p.id === player.id
                                        ? 'bg-[#00ff41]/15 text-[#33ff66]'
                                        : 'bg-[#00ff41]/5 text-[#00ff41]/30',
                            ]"
                            style="clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);"
                        >
                            {{ p.nickname.charAt(0).toUpperCase() }}
                        </div>
                        <span class="text-[10px] font-mono" :class="localCurrentTurn && localCurrentTurn.player_id === p.id ? 'text-[#00ff41]' : 'text-[#00ff41]/30'">
                            {{ p.nickname }}
                        </span>
                        <!-- Active turn indicator -->
                        <div
                            v-if="localCurrentTurn && localCurrentTurn.player_id === p.id"
                            class="w-1.5 h-1.5 rounded-full bg-[#00ff41] animate-pulse"
                        ></div>
                    </div>
                </div>
            </div>

            <!-- Hints Area -->
            <div class="border border-[#00ff41]/20 bg-[#001200]/50 p-4">
                <h3 class="text-xs tracking-[0.3em] text-[#00ff41]/50 uppercase mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
                    </svg>
                    Hints
                </h3>
                <div class="max-h-48 overflow-y-auto space-y-2 pr-1 scrollbar-thin">
                    <div
                        v-for="(hint, idx) in localHints"
                        :key="idx"
                        class="flex items-start gap-3 bg-[#000a00]/60 px-3 py-2 border-l-2 border-[#00ff41]/30"
                    >
                        <span class="text-xs text-[#00ff41]/40 font-mono shrink-0">
                            {{ hint.player_nickname || hint.nickname || '???' }}
                        </span>
                        <span class="text-sm text-[#33ff66]">{{ hint.content || hint.hint }}</span>
                    </div>
                    <div v-if="localHints.length === 0" class="text-center text-[#00ff41]/20 text-xs py-4 font-mono">
                        No hints yet...
                    </div>
                </div>
            </div>

            <!-- Input Area -->
            <div class="flex gap-2">
                <input
                    v-model="hintInput"
                    type="text"
                    maxlength="100"
                    :disabled="!isMyTurn"
                    :placeholder="isMyTurn ? t('type_hint') : 'Wait for your turn...'"
                    class="flex-1 bg-[#000a00] border border-[#00ff41]/30 px-4 py-3 text-[#33ff66] font-mono text-sm focus:border-[#00ff41] focus:outline-none focus:ring-1 focus:ring-[#00ff41]/50 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                    @keyup.enter="submitHint"
                />
                <button
                    @click="submitHint"
                    :disabled="!isMyTurn || !hintInput.trim()"
                    class="px-5 py-3 bg-[#00ff41]/20 border border-[#00ff41] text-[#00ff41] font-bold tracking-wider hover:bg-[#00ff41]/30 transition-all disabled:opacity-30 disabled:cursor-not-allowed"
                    style="clip-path: polygon(6px 0, 100% 0, calc(100% - 6px) 100%, 0 100%);"
                >
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13" />
                        <polygon points="22 2 15 22 11 13 2 9 22 2" />
                    </svg>
                </button>
            </div>
        </div>
    </GameLayout>
</template>

<style scoped>
.scrollbar-thin::-webkit-scrollbar {
    width: 4px;
}
.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
    background: #00ff4133;
    border-radius: 2px;
}
</style>
