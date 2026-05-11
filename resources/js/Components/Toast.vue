<script setup>
import { useToast } from '../Composables/useToast';

const { toasts } = useToast();

const colorMap = {
    error: 'border-[#ff3333]/60 bg-[#ff3333]/10 text-[#ff3333]',
    success: 'border-[#00ff41]/60 bg-[#00ff41]/10 text-[#00ff41]',
};
</script>

<template>
    <div class="fixed top-4 right-4 z-50 flex flex-col gap-2 max-w-sm">
        <transition-group name="toast">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="border px-4 py-3 font-mono text-sm backdrop-blur-sm"
                :class="colorMap[toast.type] || colorMap.error"
                style="clip-path: polygon(6px 0, 100% 0, calc(100% - 6px) 100%, 0 100%);"
            >
                {{ toast.message }}
            </div>
        </transition-group>
    </div>
</template>

<style scoped>
.toast-enter-active {
    transition: all 0.3s ease;
}
.toast-leave-active {
    transition: all 0.3s ease;
}
.toast-enter-from {
    opacity: 0;
    transform: translateX(40px);
}
.toast-leave-to {
    opacity: 0;
    transform: translateX(40px);
}
</style>
