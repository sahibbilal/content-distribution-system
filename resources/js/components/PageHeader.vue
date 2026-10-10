<template>
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ title }}</h1>
            <p v-if="description" class="mt-1.5 text-sm text-slate-600 sm:text-base">{{ description }}</p>
        </div>
        <div v-if="$slots.default" class="flex shrink-0 items-center gap-3">
            <slot />
        </div>
    </div>
</template>

<script>
export default {
    name: 'PageHeader',
    props: {
        title: { type: String, required: true },
        description: { type: String, default: '' },
    },
};
</script>
