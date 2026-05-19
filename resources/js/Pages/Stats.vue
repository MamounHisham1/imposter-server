<script setup>
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            games_played: 0,
            wins_as_crew: 0,
            wins_as_imposter: 0,
            win_rate: 0,
        }),
    },
    recent_games: {
        type: Array,
        default: () => [],
    },
    nickname: {
        type: String,
        default: '',
    },
    auth: {
        type: Object,
        default: () => ({ user: null }),
    },
});

function backToHome() {
    router.visit('/');
}
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center p-2 md:p-4">
        <!-- Auth Widget -->
        <div v-if="auth?.user" class="fixed top-3 right-3 z-50 flex items-center gap-3 bg-[#5c3a21] border-2 border-[#3a2010] rounded-lg px-3 py-2 shadow-lg">
            <span class="text-[#d3bfa1] text-sm font-sans">{{ auth.user.nickname }}</span>
            <a href="/shop" class="bg-[#8b6914] text-[#1a0e08] text-xs font-bold px-2 py-0.5 rounded-full no-underline hover:bg-[#a07818]">{{ auth.user.credits }} {{ t('credits') }}</a>
            <a href="/stats" class="text-[#8b6914] text-sm hover:text-[#d3bfa1] no-underline font-bold">{{ t('stats') }}</a>
            <button @click="router.post('/logout')" class="text-[#8b6914] text-sm hover:text-[#d3bfa1] cursor-pointer">{{ t('logout') }}</button>
        </div>
        <div v-else class="fixed top-3 right-3 z-50 flex items-center gap-2 bg-[#5c3a21] border-2 border-[#3a2010] rounded-lg px-3 py-2 shadow-lg">
            <span class="text-[#d3bfa1] text-sm">{{ nickname }}</span>
            <span class="text-[#8b6914]">|</span>
            <a href="/stats" class="text-[#8b6914] text-sm hover:text-[#f5e6d0] no-underline font-bold">{{ t('stats') }}</a>
        </div>

        <div class="text-center mb-4 md:mb-8 flex flex-col items-center">
            <img :src="'/logo.png'" alt="Traitor Logo" class="w-24 h-24 md:w-40 md:h-40 object-contain drop-shadow-2xl" />
        </div>

        <div class="wood-panel max-w-5xl w-full p-4 md:p-8 relative">
            <!-- Nails -->
            <div class="absolute top-2 left-2 md:top-4 md:left-4 w-3 h-3 md:w-4 md:h-4 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>
            <div class="absolute top-2 right-2 md:top-4 md:right-4 w-3 h-3 md:w-4 md:h-4 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>
            <div class="absolute bottom-2 left-2 md:bottom-4 md:left-4 w-3 h-3 md:w-4 md:h-4 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>
            <div class="absolute bottom-2 right-2 md:bottom-4 md:right-4 w-3 h-3 md:w-4 md:h-4 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>

            <div class="wanted-poster p-4 md:p-8 md:transform md:rotate-1">
                <!-- Header -->
                <header class="border-b-2 md:border-b-4 border-double border-[#8b4513] pb-4 md:pb-6 mb-6 text-center">
                    <h2 class="text-lg md:text-xl tracking-widest text-[#8b4513]">{{ t('stats') }}</h2>
                    <h1 class="text-4xl md:text-6xl wanted-text uppercase">{{ t('stats_title') }}</h1>
                    <p class="text-base md:text-lg text-gray-700 mt-2">{{ nickname }}</p>
                </header>

                <!-- Stats Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4 mb-8">
                    <div class="stat-card">
                        <div class="stat-value">{{ stats.games_played }}</div>
                        <div class="stat-label">{{ t('games_played') }}</div>
                    </div>
                    <div class="stat-card stat-crew">
                        <div class="stat-value">{{ stats.wins_as_crew }}</div>
                        <div class="stat-label">{{ t('wins_as_crew') }}</div>
                    </div>
                    <div class="stat-card stat-imposter">
                        <div class="stat-value">{{ stats.wins_as_imposter }}</div>
                        <div class="stat-label">{{ t('wins_as_imposter') }}</div>
                    </div>
                    <div class="stat-card stat-rate">
                        <div class="stat-value">{{ stats.win_rate }}%</div>
                        <div class="stat-label">{{ t('win_rate') }}</div>
                    </div>
                </div>

                <!-- Recent Games -->
                <div>
                    <h3 class="text-xl md:text-2xl text-[#4a2511] tracking-widest mb-4 text-center border-b border-dashed border-[#8b4513] pb-3">{{ t('recent_games') }}</h3>

                    <div v-if="recent_games.length === 0" class="text-center py-8">
                        <p class="text-lg text-gray-500 italic">{{ t('no_games_yet') }}</p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full western-table">
                            <thead>
                                <tr>
                                    <th>{{ t('date') }}</th>
                                    <th>{{ t('the_word') }}</th>
                                    <th>{{ t('role_crew') }}/{{ t('role_imposter') }}</th>
                                    <th>{{ t('won') }}/{{ t('lost') }}</th>
                                    <th>{{ t('score') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="game in recent_games" :key="game.id" class="game-row">
                                    <td class="text-xs md:text-sm">
                                        <div>{{ game.date }}</div>
                                        <div class="text-gray-500 text-xs">{{ game.time }}</div>
                                    </td>
                                    <td class="font-bold text-sm md:text-base">{{ game.word || '---' }}</td>
                                    <td>
                                        <span class="role-badge" :class="game.role === 'imposter' ? 'role-imposter' : 'role-crew'">
                                            {{ game.role === 'imposter' ? t('role_imposter') : t('role_crew') }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="result-badge" :class="game.won ? 'result-won' : 'result-lost'">
                                            {{ game.won ? t('won') : t('lost') }}
                                        </span>
                                    </td>
                                    <td class="text-center font-bold">{{ game.score }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Back button -->
                <div class="mt-8 pt-6 border-t border-dashed border-[#8b4513] text-center">
                    <button @click="backToHome" class="western-btn text-xl md:text-2xl px-8 py-2">
                        {{ t('back_to_lobby') }}
                    </button>
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
.western-btn:active:not(:disabled) { box-shadow: 0px 0px 0px #3a1000; transform: translate(2px, 2px); }

.stat-card {
    background: #f5efe3;
    border: 2px solid #b8a07e;
    padding: 12px 8px;
    text-align: center;
    position: relative;
    box-shadow: 1px 1px 3px rgba(0,0,0,0.15);
}
@media (min-width: 768px) {
    .stat-card { padding: 16px 12px; }
}
.stat-card::before {
    content: '';
    position: absolute;
    top: 3px;
    left: 50%;
    transform: translateX(-50%);
    width: 5px;
    height: 5px;
    background-color: #333;
    border-radius: 50%;
    box-shadow: 1px 1px 2px rgba(255,255,255,0.3), inset -1px -1px 2px rgba(0,0,0,0.8);
}
.stat-value {
    font-size: 2rem;
    line-height: 1;
    color: #4a2511;
    font-family: 'Lalezar', cursive;
}
@media (min-width: 768px) {
    .stat-value { font-size: 3rem; }
}
.stat-label {
    font-size: 0.7rem;
    color: #8b4513;
    margin-top: 4px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
@media (min-width: 768px) {
    .stat-label { font-size: 0.8rem; }
}
.stat-crew { border-color: #1b4a1b; }
.stat-crew .stat-value { color: #1b4a1b; }
.stat-imposter { border-color: #8b2500; }
.stat-imposter .stat-value { color: #8b2500; }
.stat-rate { border-color: #8b6914; }
.stat-rate .stat-value { color: #8b6914; }

.western-table {
    border-collapse: collapse;
    font-size: 0.85rem;
}
@media (min-width: 768px) {
    .western-table { font-size: 1rem; }
}
.western-table thead th {
    background: #d3bfa1;
    color: #4a2511;
    padding: 8px 6px;
    border-bottom: 2px solid #8b4513;
    text-align: center;
    font-family: 'Lalezar', cursive;
    font-size: 0.8rem;
    letter-spacing: 0.05em;
}
@media (min-width: 768px) {
    .western-table thead th { padding: 10px 12px; font-size: 0.9rem; }
}
.game-row {
    border-bottom: 1px dashed #b8a07e;
}
.game-row td {
    padding: 8px 6px;
    text-align: center;
    vertical-align: middle;
}
@media (min-width: 768px) {
    .game-row td { padding: 10px 12px; }
}
.game-row:hover {
    background: #f5efe3;
}

.role-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
@media (min-width: 768px) {
    .role-badge { font-size: 0.8rem; padding: 3px 10px; }
}
.role-crew {
    background: #d4edda;
    color: #1b4a1b;
    border: 1px solid #1b4a1b;
}
.role-imposter {
    background: #f8d7da;
    color: #8b2500;
    border: 1px solid #8b2500;
}

.result-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
@media (min-width: 768px) {
    .result-badge { font-size: 0.8rem; padding: 3px 10px; }
}
.result-won {
    background: #1b4a1b;
    color: #e8dcc4;
}
.result-lost {
    background: #8b2500;
    color: #e8dcc4;
}
</style>
