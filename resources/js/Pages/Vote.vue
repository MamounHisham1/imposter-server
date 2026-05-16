<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useToast } from '../Composables/useToast';
import GameLayout from '../layouts/GameLayout.vue';
import AvatarDisplay from '../Components/AvatarDisplay.vue';

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
    voting_started_at: {
        type: String,
        default: null,
    },
});

const selectedPlayerId = ref(null);
const hasVoted = ref(false);
const alertMessage = ref('');

// Voting timer (30s)
const VOTE_TIMER_SECONDS = 30;
const voteTimeLeft = ref(VOTE_TIMER_SECONDS);
let voteTimerInterval = null;

function updateVoteTimer() {
    if (!props.voting_started_at) return;
    const startedAt = new Date(props.voting_started_at);
    const elapsed = (Date.now() - startedAt.getTime()) / 1000;
    voteTimeLeft.value = Math.max(0, Math.ceil(VOTE_TIMER_SECONDS - elapsed));

    if (voteTimeLeft.value <= 0) {
        clearInterval(voteTimerInterval);
        voteTimerInterval = null;
        // Auto-timeout: trigger resolution
        router.post('/game/' + props.room.code + '/timeout-vote', {
            room_id: props.room.id,
        }, {
            preserveScroll: true,
            onError: () => {},
        });
    }
}

function startVoteTimer() {
    if (voteTimerInterval) clearInterval(voteTimerInterval);
    if (!props.voting_started_at) return;
    updateVoteTimer();
    voteTimerInterval = setInterval(updateVoteTimer, 1000);
}

function selectPlayer(playerId) {
    if (hasVoted.value) return;
    selectedPlayerId.value = selectedPlayerId.value === playerId ? null : playerId;
}

function submitVote() {
    if (!selectedPlayerId.value || hasVoted.value) return;

    hasVoted.value = true;

    router.post(
        '/game/' + props.room.code + '/vote',
        { target_id: selectedPlayerId.value, player_id: props.player.id },
        {
            preserveScroll: true,
            onError: (errors) => {
                const msg = Object.values(errors)[0];
                if (msg) toastError(msg);
            },
        }
    );
}

// Start vote timer on load
startVoteTimer();

// Echo listeners
onMounted(() => {
    if (window.Echo) {
        window.Echo.channel('room.' + props.room.id)
            .listen('.game.event', (e) => {
                switch (e.type) {
                    case 'room_deleted':
                        router.visit('/');
                        break;
                    case 'vote_submitted':
                        break;
                    case 'game_over':
                        router.visit('/game/' + props.room.code + '/result');
                        break;
                    case 'round_result':
                        router.visit('/game/' + props.room.code + '/result');
                        break;
                    case 'imposter_fled':
                        alertMessage.value = t('imposter_fled');
                        setTimeout(() => {
                            router.visit('/game/' + props.room.code + '/result');
                        }, 2000);
                        break;
                    case 'game_aborted':
                        alertMessage.value = t('game_aborted');
                        setTimeout(() => {
                            router.visit('/');
                        }, 2000);
                        break;
                    case 'player_left':
                        // Remove the departed player from selectable options
                        if (e.player_id && selectedPlayerId.value === e.player_id) {
                            selectedPlayerId.value = null;
                        }
                        break;
                }
            });
    }
});

onUnmounted(() => {
    if (voteTimerInterval) clearInterval(voteTimerInterval);
    if (window.Echo) {
        window.Echo.leaveChannel('room.' + props.room.id);
    }
});
</script>

<template>
    <GameLayout :room-code="room.code" :active-game="true">
        <Toast />
        <div v-if="alertMessage" class="fixed top-0 left-0 right-0 z-50 flex justify-center p-4">
            <div class="bg-[#8b2500] text-[#e8dcc4] text-2xl md:text-3xl px-8 py-4 border-4 border-[#4a1500] shadow-lg wanted-text animate-bounce">
                {{ alertMessage }}
            </div>
        </div>
        <div class="min-h-screen flex items-center justify-center p-2 md:p-4">
            <div class="wood-panel max-w-4xl w-full p-4 md:p-10 relative">
                <!-- Nails -->
                <div class="absolute top-2 left-2 md:top-4 md:left-4 w-3 h-3 md:w-4 md:h-4 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>
                <div class="absolute top-2 right-2 md:top-4 md:right-4 w-3 h-3 md:w-4 md:h-4 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>
                <div class="absolute bottom-2 left-2 md:bottom-4 md:left-4 w-3 h-3 md:w-4 md:h-4 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>
                <div class="absolute bottom-2 right-2 md:bottom-4 md:right-4 w-3 h-3 md:w-4 md:h-4 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>

                <div class="wanted-poster p-4 md:p-10 md:transform md:rotate-1">
                    <header class="text-center border-b-2 md:border-b-4 border-double border-[#8b4513] pb-4 md:pb-6 mb-6 md:mb-8">
                        <h2 class="text-xl md:text-3xl tracking-widest mb-1 md:mb-2 text-[#8b4513]">{{ t('round') }} {{ round?.round_number || 1 }}</h2>
                        <h1 class="text-5xl md:text-7xl wanted-text uppercase">{{ t('vote_now') }}</h1>
                        <p class="mt-2 md:mt-4 text-base md:text-2xl text-gray-700">{{ t('vote_instruction') }}</p>
                        <div v-if="voteTimeLeft <= 15" class="mt-3 text-3xl md:text-5xl font-bold" :class="voteTimeLeft <= 5 ? 'text-red-600 animate-pulse' : 'text-[#8b2500]'">
                            {{ voteTimeLeft }}s
                        </div>
                    </header>

                    <!-- Hints Review -->
                    <div class="mb-8 border border-dashed border-[#8b4513] p-4 bg-[#8b4513]/10">
                        <h3 class="text-2xl wanted-text mb-4 text-center">{{ t('hints') }}</h3>
                        <div class="max-h-40 overflow-y-auto space-y-2 pr-2 scrollbar-western">
                            <div v-for="(hint, idx) in hints" :key="idx" class="flex gap-2">
                                <span class="font-bold text-[#8b2500] min-w-[80px]">{{ hint.player_nickname || hint.nickname }}:</span>
                                <span class="text-[#4a2511]">{{ hint.content || hint.hint }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Player Grid -->
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <button v-for="p in players" :key="p.id"
                            @click="selectPlayer(p.id)"
                            :disabled="p.id === player.id || hasVoted"
                            class="relative p-4 border-2 shadow transition-all duration-200 flex flex-col items-center gap-2"
                            :class="[
                                p.id === player.id ? 'bg-[#d3bfa1]/50 border-dashed border-[#8b4513]/50 opacity-60 cursor-not-allowed' :
                                selectedPlayerId === p.id ? 'bg-[#8b2500] text-[#e8dcc4] border-[#4a1500] scale-105 z-10' :
                                'bg-[#d3bfa1] text-[#4a2511] border-[#8b4513] hover:bg-[#c4af8e]'
                            ]"
                            :style="`transform: rotate(${p.id % 2 === 0 ? '1deg' : '-1deg'});`"
                        >
                            <AvatarDisplay :avatar="p.avatar" :size="80" />
                            <span class="text-lg md:text-2xl font-bold truncate max-w-full">{{ p.nickname }}</span>
                            <span v-if="p.id === player.id" class="text-sm opacity-70">({{ t('you') }})</span>

                            <!-- Crosshair / Stamp if selected -->
                            <div v-if="selectedPlayerId === p.id" class="absolute inset-0 flex items-center justify-center opacity-30 pointer-events-none">
                                <svg class="w-16 h-16 text-[#e8dcc4]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="22" y1="12" x2="18" y2="12" />
                                    <line x1="6" y1="12" x2="2" y2="12" />
                                    <line x1="12" y1="6" x2="12" y2="2" />
                                    <line x1="12" y1="22" x2="12" y2="18" />
                                </svg>
                            </div>
                        </button>
                    </div>

                    <div class="mt-8 pt-6 border-t border-dashed border-[#8b4513]">
                        <button @click="submitVote" :disabled="!selectedPlayerId || hasVoted" class="western-btn text-2xl md:text-4xl px-6 py-4 w-full disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ hasVoted ? t('vote_submitted') : t('submit_vote') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </GameLayout>
</template>

<style scoped>
.wood-panel { background-color: #8b5a2b; border: 4px solid #5c3a21; box-shadow: inset 0 0 10px rgba(0,0,0,0.5), 0 5px 10px rgba(0,0,0,0.8); }
@media (min-width: 768px) { .wood-panel { border-width: 8px; box-shadow: inset 0 0 20px rgba(0,0,0,0.5), 0 10px 20px rgba(0,0,0,0.8); } }
.wanted-poster { background-color: #e8dcc4; border: 2px solid #b8a07e; box-shadow: inset 0 0 30px rgba(139, 69, 19, 0.2), 0 3px 10px rgba(0,0,0,0.5); background-image: radial-gradient(circle, transparent 20%, #e8dcc4 20%, #e8dcc4 80%, transparent 80%, transparent), radial-gradient(circle, transparent 20%, #e8dcc4 20%, #e8dcc4 80%, transparent 80%, transparent) 25px 25px; background-size: 50px 50px; }
.wanted-text { color: #4a2511; text-shadow: 1px 1px 0px rgba(255,255,255,0.8); }
.western-btn { background-color: #8b2500; color: #e8dcc4; border: 3px solid #4a1500; box-shadow: 2px 2px 0px #3a1000; transition: all 0.1s; cursor: pointer; }
@media (min-width: 768px) { .western-btn { border-width: 4px; box-shadow: 3px 3px 0px #3a1000; } }
.western-btn:active:not(:disabled) { box-shadow: 0px 0px 0px #3a1000; transform: translate(2px, 2px); }

.scrollbar-western::-webkit-scrollbar { width: 6px; }
@media (min-width: 768px) { .scrollbar-western::-webkit-scrollbar { width: 8px; } }
.scrollbar-western::-webkit-scrollbar-track { background: transparent; }
.scrollbar-western::-webkit-scrollbar-thumb { background: #8b4513; border-radius: 4px; }
</style>
