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

        <!-- CAUGHT: Prison Bars -->
        <div v-if="state === 'caught'" class="prison-bars absolute inset-0 flex justify-around px-2 pointer-events-none z-20">
            <div class="bar w-1.5 h-full bg-gradient-to-r from-gray-800 via-gray-500 to-gray-900 shadow-md"></div>
            <div class="bar w-1.5 h-full bg-gradient-to-r from-gray-800 via-gray-500 to-gray-900 shadow-md"></div>
            <div class="bar w-1.5 h-full bg-gradient-to-r from-gray-800 via-gray-500 to-gray-900 shadow-md"></div>
            <div class="bar w-1.5 h-full bg-gradient-to-r from-gray-800 via-gray-500 to-gray-900 shadow-md"></div>
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


</style>
