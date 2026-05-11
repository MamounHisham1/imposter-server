<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
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
});

const selectedPlayerId = ref(null);
const hasVoted = ref(false);

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
        }
    );
}

// Echo listeners
onMounted(() => {
    if (window.Echo) {
        window.Echo.channel('room.' + props.room.id)
            .listen('.game.event', (e) => {
                switch (e.type) {
                    case 'vote_submitted':
                        break;
                    case 'game_over':
                        router.visit('/game/' + props.room.code + '/result');
                        break;
                    case 'voting_complete':
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
        <Toast />
        <div class="max-w-lg mx-auto space-y-6">
            <!-- Vote Header -->
            <div class="text-center py-4">
                <div class="flex items-center justify-center gap-2 mb-2">
                    <!-- Crosshair SVG -->
                    <svg class="w-6 h-6 text-[#ff3333]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="22" y1="12" x2="18" y2="12" />
                        <line x1="6" y1="12" x2="2" y2="12" />
                        <line x1="12" y1="6" x2="12" y2="2" />
                        <line x1="12" y1="22" x2="12" y2="18" />
                    </svg>
                    <h2 class="text-2xl font-bold tracking-[0.2em] text-[#ff3333]">
                        {{ t('vote_now') }}
                    </h2>
                </div>
                <p class="text-sm text-[#00ff41]/50">{{ t('vote_instruction') }}</p>
            </div>

            <!-- Round info -->
            <div class="text-center">
                <div
                    class="inline-block px-4 py-1 text-xs font-bold tracking-[0.3em] border border-[#00ff41]/30 bg-[#00ff41]/5 text-[#00ff41]/60"
                    style="clip-path: polygon(8px 0, 100% 0, calc(100% - 8px) 100%, 0 100%);"
                >
                    {{ t('round') }} {{ round?.round_number || 1 }}
                </div>
            </div>

            <!-- Hints Review -->
            <div class="border border-[#00ff41]/20 bg-[#001200]/50 p-4">
                <h3 class="text-xs tracking-[0.3em] text-[#00ff41]/50 uppercase mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
                    </svg>
                    {{ t('hints') }}
                </h3>
                <div class="max-h-40 overflow-y-auto space-y-2 pr-1 scrollbar-thin">
                    <div
                        v-for="(hint, idx) in hints"
                        :key="idx"
                        class="flex items-start gap-3 bg-[#000a00]/60 px-3 py-2 border-l-2 border-[#00ff41]/30"
                    >
                        <span class="text-xs text-[#00ff41]/40 font-mono shrink-0">
                            {{ hint.player_nickname || hint.nickname || '???' }}
                        </span>
                        <span class="text-sm text-[#33ff66]">{{ hint.content || hint.hint }}</span>
                    </div>
                </div>
            </div>

            <!-- Player Cards for Voting -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <button
                    v-for="p in players"
                    :key="p.id"
                    @click="selectPlayer(p.id)"
                    :disabled="p.id === player.id || hasVoted"
                    class="relative group transition-all duration-200"
                >
                    <!-- Selection ring -->
                    <div
                        class="absolute -inset-1 border-2 transition-all duration-200"
                        :class="[
                            selectedPlayerId === p.id
                                ? 'border-[#ff3333] opacity-100'
                                : 'border-transparent opacity-0 group-hover:border-[#00ff41]/30 group-hover:opacity-100',
                        ]"
                        style="clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);"
                    ></div>

                    <div
                        class="flex flex-col items-center gap-2 p-4 bg-[#001200]/60 border transition-all duration-200"
                        :class="[
                            p.id === player.id
                                ? 'border-[#00ff41]/10 opacity-40 cursor-not-allowed'
                                : selectedPlayerId === p.id
                                    ? 'border-[#ff3333]/60 bg-[#ff3333]/10'
                                    : 'border-[#00ff41]/15 hover:border-[#00ff41]/40',
                        ]"
                        style="clip-path: polygon(8px 0, 100% 0, calc(100% - 8px) 100%, 0 100%);"
                    >
                        <!-- Hexagonal avatar -->
                        <div
                            class="w-14 h-14 flex items-center justify-center text-lg font-bold"
                            :class="selectedPlayerId === p.id ? 'bg-[#ff3333]/20 text-[#ff3333]' : 'bg-[#00ff41]/10 text-[#00ff41]'"
                            style="clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);"
                        >
                            {{ p.nickname.charAt(0).toUpperCase() }}
                        </div>
                        <span
                            class="text-xs font-mono tracking-wider"
                            :class="selectedPlayerId === p.id ? 'text-[#ff3333]' : 'text-[#33ff66]'"
                        >
                            {{ p.nickname }}
                        </span>

                        <!-- Selected indicator -->
                        <svg
                            v-if="selectedPlayerId === p.id"
                            class="w-5 h-5 text-[#ff3333]"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                        >
                            <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>

                        <span v-if="p.id === player.id" class="text-[10px] text-[#00ff41]/30">{{ t('you') }}</span>
                    </div>
                </button>
            </div>

            <!-- Submit Vote -->
            <button
                @click="submitVote"
                :disabled="!selectedPlayerId || hasVoted"
                class="w-full py-4 font-bold text-lg tracking-[0.2em] border border-[#ff3333]/60 bg-[#ff3333]/10 text-[#ff3333] hover:bg-[#ff3333]/20 transition-all disabled:opacity-30 disabled:cursor-not-allowed"
                style="clip-path: polygon(10px 0, 100% 0, calc(100% - 10px) 100%, 0 100%);"
            >
                <span class="flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 9V5a3 3 0 00-3-3l-4 9v11h11.28a2 2 0 002-1.7l1.38-9a2 2 0 00-2-2.3H14z" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M7 22H4a2 2 0 01-2-2v-7a2 2 0 012-2h3" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    {{ hasVoted ? t('vote_submitted') : t('submit_vote') }}
                </span>
            </button>
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
