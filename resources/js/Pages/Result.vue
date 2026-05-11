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
    imposter: Object,
    winner: {
        type: String,
        default: 'crew',
    },
    word: {
        type: String,
        default: '',
    },
    imposter_hint: {
        type: String,
        default: '',
    },
    hints: {
        type: Array,
        default: () => [],
    },
    vote_tally: {
        type: Array,
        default: () => [],
    },
    imposter_caught: {
        type: Boolean,
        default: false,
    },
    current_round: Object,
    is_game_over: {
        type: Boolean,
        default: false,
    },
});

const revealPhase = ref(false);
const showScores = ref(false);
const isAdvancing = ref(false);

const isImposterWin = computed(() => props.winner === 'imposter');
const isTie = computed(() => props.winner === 'tie');
const isCreator = computed(() => props.player?.id === props.room?.creator_id);

const winnerLabel = computed(() => {
    if (isTie.value) return t('tie');
    return isImposterWin.value ? t('imposter_wins') : t('crew_wins');
});

onMounted(() => {
    setTimeout(() => {
        revealPhase.value = true;
    }, 800);
    setTimeout(() => {
        showScores.value = true;
    }, 2000);
});

// Echo listeners
onMounted(() => {
    if (window.Echo) {
        window.Echo.channel('room.' + props.room?.id)
            .listen('.game.event', (e) => {
                switch (e.type) {
                    case 'next_round':
                        router.visit('/game/' + props.room.code);
                        break;
                    case 'game_over':
                        router.visit('/game/' + props.room.code + '/result');
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

function nextRound() {
    if (isAdvancing.value) return;
    isAdvancing.value = true;
    router.post('/game/' + props.room.code + '/next-round-result', {}, {
        preserveScroll: true,
        onError: () => { isAdvancing.value = false; },
    });
}

function playAgain() {
    router.post('/room', {
        nickname: props.player?.nickname,
        is_public: props.room?.is_public,
        max_players: props.room?.max_players,
        rounds_per_game: props.room?.rounds_per_game,
    });
}

function backToLobby() {
    router.visit('/');
}
</script>

<template>
    <GameLayout :room-code="room?.code">
        <Toast />
        <div class="max-w-lg mx-auto space-y-6 py-4">
            <!-- Round number badge (non-final rounds) -->
            <div v-if="!is_game_over && current_round" class="text-center">
                <div
                    class="inline-block px-4 py-1 text-xs font-bold tracking-[0.3em] border bg-[#00ff41]/5 text-[#00ff41]/60"
                    :class="isImposterWin ? 'border-[#ff3333]/30' : isTie ? 'border-[#ffaa00]/30' : 'border-[#00ff41]/30'"
                    style="clip-path: polygon(8px 0, 100% 0, calc(100% - 8px) 100%, 0 100%);"
                >
                    {{ t('round') }} {{ current_round.round_number }}
                </div>
            </div>

            <!-- Winner Banner -->
            <div class="text-center py-8">
                <transition name="fade">
                    <div v-if="revealPhase">
                        <h1
                            class="text-4xl sm:text-6xl font-extrabold tracking-[0.3em] mb-4"
                            :class="isImposterWin ? 'text-[#ff3333]' : isTie ? 'text-[#ffaa00]' : 'text-[#00ff41]'"
                            :style="isImposterWin
                                ? 'text-shadow: 0 0 30px rgba(255, 51, 51, 0.5), 0 0 60px rgba(255, 51, 51, 0.2);'
                                : isTie
                                    ? 'text-shadow: 0 0 30px rgba(255, 170, 0, 0.5), 0 0 60px rgba(255, 170, 0, 0.2);'
                                    : 'text-shadow: 0 0 30px rgba(0, 255, 65, 0.5), 0 0 60px rgba(0, 255, 65, 0.2);'"
                        >
                            {{ winnerLabel }}
                        </h1>

                        <div
                            class="w-32 h-[2px] mx-auto mb-6"
                            :class="isImposterWin ? 'bg-gradient-to-r from-transparent via-[#ff3333] to-transparent' : isTie ? 'bg-gradient-to-r from-transparent via-[#ffaa00] to-transparent' : 'bg-gradient-to-r from-transparent via-[#00ff41] to-transparent'"
                        ></div>

                        <!-- Imposter reveal -->
                        <div class="space-y-2">
                            <p class="text-xs tracking-[0.3em] text-[#00ff41]/50 uppercase">
                                {{ t('the_imposter_was') }}
                            </p>
                            <div class="flex items-center justify-center gap-3">
                                <div
                                    class="w-16 h-16 flex items-center justify-center text-2xl font-bold bg-[#ff3333]/20 text-[#ff3333]"
                                    style="clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);"
                                >
                                    {{ imposter?.nickname?.charAt(0).toUpperCase() || '?' }}
                                </div>
                            </div>
                            <p class="text-xl font-mono font-bold text-[#ff3333]">
                                {{ imposter?.nickname || '???' }}
                            </p>

                            <svg class="w-8 h-8 mx-auto text-[#ff3333]/60 mt-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.87-3.13-7-7-7z" stroke-linecap="round" stroke-linejoin="round" />
                                <line x1="9" y1="21" x2="15" y2="21" stroke-linecap="round" />
                                <circle cx="10" cy="10" r="1" fill="currentColor" stroke="none" />
                                <circle cx="14" cy="10" r="1" fill="currentColor" stroke="none" />
                            </svg>
                        </div>
                    </div>
                </transition>

                <div v-if="!revealPhase" class="space-y-4">
                    <div class="w-16 h-16 mx-auto rounded-full border-2 border-[#00ff41]/30 border-t-[#00ff41] animate-spin"></div>
                    <p class="text-sm text-[#00ff41]/40 font-mono tracking-wider">{{ t('revealing') }}</p>
                </div>
            </div>

            <!-- Word & Hint reveal -->
            <transition name="fade">
                <div v-if="showScores" class="grid grid-cols-2 gap-3">
                    <div class="border border-[#00ff41]/20 bg-[#001200]/50 p-3 text-center">
                        <p class="text-[10px] tracking-[0.3em] text-[#00ff41]/40 uppercase mb-1">{{ t('the_word') }}</p>
                        <p class="text-lg font-mono font-bold text-[#33ff66]">{{ word }}</p>
                    </div>
                    <div class="border border-[#ff3333]/20 bg-[#1a0000]/50 p-3 text-center">
                        <p class="text-[10px] tracking-[0.3em] text-[#ff3333]/40 uppercase mb-1">{{ t('imposter_hint') }}</p>
                        <p class="text-lg font-mono font-bold text-[#ff6666]">{{ imposter_hint }}</p>
                    </div>
                </div>
            </transition>

            <!-- Vote Tally -->
            <transition name="fade">
                <div v-if="showScores && vote_tally && vote_tally.length > 0" class="border border-[#00ff41]/20 bg-[#001200]/50 p-4">
                    <h3 class="text-xs tracking-[0.3em] text-[#00ff41]/50 uppercase mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 9V5a3 3 0 00-3-3l-4 9v11h11.28a2 2 0 002-1.7l1.38-9a2 2 0 00-2-2.3H14z" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M7 22H4a2 2 0 01-2-2v-7a2 2 0 012-2h3" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        {{ t('vote_tally') }}
                    </h3>
                    <div class="space-y-2">
                        <div
                            v-for="entry in [...vote_tally].sort((a, b) => b.votes - a.votes)"
                            :key="entry.player?.id"
                            class="flex items-center justify-between bg-[#000a00]/60 px-4 py-2 border-l-2"
                            :class="entry.player?.id === imposter?.id ? 'border-[#ff3333]/50' : 'border-[#00ff41]/20'"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-6 h-6 flex items-center justify-center text-[10px] font-bold"
                                    :class="entry.player?.id === imposter?.id ? 'bg-[#ff3333]/15 text-[#ff3333]' : 'bg-[#00ff41]/10 text-[#00ff41]'"
                                    style="clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);"
                                >
                                    {{ entry.player?.nickname?.charAt(0).toUpperCase() }}
                                </div>
                                <span class="font-mono text-sm" :class="entry.player?.id === imposter?.id ? 'text-[#ff6666]' : 'text-[#00ff41]/60'">
                                    {{ entry.player?.nickname }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-[#00ff41]">{{ entry.votes }}</span>
                                <svg v-if="entry.player?.id === imposter?.id && imposter_caught" class="w-4 h-4 text-[#ff3333]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- Scores -->
            <transition name="fade">
                <div v-if="showScores" class="border border-[#00ff41]/20 bg-[#001200]/50 p-4">
                    <h3 class="text-xs tracking-[0.3em] text-[#00ff41]/50 uppercase mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" stroke-linejoin="round" />
                        </svg>
                        {{ t('scores') }}
                    </h3>
                    <div class="space-y-2">
                        <div
                            v-for="(p, idx) in [...players].sort((a, b) => (b.score || 0) - (a.score || 0))"
                            :key="p.id"
                            class="flex items-center justify-between bg-[#000a00]/60 px-4 py-2 border-l-2"
                            :class="p.id === imposter?.id ? 'border-[#ff3333]/50' : 'border-[#00ff41]/30'"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-[#00ff41]/30 font-mono w-4">{{ idx + 1 }}.</span>
                                <div
                                    class="w-7 h-7 flex items-center justify-center text-xs font-bold"
                                    :class="p.id === imposter?.id ? 'bg-[#ff3333]/15 text-[#ff3333]' : 'bg-[#00ff41]/10 text-[#00ff41]'"
                                    style="clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);"
                                >
                                    {{ p.nickname.charAt(0).toUpperCase() }}
                                </div>
                                <span class="font-mono text-sm" :class="p.id === player?.id ? 'text-[#33ff66]' : 'text-[#00ff41]/60'">
                                    {{ p.nickname }}
                                    <span v-if="p.id === imposter?.id" class="text-[#ff3333]/50 text-[10px] ml-1">{{ t('imposter') }}</span>
                                    <span v-if="p.id === player?.id" class="text-[#00ff41]/30 text-[10px] ml-1">{{ t('you') }}</span>
                                </span>
                            </div>
                            <span class="font-mono font-bold text-[#00ff41]">{{ p.score || 0 }}</span>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- Action Buttons -->
            <transition name="fade">
                <div v-if="showScores" class="space-y-3">
                    <!-- Next Round button (creator only, non-final rounds) -->
                    <button
                        v-if="!is_game_over && isCreator"
                        @click="nextRound"
                        :disabled="isAdvancing"
                        class="w-full py-3 font-bold text-lg tracking-[0.2em] border border-[#00ff41] bg-[#00ff41]/15 text-[#00ff41] hover:bg-[#00ff41]/25 transition-all disabled:opacity-30"
                        style="clip-path: polygon(10px 0, 100% 0, calc(100% - 10px) 100%, 0 100%);"
                    >
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="5 3 19 12 5 21 5 3" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            {{ isAdvancing ? '...' : t('next_round') }}
                        </span>
                    </button>

                    <!-- Waiting for creator (non-creator, non-final) -->
                    <div
                        v-if="!is_game_over && !isCreator"
                        class="w-full py-3 font-bold text-lg tracking-[0.2em] border border-[#00ff41]/30 bg-[#00ff41]/5 text-[#00ff41]/40 text-center"
                        style="clip-path: polygon(10px 0, 100% 0, calc(100% - 10px) 100%, 0 100%);"
                    >
                        <span class="flex items-center justify-center gap-2">
                            <div class="w-4 h-4 rounded-full border-2 border-[#00ff41]/30 border-t-[#00ff41]/60 animate-spin"></div>
                            {{ t('waiting_for_host') }}
                        </span>
                    </div>

                    <!-- Play Again (final round only) -->
                    <button
                        v-if="is_game_over"
                        @click="playAgain"
                        class="w-full py-3 font-bold text-lg tracking-[0.2em] border border-[#00ff41] bg-[#00ff41]/15 text-[#00ff41] hover:bg-[#00ff41]/25 transition-all"
                        style="clip-path: polygon(10px 0, 100% 0, calc(100% - 10px) 100%, 0 100%);"
                    >
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="23 4 23 10 17 10" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M20.49 15a9 9 0 11-2.12-9.36L23 10" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            {{ t('play_again') }}
                        </span>
                    </button>

                    <button
                        @click="backToLobby"
                        class="w-full py-3 font-bold tracking-[0.2em] border border-[#00ff41]/30 bg-transparent text-[#00ff41]/60 hover:text-[#00ff41] hover:border-[#00ff41]/50 transition-all"
                        style="clip-path: polygon(8px 0, 100% 0, calc(100% - 8px) 100%, 0 100%);"
                    >
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 12H5" stroke-linecap="round" />
                                <polyline points="12 19 5 12 12 5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            {{ t('back_to_lobby') }}
                        </span>
                    </button>
                </div>
            </transition>
        </div>
    </GameLayout>
</template>

<style scoped>
.fade-enter-active {
    transition: all 0.8s ease;
}
.fade-leave-active {
    transition: all 0.3s ease;
}
.fade-enter-from {
    opacity: 0;
    transform: translateY(20px);
}
.fade-leave-to {
    opacity: 0;
}
</style>
