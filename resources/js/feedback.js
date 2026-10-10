import { ref } from 'vue';

// Shared state behind the toast and confirm dialog rendered by FeedbackHost.vue
export const toasts = ref([]);
export const confirmState = ref(null);

let nextId = 1;

export function toast(message, type = 'info', duration = 4000) {
    const id = nextId++;
    toasts.value.push({ id, message, type });
    setTimeout(() => dismissToast(id), duration);
}

export function dismissToast(id) {
    toasts.value = toasts.value.filter(t => t.id !== id);
}

// Resolves true when confirmed, false when cancelled
export function confirmDialog({ title = 'Are you sure?', message = '', confirmText = 'Confirm', danger = false } = {}) {
    return new Promise(resolve => {
        confirmState.value = { title, message, confirmText, danger, resolve };
    });
}

export function resolveConfirm(result) {
    confirmState.value?.resolve(result);
    confirmState.value = null;
}
