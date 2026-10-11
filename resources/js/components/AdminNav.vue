<template>
    <nav class="admin-nav">
        <router-link
            v-for="tab in tabs"
            :key="tab.to"
            :to="tab.to"
            class="tab"
            exact-active-class="active"
        >{{ tab.label }}</router-link>
    </nav>
</template>

<script>
export default {
    name: 'AdminNav',
    setup() {
        const tabs = [
            { to: '/admin', label: 'Site content' },
            { to: '/admin/users', label: 'Users' },
            { to: '/admin/ai', label: 'AI integrations' },
            { to: '/admin/email', label: 'Email' },
            { to: '/admin/settings', label: 'Settings' },
        ];
        return { tabs };
    },
};
</script>

<style scoped>
.admin-nav {
    display: flex;
    gap: 0.25rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
    border-bottom: 2px solid #e5e7f0;
}

.tab {
    padding: 0.6rem 1rem;
    color: #555;
    text-decoration: none;
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
}

.tab.active {
    color: #667eea;
    border-bottom-color: #667eea;
    font-weight: 600;
}
</style>
