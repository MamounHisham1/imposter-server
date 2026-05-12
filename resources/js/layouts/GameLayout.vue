<script setup>
import { onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePage } from '@inertiajs/vue3';
import { useErrorToasts } from '../Composables/useErrorToasts';

const { t } = useI18n();
useErrorToasts();

defineProps({
    roomCode: {
        type: String,
        default: '',
    },
});

const HEARTBEAT_INTERVAL = 15_000;

function sendHeartbeat() {
    const playerId = usePage().props.player?.id;
    if (playerId) {
        navigator.sendBeacon('/heartbeat', new URLSearchParams({ player_id: playerId }));
    }
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
            <div class="flex items-center gap-2">
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
    </div>
</template>

<style scoped>
.wanted-text { color: #4a2511; text-shadow: 1px 1px 0px rgba(255,255,255,0.8); }
</style>
