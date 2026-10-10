<template>
    <div class="grid min-h-screen lg:grid-cols-2">
        <!-- Brand panel -->
        <aside class="relative hidden overflow-hidden bg-brand-950 text-white lg:flex lg:flex-col lg:justify-between lg:p-12">
            <div class="pointer-events-none absolute -top-32 -right-32 h-96 w-96 rounded-full bg-cyan-400/20 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-40 -left-24 h-96 w-96 rounded-full bg-brand-500/30 blur-3xl"></div>

            <div class="relative flex items-center gap-2 text-sm font-medium text-brand-200">
                <Send class="h-4 w-4" /> Content Distribution System
            </div>

            <div class="relative">
                <h2 class="max-w-md text-4xl font-bold leading-tight tracking-tight">Write once. Publish everywhere.</h2>
                <p class="mt-4 max-w-md text-lg text-brand-200">
                    Connect your accounts, compose a post and send it to every platform, now or on a schedule.
                </p>
                <ul class="mt-8 space-y-4">
                    <li v-for="point in points" :key="point.text" class="flex items-center gap-3 text-brand-100">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 ring-1 ring-white/20">
                            <component :is="point.icon" class="h-4 w-4 text-cyan-300" />
                        </span>
                        {{ point.text }}
                    </li>
                </ul>
            </div>

            <div class="relative flex items-center gap-3">
                <span v-for="p in ['linkedin', 'facebook', 'youtube', 'tiktok']" :key="p"
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-white shadow-lg">
                    <PlatformIcon :type="p" :size="22" />
                </span>
            </div>
        </aside>

        <!-- Form -->
        <main class="flex items-center justify-center px-4 py-10 sm:px-8">
            <div class="w-full max-w-md">
                <BrandLogo :size="64" />
                <h1 class="mt-8 text-2xl font-bold tracking-tight text-slate-900">{{ title }}</h1>
                <p v-if="subtitle" class="mt-1.5 text-sm text-slate-600">{{ subtitle }}</p>
                <div class="mt-8">
                    <slot />
                </div>
            </div>
        </main>
    </div>
</template>

<script>
import { CalendarClock, Layers, Send, ShieldCheck } from 'lucide-vue-next';
import BrandLogo from './BrandLogo.vue';
import PlatformIcon from './PlatformIcon.vue';

export default {
    name: 'AuthLayout',
    components: { BrandLogo, PlatformIcon, Send },
    props: {
        title: { type: String, required: true },
        subtitle: { type: String, default: '' },
    },
    setup() {
        return {
            points: [
                { icon: Layers, text: 'One composer for every connected platform' },
                { icon: CalendarClock, text: 'Schedule posts ahead of time' },
                { icon: ShieldCheck, text: 'Per-platform status for every post' },
            ],
        };
    },
};
</script>
