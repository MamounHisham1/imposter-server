<script setup>
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useToast } from '../Composables/useToast';

const { t } = useI18n();
const { error: toastError } = useToast();

const form = useForm({
    nickname: '',
    email: '',
    password: '',
});

function submit() {
    form.post('/register', {
        onError: (errors) => {
            const msg = Object.values(errors)[0];
            if (msg) toastError(msg);
        },
    });
}
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center p-4">
        <div class="wanted-poster p-8 max-w-sm w-full text-center">
            <h1 class="text-4xl wanted-text uppercase mb-6">{{ t('register') }}</h1>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-lg mb-1 text-[#8b4513]">{{ t('nickname') }}</label>
                    <input v-model="form.nickname" type="text" class="western-input w-full text-xl py-1 text-center" :placeholder="t('nickname')" maxlength="20" />
                </div>
                <div>
                    <label class="block text-lg mb-1 text-[#8b4513]">{{ t('email') }} <span class="text-sm text-[#8b6914]">({{ t('optional') }})</span></label>
                    <input v-model="form.email" type="email" class="western-input w-full text-xl py-1 text-center" :placeholder="t('email')" />
                </div>
                <div>
                    <label class="block text-lg mb-1 text-[#8b4513]">{{ t('password') }}</label>
                    <input v-model="form.password" type="password" class="western-input w-full text-xl py-1 text-center" :placeholder="t('password')" />
                </div>
                <button type="submit" :disabled="form.processing" class="western-btn text-xl px-6 py-2 w-full disabled:opacity-50">
                    {{ t('register') }}
                </button>
            </form>

            <div class="mt-6 space-y-2">
                <a href="/auth/google" class="block western-btn-alt text-lg px-4 py-2 border-2 text-center">
                    {{ t('login_with_google') }}
                </a>
                <a href="/" class="block text-[#8b4513] text-lg hover:underline">{{ t('or_continue_as_guest') }}</a>
                <a href="/login" class="block text-[#8b4513] text-lg hover:underline">{{ t('already_have_account') }}</a>
            </div>
        </div>
    </div>
</template>

<style scoped>
.wanted-poster { background-color: #e8dcc4; border: 2px solid #b8a07e; box-shadow: inset 0 0 30px rgba(139, 69, 19, 0.2), 0 3px 10px rgba(0,0,0,0.5); }
.wanted-text { color: #4a2511; text-shadow: 1px 1px 0px rgba(255,255,255,0.8); font-family: 'Lalezar', cursive; }
.western-input { background: transparent; border: none; border-bottom: 2px dashed #8b4513; color: #4a2511; font-family: 'Lalezar', cursive; }
.western-input:focus { outline: none; border-bottom-style: solid; }
.western-btn { background-color: #8b2500; color: #e8dcc4; border: 3px solid #4a1500; box-shadow: 2px 2px 0px #3a1000; cursor: pointer; font-family: 'Lalezar', cursive; }
.western-btn:active:not(:disabled) { box-shadow: none; transform: translate(2px, 2px); }
.western-btn-alt { background-color: #d3bfa1; color: #4a2511; border-color: #8b4513; cursor: pointer; transition: all 0.1s; font-family: 'Lalezar', cursive; text-decoration: none; display: block; }
.western-btn-alt:active:not(:disabled) { transform: translate(2px, 2px); }
</style>
