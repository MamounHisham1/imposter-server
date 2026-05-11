import { ref } from 'vue';

const toasts = ref([]);
let nextId = 0;

export function useToast() {
    function show(message, type = 'error', duration = 4000) {
        const id = nextId++;
        toasts.value.push({ id, message, type });
        if (duration > 0) {
            setTimeout(() => {
                toasts.value = toasts.value.filter((t) => t.id !== id);
            }, duration);
        }
    }

    function success(message, duration = 4000) {
        show(message, 'success', duration);
    }

    function error(message, duration = 4000) {
        show(message, 'error', duration);
    }

    return { toasts, show, success, error };
}
