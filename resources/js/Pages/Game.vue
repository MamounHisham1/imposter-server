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

const localPlayer = ref({ ...props.player });
const localHints = ref([...(props.hints || [])]);
const localHintOrder = ref([...(props.hint_order || [])]);
const localCurrentTurnPlayerId = ref(props.current_turn_player_id || null);
const localHintsComplete = ref(props.hints_complete || false);
const hintInput = ref('');
const localRound = ref(props.round || null);
const localWord = ref(props.word || null);
const localHintForImposter = ref(props.hint_for_imposter || null);
const alertMessage = ref('');

const isCreator = computed(() => {
    return props.player?.id === props.room?.creator_id;
});

const wordLabel = computed(() => {
    return t('your_word');
});

const wordValue = computed(() => {
    if (localPlayer.value?.is_imposter) {
        return '???';
    }
    return localWord.value || '';
});

const imposterHint = computed(() => {
    if (localPlayer.value?.is_imposter && localHintForImposter.value) {
        return localHintForImposter.value;
    }
    return null;
});

const hasSubmittedHint = computed(() => {
    return localHints.value.some((h) => h.player_id === props.player?.id);
});

const sortedHints = computed(() => {
    return [...localHints.value].reverse();
});

const isMyTurn = computed(() => {
    return localCurrentTurnPlayerId.value === props.player?.id;
});

const waitingPlayer = computed(() => {
    if (!localCurrentTurnPlayerId.value) return null;
    return props.players.find((p) => p.id === localCurrentTurnPlayerId.value);
});

const orderedPlayers = computed(() => {
    if (localHintOrder.value.length === 0) return props.players;
    return localHintOrder.value
        .map((id) => props.players.find((p) => p.id === id))
        .filter(Boolean);
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
            onError: (errors) => {
                const msg = Object.values(errors)[0];
                if (msg) toastError(msg);
            },
        }
    );
}

function goToVoting() {
    router.post('/game/' + props.room.code + '/start-voting', {
        player_id: props.player.id,
    }, {
        onError: (errors) => {
            const msg = Object.values(errors)[0];
            if (msg) toastError(msg);
        },
    });
}

function continueRound() {
    router.post('/game/' + props.room.code + '/next-round', {
        player_id: props.player.id,
    }, {
        onError: (errors) => {
            const msg = Object.values(errors)[0];
            if (msg) toastError(msg);
        },
    });
}

onMounted(() => {
    if (window.Echo) {
        window.Echo.channel('room.' + props.room?.id)
            .listen('.game.event', (e) => {
                switch (e.type) {
                    case 'room_deleted':
                        router.visit('/');
                        break;
                    case 'hint_submitted':
                        if (e.hints) localHints.value = e.hints;
                        if (e.next_player_id !== undefined) localCurrentTurnPlayerId.value = e.next_player_id;
                        if (e.hint_order) localHintOrder.value = e.hint_order;
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
                        if (e.players) {
                            const me = e.players.find((p) => p.id === props.player?.id);
                            if (me) localPlayer.value = { ...localPlayer.value, is_imposter: me.is_imposter };
                        }
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
                    case 'imposter_fled':
                        alertMessage.value = t('imposter_fled');
                        setTimeout(() => {
                            if (e.is_game_over) {
                                router.visit('/game/' + props.room.code + '/result');
                            } else {
                                router.visit('/game/' + props.room.code + '/result');
                            }
                        }, 2000);
                        break;
                    case 'game_aborted':
                        alertMessage.value = t('game_aborted');
                        setTimeout(() => {
                            router.visit('/');
                        }, 2000);
                        break;
                    case 'hint_order_updated':
                        if (e.hint_order) localHintOrder.value = e.hint_order;
                        if (e.current_turn_player_id !== undefined) localCurrentTurnPlayerId.value = e.current_turn_player_id;
                        if (e.hints) localHints.value = e.hints;
                        break;
                    case 'player_left':
                        if (e.room) {
                            // Update hint order to reflect departed player
                            localHintOrder.value = localHintOrder.value.filter(id => id !== e.player_id);
                        }
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
    <GameLayout :room-code="room?.code" :active-game="true">
        <Toast />
        <div v-if="alertMessage" class="fixed top-0 left-0 right-0 z-50 flex justify-center p-4">
            <div class="bg-[#8b2500] text-[#e8dcc4] text-2xl md:text-3xl px-8 py-4 border-4 border-[#4a1500] shadow-lg wanted-text animate-bounce">
                {{ alertMessage }}
            </div>
        </div>
        <div class="min-h-screen flex items-center justify-center p-2 md:p-4">
            <div class="wood-panel max-w-3xl w-full p-4 md:p-12 relative">
                <!-- Nails -->
                <div class="absolute top-2 left-2 md:top-4 md:left-4 w-3 h-3 md:w-4 md:h-4 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>
                <div class="absolute top-2 right-2 md:top-4 md:right-4 w-3 h-3 md:w-4 md:h-4 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>
                <div class="absolute bottom-2 left-2 md:bottom-4 md:left-4 w-3 h-3 md:w-4 md:h-4 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>
                <div class="absolute bottom-2 right-2 md:bottom-4 md:right-4 w-3 h-3 md:w-4 md:h-4 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>

                <div class="wanted-poster p-4 md:p-12 md:transform md:rotate-1">
                    <header class="text-center border-b-2 md:border-b-4 border-double border-[#8b4513] pb-4 md:pb-6 mb-4 md:mb-8">
                        <h2 class="text-xl md:text-3xl tracking-widest text-[#8b4513]">{{ t('round') }} {{ localRound?.round_number || round?.round_number || 1 }}</h2>
                    </header>

                    <!-- The Word Display -->
                    <div class="text-center mb-6 md:mb-10">
                        <div class="text-4xl md:text-6xl wanted-text my-4 md:my-6 py-4 md:py-6" :class="localPlayer?.is_imposter ? 'text-[#8b2500]' : ''">
                            {{ wordValue }}
                        </div>
                        <p v-if="imposterHint" class="text-sm md:text-lg leading-relaxed text-[#8b2500] mt-2">{{ t('imposter_hint') }}: {{ imposterHint }}</p>
                        <p v-if="localPlayer?.is_imposter" class="text-base md:text-2xl leading-relaxed text-[#8b2500] mt-2">{{ t('you_are_imposter') }}</p>
                        <p v-else class="text-base md:text-2xl leading-relaxed max-w-md mx-auto">{{ t('vote_instruction') }}</p>
                    </div>

                    <!-- Turn Order & Submitted Hints -->
                    <div class="mt-8 md:mt-12 pt-6 md:pt-8 border-t-2 border-[#8b4513]">
                        <h3 class="text-2xl md:text-3xl wanted-text mb-4 text-center">{{ t('hints') }}</h3>
                        
                        <!-- List of Hints -->
                        <TransitionGroup v-if="localHints.length > 0" name="hint-slide" tag="div" class="space-y-3 mb-6 max-h-48 overflow-y-auto pr-2 scrollbar-western">
                            <div v-for="hint in sortedHints" :key="hint.id" class="flex items-center gap-3 bg-[#d3bfa1] p-3 border border-[#8b4513] transform rotate-1">
                                <span class="text-lg md:text-xl font-bold text-[#8b2500] w-1/4 truncate">{{ hint.player_nickname || hint.nickname }}</span>
                                <span class="text-xl md:text-2xl flex-1 text-[#4a2511] border-r-2 border-dashed border-[#8b4513] pr-3">{{ hint.content || hint.hint }}</span>
                            </div>
                        </TransitionGroup>

                        <!-- Turn Indicators -->
                        <div class="flex flex-wrap justify-center gap-3 md:gap-4 mt-4">
                            <div v-for="p in orderedPlayers" :key="p.id"
                                class="px-3 md:px-4 py-2 md:py-3 border shadow text-sm md:text-lg transition-all relative flex flex-col items-center gap-1"
                                :class="[
                                    localCurrentTurnPlayerId === p.id ? 'bg-[#8b2500] text-[#e8dcc4] border-[#4a1500] scale-110 z-10' :
                                    localHints.some(h => h.player_id === p.id) ? 'bg-[#d3bfa1] text-[#4a2511] border-[#8b4513]' :
                                    'bg-transparent text-[#8b4513] border-dashed border-[#8b4513] opacity-60'
                                ]"
                                :style="`transform: rotate(${p.id % 2 === 0 ? '2deg' : '-2deg'});`"
                            >
                                <AvatarDisplay :avatar="p.avatar" :size="48" />
                                <span class="truncate max-w-[70px] md:max-w-[90px]">{{ p.nickname }}</span>
                                <div v-if="localCurrentTurnPlayerId === p.id" class="absolute -top-3 -right-3 w-6 h-6 bg-[#d3bfa1] border-2 border-[#8b2500] rounded-full animate-bounce"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Hint Input Area -->
                    <div v-if="!localHintsComplete && isMyTurn && !hasSubmittedHint" class="flex flex-col md:flex-row gap-4 md:gap-6 items-end mt-6 md:mt-12 bg-[#8b4513]/10 p-4 border-2 border-dashed border-[#8b4513]">
                        <div class="flex-1 w-full">
                            <label class="block text-lg md:text-xl mb-1 md:mb-2 text-right">{{ t('your_hint') }}</label>
                            <input v-model="hintInput" @keyup.enter="submitHint" type="text" class="western-input w-full text-2xl md:text-4xl py-1 md:py-2 px-2 md:px-4" placeholder="قل ما لديك..." maxlength="100" />
                        </div>
                        <button @click="submitHint" :disabled="!hintInput.trim()" class="western-btn text-xl md:text-3xl px-6 md:px-8 py-3 md:py-4 uppercase w-full md:w-auto disabled:opacity-50">إرسال</button>
                    </div>

                    <!-- Waiting Text -->
                    <div v-if="!localHintsComplete && !isMyTurn" class="mt-8 text-center text-xl md:text-2xl text-[#8b4513] animate-pulse">
                        <p v-if="waitingPlayer">{{ waitingPlayer.nickname }} {{ t('is_typing_hint') }}</p>
                        <p v-else>{{ t('waiting_for_players') }}</p>
                    </div>

                    <!-- Actions when hints complete -->
                    <div v-if="localHintsComplete && isCreator" class="flex flex-col sm:flex-row gap-4 mt-8 pt-6 border-t border-dashed border-[#8b4513]">
                        <button @click="continueRound" class="western-btn-alt text-xl md:text-2xl px-4 py-3 flex-1 border-2 border-[#8b4513]">{{ t('continue_hints') }}</button>
                        <button @click="goToVoting" class="western-btn text-xl md:text-2xl px-4 py-3 flex-1">{{ t('start_voting') }}</button>
                    </div>
                    <div v-if="localHintsComplete && !isCreator" class="mt-8 text-center text-xl md:text-2xl text-[#8b4513] animate-pulse">
                        {{ t('waiting_for_host') }}
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
.western-input { background: transparent; border: none; border-bottom: 2px dashed #8b4513; color: #4a2511; font-family: 'Lalezar', cursive; }
@media (min-width: 768px) { .western-input { border-bottom-width: 3px; } }
.western-input:focus { outline: none; border-bottom-style: solid; }
.western-btn { background-color: #8b2500; color: #e8dcc4; border: 3px solid #4a1500; box-shadow: 2px 2px 0px #3a1000; transition: all 0.1s; cursor: pointer; }
@media (min-width: 768px) { .western-btn { border-width: 4px; box-shadow: 3px 3px 0px #3a1000; } }
.western-btn:active:not(:disabled) { box-shadow: 0px 0px 0px #3a1000; transform: translate(2px, 2px); }
.western-btn-alt { background-color: #d3bfa1; color: #4a2511; border-color: #8b4513; cursor: pointer; }

.scrollbar-western::-webkit-scrollbar { width: 6px; }
@media (min-width: 768px) { .scrollbar-western::-webkit-scrollbar { width: 8px; } }
.scrollbar-western::-webkit-scrollbar-track { background: transparent; }
.scrollbar-western::-webkit-scrollbar-thumb { background: #8b4513; border-radius: 4px; }

.hint-slide-enter-active { transition: all 0.3s ease-out; }
.hint-slide-leave-active { transition: all 0.2s ease-out; }
.hint-slide-enter-from { opacity: 0; transform: translateX(40px) rotate(0deg); }
.hint-slide-leave-to { opacity: 0; transform: translateX(-20px); }
.hint-slide-move { transition: transform 0.3s ease-out; }
</style>
