<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useToast } from '../Composables/useToast';
import GameLayout from '../layouts/GameLayout.vue';

const { t } = useI18n();
const { error: toastError } = useToast();

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
    word: {
        type: String,
        default: null,
    },
    hint_for_imposter: {
        type: String,
        default: null,
    },
    hint_order: {
        type: Array,
        default: () => [],
    },
    current_turn_player_id: {
        type: Number,
        default: null,
    },
    hints_complete: {
        type: Boolean,
        default: false,
    },
});

const localHints = ref([...(props.hints || [])]);
const localHintOrder = ref([...(props.hint_order || [])]);
const localCurrentTurnPlayerId = ref(props.current_turn_player_id || null);
const localHintsComplete = ref(props.hints_complete || false);
const hintInput = ref('');
const localRound = ref(props.round || null);
const localWord = ref(props.word || null);
const localHintForImposter = ref(props.hint_for_imposter || null);

const isCreator = computed(() => {
    return props.player?.id === props.room?.creator_id;
});

const wordLabel = computed(() => {
    return props.player?.is_imposter ? t('your_hint') : t('your_word');
});

const wordValue = computed(() => {
    if (props.player?.is_imposter) {
        return localHintForImposter.value || '???';
    }
    return localWord.value || '';
});

const hasSubmittedHint = computed(() => {
    return localHints.value.some((h) => h.player_id === props.player?.id);
});

const isMyTurn = computed(() => {
    return localCurrentTurnPlayerId.value === props.player?.id;
});

const waitingPlayer = computed(() => {
    if (!localCurrentTurnPlayerId.value) return null;
    return props.players.find((p) => p.id === localCurrentTurnPlayerId.value);
});

// Build ordered player list based on hint_order
const orderedPlayers = computed(() => {
    if (localHintOrder.value.length === 0) return props.players;
    return localHintOrder.value
        .map((id) => props.players.find((p) => p.id === id))
        .filter(Boolean);
});

const turnPosition = computed(() => {
    if (localHintOrder.value.length === 0) return -1;
    const submittedCount = localHints.value.length;
    return submittedCount; // 0-indexed position of current turn
});

function submitHint() {
    if (!hintInput.value.trim() || !isMyTurn.value) return;

    router.post(
        '/game/' + props.room.code + '/hint',
        { content: hintInput.value.trim(), player_id: props.player.id },
        {
            preserveScroll: true,
            onSuccess: () => {
                hintInput.value = '';
            },
        }
    );
}

function goToVoting() {
    router.post('/game/' + props.room.code + '/start-voting', {
        player_id: props.player.id,
    });
}

function continueRound() {
    router.post('/game/' + props.room.code + '/next-round', {
        player_id: props.player.id,
    });
}

onMounted(() => {
    if (window.Echo) {
        window.Echo.channel('room.' + props.room?.id)
            .listen('.game.event', (e) => {
                switch (e.type) {
                    case 'hint_submitted':
                        if (e.hints) {
                            localHints.value = e.hints;
                        }
                        if (e.next_player_id !== undefined) {
                            localCurrentTurnPlayerId.value = e.next_player_id;
                        }
                        if (e.hint_order) {
                            localHintOrder.value = e.hint_order;
                        }
                        break;
                    case 'hints_complete':
                        if (e.hints) localHints.value = e.hints;
                        localHintsComplete.value = true;
                        localCurrentTurnPlayerId.value = null;
                        break;
                    case 'round_complete':
                        if (e.hints) localHints.value = [];
                        if (e.current_round) localRound.value = e.current_round;
                        if (e.word !== undefined) localWord.value = e.word;
                        if (e.hint_for_imposter !== undefined) localHintForImposter.value = e.hint_for_imposter;
                        if (e.current_turn_player_id !== undefined) localCurrentTurnPlayerId.value = e.current_turn_player_id;
                        if (e.hint_order) localHintOrder.value = e.hint_order;
                        hintInput.value = '';
                        localHintsComplete.value = false;
                        localHints.value = [];
                        break;
                    case 'next_round':
                        localHints.value = [];
                        localHintsComplete.value = false;
                        localCurrentTurnPlayerId.value = null;
                        hintInput.value = '';
                        router.visit('/game/' + props.room.code);
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
        window.Echo.leaveChannel('room.' + props.room?.id);
    }
});
</script>

<template>
    <GameLayout :room-code="room?.code">
        <Toast />
        <div class="max-w-lg mx-auto space-y-5">
            <!-- Round badge -->
            <div class="flex items-center justify-center gap-3">
                <div
                    class="px-4 py-1 text-xs font-bold tracking-[0.3em] border border-[#00ff41]/40 bg-[#00ff41]/10 text-[#00ff41]"
                    style="clip-path: polygon(8px 0, 100% 0, calc(100% - 8px) 100%, 0 100%);"
                >
                    {{ t('round') }} {{ localRound?.round_number || round?.round_number || 1 }}
                </div>
            </div>

            <!-- Word display -->
            <div
                class="border border-[#00ff41]/30 bg-[#001200]/80 p-6 text-center"
                style="clip-path: polygon(0 0, 100% 0, 100% calc(100% - 10px), calc(100% - 10px) 100%, 0 100%);"
            >
                <p class="text-xs tracking-[0.3em] text-[#00ff41]/50 uppercase mb-2">
                    {{ wordLabel }}
                </p>
                <p
                    class="text-2xl sm:text-3xl font-bold font-mono tracking-wider"
                    :class="player?.is_imposter ? 'text-[#ff3333]' : 'text-[#00ff41]'"
                    style="text-shadow: 0 0 20px rgba(0, 255, 65, 0.3);"
                >
                    {{ wordValue }}
                </p>
                <p v-if="player?.is_imposter" class="text-[10px] text-[#ff3333]/50 mt-2 tracking-wider uppercase">
                    {{ t('you_are_imposter') }}
                </p>
            </div>

            <!-- Turn order indicator -->
            <div class="border border-[#00ff41]/15 bg-[#001200]/30 p-3">
                <div class="flex flex-wrap gap-2 justify-center">
                    <div
                        v-for="p in orderedPlayers"
                        :key="p.id"
                        class="flex flex-col items-center gap-1 relative"
                    >
                        <!-- Turn order number -->
                        <span class="text-[9px] font-mono text-[#00ff41]/25">
                            {{ orderedPlayers.indexOf(p) + 1 }}
                        </span>
                        <div
                            class="w-10 h-10 flex items-center justify-center text-xs font-bold transition-all duration-300"
                            :class="[
                                localCurrentTurnPlayerId === p.id
                                    ? 'bg-[#00ff41]/30 text-[#00ff41] scale-110 ring-2 ring-[#00ff41]/40'
                                    : localHints.some(h => h.player_id === p.id)
                                        ? 'bg-[#00ff41]/15 text-[#33ff66]'
                                        : p.id === player?.id
                                            ? 'bg-[#00ff41]/15 text-[#33ff66]'
                                            : 'bg-[#00ff41]/5 text-[#00ff41]/30',
                            ]"
                            style="clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);"
                        >
                            {{ p.nickname.charAt(0).toUpperCase() }}
                        </div>
                        <span
                            class="text-[10px] font-mono"
                            :class="localCurrentTurnPlayerId === p.id ? 'text-[#00ff41] font-bold' : 'text-[#00ff41]/30'"
                        >
                            {{ p.nickname }}
                        </span>
                        <!-- Active turn dot -->
                        <div
                            v-if="localCurrentTurnPlayerId === p.id"
                            class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-[#00ff41] animate-pulse"
                        ></div>
                        <!-- Checkmark for submitted -->
                        <svg
                            v-if="localHints.some(h => h.player_id === p.id) && localCurrentTurnPlayerId !== p.id"
                            class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-3 h-3 text-[#00ff41]/50"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="3"
                        >
                            <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Turn status message -->
            <div v-if="!localHintsComplete && !hasSubmittedHint && !isMyTurn" class="text-center py-2">
                <p class="text-xs tracking-[0.2em] text-[#ffaa00]/70 font-mono animate-pulse">
                    {{ waitingPlayer?.nickname || '...' }} {{ t('is_typing_hint') }}
                </p>
            </div>

            <!-- Hints list — shown in order as they're submitted -->
            <div class="border border-[#00ff41]/20 bg-[#001200]/50 p-4">
                <h3 class="text-xs tracking-[0.3em] text-[#00ff41]/50 uppercase mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
                    </svg>
                    {{ t('hints') }}
                    <span class="text-[#00ff41]/30">({{ localHints.length }}/{{ players.length }})</span>
                </h3>
                <div class="max-h-48 overflow-y-auto space-y-2 pr-1 scrollbar-thin">
                    <div
                        v-for="(hint, idx) in localHints"
                        :key="idx"
                        class="flex items-start gap-3 bg-[#000a00]/60 px-3 py-2 border-l-2 transition-all duration-300"
                        :class="idx === localHints.length - 1 ? 'border-[#00ff41]/60' : 'border-[#00ff41]/30'"
                    >
                        <span class="text-xs text-[#00ff41]/40 font-mono shrink-0">
                            {{ hint.player_nickname || hint.nickname || '???' }}
                        </span>
                        <span class="text-sm text-[#33ff66]">{{ hint.content || hint.hint }}</span>
                    </div>
                    <div v-if="localHints.length === 0" class="text-center text-[#00ff41]/20 text-xs py-4 font-mono">
                        {{ t('no_hints_yet') }}
                    </div>
                </div>
            </div>

            <!-- Input: only show when it's your turn and you haven't submitted -->
            <div v-if="!localHintsComplete && isMyTurn && !hasSubmittedHint" class="flex gap-2">
                <input
                    v-model="hintInput"
                    type="text"
                    maxlength="100"
                    :placeholder="t('type_hint')"
                    class="flex-1 bg-[#000a00] border border-[#00ff41]/50 px-4 py-3 text-[#33ff66] font-mono text-sm focus:border-[#00ff41] focus:outline-none focus:ring-1 focus:ring-[#00ff41]/50 transition-colors"
                    autofocus
                    @keyup.enter="submitHint"
                />
                <button
                    @click="submitHint"
                    :disabled="!hintInput.trim()"
                    class="px-5 py-3 bg-[#00ff41]/20 border border-[#00ff41] text-[#00ff41] font-bold tracking-wider hover:bg-[#00ff41]/30 transition-all disabled:opacity-30 disabled:cursor-not-allowed"
                    style="clip-path: polygon(6px 0, 100% 0, calc(100% - 6px) 100%, 0 100%);"
                >
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13" />
                        <polygon points="22 2 15 22 11 13 2 9 22 2" />
                    </svg>
                </button>
            </div>

            <!-- Already submitted (waiting for others) -->
            <div
                v-if="!localHintsComplete && hasSubmittedHint"
                class="text-center py-3 border border-[#00ff41]/20 bg-[#00ff41]/5"
            >
                <p class="text-xs tracking-[0.2em] text-[#00ff41]/50 font-mono">
                    {{ t('hint_submitted') }}
                    <span v-if="waitingPlayer"> — {{ waitingPlayer.nickname }} {{ t('is_typing_hint') }}</span>
                </p>
            </div>

            <!-- All hints submitted: creator controls -->
            <div v-if="localHintsComplete && isCreator" class="flex gap-3">
                <button
                    @click="continueRound"
                    class="flex-1 py-3 bg-[#00ff41]/20 border border-[#00ff41] text-[#00ff41] font-bold text-sm tracking-wider hover:bg-[#00ff41]/30 transition-all"
                    style="clip-path: polygon(6px 0, 100% 0, calc(100% - 6px) 100%, 0 100%);"
                >
                    <span class="flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M20.49 15a9 9 0 11-2.12-9.36L23 10" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        {{ t('continue_hints') }}
                    </span>
                </button>
                <button
                    @click="goToVoting"
                    class="flex-1 py-3 bg-[#ff3333]/20 border border-[#ff3333] text-[#ff3333] font-bold text-sm tracking-wider hover:bg-[#ff3333]/30 transition-all"
                    style="clip-path: polygon(6px 0, 100% 0, calc(100% - 6px) 100%, 0 100%);"
                >
                    <span class="flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 9V5a3 3 0 00-3-3l-4 9v11h11.28a2 2 0 002-1.7l1.38-9a2 2 0 00-2-2.3H14z" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M7 22H4a2 2 0 01-2-2v-7a2 2 0 012-2h3" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        {{ t('start_voting') }}
                    </span>
                </button>
            </div>

            <div v-if="localHintsComplete && !isCreator" class="text-center py-3 border border-[#ffaa00]/30 bg-[#ffaa00]/5">
                <p class="text-xs tracking-[0.2em] text-[#ffaa00]/70 font-mono animate-pulse">
                    {{ t('waiting_for_creator') }}
                </p>
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
