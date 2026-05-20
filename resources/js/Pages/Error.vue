<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Head, Link, usePage } from '@inertiajs/vue3';
import NavBar from '../Components/NavBar.vue';
import SiteFooter from '../Components/SiteFooter.vue';

const { t, locale } = useI18n();

const props = defineProps({
    status: {
        type: Number,
        required: true,
    },
});

const errorDetails = computed(() => {
    const code = props.status;
    const validCodes = [400, 403, 404, 500];
    const isSupported = validCodes.includes(code);
    
    return {
        code: code,
        title: isSupported ? t(`error_page_${code}_title`) : t('error_page_generic_title'),
        desc: isSupported ? t(`error_page_${code}_desc`) : t('error_page_generic_desc'),
        btnText: isSupported ? t(`error_page_${code}_btn`) : t('error_page_generic_btn'),
    };
});

function switchLocale() {
    const next = locale.value === 'ar' ? 'en' : 'ar';
    locale.value = next;
    document.documentElement.lang = next;
    document.documentElement.dir = next === 'ar' ? 'rtl' : 'ltr';
    localStorage.setItem('locale', next);
    fetch('/locale', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': document.cookie.split('; ').find(r => r.startsWith('XSRF-TOKEN='))?.split('=')[1] || '',
        },
        body: JSON.stringify({ locale: next }),
    }).then(() => {
        window.location.reload();
    });
}
</script>

<template>
    <Head>
        <title>{{ errorDetails.title }} — Traitor (الخائن)</title>
        <meta name="description" :content="errorDetails.desc" head-key="description" />
    </Head>
    
    <div class="min-h-screen flex flex-col items-center justify-center p-4 relative overflow-hidden select-none">
        <!-- Sun/Desert Sky Background Glow -->
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-amber-500/10 rounded-full blur-[120px] pointer-events-none"></div>

        <NavBar />

        <!-- Main Poster Panel -->
        <div class="wood-panel max-w-2xl w-full p-4 md:p-8 relative z-10 transition-all duration-300">
            <!-- Corner Bolts -->
            <div class="absolute top-2 left-2 md:top-4 md:left-4 w-3.5 h-3.5 rounded-full bg-zinc-800 shadow-lg border border-zinc-900 flex items-center justify-center">
                <div class="w-1.5 h-0.5 bg-zinc-600 rotate-45"></div>
            </div>
            <div class="absolute top-2 right-2 md:top-4 md:right-4 w-3.5 h-3.5 rounded-full bg-zinc-800 shadow-lg border border-zinc-900 flex items-center justify-center">
                <div class="w-1.5 h-0.5 bg-zinc-600 -rotate-45"></div>
            </div>
            <div class="absolute bottom-2 left-2 md:bottom-4 md:left-4 w-3.5 h-3.5 rounded-full bg-zinc-800 shadow-lg border border-zinc-900 flex items-center justify-center">
                <div class="w-1.5 h-0.5 bg-zinc-600 -rotate-12"></div>
            </div>
            <div class="absolute bottom-2 right-2 md:bottom-4 md:right-4 w-3.5 h-3.5 rounded-full bg-zinc-800 shadow-lg border border-zinc-900 flex items-center justify-center">
                <div class="w-1.5 h-0.5 bg-zinc-600 rotate-12"></div>
            </div>

            <!-- Inside Wanted Poster -->
            <div class="wanted-poster p-6 md:p-10 text-center flex flex-col items-center relative overflow-hidden">
                <!-- Watermark Background Lines -->
                <div class="absolute inset-0 bg-repeat opacity-[0.03] pointer-events-none" style="background-image: radial-gradient(#000 20%, transparent 20%); background-size: 15px 15px;"></div>

                <header class="border-b-4 border-double border-[#8b4513] pb-6 mb-8 w-full">
                    <h2 class="text-2xl md:text-3xl font-sans font-extrabold tracking-widest text-[#8b4513] uppercase mb-2">
                        {{ t('game') }} - STATUS: {{ props.status }}
                    </h2>
                    <h1 class="text-4xl md:text-6xl wanted-text uppercase mb-2 leading-none">
                        {{ errorDetails.title }}
                    </h1>
                </header>

                <!-- Interactive Themed Error Visuals -->
                <div class="visual-container w-full h-48 md:h-56 flex items-center justify-center relative mb-8 rounded-lg bg-[#ebd9b9] border border-[#d3bfa1] shadow-inner overflow-hidden">
                    
                    <!-- 404: Tumbleweed & Cactus -->
                    <template v-if="props.status === 404">
                        <div class="absolute bottom-4 left-1/4 w-8 h-24 cactus-group select-none">
                            <!-- Cactus Drawing -->
                            <div class="w-3 h-20 bg-[#5d7d59] border-2 border-[#3c5239] rounded-t-full relative">
                                <!-- Left Arm -->
                                <div class="absolute top-6 -left-4 w-5 h-8 border-t-2 border-l-2 border-r-0 border-[#3c5239] bg-[#5d7d59] rounded-tl-lg flex flex-col justify-end">
                                    <div class="w-3 h-4 bg-[#5d7d59] border-r-2 border-[#3c5239] rounded-t-full -mt-2 ml-[6px]"></div>
                                </div>
                                <!-- Right Arm -->
                                <div class="absolute top-10 -right-4 w-5 h-8 border-t-2 border-r-2 border-l-0 border-[#3c5239] bg-[#5d7d59] rounded-tr-lg">
                                    <div class="w-3 h-4 bg-[#5d7d59] border-l-2 border-[#3c5239] rounded-t-full -mt-2 -mr-1 float-right"></div>
                                </div>
                                <!-- Needles -->
                                <div class="absolute top-4 left-1/2 w-0.5 h-1 bg-[#3c5239]"></div>
                                <div class="absolute top-10 left-1 w-0.5 h-1 bg-[#3c5239]"></div>
                                <div class="absolute top-14 right-1 w-0.5 h-1 bg-[#3c5239]"></div>
                            </div>
                        </div>

                        <!-- Wind dust lines -->
                        <div class="absolute top-1/3 left-0 w-full h-12 wind-container pointer-events-none opacity-40">
                            <div class="wind-line w-24 h-0.5 bg-amber-800/30 rounded absolute top-2 left-[-100px] animate-wind-slow"></div>
                            <div class="wind-line w-36 h-0.5 bg-amber-800/30 rounded absolute top-8 left-[-150px] animate-wind-fast"></div>
                        </div>

                        <!-- Rolling Tumbleweed -->
                        <div class="absolute bottom-4 w-16 h-16 tumbleweed animate-tumble">
                            <svg viewBox="0 0 100 100" class="w-full h-full text-amber-900/60" fill="none" stroke="currentColor" stroke-width="6">
                                <circle cx="50" cy="50" r="45" stroke-dasharray="10 10" />
                                <circle cx="50" cy="50" r="35" stroke-dasharray="8 8" />
                                <circle cx="50" cy="50" r="25" stroke-dasharray="6 6" />
                                <path d="M50,5 L50,95 M5,50 L95,50 M18,18 L82,82 M18,82 L82,18" />
                                <path d="M30,10 C 40,40 60,60 70,90 M10,30 C 40,40 60,60 90,70" />
                            </svg>
                        </div>
                    </template>

                    <!-- 403: Jail Bars & Sheriff Star -->
                    <template v-else-if="props.status === 403">
                        <!-- Wooden Frame / Window View -->
                        <div class="absolute inset-0 flex flex-col justify-between p-1 bg-[#26160b]">
                            <!-- Sunset Glow Behind Bars -->
                            <div class="absolute inset-0 bg-gradient-to-t from-amber-700/40 via-red-900/40 to-transparent"></div>
                            <!-- Bars -->
                            <div class="absolute inset-0 flex justify-around px-8 pointer-events-none">
                                <div class="w-2.5 h-full bg-[#1c1c1c] border-x border-[#3a3a3a] shadow-[inset_1px_1px_5px_rgba(255,255,255,0.2)]"></div>
                                <div class="w-2.5 h-full bg-[#1c1c1c] border-x border-[#3a3a3a] shadow-[inset_1px_1px_5px_rgba(255,255,255,0.2)]"></div>
                                <div class="w-2.5 h-full bg-[#1c1c1c] border-x border-[#3a3a3a] shadow-[inset_1px_1px_5px_rgba(255,255,255,0.2)]"></div>
                                <div class="w-2.5 h-full bg-[#1c1c1c] border-x border-[#3a3a3a] shadow-[inset_1px_1px_5px_rgba(255,255,255,0.2)]"></div>
                            </div>
                            <!-- Shiny Golden Sheriff Star Badge -->
                            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-28 h-28 drop-shadow-[0_10px_20px_rgba(0,0,0,0.8)] animate-badge-glow">
                                <svg viewBox="0 0 100 100" class="w-full h-full text-amber-500 fill-current" stroke="#5c3f15" stroke-width="2">
                                    <polygon points="50,5 63,33 93,37 70,57 77,87 50,71 23,87 30,57 7,37 37,33" />
                                    <!-- Inner Circle -->
                                    <circle cx="50" cy="50" r="18" fill="#df9f28" stroke="#5c3f15" stroke-width="1.5" />
                                    <!-- Star Tips Circles -->
                                    <circle cx="50" cy="5" r="4" fill="#ffd700" stroke="#5c3f15" stroke-width="1" />
                                    <circle cx="93" cy="37" r="4" fill="#ffd700" stroke="#5c3f15" stroke-width="1" />
                                    <circle cx="77" cy="87" r="4" fill="#ffd700" stroke="#5c3f15" stroke-width="1" />
                                    <circle cx="23" cy="87" r="4" fill="#ffd700" stroke="#5c3f15" stroke-width="1" />
                                    <circle cx="7" cy="37" r="4" fill="#ffd700" stroke="#5c3f15" stroke-width="1" />
                                </svg>
                                <!-- Lock Icon overlaying badge -->
                                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-zinc-900 text-3xl font-sans mt-0.5">🔒</div>
                            </div>
                        </div>
                    </template>

                    <!-- 400: Broken Wagon Wheel -->
                    <template v-else-if="props.status === 400">
                        <div class="absolute bottom-2 flex flex-col items-center">
                            <!-- Cracked Desert Ground -->
                            <div class="w-64 h-2 bg-[#d6be96] border-b border-[#a89067] rounded-full opacity-60"></div>
                        </div>
                        <!-- Tilting and wobbly wagon wheel -->
                        <div class="w-32 h-32 relative transform rotate-[18deg] -translate-y-2 animate-wagon-wobble">
                            <svg viewBox="0 0 120 120" class="w-full h-full text-[#5c3a21] fill-none" stroke="currentColor" stroke-width="6">
                                <!-- Outer Wooden Rim -->
                                <circle cx="60" cy="60" r="50" stroke-width="8" />
                                <!-- Iron Outer Band -->
                                <circle cx="60" cy="60" r="54" stroke="#333" stroke-width="2" />
                                <!-- Spokes -->
                                <line x1="60" y1="10" x2="60" y2="110" />
                                <line x1="10" y1="60" x2="110" y2="60" />
                                <line x1="25" y1="25" x2="95" y2="95" />
                                <line x1="25" y1="95" x2="95" y2="25" />
                                <!-- Center Hub -->
                                <circle cx="60" cy="60" r="14" fill="#e8dcc4" stroke-width="5" />
                                <!-- Broken spoke overlay / cracks -->
                                <path d="M 60,60 L 95,95" stroke="#ebd9b9" stroke-width="4" stroke-linecap="round" />
                                <line x1="60" y1="22" x2="52" y2="28" stroke="#ebd9b9" stroke-width="3" />
                                <!-- Crack in wood -->
                                <path d="M 94,22 L 102,12" stroke="#333" stroke-width="2" />
                            </svg>
                        </div>
                        <div class="absolute top-1/4 left-1/3 text-[#4a2511] font-bold text-lg select-none opacity-70 animate-bounce">⚡</div>
                    </template>

                    <!-- 500: Saloon Brawl / Dynamite Barrel -->
                    <template v-else-if="props.status === 500">
                        <div class="absolute inset-0 bg-[#3a1505] flex items-center justify-center overflow-hidden">
                            <!-- Explosive fire background flash -->
                            <div class="absolute inset-0 bg-[#e65c00]/15 animate-flash-explosion"></div>
                            
                            <!-- Dynamite Box / Barrel -->
                            <div class="w-36 h-36 relative flex flex-col items-center justify-center animate-barrel-shake">
                                <!-- Wooden Barrel SVG -->
                                <svg viewBox="0 0 100 100" class="w-24 h-24 text-red-700 fill-current drop-shadow-[0_8px_16px_rgba(0,0,0,0.6)]">
                                    <rect x="25" y="10" width="50" height="80" rx="6" stroke="#2a0a00" stroke-width="3" />
                                    <!-- Steel Hoops -->
                                    <rect x="23" y="25" width="54" height="6" fill="#3a3a3a" stroke="#1c1c1c" stroke-width="1" />
                                    <rect x="23" y="65" width="54" height="6" fill="#3a3a3a" stroke="#1c1c1c" stroke-width="1" />
                                    <!-- TNT Text -->
                                    <text x="50" y="55" font-family="'Lalezar', cursive" font-size="20" font-weight="900" fill="#f5c830" stroke="#000" stroke-width="1" text-anchor="middle">TNT</text>
                                </svg>
                                
                                <!-- Burning Fuse -->
                                <div class="absolute top-[-25px] left-1/2 -translate-x-1/2 w-1 h-10 bg-zinc-800 rounded-t">
                                    <!-- Fuse spark -->
                                    <div class="absolute -top-3 -left-3.5 w-8 h-8 flex items-center justify-center animate-fuse-glow">
                                        <div class="w-3.5 h-3.5 bg-yellow-400 rounded-full border-2 border-amber-500 shadow-[0_0_12px_#ff9900] animate-ping"></div>
                                        <div class="w-2 h-2 bg-white rounded-full absolute"></div>
                                    </div>
                                    <div class="absolute -top-1 -left-1 w-3 h-3 bg-orange-500 rounded-full blur-[2px] animate-pulse"></div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Generic / Other errors: Skull & Badge -->
                    <template v-else>
                        <div class="text-6xl md:text-7xl animate-pulse">&#9760;</div>
                    </template>
                </div>

                <!-- Error Message Description -->
                <p class="text-xl md:text-2xl text-[#4a2511]/90 mb-8 font-sans font-medium max-w-md leading-relaxed">
                    {{ errorDetails.desc }}
                </p>

                <!-- Navigation buttons -->
                <div class="flex flex-col sm:flex-row gap-4 w-full justify-center">
                    <Link href="/" class="western-btn text-xl md:text-2xl px-8 py-3.5 no-underline flex items-center justify-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1" />
                        </svg>
                        {{ errorDetails.btnText }}
                    </Link>
                    <button @click="switchLocale" class="western-btn-alt border-2 border-[#8b4513] text-xl md:text-2xl px-6 py-3">
                        {{ locale === 'ar' ? 'English Version' : 'النسخة العربية' }}
                    </button>
                </div>
            </div>
        </div>

        <SiteFooter />
    </div>
</template>

<style scoped>
.wood-panel { 
    background-color: #8b5a2b; 
    border: 6px solid #5c3a21; 
    box-shadow: inset 0 0 15px rgba(0,0,0,0.6), 0 10px 25px rgba(0,0,0,0.8); 
    border-radius: 12px;
}
@media (min-width: 768px) { 
    .wood-panel { 
        border-width: 8px; 
        box-shadow: inset 0 0 25px rgba(0,0,0,0.6), 0 15px 35px rgba(0,0,0,0.9); 
    } 
}

.wanted-poster { 
    background-color: #e8dcc4; 
    border: 2px solid #b8a07e; 
    box-shadow: inset 0 0 30px rgba(139, 69, 19, 0.25), 0 5px 15px rgba(0,0,0,0.3); 
    background-image: radial-gradient(circle, transparent 20%, #e8dcc4 20%, #e8dcc4 80%, transparent 80%, transparent), radial-gradient(circle, transparent 20%, #e8dcc4 20%, #e8dcc4 80%, transparent 80%, transparent) 25px 25px; 
    background-size: 50px 50px; 
    border-radius: 6px;
}

.wanted-text { 
    color: #4a2511; 
    text-shadow: 2px 2px 0px rgba(255,255,255,0.7); 
}

.western-btn { 
    background-color: #8b2500; 
    color: #e8dcc4; 
    border: 3px solid #4a1500; 
    box-shadow: 3px 3px 0px #3a1000; 
    transition: all 0.1s ease-in-out; 
    cursor: pointer; 
    border-radius: 6px;
    font-weight: 700;
}
.western-btn:active { 
    box-shadow: 0px 0px 0px #3a1000; 
    transform: translate(3px, 3px); 
}

.western-btn-alt { 
    background-color: #d3bfa1; 
    color: #4a2511; 
    border-color: #8b4513; 
    cursor: pointer; 
    transition: all 0.1s ease-in-out; 
    border-radius: 6px;
    font-weight: 700;
}
.western-btn-alt:active { 
    transform: translate(2px, 2px); 
}

/* Animations for 404 Tumbleweed */
@keyframes tumble {
    0% {
        transform: translateX(-80px) rotate(0deg) translateY(0);
    }
    15% {
        transform: translateX(40px) rotate(90deg) translateY(-25px);
    }
    30% {
        transform: translateX(160px) rotate(180deg) translateY(0);
    }
    45% {
        transform: translateX(280px) rotate(270deg) translateY(-20px);
    }
    60% {
        transform: translateX(400px) rotate(360deg) translateY(0);
    }
    75% {
        transform: translateX(520px) rotate(450deg) translateY(-15px);
    }
    90% {
        transform: translateX(640px) rotate(540deg) translateY(0);
    }
    100% {
        transform: translateX(760px) rotate(630deg) translateY(-10px);
    }
}
.tumbleweed {
    animation: tumble 7s infinite linear;
}

@keyframes wind {
    0% {
        transform: translateX(0);
        opacity: 0;
    }
    10% {
        opacity: 0.5;
    }
    90% {
        opacity: 0.5;
    }
    100% {
        transform: translateX(900px);
        opacity: 0;
    }
}
.animate-wind-slow {
    animation: wind 5s infinite linear;
}
.animate-wind-fast {
    animation: wind 3.5s infinite linear 1.5s;
}

/* Animations for 403 Sheriff Star Glow */
@keyframes badge-glow {
    0%, 100% {
        filter: drop-shadow(0 0 10px rgba(245, 158, 11, 0.4));
    }
    50% {
        filter: drop-shadow(0 0 25px rgba(245, 158, 11, 0.8));
    }
}
.animate-badge-glow {
    animation: badge-glow 3s infinite ease-in-out;
}

/* Animations for 400 Wagon Wheel Wobble */
@keyframes wagon-wobble {
    0%, 100% {
        transform: rotate(18deg) translateY(0) scale(1);
    }
    50% {
        transform: rotate(23deg) translateY(-4px) scale(0.98);
    }
}
.animate-wagon-wobble {
    animation: wagon-wobble 2.5s infinite ease-in-out;
}

/* Animations for 500 Dynamite */
@keyframes flash-explosion {
    0%, 100% {
        opacity: 0.1;
    }
    50% {
        opacity: 0.4;
    }
}
.animate-flash-explosion {
    animation: flash-explosion 1.2s infinite ease-in-out;
}

@keyframes barrel-shake {
    0%, 100% { transform: translate(0, 0) rotate(0deg); }
    10% { transform: translate(-2px, -1px) rotate(-1deg); }
    20% { transform: translate(-1px, 2px) rotate(1deg); }
    30% { transform: translate(1px, -1px) rotate(0deg); }
    40% { transform: translate(-2px, 1px) rotate(-1deg); }
    50% { transform: translate(2px, -2px) rotate(1deg); }
    60% { transform: translate(1px, 2px) rotate(0deg); }
    70% { transform: translate(-1px, -1px) rotate(-1deg); }
    80% { transform: translate(2px, 1px) rotate(1deg); }
    90% { transform: translate(-1px, -2px) rotate(0deg); }
}
.animate-barrel-shake {
    animation: barrel-shake 0.4s infinite linear;
}

@keyframes fuse-glow {
    0%, 100% {
        transform: scale(0.9);
        opacity: 0.8;
    }
    50% {
        transform: scale(1.25);
        opacity: 1;
    }
}
.animate-fuse-glow {
    animation: fuse-glow 0.15s infinite ease-in-out;
}
</style>
