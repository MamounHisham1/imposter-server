<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useErrorToasts } from '../Composables/useErrorToasts';

const { t } = useI18n();
useErrorToasts();

const props = defineProps({
    roomCode: {
        type: String,
        default: '',
    },
    activeGame: {
        type: Boolean,
        default: false,
    },
});

const showLeaveModal = ref(false);
const confirmInput = ref('');

const HEARTBEAT_INTERVAL = 15_000;

function sendHeartbeat() {
    const playerId = usePage().props.player?.id;
    if (playerId) {
        navigator.sendBeacon('/heartbeat', new URLSearchParams({ player_id: playerId }));
    }
}

function goHome() {
    if (props.activeGame && props.roomCode) {
        showLeaveModal.value = true;
        confirmInput.value = '';
    } else {
        router.visit('/');
    }
}

function cancelLeave() {
    showLeaveModal.value = false;
    confirmInput.value = '';
}

function confirmLeave() {
    if (confirmInput.value.trim().toLowerCase() !== 'yes') return;
    router.post('/room/' + props.roomCode + '/leave', {}, {
        onFinish: () => {
            router.visit('/');
        },
    });
}

onMounted(() => {
    sendHeartbeat();
    window._heartbeatTimer = setInterval(sendHeartbeat, HEARTBEAT_INTERVAL);
});

onUnmounted(() => {
    clearInterval(window._heartbeatTimer);
});
</script>

<template>
    <div class="flex flex-col min-h-screen">
        <header class="relative px-4 pt-4 pb-2 z-10 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button @click="goHome" class="western-home-btn flex items-center gap-1.5 px-3 py-1.5 border-2 border-[#8b4513] bg-[#d3bfa1] text-[#4a2511] font-bold text-sm uppercase tracking-wider transition-all hover:bg-[#c4af8e] hover:shadow-md active:translate-y-px" title="Home">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1" />
                    </svg>
                    <span class="hidden sm:inline">HOME</span>
                </button>
                <h1 class="text-3xl wanted-text uppercase tracking-widest text-[#4a2511]">
                    {{ t('title') }}
                </h1>
            </div>
            <div v-if="roomCode" class="border-2 border-dashed border-[#8b4513] px-3 py-1 bg-[#d3bfa1] text-[#4a2511] font-sans font-bold text-lg">
                {{ roomCode }}
            </div>
        </header>
        <main class="flex-1">
            <slot />
        </main>

        <!-- Leave Confirmation Modal -->
        <div v-if="showLeaveModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="cancelLeave">
            <!-- Dark Overlay -->
            <div class="absolute inset-0 bg-black/70"></div>

            <!-- Wanted Poster Dialog -->
            <div class="relative w-full max-w-md p-6 md:p-8" style="background-color: #e8dcc4; border: 4px solid #8b4513; box-shadow: inset 0 0 30px rgba(139,69,19,0.2), 0 10px 30px rgba(0,0,0,0.7);">
                <!-- Corner Nails -->
                <div class="absolute top-2 left-2 w-2.5 h-2.5 rounded-full bg-gray-800 border border-gray-900"></div>
                <div class="absolute top-2 right-2 w-2.5 h-2.5 rounded-full bg-gray-800 border border-gray-900"></div>
                <div class="absolute bottom-2 left-2 w-2.5 h-2.5 rounded-full bg-gray-800 border border-gray-900"></div>
                <div class="absolute bottom-2 right-2 w-2.5 h-2.5 rounded-full bg-gray-800 border border-gray-900"></div>

                <div class="text-center">
                    <!-- Skull Warning -->
                    <div class="text-5xl mb-3">&#9760;</div>

                    <h2 class="text-2xl md:text-3xl wanted-text uppercase mb-2">Hold Your Horses!</h2>
                    <p class="text-lg text-[#8b4513] mb-1">You're in the middle of a showdown, partner!</p>
                    <p class="text-base text-[#4a2511]/70 mb-6">Leavin' now means abandonin' the game. Are you sure?</p>

                    <div class="mb-4">
                        <label class="block text-sm text-[#8b4513] mb-2 uppercase tracking-wider">Type <strong>"yes"</strong> to confirm</label>
                        <input
                            v-model="confirmInput"
                            type="text"
                            class="w-full text-center text-xl py-2 px-3 bg-transparent border-2 border-dashed border-[#8b4513] text-[#4a2511] font-sans focus:outline-none focus:border-solid"
                            placeholder="yes"
                            @keyup.enter="confirmLeave"
                            autofocus
                        />
                    </div>

                    <div class="flex gap-3 mt-4">
                        <button @click="confirmLeave" :disabled="confirmInput.trim().toLowerCase() !== 'yes'" class="western-btn text-lg px-4 py-2.5 flex-1 disabled:opacity-40 disabled:cursor-not-allowed">
                            Ride Out
                        </button>
                        <button @click="cancelLeave" class="western-btn-alt border-2 border-[#8b4513] text-lg px-4 py-2.5 flex-1">
                            Stay
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.wanted-text { color: #4a2511; text-shadow: 1px 1px 0px rgba(255,255,255,0.8); }
.western-btn { background-color: #8b2500; color: #e8dcc4; border: 3px solid #4a1500; box-shadow: 2px 2px 0px #3a1000; transition: all 0.1s; cursor: pointer; }
.western-btn:active:not(:disabled) { box-shadow: 0px 0px 0px #3a1000; transform: translate(2px, 2px); }
.western-btn-alt { background-color: #d3bfa1; color: #4a2511; border-color: #8b4513; cursor: pointer; transition: all 0.1s; }
.western-btn-alt:hover { background-color: #c4af8e; }
</style>
