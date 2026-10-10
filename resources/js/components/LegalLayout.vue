<template>
    <div class="legal">
        <header class="legal-nav">
            <BrandLogo :size="44" pill />
        </header>
        <main class="legal-body">
            <h1>{{ title }}</h1>
            <p class="updated">Last updated: {{ updated }}</p>
            <slot :contact-email="contactEmail" />
        </main>
        <footer class="legal-footer">
            <router-link to="/terms">Terms of Service</router-link>
            &middot; <router-link to="/privacy">Privacy Policy</router-link>
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

<style scoped>
.legal { min-height: 100vh; background: #fff; color: #2c3e50; }
.legal-nav { padding: 1rem 2rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.legal-body { max-width: 780px; margin: 0 auto; padding: 2rem 1.5rem 3rem; line-height: 1.7; }
.legal-body h1 { margin-bottom: 0.25rem; }
.updated { color: #888; margin-bottom: 2rem; }
.legal-body :slotted(h2) { margin: 2rem 0 0.5rem; font-size: 1.25rem; }
.legal-body :slotted(ul) { margin: 0.5rem 0 0.5rem 1.5rem; }
.legal-body :slotted(a) { color: #667eea; }
.legal-footer { padding: 1.5rem; text-align: center; background: #f5f5f5; color: #888; }
.legal-footer a { color: #667eea; text-decoration: none; }
</style>
