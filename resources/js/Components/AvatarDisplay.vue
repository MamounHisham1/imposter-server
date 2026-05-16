<script setup>
import { computed } from 'vue';
import { getLayerStyle, AVATAR_BASE } from '../Composables/useAvatarConfig';

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
        <img v-if="av.beard" :src="AVATAR_BASE + av.beard" class="avatar-layer" :style="layerStyle('beard', av.beard)" />
        <img v-if="av.eyes" :src="AVATAR_BASE + av.eyes" class="avatar-layer" :style="layerStyle('eyes', av.eyes)" />
        <img v-if="av.hair" :src="AVATAR_BASE + av.hair" class="avatar-layer" :style="layerStyle('hair', av.hair)" />
    </div>
</template>

<style scoped>
.avatar-display { position: relative; flex-shrink: 0; overflow: hidden; border-radius: 8px; border: 2px solid #8b6914; background: #d3bfa1; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
.avatar-display.lg { border-radius: 10px; border-width: 3px; }
.avatar-layer { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain; pointer-events: none; }
</style>
