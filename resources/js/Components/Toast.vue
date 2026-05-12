<script setup>
import { useToast } from '../Composables/useToast';

const { toasts } = useToast();

const iconMap = {
    error: '⚠',
    success: '✓',
};
</script>

<template>
    <div class="fixed top-4 right-4 z-50 flex flex-col gap-3 max-w-sm">
        <transition-group name="toast">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="relative border-2 px-5 py-3 text-base md:text-lg shadow-lg"
                :class="toast.type === 'success'
                    ? 'bg-[#1b4a1b] border-[#0d2e0d] text-[#a8e6a8]'
                    : 'bg-[#4a1010] border-[#2a0808] text-[#e8a0a0]'"
            >
                <!-- Nail decorations -->
                <div class="absolute top-1 left-1 w-2 h-2 rounded-full bg-gray-600 border border-gray-800"></div>
                <div class="absolute top-1 right-1 w-2 h-2 rounded-full bg-gray-600 border border-gray-800"></div>
                <div class="flex items-center gap-2">
                    <span class="text-xl">{{ iconMap[toast.type] || iconMap.error }}</span>
                    <span>{{ toast.message }}</span>
                </div>
            </div>
        </transition-group>
    </div>
</template>

<style scoped>
.toast-enter-active {
    transition: all 0.4s ease;
}
.toast-leave-active {
    transition: all 0.3s ease;
}
.toast-enter-from {
    opacity: 0;
    transform: translateX(40px) rotate(3deg);
}
.toast-leave-to {
    opacity: 0;
    transform: translateX(40px);
}
</style>
