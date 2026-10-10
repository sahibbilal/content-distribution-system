<template>
    <router-link to="/" class="inline-flex items-center" aria-label="Content Distribution System - home">
        <img src="/images/logo.png" alt="Content Distribution System" class="block h-auto w-auto max-w-full" :style="{ height: size + 'px' }" />
    </router-link>
</template>

<script>
export default {
    name: 'BrandLogo',
    props: {
        size: { type: Number, default: 40 },
    },
};
</script>
