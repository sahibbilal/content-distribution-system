<template>
    <div>
        <!-- Toasts -->
        <div class="pointer-events-none fixed inset-x-0 top-4 z-[70] flex flex-col items-center gap-2 px-4 sm:items-end sm:px-6" aria-live="polite">
            <TransitionGroup enter-active-class="transition duration-200" enter-from-class="translate-y-2 opacity-0"
                leave-active-class="transition duration-150" leave-to-class="opacity-0">
                <div v-for="t in toasts" :key="t.id"
                    class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl bg-white p-4 shadow-pop ring-1 ring-slate-900/10">
                    <CheckCircle2 v-if="t.type === 'success'" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                    <XCircle v-else-if="t.type === 'error'" class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
                    <Info v-else class="mt-0.5 h-5 w-5 shrink-0 text-brand-600" />
                    <p class="flex-1 text-sm text-slate-700">{{ t.message }}</p>
                    <button type="button" class="text-slate-400 hover:text-slate-600" aria-label="Dismiss" @click="dismissToast(t.id)">
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </TransitionGroup>
        </div>

        <!-- Confirm dialog -->
        <Transition enter-active-class="transition duration-150" enter-from-class="opacity-0" leave-active-class="transition duration-100" leave-to-class="opacity-0">
            <div v-if="confirmState" class="fixed inset-0 z-[80] flex items-center justify-center p-4" role="dialog" aria-modal="true" @keydown.esc="resolveConfirm(false)">
                <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="resolveConfirm(false)"></div>
                <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-pop">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                            :class="confirmState.danger ? 'bg-red-100 text-red-600' : 'bg-brand-100 text-brand-600'">
                            <AlertTriangle class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">{{ confirmState.title }}</h3>
                            <p v-if="confirmState.message" class="mt-1 text-sm text-slate-600">{{ confirmState.message }}</p>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" class="btn btn-secondary" @click="resolveConfirm(false)">Cancel</button>
                        <button type="button" class="btn" :class="confirmState.danger ? 'btn-danger' : 'btn-primary'" @click="resolveConfirm(true)">
                            {{ confirmState.confirmText }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script>
import { AlertTriangle, CheckCircle2, Info, X, XCircle } from 'lucide-vue-next';
import { toasts, confirmState, dismissToast, resolveConfirm } from '../feedback.js';

export default {
    name: 'FeedbackHost',
    components: { AlertTriangle, CheckCircle2, Info, X, XCircle },
    setup() {
        return { toasts, confirmState, dismissToast, resolveConfirm };
    },
};
</script>
