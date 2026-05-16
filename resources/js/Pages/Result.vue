<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
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
    imposter: Object,
    winner: {
        type: String,
        default: 'crew',
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
const alertMessage = ref('');

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
                    case 'room_deleted':
                        router.visit('/');
                        break;
                    case 'next_round':
                        router.visit('/game/' + props.room.code);
                        break;
                    case 'game_over':
                        router.visit('/game/' + props.room.code + '/result');
                        break;
                    case 'imposter_fled':
                        alertMessage.value = t('imposter_fled');
                        if (e.is_game_over) {
                            // Reload to show final game-over result
                            setTimeout(() => {
                                router.visit('/game/' + props.room.code + '/result');
                            }, 2000);
                        } else {
                            // Show round result, then allow advancing
                            setTimeout(() => {
                                router.visit('/game/' + props.room.code + '/result');
                            }, 2000);
                        }
                        break;
                    case 'game_aborted':
                        alertMessage.value = t('game_aborted');
                        setTimeout(() => {
                            router.visit('/');
                        }, 2000);
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
    <GameLayout :room-code="room?.code" :active-game="!is_game_over">
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
                    <header class="text-center border-b-2 md:border-b-4 border-double border-[#8b4513] pb-4 md:pb-6 mb-6">
                        <h2 v-if="!is_game_over && current_round" class="text-xl md:text-3xl tracking-widest mb-1 md:mb-2 text-[#8b4513]">{{ t('round') }} {{ current_round.round_number }}</h2>
                        <h2 v-else class="text-xl md:text-3xl tracking-widest mb-1 md:mb-2 text-[#8b4513]">{{ t('game_over') }}</h2>
                    </header>

                    <!-- Reveal Phase Loading -->
                    <div v-if="!revealPhase" class="text-center py-10">
                        <div class="inline-block w-16 h-16 border-4 border-dashed border-[#8b4513] border-t-transparent rounded-full animate-spin"></div>
                        <p class="text-2xl mt-4 text-[#8b4513]">{{ t('revealing') }}</p>
                    </div>

                    <transition name="fade">
                        <div v-if="revealPhase" class="text-center">
                            <!-- Winner Banner -->
                            <div class="py-4 md:py-6 mb-6" :class="isImposterWin ? 'bg-[#8b2500]/10 border-y-4 border-double border-[#8b2500]' : isTie ? 'bg-[#8b4513]/10 border-y-4 border-dashed border-[#8b4513]' : 'bg-[#1b4a1b]/10 border-y-4 border-double border-[#1b4a1b]'">
                                <h1 class="text-5xl md:text-7xl wanted-text uppercase" :class="isImposterWin ? 'text-[#8b2500]' : isTie ? 'text-[#8b4513]' : 'text-[#1b4a1b]'">
                                    {{ winnerLabel }}
                                </h1>
                            </div>

                            <!-- Imposter Reveal -->
                            <div class="mb-8">
                                <p class="text-xl md:text-2xl text-gray-700 mb-4">{{ t('the_imposter_was') }}</p>
                                <div class="flex flex-col items-center gap-3">
                                    <AvatarDisplay v-if="imposter?.avatar" :avatar="imposter.avatar" :size="120" />
                                    <div class="px-8 py-3 bg-[#8b2500] text-[#e8dcc4] text-4xl md:text-6xl border-4 border-[#4a1500] transform -rotate-2 shadow-lg">
                                        {{ imposter?.nickname || '???' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </transition>

                    <transition name="fade">
                        <div v-if="showScores" class="space-y-6 md:space-y-8">

                            <!-- Actions -->
                            <div class="mt-8 pt-6 border-t border-dashed border-[#8b4513] flex flex-col sm:flex-row gap-4">
                                <template v-if="!is_game_over">
                                    <button v-if="isCreator" @click="nextRound" :disabled="isAdvancing" class="western-btn text-2xl md:text-3xl px-6 py-3 w-full disabled:opacity-50">
                                        {{ isAdvancing ? '...' : t('next_round') }}
                                    </button>
                                    <div v-else class="text-center text-xl text-[#8b4513] animate-pulse w-full py-3 border-2 border-dashed border-[#8b4513]">
                                        {{ t('waiting_for_host') }}
                                    </div>
                                </template>
                                
                                <template v-else>
                                    <button @click="playAgain" class="western-btn text-2xl md:text-3xl px-6 py-3 flex-1">
                                        {{ t('play_again') }}
                                    </button>
                                    <button @click="backToLobby" class="western-btn-alt text-2xl md:text-3xl px-6 py-3 flex-1 border-2 border-[#8b4513]">
                                        {{ t('back_to_lobby') }}
                                    </button>
                                </template>
                            </div>

                        </div>
                    </transition>
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
.western-btn-alt { background-color: #d3bfa1; color: #4a2511; border-color: #8b4513; cursor: pointer; }

.fade-enter-active { transition: all 0.8s ease; }
.fade-leave-active { transition: all 0.3s ease; }
.fade-enter-from { opacity: 0; transform: translateY(20px); }
.fade-leave-to { opacity: 0; }
</style>
