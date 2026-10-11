<template>
    <div class="min-h-screen bg-white">
        <header class="border-b border-slate-200">
            <div class="mx-auto flex h-16 max-w-3xl items-center justify-between px-4 sm:px-6">
                <BrandLogo :size="36" />
                <router-link to="/login" class="btn btn-ghost">Log in</router-link>
            </div>
        </header>
        <main class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
            <h1 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">{{ title }}</h1>
            <p class="mt-2 text-sm text-slate-500">Last updated: {{ updated }}</p>
            <div class="legal-prose mt-8">
                <slot :contact-email="contactEmail" />
            </div>
        </main>
        <footer class="border-t border-slate-200 bg-slate-50 py-8 text-center text-sm text-slate-500">
            <router-link to="/terms" class="hover:text-slate-900">Terms of Service</router-link>
            <span class="mx-1">&middot;</span>
            <router-link to="/privacy" class="hover:text-slate-900">Privacy Policy</router-link>
            <span class="mx-1">&middot;</span>
            <router-link to="/data-deletion" class="hover:text-slate-900">Data Deletion</router-link>
        </footer>
    </div>
</template>

<script>
import BrandLogo from './BrandLogo.vue';
import { computed, onMounted } from 'vue';
import { siteContent, loadSiteContent } from '../siteContent.js';

export default {
    components: { BrandLogo },
    name: 'LegalLayout',
    props: {
        title: { type: String, required: true },
        updated: { type: String, default: 'October 11, 2026' },
    },
    setup() {
        onMounted(() => loadSiteContent().catch(() => {}));
        const contactEmail = computed(() => siteContent.value?.contact_email || '');
        return { contactEmail };
    },
};
</script>
