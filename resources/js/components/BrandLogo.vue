<template>
    <router-link to="/" class="brand-logo" :class="{ pill }" aria-label="Content Distribution System - home">
        <img src="/images/logo.png" alt="Content Distribution System" :style="{ height: size + 'px' }" />
    </router-link>
</template>

<script>
export default {
    name: 'BrandLogo',
    props: {
        size: { type: Number, default: 40 },
        // White rounded background, for use on the coloured header and sidebar
        pill: { type: Boolean, default: false },
    },
};
</script>

<style scoped>
.brand-logo {
    display: inline-flex;
    align-items: center;
}

.brand-logo img {
    display: block;
    width: auto;
    max-width: 100%;
}

.brand-logo.pill {
    background: #fff;
    padding: 0.25rem 0.75rem;
    border-radius: 8px;
}
</style>
