<template>
    <div class="legal">
        <header class="legal-nav">
            <router-link to="/" class="legal-brand">Content Distribution</router-link>
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
export default {
    name: 'LegalLayout',
    props: {
        title: { type: String, required: true },
        updated: { type: String, default: 'October 11, 2026' },
    },
    setup() {
        // Single place to change the public contact address used in the legal pages
        return { contactEmail: 'support@bilalmahmood.dev' };
    },
};
</script>

<style scoped>
.legal { min-height: 100vh; background: #fff; color: #2c3e50; }
.legal-nav { padding: 1rem 2rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.legal-brand { color: #fff; font-weight: bold; font-size: 1.25rem; text-decoration: none; }
.legal-body { max-width: 780px; margin: 0 auto; padding: 2rem 1.5rem 3rem; line-height: 1.7; }
.legal-body h1 { margin-bottom: 0.25rem; }
.updated { color: #888; margin-bottom: 2rem; }
.legal-body :slotted(h2) { margin: 2rem 0 0.5rem; font-size: 1.25rem; }
.legal-body :slotted(ul) { margin: 0.5rem 0 0.5rem 1.5rem; }
.legal-body :slotted(a) { color: #667eea; }
.legal-footer { padding: 1.5rem; text-align: center; background: #f5f5f5; color: #888; }
.legal-footer a { color: #667eea; text-decoration: none; }
</style>
