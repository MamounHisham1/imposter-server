<script setup>
import { onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePage } from '@inertiajs/vue3';

const { t } = useI18n();

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
    <div class="min-h-screen bg-[#000a00] text-[#33ff66] flex flex-col">
        <header class="relative px-4 pt-4 pb-2">
            <div class="flex items-center justify-between">
                <h1
                    class="text-2xl font-bold tracking-[0.3em]"
                    style="
                        background: linear-gradient(90deg, #00ff41, #33ff66, #00ff41);
                        -webkit-background-clip: text;
                        -webkit-text-fill-color: transparent;
                        background-clip: text;
                    "
                >
                    {{ t('title') }}
                </h1>
                <div
                    v-if="roomCode"
                    class="font-mono text-sm tracking-[0.2em] border border-[#00ff41]/40 px-3 py-1 rounded bg-[#00ff41]/5"
                >
                    {{ roomCode }}
                </div>
            </div>
            <div class="glitch-line mt-2"></div>
        </header>
        <main class="flex-1 px-4 py-4">
            <slot />
        </main>
    </div>
</template>

<style scoped>
.glitch-line {
    height: 2px;
    background: linear-gradient(90deg, transparent, #00ff41, #33ff66, #00ff41, transparent);
    position: relative;
    overflow: visible;
}

.glitch-line::before {
    content: '';
    position: absolute;
    top: -1px;
    left: 0;
    right: 0;
    height: 4px;
    background: inherit;
    opacity: 0.3;
    animation: glitch-skew 3s infinite linear alternate-reverse;
}

@keyframes glitch-skew {
    0% {
        transform: skewX(0deg);
        opacity: 0.3;
    }
    25% {
        transform: skewX(-2deg);
        opacity: 0.5;
    }
    50% {
        transform: skewX(1deg);
        opacity: 0.2;
    }
    75% {
        transform: skewX(-1deg);
        opacity: 0.4;
    }
    100% {
        transform: skewX(0deg);
        opacity: 0.3;
    }
}
</style>
