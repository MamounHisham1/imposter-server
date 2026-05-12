<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';

const { t, locale } = useI18n();

const deferredPrompt = ref(null);
const showInstallBtn = ref(false);

function handleBeforeInstallPrompt(e) {
    e.preventDefault();
    deferredPrompt.value = e;
    showInstallBtn.value = true;
}

onMounted(() => {
    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
});

onUnmounted(() => {
    window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
});

async function installPWA() {
    if (!deferredPrompt.value) return;
    
    deferredPrompt.value.prompt();
    const { outcome } = await deferredPrompt.value.userChoice;
    
    if (outcome === 'accepted') {
        showInstallBtn.value = false;
    }
    deferredPrompt.value = null;
}

function goHome() {
    router.visit('/');
}

function toggleLanguage() {
    const newLang = locale.value === 'ar' ? 'en' : 'ar';
    locale.value = newLang;
    document.documentElement.lang = newLang;
    document.documentElement.dir = newLang === 'ar' ? 'rtl' : 'ltr';
}
</script>

<template>
    <div class="min-h-screen flex flex-col items-center p-2 md:p-6 pb-10">
        
        <!-- Header Controls -->
        <div class="w-full max-w-3xl flex justify-between items-center mb-6 z-10 px-2 mt-4">
            <button @click="goHome" class="western-btn-alt px-4 py-2 border-2 text-lg">
                {{ t('back_to_lobby') || 'العودة' }}
            </button>
            <button @click="toggleLanguage" class="western-btn-alt px-4 py-2 border-2 text-lg">
                {{ locale === 'ar' ? 'English' : 'العربية' }}
            </button>
        </div>

        <!-- Logo -->
        <div class="text-center mb-6 z-10 flex flex-col items-center">
            <img :src="'/logo.png'" alt="Traitor Logo" class="w-48 h-48 md:w-80 md:h-80 object-contain drop-shadow-2xl" />
        </div>

        <!-- Main Content -->
        <div class="wood-panel max-w-3xl w-full p-4 md:p-8 relative">
            <div class="absolute top-2 left-2 w-3 h-3 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>
            <div class="absolute top-2 right-2 w-3 h-3 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>
            <div class="absolute bottom-2 left-2 w-3 h-3 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>
            <div class="absolute bottom-2 right-2 w-3 h-3 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>

            <div class="wanted-poster p-6 md:p-10 text-center relative z-10">
                <header class="border-b-2 border-dashed border-[#8b4513] pb-6 mb-6">
                    <h1 class="text-5xl md:text-6xl wanted-text uppercase mb-2">{{ t('title') }}</h1>
                    <p class="text-xl md:text-2xl text-[#8b2500]">{{ t('subtitle') || 'من هو الخائن في البلدة؟' }}</p>
                </header>

                <div class="space-y-8" :dir="locale === 'ar' ? 'rtl' : 'ltr'" :class="locale === 'ar' ? 'text-right' : 'text-left'">
                    
                    <!-- About Section -->
                    <section>
                        <h2 class="text-3xl wanted-text mb-3 border-b border-[#8b4513]/30 pb-1 inline-block">{{ t('about_game') || 'عن اللعبة' }}</h2>
                        <p class="text-lg md:text-xl text-[#4a2511] leading-relaxed">
                            {{ t('about_game_desc') || 'لعبة اجتماعية مبنية على الخداع حيث يحاول الخائن التخفي بين أفراد العصابة وتجنب الشنق.' }}
                        </p>
                    </section>

                    <!-- How to Play Section -->
                    <section>
                        <h2 class="text-3xl wanted-text mb-3 border-b border-[#8b4513]/30 pb-1 inline-block">{{ t('how_to_play') || 'كيف تلعب؟' }}</h2>
                        <ul class="text-lg md:text-xl text-[#4a2511] space-y-2 leading-relaxed">
                            <li>{{ t('how_to_play_desc_1') || '1. انضم لغرفة مع أصدقائك.' }}</li>
                            <li>{{ t('how_to_play_desc_2') || '2. إذا كنت من العصابة، حاول معرفة الخائن عبر التلميحات.' }}</li>
                            <li>{{ t('how_to_play_desc_3') || '3. إذا كنت الخائن، اخدع الجميع!' }}</li>
                            <li>{{ t('how_to_play_desc_4') || '4. صوّت للشنق قبل أن يفوز الخائن.' }}</li>
                        </ul>
                    </section>

                </div>

                <!-- Install Button -->
                <div class="mt-10 pt-6 border-t-2 border-dashed border-[#8b4513]">
                    <button v-if="showInstallBtn" @click="installPWA" class="western-btn text-2xl md:text-4xl px-8 py-4 w-full flex items-center justify-center gap-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        {{ t('install_app') || 'تثبيت اللعبة' }}
                    </button>
                    <div v-else class="text-lg md:text-xl text-[#8b4513] border-2 border-[#8b4513]/30 bg-[#8b4513]/5 p-4">
                        {{ t('install_app') || 'تثبيت اللعبة' }} - (متاح عبر متصفح الجوال أو في حال لم تكن اللعبة مثبتة مسبقاً)
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.wood-panel { background-color: #8b5a2b; border: 4px solid #5c3a21; box-shadow: inset 0 0 10px rgba(0,0,0,0.5), 0 5px 10px rgba(0,0,0,0.8); }
@media (min-width: 768px) { .wood-panel { border-width: 8px; box-shadow: inset 0 0 20px rgba(0,0,0,0.5), 0 10px 20px rgba(0,0,0,0.8); } }
.wanted-poster { background-color: #e8dcc4; border: 2px solid #b8a07e; box-shadow: inset 0 0 30px rgba(139, 69, 19, 0.2), 0 3px 10px rgba(0,0,0,0.5); background-image: radial-gradient(circle, transparent 20%, #e8dcc4 20%, #e8dcc4 80%, transparent 80%, transparent), radial-gradient(circle, transparent 20%, #e8dcc4 20%, #e8dcc4 80%, transparent 80%, transparent) 25px 25px; background-size: 50px 50px; }
.wanted-text { color: #4a2511; text-shadow: 1px 1px 0px rgba(255,255,255,0.8); }
.western-btn { background-color: #8b2500; color: #e8dcc4; border: 3px solid #4a1500; box-shadow: 2px 2px 0px #3a1000; transition: all 0.1s; cursor: pointer; }
@media (min-width: 768px) { .western-btn { border-width: 4px; box-shadow: 3px 3px 0px #3a1000; } }
.western-btn:active { box-shadow: 0px 0px 0px #3a1000; transform: translate(2px, 2px); }
.western-btn-alt { background-color: #d3bfa1; color: #4a2511; border-color: #8b4513; cursor: pointer; transition: all 0.1s; }
.western-btn-alt:active { transform: translate(2px, 2px); }
</style>
