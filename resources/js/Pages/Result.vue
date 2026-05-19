<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useToast } from '../Composables/useToast';
import { useSound } from '../Composables/useSound';
import GameLayout from '../layouts/GameLayout.vue';
import AvatarDisplay from '../Components/AvatarDisplay.vue';

const { t } = useI18n();
const { error: toastError } = useToast();
const { playImposterRevealed, playCrewWins, playImposterWins } = useSound();

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
    word: {
        type: String,
        default: null,
    },
    imposter_hint: {
        type: String,
        default: null,
    },
    vote_tally: {
        type: Array,
        default: () => [],
    },
    hints: {
        type: Array,
        default: () => [],
    },
    votes: {
        type: Array,
        default: () => [],
    },
    barkeep_recap: {
        type: String,
        default: '',
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

const sortedPlayers = computed(() => {
    return [...props.players].sort((a, b) => (b.score || 0) - (a.score || 0));
});

const sortedTally = computed(() => {
    if (!props.vote_tally) return [];
    return [...props.vote_tally].sort((a, b) => (b.votes || 0) - (a.votes || 0));
});

// Build "who voted for whom" map from raw votes
const voteDetails = computed(() => {
    if (!props.votes || props.votes.length === 0) return [];
    return props.votes.filter(v => v.target_id).map(v => {
        const voter = props.players.find(p => p.id === v.voter_id);
        const target = props.players.find(p => p.id === v.target_id);
        return {
            voterNickname: voter?.nickname || '?',
            voterAvatar: voter?.avatar,
            targetNickname: target?.nickname || '?',
            targetAvatar: target?.avatar,
        };
    });
});

onMounted(() => {
    setTimeout(() => {
        revealPhase.value = true;
        playImposterRevealed();
    }, 800);
    setTimeout(() => {
        showScores.value = true;
        if (isImposterWin.value) {
            playImposterWins();
        } else {
            playCrewWins();
        }
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
                    case 'rematch':
                        router.visit('/room/' + props.room.code);
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
    if (!isCreator.value) return;
    isAdvancing.value = true;
    router.post('/game/' + props.room.code + '/rematch', {}, {
        preserveScroll: true,
        onError: () => { isAdvancing.value = false; },
    });
}

function backToLobby() {
    router.visit('/');
}
</script>

<template>
    <Head>
        <meta name="robots" content="noindex, nofollow" head-key="robots" />
    </Head>
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
                                    <AvatarDisplay v-if="imposter?.avatar" :avatar="imposter.avatar" :size="120" :state="imposter_caught ? 'caught' : 'normal'" />
                                    <div class="px-8 py-3 bg-[#8b2500] text-[#e8dcc4] text-4xl md:text-6xl border-4 border-[#4a1500] transform -rotate-2 shadow-lg">
                                        {{ imposter?.nickname || '???' }}
                                    </div>
                                </div>
                            </div>

                            <!-- Newspaper Scoop Card (Old, Burned, and Rusty) -->
                            <div v-if="barkeep_recap" 
                                 class="mt-8 mb-8 max-w-xl mx-auto p-6 md:p-8 rounded shadow-2xl transform rotate-[-0.7deg] relative text-[#1a0f07] font-serif border-4 border-double border-[#2d1b0f]"
                                 style="
                                     background: radial-gradient(circle at 50% 50%, #fcf8f0 20%, #ebdcb9 60%, #a27246 88%, #3e2717 100%);
                                     box-shadow: inset 0 0 25px rgba(62, 39, 23, 0.45), inset 0 0 50px rgba(0, 0, 0, 0.8), 0 10px 25px rgba(0, 0, 0, 0.55);
                                 ">
                                <!-- Newspaper Header -->
                                <div class="border-b-2 border-[#3a2717] pb-1 mb-4 text-center select-none">
                                    <h4 class="text-lg md:text-xl tracking-[0.2em] font-black uppercase text-[#1a1007] mb-1">
                                        {{ room?.language === 'ar' ? 'بريد الصحراء' : 'THE DESERT POST' }}
                                    </h4>
                                    <div class="flex justify-between items-center text-[10px] uppercase font-bold text-[#8c593b] border-t border-[#3a2717] pt-1 px-1">
                                        <span>{{ room?.language === 'ar' ? 'العدد الأول' : 'Vol. 1 - No. 1' }}</span>
                                        <span class="italic text-[9px] font-normal normal-case">{{ room?.language === 'ar' ? '«الحقيقة والذهب والرصاص»' : '“Truth, Gold, & Bullets”' }}</span>
                                        <span>{{ room?.language === 'ar' ? 'الثمن: ٥ سنتات' : 'Price: 5¢' }}</span>
                                    </div>
                                </div>

                                <!-- Scoop Section -->
                                <h3 class="text-sm md:text-base font-extrabold uppercase text-[#8b2500] mb-2 tracking-widest text-center">
                                    {{ t('barkeep_says') }}
                                </h3>

                                <div class="border-t border-b border-dashed border-[#3a2717] py-4 my-2 text-center leading-relaxed text-base md:text-lg italic font-serif px-2">
                                    “ {{ barkeep_recap }} ”
                                </div>
                            </div>
                        </div>
                    </transition>

                    <transition name="fade">
                        <div v-if="showScores" class="space-y-6 md:space-y-8">

                            <!-- Word Reveal -->
                            <div v-if="word" class="text-center py-4 border-b-2 border-double border-[#8b4513]">
                                <p class="text-lg text-[#8b4513] mb-1 uppercase tracking-widest">{{ t('the_word_was') || 'The Secret Word' }}</p>
                                <div class="inline-block px-6 py-2 bg-[#f5e4c3] text-3xl md:text-4xl wanted-text border-2 border-[#8b4513] shadow-md transform -rotate-1 font-black">
                                    {{ word }}
                                </div>
                                <div v-if="imposter_hint" class="mt-2 text-base text-[#8b2500] italic">
                                    {{ t('imposter_hint_label') || 'Imposter hint' }}: "{{ imposter_hint }}"
                                </div>
                            </div>

                            <!-- Hints Submitted -->
                            <div v-if="hints && hints.length > 0" class="py-3 border-b border-dashed border-[#b8a07e]">
                                <p class="text-center text-lg text-[#8b4513] mb-3 uppercase tracking-widest">{{ t('hints_given') || 'Clues Given' }}</p>
                                <div class="flex flex-wrap justify-center gap-3">
                                    <div v-for="hint in hints" :key="hint.id"
                                         class="px-4 py-2 bg-[#d3bfa1]/60 border border-[#b8a07e] rounded text-[#4a2511] text-lg shadow-sm">
                                        <span class="font-semibold">{{ hint.player_nickname }}:</span> "{{ hint.content }}"
                                    </div>
                                </div>
                            </div>

                            <!-- Vote Tally -->
                            <div v-if="sortedTally.length > 0" class="py-3 border-b border-dashed border-[#b8a07e]">
                                <p class="text-center text-lg text-[#8b4513] mb-3 uppercase tracking-widest">{{ t('vote_tally') || 'Vote Tally' }}</p>
                                <div class="space-y-2 max-w-md mx-auto">
                                    <div v-for="(entry, idx) in sortedTally" :key="idx"
                                         class="flex items-center gap-3 px-4 py-2 border"
                                         :class="entry.player?.is_imposter ? 'bg-[#8b2500]/10 border-[#8b2500]' : 'bg-[#e8dcc4] border-[#b8a07e]'">
                                        <AvatarDisplay v-if="entry.player?.avatar" :avatar="entry.player.avatar" :size="36" />
                                        <div v-else class="w-9 h-9 rounded bg-[#b8a07e] flex items-center justify-center text-[#4a2511] text-lg font-bold flex-shrink-0">
                                            {{ entry.player?.nickname?.charAt(0)?.toUpperCase() || '?' }}
                                        </div>
                                        <span class="flex-1 text-[#4a2511] text-lg"
                                              :class="entry.player?.is_imposter ? 'font-bold' : ''">
                                            {{ entry.player?.nickname || '?' }}
                                            <span v-if="entry.player?.is_imposter" class="text-[#8b2500] text-sm ml-1">({{ t('imposter') || 'Imposter' }})</span>
                                        </span>
                                        <span class="text-xl font-bold"
                                              :class="entry.votes >= 2 ? 'text-[#8b2500]' : 'text-[#8b4513]'">
                                            {{ entry.votes }}
                                        </span>
                                        <div class="flex gap-0.5">
                                            <div v-for="n in entry.votes" :key="n" class="w-3 h-3 bg-[#8b2500] rounded-full border border-[#4a1500]"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Who voted for whom -->
                                <div v-if="voteDetails.length > 0" class="mt-4 max-w-md mx-auto">
                                    <p class="text-sm text-[#8b4513] mb-3 uppercase tracking-wider text-center">{{ t('who_voted_for_whom') }}</p>
                                    <div class="space-y-2">
                                        <div v-for="(vd, idx) in voteDetails" :key="idx"
                                             class="flex items-center gap-2 px-3 py-2 bg-[#d3bfa1]/40 border border-[#b8a07e] rounded">
                                            <div class="flex items-center gap-1.5 min-w-0 flex-1 justify-end">
                                                <span class="text-sm font-semibold text-[#4a2511] truncate">{{ vd.voterNickname }}</span>
                                                <AvatarDisplay v-if="vd.voterAvatar" :avatar="vd.voterAvatar" :size="24" />
                                            </div>
                                            <div class="flex-shrink-0 w-7 h-7 rounded-full bg-[#8b2500] flex items-center justify-center">
                                                <svg :class="['w-3.5 h-3.5 text-[#e8dcc4] transition-transform duration-200', room?.language === 'ar' ? 'rotate-180' : '']" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                                </svg>
                                            </div>
                                            <div class="flex items-center gap-1.5 min-w-0 flex-1">
                                                <AvatarDisplay v-if="vd.targetAvatar" :avatar="vd.targetAvatar" :size="24" />
                                                <span class="text-sm font-semibold text-[#8b2500] truncate">{{ vd.targetNickname }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Scoreboard -->
                            <div class="py-3">
                                <p class="text-center text-xl md:text-2xl text-[#8b4513] mb-4 uppercase tracking-widest wanted-text">
                                    {{ is_game_over ? (t('final_scores') || 'Final Scores') : (t('scoreboard') || 'Scoreboard') }}
                                </p>
                                <div class="max-w-lg mx-auto space-y-2">
                                    <div v-for="(p, idx) in sortedPlayers" :key="p.id"
                                         class="flex items-center gap-3 px-4 py-3 border-2 transition-all"
                                         :class="[
                                             idx === 0 ? 'bg-[#8b4513]/15 border-[#8b4513] shadow-md' : 'bg-[#e8dcc4]/80 border-[#b8a07e]',
                                             p.is_imposter ? 'ring-2 ring-[#8b2500] ring-offset-1 ring-offset-[#e8dcc4]' : ''
                                         ]">
                                        <!-- Rank -->
                                        <div class="w-8 h-8 flex items-center justify-center text-xl font-bold rounded-full"
                                             :class="idx === 0 ? 'bg-[#8b4513] text-[#e8dcc4]' : idx === 1 ? 'bg-[#8b6914] text-[#e8dcc4]' : idx === 2 ? 'bg-[#8b2500] text-[#e8dcc4]' : 'bg-[#b8a07e] text-[#4a2511]'">
                                            {{ idx + 1 }}
                                        </div>
                                        <!-- Avatar -->
                                        <AvatarDisplay v-if="p.avatar" :avatar="p.avatar" :size="40" :state="p.is_imposter ? (imposter_caught ? 'caught' : (isImposterWin ? 'celebrating' : 'normal')) : (winner === 'crew' ? 'celebrating' : 'normal')" />
                                        <div v-else class="w-10 h-10 rounded bg-[#b8a07e] flex items-center justify-center text-[#4a2511] text-xl font-bold flex-shrink-0">
                                            {{ p.nickname?.charAt(0)?.toUpperCase() || '?' }}
                                        </div>
                                        <!-- Name -->
                                        <div class="flex-1">
                                            <span class="text-lg font-semibold text-[#4a2511]">{{ p.nickname }}</span>
                                            <span v-if="p.id === player?.id" class="text-sm text-[#8b4513] ml-1">({{ t('you') || 'you' }})</span>
                                            <span v-if="p.is_imposter" class="text-sm text-[#8b2500] ml-1">({{ t('imposter') || 'Imposter' }})</span>
                                        </div>
                                        <!-- Score -->
                                        <div class="text-right">
                                            <span class="text-2xl font-bold text-[#4a2511]">{{ p.score || 0 }}</span>
                                            <span class="text-sm text-[#8b4513] ml-1">{{ t('pts') || 'pts' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

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
                                    <button v-if="isCreator" @click="playAgain" :disabled="isAdvancing" class="western-btn text-2xl md:text-3xl px-6 py-3 flex-1 disabled:opacity-50">
                                        {{ isAdvancing ? '...' : t('play_again') }}
                                    </button>
                                    <div v-else class="text-center text-xl text-[#8b4513] animate-pulse w-full py-3 border-2 border-dashed border-[#8b4513] flex-1">
                                        {{ t('waiting_for_host') }}
                                    </div>
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
