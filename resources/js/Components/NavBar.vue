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
    <div v-if="auth?.user" class="fixed top-3 right-3 z-50 flex items-center gap-3 bg-[#5c3a21] border-2 border-[#3a2010] rounded-lg px-3.5 py-2 shadow-lg transition-all duration-300">
        <a href="/stats" class="text-[#e8dcc4] text-sm font-sans font-medium no-underline hover:text-[#f5e6d0] transition-colors duration-200">{{ auth.user.nickname }}</a>
        <!-- <a href="/shop" class="bg-[#e5a93b] text-[#2d1607] text-xs font-bold px-2.5 py-0.5 rounded-full no-underline hover:bg-[#f3b953] transition-colors duration-200 shadow-sm">{{ auth.user.credits }} {{ t('credits') }}</a> -->
        <a href="/credits" class="text-[#e5a93b] text-sm hover:text-[#f5e6d0] no-underline font-medium transition-colors duration-200">{{ t('credits') }}</a>
        <a href="/stats" class="text-[#e5a93b] text-sm hover:text-[#f5e6d0] no-underline font-bold transition-colors duration-200">{{ t('stats') }}</a>
        <button @click="logout" class="text-[#e5a93b] text-sm hover:text-[#f5e6d0] cursor-pointer font-medium transition-colors duration-200">{{ t('logout') }}</button>
    </div>
    <div v-else class="fixed top-3 right-3 z-50 flex items-center gap-2 bg-[#5c3a21] border-2 border-[#3a2010] rounded-lg px-3.5 py-2 shadow-lg transition-all duration-300">
        <a href="/login" class="text-[#e8dcc4] text-sm no-underline hover:text-[#e5a93b] transition-colors duration-200">{{ t('login') }}</a>
        <span class="text-[#b8a07e]/60">|</span>
        <a href="/register" class="text-[#e8dcc4] text-sm no-underline hover:text-[#e5a93b] transition-colors duration-200">{{ t('register') }}</a>
    </div>
</template>
