<script setup>
import { computed } from 'vue';
import { useForm, Link, router, Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useToast } from '../Composables/useToast';
import { AVATAR_BASE, getLayerStyle } from '../Composables/useAvatarConfig';
import AvatarDisplay from '../Components/AvatarDisplay.vue';
import NavBar from '../Components/NavBar.vue';

const { t } = useI18n();
const { error: toastError, success: toastSuccess } = useToast();

const props = defineProps({
    shopItems: { type: Object, default: () => ({ paid: {}, costumes: [] }) },
    inventory: { type: Object, default: () => ({ elements: [], costumes: [] }) },
    credits: { type: Number, default: 0 },
});

const ownedSet = computed(() => new Set(props.inventory.elements || []));
const ownedCostumeSet = computed(() => new Set(props.inventory.costumes || []));

const paidByLayer = computed(() => {
    const layers = { eyes: [], hair: [], beard: [] };
    for (const [filename, price] of Object.entries(props.shopItems.paid || {})) {
        let layer = 'eyes';
        if (filename.startsWith('hair') || filename.startsWith('haur')) layer = 'hair';
        else if (filename.startsWith('beard')) layer = 'beard';
        layers[layer].push({ filename, price });
    }
    return layers;
});

function buyElement(filename) {
    router.post('/shop/buy/element', { filename }, {
        preserveScroll: true,
        onSuccess: () => toastSuccess('Item purchased!'),
        onError: (errors) => {
            const msg = Object.values(errors)[0];
            if (msg) toastError(msg);
        },
    });
}

function buyCostume(costumeId) {
    router.post('/shop/buy/costume', { costume_id: costumeId }, {
        preserveScroll: true,
        onSuccess: () => toastSuccess('Costume purchased!'),
        onError: (errors) => {
            const msg = Object.values(errors)[0];
            if (msg) toastError(msg);
        },
    });
}
</script>

<template>
    <Head>
        <title>Avatar Shop — Traitor (الخائن) Game</title>
        <meta name="description" content="Customize your Traitor game avatar! Buy hairstyles, eyewear, chin styles, and costumes with credits. Personalize your Wild West character." head-key="description" />
    </Head>
    <div class="min-h-screen flex flex-col items-center justify-center p-4">
        <NavBar />

        <div class="wanted-poster p-6 md:p-8 max-w-2xl w-full">
            <div class="flex justify-between items-center border-b-2 border-dashed border-[#8b4513] pb-4 mb-6">
                <Link href="/" class="flex items-center gap-1.5 px-3 py-1.5 border-2 border-[#8b4513] bg-[#d3bfa1] text-[#4a2511] font-bold text-sm uppercase tracking-wider transition-all hover:bg-[#c4af8e] hover:shadow-md active:translate-y-px no-underline" title="Home">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1" /></svg>
                    <span class="hidden sm:inline">{{ t('back_to_lobby') }}</span>
                </Link>
                <h1 class="text-3xl wanted-text uppercase">{{ t('shop') }}</h1>
                <Link href="/credits" class="western-btn-alt px-3 py-1 border-2 text-sm flex items-center gap-1">
                    <span class="font-sans font-bold">{{ credits }}</span> {{ t('credits') }}
                </Link>
            </div>

            <!-- Elements Section -->
            <div class="mb-8">
                <h2 class="text-2xl text-[#8b4513] mb-4 border-b border-dashed border-[#b8a07e] pb-2">{{ t('elements') }}</h2>

                <div v-for="(items, layer) in paidByLayer" :key="layer" class="mb-4">
                    <h3 class="text-lg text-[#4a2511] mb-2">{{ t('avatar_' + layer) }}</h3>
                    <div v-if="items.length === 0" class="text-[#8b6914] text-sm">No paid items</div>
                    <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div v-for="item in items" :key="item.filename"
                            class="bg-[#d3bfa1]/50 border-2 border-dashed border-[#b8a07e] p-3 rounded-lg flex items-center gap-3">
                            <div class="w-12 h-12 rounded-md overflow-hidden bg-[#d3bfa1] border border-[#b8a07e] flex-shrink-0">
                                <img :src="`/avatars/${item.filename}`" class="w-full h-full object-contain" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs text-[#8b6914] truncate">{{ item.filename }}</div>
                                <div class="font-bold text-[#8b6914] font-sans">{{ item.price }} {{ t('credits') }}</div>
                            </div>
                            <button v-if="ownedSet.has(item.filename)"
                                class="text-green-700 text-xs font-bold border border-green-700 rounded px-2 py-1 bg-green-50 cursor-default">
                                {{ t('owned') }}
                            </button>
                            <button v-else
                                @click="buyElement(item.filename)"
                                :disabled="credits < item.price"
                                class="western-btn text-xs px-2 py-1 disabled:opacity-40">
                                {{ t('buy') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Costumes Section -->
            <div>
                <h2 class="text-2xl text-[#8b4513] mb-4 border-b border-dashed border-[#b8a07e] pb-2">{{ t('costumes') }}</h2>
                <div v-if="(shopItems.costumes || []).length === 0" class="text-[#8b6914]">No costumes available</div>
                <div v-else class="space-y-3">
                    <div v-for="costume in shopItems.costumes" :key="costume.id"
                        class="bg-[#d3bfa1]/50 border-2 border-dashed border-[#b8a07e] p-4 rounded-lg flex items-center gap-4">
                        <AvatarDisplay :avatar="{ head: costume.head, eyes: costume.items?.eyes, hair: costume.items?.hair, beard: costume.items?.beard }" :size="64" />
                        <div class="flex-1">
                            <div class="text-lg text-[#4a2511]">{{ costume.name }}</div>
                            <div class="text-sm text-[#8b6914]">{{ costume.price }} {{ t('credits') }}</div>
                            <div class="text-xs text-[#8b6914]">
                                <span v-if="costume.items?.eyes">eyes</span>
                                <span v-if="costume.items?.hair"> hair</span>
                                <span v-if="costume.items?.beard"> beard</span>
                            </div>
                        </div>
                        <button v-if="ownedCostumeSet.has(costume.id)"
                            class="text-green-700 text-sm font-bold border border-green-700 rounded px-3 py-1 bg-green-50 cursor-default">
                            {{ t('owned') }}
                        </button>
                        <button v-else
                            @click="buyCostume(costume.id)"
                            :disabled="credits < costume.price"
                            class="western-btn text-sm px-3 py-1 disabled:opacity-40">
                            {{ t('buy') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.wanted-poster { background-color: #e8dcc4; border: 2px solid #b8a07e; box-shadow: inset 0 0 30px rgba(139, 69, 19, 0.2), 0 3px 10px rgba(0,0,0,0.5); }
.wanted-text { color: #4a2511; text-shadow: 1px 1px 0px rgba(255,255,255,0.8); font-family: 'Lalezar', cursive; }
.western-btn { background-color: #8b2500; color: #e8dcc4; border: 3px solid #4a1500; cursor: pointer; font-family: 'Lalezar', cursive; }
.western-btn:active:not(:disabled) { transform: translate(1px, 1px); }
.western-btn-alt { background-color: #d3bfa1; color: #4a2511; border-color: #8b4513; cursor: pointer; font-family: 'Lalezar', cursive; text-decoration: none; }
</style>
