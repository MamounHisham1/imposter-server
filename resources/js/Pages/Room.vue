<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
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
});

const localPlayers = ref([...props.players]);
const localRoom = ref({ ...props.room });

const isCreator = computed(() => {
    return props.player.id === localRoom.value.creator_id;
});

const allReady = computed(() => {
    return (
        localPlayers.value.length >= 3 &&
        localPlayers.value.every((p) => p.is_ready)
    );
});

function kickPlayer(targetId) {
    router.post('/room/' + props.room.code + '/kick', {
        target_id: targetId,
        player_id: props.player.id,
        room_id: props.room.id,
    }, {
        preserveScroll: true,
        onError: (errors) => {
            const msg = Object.values(errors)[0];
            if (msg) toastError(msg);
        },
    });
}

function toggleReady() {
    router.post('/room/' + props.room.code + '/ready', { player_id: props.player.id }, {
        preserveScroll: true,
        onSuccess: (page) => {
            localPlayers.value = page.props.players || localPlayers.value;
        },
        onError: (errors) => {
            const msg = Object.values(errors)[0];
            if (msg) toastError(msg);
        },
    });
}

function startGame() {
    router.post('/room/' + props.room.code + '/start', { player_id: props.player.id, room_id: props.room.id }, {
        preserveScroll: true,
        onError: (errors) => {
            const msg = Object.values(errors)[0];
            if (msg) toastError(msg);
        },
    });
}

function leaveRoom() {
    router.post('/room/' + props.room.code + '/leave', { player_id: props.player.id });
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
                    case 'player_left':
                        localPlayers.value = localPlayers.value.filter((p) => p.id !== e.player_id);
                        if (e.room) localRoom.value = e.room;
                        break;
                    case 'player_ready':
                        if (e.player) {
                            const idx = localPlayers.value.findIndex((p) => p.id === e.player.id);
                            if (idx !== -1) localPlayers.value[idx].is_ready = e.player.is_ready;
                        }
                        break;
                    case 'creator_changed':
                        if (e.room) localRoom.value = e.room;
                        break;
                    case 'game_started':
                        router.visit('/game/' + props.room.code);
                        break;
                    case 'room_deleted':
                        router.visit('/');
                        break;
                    case 'game_aborted':
                        router.visit('/');
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
    <GameLayout :room-code="room.code" :active-game="false">
        <Toast />
        <div class="min-h-screen flex items-center justify-center p-2 md:p-4">
            <div class="wood-panel max-w-3xl w-full p-4 md:p-12 relative">
                <!-- Nails -->
                <div class="absolute top-2 left-2 md:top-4 md:left-4 w-3 h-3 md:w-4 md:h-4 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>
                <div class="absolute top-2 right-2 md:top-4 md:right-4 w-3 h-3 md:w-4 md:h-4 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>
                <div class="absolute bottom-2 left-2 md:bottom-4 md:left-4 w-3 h-3 md:w-4 md:h-4 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>
                <div class="absolute bottom-2 right-2 md:bottom-4 md:right-4 w-3 h-3 md:w-4 md:h-4 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>

                <div class="wanted-poster p-4 md:p-10 md:transform md:rotate-1">
                    <header class="text-center border-b-2 md:border-b-4 border-double border-[#8b4513] pb-4 md:pb-6 mb-6">
                        <h2 class="text-xl md:text-2xl tracking-widest mb-1 text-[#8b4513]">{{ t('room_code') }}</h2>
                        <h1 class="text-5xl md:text-7xl wanted-text uppercase font-sans tracking-widest">{{ room.code }}</h1>
                    </header>

                    <div class="text-center mb-6">
                        <h3 class="text-2xl md:text-3xl wanted-text mb-4">{{ t('waiting_for_players') }} ({{ localPlayers.length }})</h3>
                        <div class="flex flex-wrap justify-center gap-3 md:gap-4">
                            <div v-for="p in localPlayers" :key="p.id"
                                class="px-3 md:px-4 py-3 border shadow text-base md:text-xl transition-all relative flex flex-col items-center gap-2"
                                :class="p.is_ready ? 'bg-[#8b4513] text-[#e8dcc4] border-[#4a2511]' : 'bg-[#d3bfa1] text-[#4a2511] border-[#8b4513] opacity-80'"
                                :style="`transform: rotate(${p.id % 2 === 0 ? '2deg' : '-2deg'});`"
                            >
                                <button v-if="isCreator && p.id !== player.id" @click="kickPlayer(p.id)" class="absolute -top-2 -left-2 w-6 h-6 bg-red-700 text-white rounded-full text-xs flex items-center justify-center shadow hover:bg-red-900 z-10" :title="t('kick_player')">&times;</button>
                                <AvatarDisplay :avatar="p.avatar" :size="56" />
                                <span class="truncate max-w-[80px] md:max-w-[100px]">{{ p.nickname }}</span>
                                <span v-if="p.id === localRoom.creator_id" class="text-xs absolute -top-2 -right-2 bg-yellow-500 text-black px-1 rounded transform rotate-12">الزعيم</span>
                                <span v-if="p.id === player.id" class="text-xs text-black/50">({{ t('you') }})</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-4 mt-8 pt-6 border-t border-dashed border-[#8b4513]">
                        <button @click="toggleReady" class="western-btn text-xl md:text-3xl px-6 py-3 w-full" :class="player.is_ready ? 'bg-[#4a2511] border-[#2b1d14]' : ''">
                            {{ player.is_ready ? t('not_ready') : t('ready') }}
                        </button>
                        
                        <button v-if="isCreator" @click="startGame" :disabled="!allReady" class="western-btn-alt western-btn text-xl md:text-3xl px-6 py-3 w-full disabled:opacity-50 border-2 border-[#8b4513]">
                            {{ t('start_game') }}
                        </button>

                        <button @click="leaveRoom" class="text-[#8b4513] text-lg hover:underline mt-2">
                            {{ t('leave_room') }}
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
.western-btn-alt { background-color: #d3bfa1; color: #4a2511; border-color: #8b4513; }
</style>
