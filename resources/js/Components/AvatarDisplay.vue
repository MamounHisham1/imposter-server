<script setup>
import { computed } from 'vue';
import { getLayerStyle, AVATAR_BASE, AVATAR_ALIGNMENT, resolveImageFile } from '../Composables/useAvatarConfig';

const props = defineProps({
    avatar: {
        type: Object,
        default: () => ({}),
    },
    size: {
        type: Number,
        default: 40,
    },
    state: {
        type: String,
        default: 'normal', // 'normal', 'tense', 'shooting', 'caught', 'celebrating'
    }
});

const av = computed(() => props.avatar || {});

// Build a flat list of all layers to render, including duplicates
const layers = computed(() => {
    const result = [];
    const order = ['beard', 'eyes', 'hair'];
    for (const layer of order) {
        const file = av.value[layer];
        if (!file) continue;

        // Main item
        result.push({ layer, file, src: resolveImageFile(file) || file });

        // Check for duplicates (e.g., beard3_dup1.png, beard3_dup2.png)
        const baseName = file.replace('.png', '');
        const alData = AVATAR_ALIGNMENT[layer] || {};
        for (const key of Object.keys(alData)) {
            if (key.startsWith(baseName + '_dup')) {
                result.push({ layer, file: key, src: resolveImageFile(key) });
            }
        }
    }
    return result;
});

function layerStyle(layer, filename) {
    if (!filename) return { display: 'none' };
    const s = getLayerStyle(layer, filename, props.size);
    if (!s || s.display === 'none') return { display: 'none' };
    return { transform: s.transform };
}
</script>

<template>
    <div class="avatar-display" :class="[ { lg: size >= 80 }, state ]" :style="{ width: size + 'px', height: size + 'px' }">
        <div class="avatar-layer-wrapper">
            <img v-if="av.head" :src="AVATAR_BASE + av.head" class="avatar-layer" />
        </div>
        
        <div v-for="l in layers" :key="l.file" class="avatar-layer-wrapper" :class="{ 'eyes-dart': state === 'tense' && l.layer === 'eyes' }">
            <img :src="AVATAR_BASE + l.src" class="avatar-layer" :style="layerStyle(l.layer, l.file)" />
        </div>

        <!-- TENSE: Sweat Droplets -->
        <div v-if="state === 'tense'" class="sweat-container absolute top-2 right-2 w-6 h-6 z-20 pointer-events-none">
            <div class="sweat-drop drop-1"></div>
            <div class="sweat-drop drop-2"></div>
        </div>

        <!-- SHOOTING: Muzzle Flash & Rising Smoke -->
        <div v-if="state === 'shooting'" class="shooting-container absolute inset-0 z-30 pointer-events-none overflow-hidden">
            <!-- Muzzle Flash -->
            <svg viewBox="0 0 24 24" class="flash-svg absolute bottom-2 right-6 w-8 h-8 text-yellow-400 fill-current animate-flash z-30">
                <path d="M12 2l2.5 5.5L20 9l-4.5 4L17 19.5l-5-3.5-5 3.5 1.5-6.5L4 9l5.5-1.5L12 2z" />
            </svg>
            <!-- Smoke puffs -->
            <div class="smoke smoke-1"></div>
            <div class="smoke smoke-2"></div>
            <!-- Revolver drawing/firing silhouette -->
            <svg viewBox="0 0 24 24" class="revolver-svg absolute bottom-1 right-1 w-6 h-6 text-[#2f190e] fill-current animate-draw-gun z-30">
                <path d="M19.5 8h-4.5v-1.5c0-.83-.67-1.5-1.5-1.5h-5.5c-.83 0-1.5.67-1.5 1.5v1.5h-2.5c-1.1 0-2 .9-2 2v2c0 1.1.9 2 2 2h3.5v3.5c0 .83.67 1.5 1.5 1.5h4c.83 0 1.5-.67 1.5-1.5v-3.5h1.5v1.5c0 .83.67 1.5 1.5 1.5h3c.83 0 1.5-.67 1.5-1.5v-4.5c0-1.1-.9-2-2-2zm-12.5 4h-2.5v-2h2.5v2z" />
            </svg>
        </div>

        <!-- CAUGHT: Prison Bars -->
        <div v-if="state === 'caught'" class="prison-bars absolute inset-0 flex justify-around px-2 pointer-events-none z-20">
            <div class="bar w-1.5 h-full bg-gradient-to-r from-gray-800 via-gray-500 to-gray-900 shadow-md"></div>
            <div class="bar w-1.5 h-full bg-gradient-to-r from-gray-800 via-gray-500 to-gray-900 shadow-md"></div>
            <div class="bar w-1.5 h-full bg-gradient-to-r from-gray-800 via-gray-500 to-gray-900 shadow-md"></div>
            <div class="bar w-1.5 h-full bg-gradient-to-r from-gray-800 via-gray-500 to-gray-900 shadow-md"></div>
        </div>

        <!-- CELEBRATING: Clinking Beer Mugs -->
        <div v-if="state === 'celebrating'" class="celebrating-container absolute inset-0 z-30 pointer-events-none flex items-center justify-center bg-yellow-500/10">
            <div class="mugs-wrapper flex items-center justify-center gap-0.5 animate-clink">
                <svg viewBox="0 0 24 24" class="w-6 h-6 text-amber-700 fill-current transform rotate-12">
                    <path d="M4 6h8v10c0 1.1-.9 2-2 2H6c-1.1 0-2-.9-2-2V6zm8 2h2c.55 0 1 .45 1 1v3c0 .55-.45 1-1 1h-2V8zm-6-4c-.55 0-1 .45-1 1h8c0-.55-.45-1-1-1H6z" />
                </svg>
                <svg viewBox="0 0 24 24" class="w-6 h-6 text-amber-700 fill-current transform -rotate-12 scale-x-[-1]">
                    <path d="M4 6h8v10c0 1.1-.9 2-2 2H6c-1.1 0-2-.9-2-2V6zm8 2h2c.55 0 1 .45 1 1v3c0 .55-.45 1-1 1h-2V8zm-6-4c-.55 0-1 .45-1 1h8c0-.55-.45-1-1-1H6z" />
                </svg>
            </div>
        </div>
    </div>
</template>

<style scoped>
.avatar-display { position: relative; flex-shrink: 0; overflow: hidden; border-radius: 8px; border: 2px solid #8b6914; background: #d3bfa1; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
.avatar-display.lg { border-radius: 10px; border-width: 3px; }
.avatar-layer-wrapper { position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; }
.avatar-layer { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain; pointer-events: none; }

/* TENSE: sweat animations */
.sweat-drop {
    position: absolute;
    width: 4px;
    height: 8px;
    background-color: #60a5fa;
    border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%;
    opacity: 0;
}
.drop-1 {
    top: 0;
    right: 2px;
    animation: sweat-drip 1.8s infinite ease-in;
}
.drop-2 {
    top: 4px;
    right: 8px;
    animation: sweat-drip 2.2s infinite ease-in 0.6s;
}
@keyframes sweat-drip {
    0% { transform: translateY(0) scaleY(0.7); opacity: 0; }
    20% { opacity: 0.8; }
    80% { opacity: 0.8; }
    100% { transform: translateY(12px) scaleY(1); opacity: 0; }
}

/* TENSE: eyes darting wrapper */
@keyframes eyes-dart-anim {
    0%, 100% { transform: translateX(0); }
    15% { transform: translateX(-1.5px); }
    30% { transform: translateX(1.5px); }
    45% { transform: translateX(0); }
    60% { transform: translateX(-1.5px); }
    75% { transform: translateX(1.5px); }
}
.eyes-dart {
    animation: eyes-dart-anim 2.5s infinite ease-in-out;
}

/* SHOOTING: Gun and smoke */
.smoke {
    position: absolute;
    background-color: rgba(220, 220, 220, 0.7);
    border-radius: 50%;
    pointer-events: none;
    opacity: 0;
}
.smoke-1 {
    bottom: 12px;
    right: 24px;
    width: 8px;
    height: 8px;
    animation: rise-smoke 1.2s ease-out infinite;
}
.smoke-2 {
    bottom: 16px;
    right: 28px;
    width: 12px;
    height: 12px;
    animation: rise-smoke 1.6s ease-out infinite 0.4s;
}
@keyframes rise-smoke {
    0% { transform: translateY(0) scale(0.5); opacity: 0; }
    10% { opacity: 0.8; }
    50% { background-color: rgba(180, 180, 180, 0.4); }
    100% { transform: translateY(-16px) translateX(-8px) scale(1.8); opacity: 0; }
}
.animate-draw-gun {
    animation: draw-gun 2.2s infinite ease-out;
}
.animate-flash {
    animation: flash 2.2s infinite ease-out;
}
@keyframes draw-gun {
    0% { transform: translateY(12px) rotate(15deg); opacity: 0; }
    10% { transform: translateY(0) rotate(0deg); opacity: 1; }
    90% { transform: translateY(0) rotate(0deg); opacity: 1; }
    100% { transform: translateY(12px) rotate(15deg); opacity: 0; }
}
@keyframes flash {
    0% { transform: scale(0); opacity: 0; }
    8% { transform: scale(0); opacity: 0; }
    10% { transform: scale(1.3); opacity: 1; }
    13% { transform: scale(1); opacity: 1; }
    18% { transform: scale(0); opacity: 0; }
    100% { transform: scale(0); opacity: 0; }
}

/* CELEBRATING: Clinking beer mugs */
.animate-clink {
    animation: clink-mug 1.8s infinite ease-out;
}
@keyframes clink-mug {
    0% { transform: scale(0.6) translateY(12px); opacity: 0; }
    20% { transform: scale(1.1) translateY(-2px); opacity: 1; }
    30% { transform: scale(1) translateY(0) rotate(8deg); }
    40% { transform: scale(1) translateY(0) rotate(-8deg); }
    80% { opacity: 1; }
    100% { transform: scale(0.6) translateY(12px); opacity: 0; }
}
</style>
