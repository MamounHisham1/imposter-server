<script setup>
import { useI18n } from 'vue-i18n';
import { Link, Head } from '@inertiajs/vue3';
import NavBar from '../Components/NavBar.vue';
import SiteFooter from '../Components/SiteFooter.vue';

const { t } = useI18n();

defineProps({
    credits: { type: Number, default: 0 },
    transactions: { type: Array, default: () => [] },
});

function translateDescription(desc) {
    if (!desc) return '';
    
    if (desc.startsWith('Reward: ')) {
        const event = desc.replace('Reward: ', '').trim();
        const key = `reward_${event}`;
        return t(key) !== key ? t(key) : desc;
    }
    
    if (desc.startsWith('Purchased item: ')) {
        const item = desc.replace('Purchased item: ', '').trim();
        return t('purchased_item', { item: item });
    }
    
    if (desc.startsWith('Purchased costume: ')) {
        const costume = desc.replace('Purchased costume: ', '').trim();
        return t('purchased_costume', { costume: costume });
    }
    
    if (desc.startsWith('Granted by ')) {
        const admin = desc.replace('Granted by ', '').trim();
        return t('granted_by', { admin: admin });
    }
    
    return desc;
}
</script>

<template>
    <Head>
        <title>Credits — Traitor (الخائن) Game</title>
        <meta name="description" content="View and manage your Traitor game credits. Earn credits by playing games and spend them in the avatar shop." head-key="description" />
    </Head>
    <div class="min-h-screen flex flex-col items-center justify-center p-4">
        <NavBar />

        <div class="wanted-poster p-8 max-w-lg w-full text-center">
            <div class="flex justify-between items-center border-b-2 border-dashed border-[#8b4513] pb-4 mb-6">
                <Link href="/" class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-[#8b4513] bg-[#d3bfa1] text-[#4a2511] font-bold text-sm uppercase tracking-wider transition-all hover:bg-[#c4af8e] hover:shadow-md active:translate-y-px no-underline" title="Home">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1" /></svg>
                    <span class="hidden sm:inline">{{ t('back_to_lobby') }}</span>
                </Link>
                <h1 class="text-3xl wanted-text uppercase">{{ t('credits') }}</h1>
                <Link href="/shop" class="western-btn-alt px-3 py-1 border-2 text-sm">{{ t('shop') }}</Link>
            </div>

            <div class="bg-[#d3bfa1]/50 border-2 border-dashed border-[#8b4513] p-4 rounded-lg mb-6">
                <div class="text-5xl text-[#8b6914] font-bold font-sans">{{ credits }}</div>
                <div class="text-lg text-[#8b4513]">{{ t('credits') }}</div>
            </div>

            <div v-if="transactions.length === 0" class="text-[#8b6914] text-lg py-4">
                {{ t('no_transactions') }}
            </div>

            <div v-else class="space-y-2 max-h-80 overflow-y-auto text-left">
                <div v-for="tx in transactions" :key="tx.id" class="flex justify-between items-center p-2 border-b border-dashed border-[#b8a07e]">
                    <div>
                        <div class="text-sm text-[#4a2511]">{{ translateDescription(tx.description || tx.type) }}</div>
                        <div class="text-xs text-[#8b6914]">{{ new Date(tx.created_at).toLocaleDateString() }}</div>
                    </div>
                    <div :class="tx.amount > 0 ? 'text-green-700' : 'text-[#8b2500]'" class="font-bold font-sans">
                        {{ tx.amount > 0 ? '+' : '' }}{{ tx.amount }}
                    </div>
                </div>
            </div>
        </div>
        <SiteFooter />
    </div>
</template>

<style scoped>
.wanted-poster { background-color: #e8dcc4; border: 2px solid #b8a07e; box-shadow: inset 0 0 30px rgba(139, 69, 19, 0.2), 0 3px 10px rgba(0,0,0,0.5); }
.wanted-text { color: #4a2511; text-shadow: 1px 1px 0px rgba(255,255,255,0.8); font-family: 'Lalezar', cursive; }
.western-btn-alt { background-color: #d3bfa1; color: #4a2511; border-color: #8b4513; cursor: pointer; font-family: 'Lalezar', cursive; text-decoration: none; }
.western-btn-alt:active { transform: translate(1px, 1px); }
</style>
