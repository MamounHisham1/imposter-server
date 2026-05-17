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
    <div class="avatar-display" :class="{ lg: size >= 80 }" :style="{ width: size + 'px', height: size + 'px' }">
        <img v-if="av.head" :src="AVATAR_BASE + av.head" class="avatar-layer" />
        <img v-for="l in layers" :key="l.file" :src="AVATAR_BASE + l.src" class="avatar-layer" :style="layerStyle(l.layer, l.file)" />
    </div>
</template>

<style scoped>
.avatar-display { position: relative; flex-shrink: 0; overflow: hidden; border-radius: 8px; border: 2px solid #8b6914; background: #d3bfa1; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
.avatar-display.lg { border-radius: 10px; border-width: 3px; }
.avatar-layer { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain; pointer-events: none; }
</style>
