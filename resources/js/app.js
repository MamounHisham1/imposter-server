import { createInertiaApp, router } from '@inertiajs/vue3';
import { createSSRApp, h } from 'vue';
import { createI18n } from 'vue-i18n';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import Toast from './Components/Toast.vue';
import { useToast } from './Composables/useToast';
import '../css/app.css';

import en from './i18n/en.json';
import ar from './i18n/ar.json';

const i18n = createI18n({
    legacy: false,
    locale: document.documentElement.lang || 'en',
    fallbackLocale: 'en',
    messages: { en, ar },
});

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    auth: {
        headers: {
            'X-XSRF-TOKEN': document.cookie
                .split('; ')
                .find((row) => row.startsWith('XSRF-TOKEN='))
                ?.split('=')[1],
        },
    },
});

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        const app = createSSRApp(App, props);
        app.use(plugin);
        app.use(i18n);

        if (props.initialPage?.props?.room?.language) {
            i18n.global.locale.value = props.initialPage.props.room.language;
            document.documentElement.lang = props.initialPage.props.room.language;
            document.documentElement.dir = props.initialPage.props.room.language === 'ar' ? 'rtl' : 'ltr';
        }

        app.component('Toast', Toast);
        app.mount(el);

        // Show server errors as toasts on every Inertia navigation
        const { error: toastError } = useToast();
        router.on('finish', (event) => {
            const page = event.detail?.page || event.page;
            const errors = page?.props?.errors || {};
            Object.values(errors).forEach((msg) => {
                if (msg) toastError(msg);
            });
        });

        // Register Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js');
            });
        }
    },
    progress: {
        color: '#00ff41',
    },
});
