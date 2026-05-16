<script setup>
import { ref, onMounted, onUnmounted, watch, computed, nextTick } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useToast } from '../Composables/useToast';
import { useErrorToasts } from '../Composables/useErrorToasts';
import { useAvatarBuilder } from '../Composables/useAvatarBuilder';
import { AVATAR_BASE, getLayerStyle } from '../Composables/useAvatarConfig';

const { t, locale } = useI18n();
const { error: toastError } = useToast();
useErrorToasts();

const { state: avatarState, heads: avatarHeads, selectHead, getFilename: getAvatarFilename, isNone, setNone, prevItem, nextItem, getAvatarData: buildAvatarData } = useAvatarBuilder();

const avatarWrap = ref(null);
const previewSize = ref(120);

onMounted(() => {
    const update = () => {
        if (avatarWrap.value) previewSize.value = avatarWrap.value.offsetWidth || 120;
    };
    update();
    window.addEventListener('resize', update);
    onUnmounted(() => window.removeEventListener('resize', update));
});

function getAvatarLayerStyle(layer, filename) {
    if (!filename) return {};
    const al = getLayerStyle(layer, filename, previewSize.value);
    if (!al || al.display === 'none') return {};
    return { transform: al.transform };
}

const props = defineProps({
    rooms: {
        type: Array,
        default: () => [],
    },
});

const localRooms = ref([...props.rooms]);
const showSettings = ref(false);

onMounted(() => {
    if (window.Echo) {
        window.Echo.channel('public-rooms')
            .listen('.rooms.event', (e) => {
                switch (e.action) {
                    case 'created':
                        if (!localRooms.value.find((r) => r.id === e.room.id)) {
                            localRooms.value.unshift(e.room);
                        }
                        break;
                    case 'updated':
                        const idx = localRooms.value.findIndex((r) => r.id === e.room.id);
                        if (idx !== -1) {
                            localRooms.value[idx] = e.room;
                        } else {
                            localRooms.value.unshift(e.room);
                        }
                        break;
                    case 'removed':
                        localRooms.value = localRooms.value.filter((r) => r.id !== e.room.id);
                        break;
                }
            });
    }
});

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leaveChannel('public-rooms');
    }
});

const createForm = useForm({
    nickname: '',
    type: 'public',
    max_players: 6,
    rounds_per_game: 3,
    language: 'ar',
    avatar: {},
});

const joinForm = useForm({
    code: '',
    nickname: '',
    avatar: {},
});

// Sync nicknames
watch(() => createForm.nickname, (val) => {
    joinForm.nickname = val;
});
watch(() => joinForm.nickname, (val) => {
    createForm.nickname = val;
});

function toggleSettings() {
    showSettings.value = !showSettings.value;
}

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
            'X-XSRF-TOKEN': document.cookie.split('; ').find(r => r.startsWith('XSRF-TOKEN='))?.split('=')[1],
        },
        body: JSON.stringify({ locale: next }),
    });
}

function submitCreate() {
    createForm.avatar = buildAvatarData();
    createForm.post('/room', {
        preserveScroll: true,
        onError: (errors) => {
            const msg = Object.values(errors)[0];
            if (msg) toastError(msg);
        },
    });
}

function submitJoin() {
    joinForm.avatar = buildAvatarData();
    joinForm.post('/room/join', {
        preserveScroll: true,
        onError: (errors) => {
            const msg = Object.values(errors)[0];
            if (msg) toastError(msg);
        },
    });
}
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center p-2 md:p-4">
        <Toast />
        
        <div class="text-center mb-4 md:mb-8 flex flex-col items-center">
            <img :src="'/logo.png'" alt="Traitor Logo" class="w-40 h-40 md:w-64 md:h-64 object-contain drop-shadow-2xl" />
            <button @click="switchLocale" class="western-btn-alt mt-3 px-5 py-1 text-lg md:text-xl border-2 cursor-pointer tracking-wide">
                {{ locale === 'ar' ? 'EN' : 'عربي' }}
            </button>
        </div>

        <div class="wood-panel max-w-5xl w-full p-4 md:p-8 relative flex flex-col md:flex-row gap-6 md:gap-8">
            <div class="absolute top-2 left-2 md:top-4 md:left-4 w-3 h-3 md:w-4 md:h-4 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>
            <div class="absolute top-2 right-2 md:top-4 md:right-4 w-3 h-3 md:w-4 md:h-4 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>
            <div class="absolute bottom-2 left-2 md:bottom-4 md:left-4 w-3 h-3 md:w-4 md:h-4 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>
            <div class="absolute bottom-2 right-2 md:bottom-4 md:right-4 w-3 h-3 md:w-4 md:h-4 rounded-full bg-gray-800 shadow-sm border border-gray-900"></div>

            <!-- Create/Join Section -->
            <div class="wanted-poster p-6 md:p-8 md:transform md:rotate-1 text-center flex-1 z-10">
                <header class="border-b-2 md:border-b-4 border-double border-[#8b4513] pb-4 md:pb-6 mb-6 md:mb-8">
                    <h2 class="text-xl md:text-3xl tracking-widest mb-1 md:mb-2 text-[#8b4513]">{{ t('game') }}</h2>
                    <h1 class="text-5xl md:text-7xl wanted-text uppercase mb-1 md:mb-2">{{ t('title') }}</h1>
                    <p class="text-lg md:text-xl text-gray-700">{{ t('subtitle') }}</p>
                </header>

                <div class="space-y-6 md:space-y-8">
                    <div>
                        <label class="block text-lg md:text-xl mb-1 md:mb-2 text-[#8b4513]">{{ t('nickname') }}:</label>
                        <input v-model="createForm.nickname" type="text" class="western-input w-full text-2xl md:text-3xl py-1 md:py-2 text-center" :placeholder="t('nickname')" maxlength="20" />
                    </div>

                    <!-- Avatar Builder -->
                    <div class="mt-4">
                        <!-- Preview -->
                        <div class="flex justify-center mb-3">
                            <div class="avatar-preview-wrap" ref="avatarWrap">
                                <img :src="`/avatars/${avatarHeads[avatarState.head] || avatarHeads[0]}`" class="avatar-layer" />
                                <img v-if="getAvatarFilename('beard')" :src="`/avatars/${getAvatarFilename('beard')}`"
                                    class="avatar-layer"
                                    :style="getAvatarLayerStyle('beard', getAvatarFilename('beard'))" />
                                <img v-if="getAvatarFilename('eyes')" :src="`/avatars/${getAvatarFilename('eyes')}`"
                                    class="avatar-layer"
                                    :style="getAvatarLayerStyle('eyes', getAvatarFilename('eyes'))" />
                                <img v-if="getAvatarFilename('hair')" :src="`/avatars/${getAvatarFilename('hair')}`"
                                    class="avatar-layer"
                                    :style="getAvatarLayerStyle('hair', getAvatarFilename('hair'))" />
                            </div>
                        </div>

                        <!-- Head colors -->
                        <div class="flex justify-center gap-2 mb-3 flex-wrap">
                            <div v-for="(head, i) in avatarHeads" :key="head"
                                class="head-swatch" :class="{ active: avatarState.head === i }"
                                @click="selectHead(i)">
                                <img :src="`/avatars/${head}`" alt="" />
                            </div>
                        </div>

                        <!-- Selector rows -->
                        <div class="space-y-2">
                            <div v-for="layer in ['hair', 'eyes', 'beard']" :key="layer" class="selector-row">
                                <button class="sel-btn" @click="prevItem(layer)" type="button">&#8249;</button>
                                <span class="sel-label">{{ t('avatar_' + layer) }}</span>
                                <button class="none-btn" :class="{ active: isNone(layer) }" @click="setNone(layer)" type="button">{{ t('avatar_none') }}</button>
                                <button class="sel-btn" @click="nextItem(layer)" type="button">&#8250;</button>
                            </div>
                        </div>
                    </div>

                    <!-- Expandable Settings for Create -->
                    <div v-if="showSettings" class="bg-[#d3bfa1]/50 border-2 border-dashed border-[#8b4513] p-4 text-right space-y-4">
                        <div>
                            <label class="block text-lg mb-1 text-[#4a2511]">{{ t('public') }} / {{ t('private') }}:</label>
                            <div class="flex gap-2">
                                <button type="button" @click="createForm.type = 'public'" class="western-btn-alt px-4 py-1 flex-1 text-xl border-2" :class="createForm.type === 'public' ? 'selected-opt' : ''">{{ t('public') }}</button>
                                <button type="button" @click="createForm.type = 'private'" class="western-btn-alt px-4 py-1 flex-1 text-xl border-2" :class="createForm.type === 'private' ? 'selected-opt' : ''">{{ t('private') }}</button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-lg mb-1 text-[#4a2511]">{{ t('language') }}:</label>
                            <div class="flex gap-2">
                                <button type="button" @click="createForm.language = 'ar'" class="western-btn-alt px-4 py-1 flex-1 text-xl border-2" :class="createForm.language === 'ar' ? 'selected-opt' : ''">{{ t('arabic') }}</button>
                                <button type="button" @click="createForm.language = 'en'" class="western-btn-alt px-4 py-1 flex-1 text-xl border-2" :class="createForm.language === 'en' ? 'selected-opt' : ''">{{ t('english') }}</button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-lg mb-1 text-[#4a2511]">{{ t('max_players') }}: <span class="text-[#8b2500]">{{ createForm.max_players }}</span></label>
                            <input v-model.number="createForm.max_players" type="range" min="3" max="10" class="w-full accent-[#8b2500]" />
                        </div>
                        <div>
                            <label class="block text-lg mb-1 text-[#4a2511]">{{ t('rounds') }}: <span class="text-[#8b2500]">{{ createForm.rounds_per_game }}</span></label>
                            <input v-model.number="createForm.rounds_per_game" type="range" min="1" max="5" class="w-full accent-[#8b2500]" />
                        </div>
                    </div>

                    <div class="pt-4 md:pt-6 border-t border-dashed border-[#8b4513] space-y-4 md:space-y-6">
                        <div class="flex gap-2">
                            <button @click="showSettings ? submitCreate() : toggleSettings()" :disabled="createForm.processing" class="western-btn text-xl md:text-3xl px-6 md:px-8 py-2 md:py-3 flex-1 disabled:opacity-50">
                                {{ showSettings ? t('create_room') : t('create_room') }}
                            </button>
                            <button v-if="showSettings" @click="toggleSettings" type="button" class="western-btn-alt px-4 text-xl border-2">{{ t('cancel') }}</button>
                        </div>
                        
                        <div class="relative">
                            <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-[#8b4513]"></div></div>
                            <div class="relative flex justify-center"><span class="bg-[#e8dcc4] px-3 md:px-4 text-base md:text-lg text-[#8b4513]">{{ t('or') }}</span></div>
                        </div>

                        <form @submit.prevent="submitJoin" class="flex flex-col sm:flex-row gap-2">
                            <input v-model="joinForm.code" type="text" class="western-input flex-1 text-xl md:text-2xl text-center uppercase tracking-widest" :placeholder="t('room_code')" maxlength="6" />
                            <button type="submit" :disabled="joinForm.processing" class="western-btn western-btn-alt text-xl md:text-2xl px-4 py-2 disabled:opacity-50">{{ t('join_room') }}</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Bounty Board (Public Rooms) -->
            <div class="bounty-board flex-1 md:transform md:-rotate-1 flex flex-col h-[350px] sm:h-[400px] md:h-auto md:max-h-[600px] z-10">
                <h2 class="text-xl md:text-3xl text-center text-[#d3bfa1] mb-4 md:mb-6 border-b border-dashed border-[#d3bfa1] pb-2 tracking-widest">{{ t('public_rooms') }}</h2>
                
                <div v-if="localRooms.length === 0" class="text-center text-[#d3bfa1]/50 text-xl py-8 font-sans">
                    {{ t('no_rooms') }}
                </div>

                <div v-else class="overflow-y-auto pr-1 md:pr-2 space-y-3 md:space-y-4 flex-1 scrollbar-western">
                    <!-- Room items -->
                    <div v-for="(room, index) in localRooms" :key="room.id" 
                        class="bounty-item transition-transform"
                        :class="room.players_count >= room.max_players ? 'opacity-70' : 'hover:scale-105 cursor-pointer'"
                        @click="if(room.players_count < room.max_players) { joinForm.code = room.code; submitJoin(); }"
                        :style="`transform: rotate(${index % 2 === 0 ? '1deg' : '-1deg'});`"
                    >
                        <div v-if="room.players_count >= room.max_players" class="absolute top-0 right-0 w-full h-full bg-[#8b2500]/10 flex items-center justify-center z-10 pointer-events-none">
                            <div class="border-2 md:border-4 border-[#8b2500] text-[#8b2500] text-xl md:text-3xl px-2 md:px-4 transform -rotate-12 bg-[#e8dcc4]/80">{{ t('full') }}</div>
                        </div>
                        <div class="flex justify-between items-end mt-1 md:mt-2">
                            <div>
                                <div class="text-xl md:text-2xl text-[#4a2511] font-sans font-bold uppercase tracking-widest">{{ room.code }}</div>
                                <div class="text-sm md:text-lg text-gray-700 mt-1">{{ t('player_count', { current: room.players_count || 0, max: room.max_players || 6 }) }}</div>
                            </div>
                            <button :disabled="room.players_count >= room.max_players" class="western-btn text-lg md:text-xl px-3 md:px-4 py-1 disabled:bg-gray-600 disabled:border-gray-800 disabled:text-gray-400 disabled:cursor-not-allowed">{{ t('join') }}</button>
                        </div>
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
.western-input { background: transparent; border: none; border-bottom: 2px dashed #8b4513; color: #4a2511; font-family: 'Lalezar', cursive; }
@media (min-width: 768px) { .western-input { border-bottom-width: 3px; } }
.western-input:focus { outline: none; border-bottom-style: solid; }
.western-btn { background-color: #8b2500; color: #e8dcc4; border: 3px solid #4a1500; box-shadow: 2px 2px 0px #3a1000; transition: all 0.1s; cursor: pointer; }
@media (min-width: 768px) { .western-btn { border-width: 4px; box-shadow: 3px 3px 0px #3a1000; } }
.western-btn:active:not(:disabled) { box-shadow: 0px 0px 0px #3a1000; transform: translate(2px, 2px); }
.western-btn-alt { background-color: #d3bfa1; color: #4a2511; border-color: #8b4513; cursor: pointer; transition: all 0.1s; }
.western-btn-alt:active:not(:disabled) { transform: translate(2px, 2px); }
.selected-opt { background-color: #8b4513 !important; color: #e8dcc4 !important; border-color: #4a1500 !important; box-shadow: inset 0 0 8px rgba(0,0,0,0.3); }

.bounty-board { background-color: #5c3a21; border: 3px solid #3a2010; box-shadow: inset 0 0 10px rgba(0,0,0,0.8); padding: 10px; }
@media (min-width: 768px) { .bounty-board { border-width: 4px; box-shadow: inset 0 0 20px rgba(0,0,0,0.8); padding: 15px; } }
.bounty-item { background-color: #e8dcc4; border: 2px dashed #8b4513; padding: 8px 12px; position: relative; }
@media (min-width: 768px) { .bounty-item { padding: 10px 15px; } }
.bounty-item::before { content: ''; position: absolute; top: 4px; left: 50%; transform: translateX(-50%); width: 6px; height: 6px; background-color: #333; border-radius: 50%; box-shadow: 1px 1px 2px rgba(255,255,255,0.3), inset -1px -1px 2px rgba(0,0,0,0.8); }
@media (min-width: 768px) { .bounty-item::before { top: 5px; width: 8px; height: 8px; } }

.scrollbar-western::-webkit-scrollbar { width: 6px; }
@media (min-width: 768px) { .scrollbar-western::-webkit-scrollbar { width: 8px; } }
.scrollbar-western::-webkit-scrollbar-track { background: #3a2010; }
.scrollbar-western::-webkit-scrollbar-thumb { background: #8b5a2b; border-radius: 4px; }

.avatar-preview-wrap { width: 120px; height: 120px; position: relative; margin: 0 auto 10px; border-radius: 10px; overflow: hidden; border: 3px solid #8b6914; box-shadow: 0 4px 12px rgba(0,0,0,0.3); background: #d3bfa1; }
@media (min-width: 768px) { .avatar-preview-wrap { width: 150px; height: 150px; } }
.avatar-layer { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain; pointer-events: none; }
.head-swatch { width: 32px; height: 32px; border-radius: 50%; overflow: hidden; border: 2px solid #b8a07e; cursor: pointer; transition: border-color 0.15s; }
@media (min-width: 768px) { .head-swatch { width: 38px; height: 38px; } }
.head-swatch:hover { border-color: #8b4513; }
.head-swatch.active { border-color: #4a2511; box-shadow: 0 0 6px rgba(74,37,17,0.4); }
.head-swatch img { width: 100%; height: 100%; object-fit: cover; pointer-events: none; }
.selector-row { display: flex; align-items: center; justify-content: center; gap: 6px; margin-bottom: 8px; }
.sel-label { color: #8b4513; font-size: 13px; width: 48px; text-align: center; }
@media (min-width: 768px) { .sel-label { font-size: 15px; width: 55px; } }
.sel-btn { width: 34px; height: 34px; border-radius: 6px; border: 2px solid #4a1500; background: #8b2500; color: #e8dcc4; font-family: 'Lalezar', cursive; font-size: 18px; cursor: pointer; box-shadow: 1px 1px 0 #3a1000; display: flex; align-items: center; justify-content: center; transition: all 0.1s; }
@media (min-width: 768px) { .sel-btn { width: 38px; height: 38px; } }
.sel-btn:active { box-shadow: none; transform: translate(1px, 1px); }
.none-btn { width: 34px; height: 34px; border-radius: 6px; border: 2px solid #b8a07e; background: #d3bfa1; color: #8b4513; font-family: 'Lalezar', cursive; font-size: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
@media (min-width: 768px) { .none-btn { width: 38px; height: 38px; font-size: 11px; } }
.none-btn:hover { border-color: #8b4513; }
.none-btn.active { background: #8b4513; color: #e8dcc4; border-color: #4a1500; }
</style>
