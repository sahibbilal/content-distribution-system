<template>
    <div>
        <PageHeader title="Video episodes" description="The daily video: script, voice, avatar, edit, then scheduled posts. Text and image posts are written on Create Post.">
            <button type="button" class="btn btn-primary" :disabled="starting" @click="startVideo">
                <Clapperboard class="h-4 w-4" /> {{ starting ? 'Starting...' : "Start today's video" }}
            </button>
        </PageHeader>

        <p v-if="error" class="alert alert-error mb-5">{{ error }}</p>

        <PipelineChecks v-if="checks" :checks="checks" settings-link class="mb-6" />

        <div class="card">
            <div v-if="loading" class="space-y-4 p-5">
                <div v-for="n in 4" :key="n" class="h-4 w-3/4 animate-pulse rounded bg-slate-200"></div>
            </div>

            <div v-else-if="episodes.length === 0" class="px-5 py-14 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                    <Clapperboard class="h-6 w-6" />
                </div>
                <p class="mt-4 font-medium text-slate-900">No video episodes yet</p>
                <p class="mt-1 text-sm text-slate-500">Research starts by itself every day, or start today's video now.</p>
            </div>

            <ul v-else class="divide-y divide-slate-100">
                <li v-for="e in episodes" :key="e.id">
                    <router-link :to="`/episodes/${e.id}`" class="flex items-center gap-4 p-5 transition hover:bg-slate-50">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-900">{{ e.topic || 'Video ' + e.run_date }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ e.run_date }} &middot; publishes {{ e.scheduled_for ? new Date(e.scheduled_for).toLocaleString() : '-' }}
                            </p>
                            <p v-if="e.error" class="mt-1 truncate text-xs text-red-600">{{ e.error }}</p>
                        </div>
                        <span class="badge" :class="statusBadge(e.status)">{{ statusLabel(e.status) }}</span>
                    </router-link>
                </li>
            </ul>
        </div>

        <div v-if="lastPage > 1" class="mt-5 flex items-center justify-center gap-2">
            <button class="btn btn-secondary btn-sm" :disabled="page <= 1" @click="go(page - 1)">Previous</button>
            <span class="text-sm text-slate-600">Page {{ page }} of {{ lastPage }}</span>
            <button class="btn btn-secondary btn-sm" :disabled="page >= lastPage" @click="go(page + 1)">Next</button>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { Clapperboard } from 'lucide-vue-next';
import PageHeader from '../components/PageHeader.vue';
import PipelineChecks from '../components/PipelineChecks.vue';
import { statusBadge, statusLabel } from '../episodes.js';

export default {
    name: 'Episodes',
    components: { Clapperboard, PageHeader, PipelineChecks },
    setup() {
        const router = useRouter();
        const episodes = ref([]);
        const checks = ref(null); // connection checklist, admins only
        const loading = ref(true);
        const starting = ref(false);
        const error = ref('');
        const page = ref(1);
        const lastPage = ref(1);

        const load = async () => {
            loading.value = true;
            try {
                const { data } = await axios.get('/episodes', { params: { page: page.value, kind: 'video' } });
                episodes.value = data.data;
                lastPage.value = data.last_page;
            } catch (err) {
                error.value = err.response?.data?.message || 'Could not load the list.';
            } finally {
                loading.value = false;
            }
        };

        // the checklist covers server-wide settings, so only an admin can see it
        const loadChecks = async () => {
            try {
                if (!JSON.parse(localStorage.getItem('user') || 'null')?.is_admin) return;
                checks.value = (await axios.get('/admin/pipeline')).data.checks;
            } catch (err) {
                // the list itself still works without it
            }
        };

        const go = (n) => {
            page.value = n;
            load();
        };

        const startVideo = async () => {
            starting.value = true;
            error.value = '';
            try {
                const { data } = await axios.post('/episodes', { kind: 'video' });
                router.push(`/episodes/${data.id}`);
            } catch (err) {
                error.value = err.response?.data?.message || 'Could not start the episode.';
            } finally {
                starting.value = false;
            }
        };

        onMounted(() => {
            load();
            loadChecks();
        });

        return { episodes, checks, loading, starting, error, page, lastPage, go, startVideo, statusBadge, statusLabel };
    },
};
</script>
