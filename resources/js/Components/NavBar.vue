<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';

const { t, locale } = useI18n();
const auth = computed(() => usePage().props.auth);

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div v-if="auth?.user" class="fixed top-3 right-3 z-50 flex items-center gap-3 bg-[#5c3a21] border-2 border-[#3a2010] rounded-lg px-3 py-2 shadow-lg">
        <a href="/stats" class="text-[#d3bfa1] text-sm font-sans no-underline hover:text-[#f5e6d0]">{{ auth.user.nickname }}</a>
        <a href="/shop" class="bg-[#8b6914] text-[#1a0e08] text-xs font-bold px-2 py-0.5 rounded-full no-underline hover:bg-[#a07818]">{{ auth.user.credits }} {{ t('credits') }}</a>
        <a href="/credits" class="text-[#8b6914] text-sm hover:text-[#d3bfa1] no-underline">{{ t('credits') }}</a>
        <a href="/stats" class="text-[#8b6914] text-sm hover:text-[#d3bfa1] no-underline font-bold">{{ t('stats') }}</a>
        <button @click="logout" class="text-[#8b6914] text-sm hover:text-[#d3bfa1] cursor-pointer">{{ t('logout') }}</button>
    </div>
    <div v-else class="fixed top-3 right-3 z-50 flex items-center gap-2 bg-[#5c3a21] border-2 border-[#3a2010] rounded-lg px-3 py-2 shadow-lg">
        <a href="/login" class="text-[#d3bfa1] text-sm no-underline hover:text-[#f5e6d0]">{{ t('login') }}</a>
        <span class="text-[#8b6914]">|</span>
        <a href="/register" class="text-[#d3bfa1] text-sm no-underline hover:text-[#f5e6d0]">{{ t('register') }}</a>
    </div>
</template>
