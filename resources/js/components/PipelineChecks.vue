<template>
    <section class="card p-5">
        <h2 class="flex items-center gap-2 font-semibold text-slate-900">
            <ListChecks class="h-4 w-4 text-slate-400" /> Is everything in place?
            <span class="badge ml-auto" :class="allOk ? 'badge-success' : 'badge-warning'">{{ allOk ? 'Ready' : 'Needs attention' }}</span>
        </h2>
        <router-link v-if="settingsLink" to="/admin/pipeline" class="mt-1 inline-block text-sm font-medium text-brand-700 hover:text-brand-800">Open connection settings</router-link>
        <ul class="mt-3 divide-y divide-slate-100">
            <li v-for="c in checks" :key="c.key" class="flex items-start gap-3 py-2.5 text-sm">
                <CheckCircle2 v-if="c.ok" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                <AlertCircle v-else class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                <div class="min-w-0">
                    <p class="font-medium text-slate-900">{{ c.label }}</p>
                    <p class="break-words text-slate-500">{{ c.detail }}</p>
                </div>
            </li>
        </ul>
    </section>
</template>

<script>
import { computed } from 'vue';
import { AlertCircle, CheckCircle2, ListChecks } from 'lucide-vue-next';

export default {
    name: 'PipelineChecks',
    components: { AlertCircle, CheckCircle2, ListChecks },
    props: { checks: { type: Array, required: true }, settingsLink: { type: Boolean, default: false } },
    setup(props) {
        return { allOk: computed(() => props.checks.every(c => c.ok)) };
    },
};
</script>
