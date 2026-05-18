<script setup>
import { useI18n } from 'vue-i18n';
import { Link } from '@inertiajs/vue3';

const { t } = useI18n();

defineProps({
    credits: { type: Number, default: 0 },
    transactions: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center p-4">
        <div class="wanted-poster p-8 max-w-lg w-full text-center">
            <div class="flex justify-between items-center border-b-2 border-dashed border-[#8b4513] pb-4 mb-6">
                <Link href="/" class="text-[#8b4513] text-lg hover:underline">{{ t('back_to_lobby') }}</Link>
                <h1 class="text-3xl wanted-text uppercase">{{ t('credits') }}</h1>
                <Link href="/shop" class="western-btn-alt px-3 py-1 border-2 text-sm">{{ t('shop') }}</Link>
            </div>

            <div class="bg-[#d3bfa1]/50 border-2 border-dashed border-[#8b4513] p-4 rounded-lg mb-6">
                <div class="text-5xl text-[#8b6914] font-bold font-sans">{{ credits }}</div>
                <div class="text-lg text-[#8b4513]">{{ t('credits') }}</div>
            </div>

            <div v-if="transactions.length === 0" class="text-[#8b6914] text-lg py-4">
                No transactions yet. Play games to earn credits!
            </div>

            <div v-else class="space-y-2 max-h-80 overflow-y-auto text-left">
                <div v-for="tx in transactions" :key="tx.id" class="flex justify-between items-center p-2 border-b border-dashed border-[#b8a07e]">
                    <div>
                        <div class="text-sm text-[#4a2511]">{{ tx.description || tx.type }}</div>
                        <div class="text-xs text-[#8b6914]">{{ new Date(tx.created_at).toLocaleDateString() }}</div>
                    </div>
                    <div :class="tx.amount > 0 ? 'text-green-700' : 'text-[#8b2500]'" class="font-bold font-sans">
                        {{ tx.amount > 0 ? '+' : '' }}{{ tx.amount }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.wanted-poster { background-color: #e8dcc4; border: 2px solid #b8a07e; box-shadow: inset 0 0 30px rgba(139, 69, 19, 0.2), 0 3px 10px rgba(0,0,0,0.5); }
.wanted-text { color: #4a2511; text-shadow: 1px 1px 0px rgba(255,255,255,0.8); font-family: 'Lalezar', cursive; }
.western-btn-alt { background-color: #d3bfa1; color: #4a2511; border-color: #8b4513; cursor: pointer; font-family: 'Lalezar', cursive; text-decoration: none; }
.western-btn-alt:active { transform: translate(1px, 1px); }
</style>
