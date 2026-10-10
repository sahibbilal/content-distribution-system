<template>
    <div>
        <PageHeader title="Dashboard" description="An overview of your posts, schedule and connected platforms.">
            <router-link to="/create-post" class="btn btn-primary">
                <Plus class="h-4 w-4" /> New post
            </router-link>
        </PageHeader>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div v-for="stat in stats" :key="stat.label" class="card p-5">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-slate-500">{{ stat.label }}</p>
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg" :class="stat.tone">
                        <component :is="stat.icon" class="h-5 w-5" />
                    </span>
                </div>
                <p class="mt-3 text-3xl font-bold tracking-tight text-slate-900">
                    <span v-if="loading" class="inline-block h-8 w-12 animate-pulse rounded bg-slate-200"></span>
                    <template v-else>{{ stat.value }}</template>
                </p>
            </div>
        </div>

        <div v-if="!loading && failedCount" class="alert alert-error mt-6 items-center">
            <AlertTriangle class="h-5 w-5 shrink-0" />
            <span>{{ failedCount }} {{ failedCount === 1 ? 'post needs' : 'posts need' }} attention. Check the platform details below.</span>
        </div>

        <div class="mt-8 grid gap-6 xl:grid-cols-3">
            <!-- Recent posts -->
            <section class="card xl:col-span-2">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="card-title">Recent posts</h2>
                </div>

                <div v-if="loading" class="space-y-4 p-5">
                    <div v-for="n in 3" :key="n" class="space-y-2">
                        <div class="h-4 w-3/4 animate-pulse rounded bg-slate-200"></div>
                        <div class="h-3 w-1/3 animate-pulse rounded bg-slate-100"></div>
                    </div>
                </div>

                <div v-else-if="posts.length === 0" class="px-5 py-14 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                        <FileText class="h-6 w-6" />
                    </div>
                    <p class="mt-4 font-medium text-slate-900">No posts yet</p>
                    <p class="mt-1 text-sm text-slate-500">Compose your first post and publish it to your platforms.</p>
                    <router-link to="/create-post" class="btn btn-primary mt-5">Create your first post</router-link>
                </div>

                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="post in posts" :key="post.id" class="p-5">
                        <div class="flex items-start justify-between gap-4">
                            <p class="line-clamp-3 min-w-0 whitespace-pre-line break-words text-sm text-slate-800">{{ post.content }}</p>
                            <span class="badge shrink-0 capitalize" :class="statusBadge(post.status)">{{ post.status }}</span>
                        </div>
                        <p class="mt-1.5 flex items-center gap-1.5 text-xs text-slate-500">
                            <Clock class="h-3.5 w-3.5" /> {{ formatDate(post.created_at) }}
                        </p>

                        <div v-if="post.platform_statuses" class="mt-3 space-y-2">
                            <div v-for="(status, platformId) in post.platform_statuses" :key="platformId"
                                class="rounded-lg bg-slate-50 px-3 py-2 ring-1 ring-slate-900/5">
                                <div class="flex flex-wrap items-center gap-2 text-sm">
                                    <PlatformIcon :type="getPlatformType(platformId)" :size="18" />
                                    <span class="font-medium text-slate-700">{{ getPlatformName(platformId) }}</span>
                                    <span class="badge capitalize" :class="statusBadge(status.status)">{{ status.status }}</span>
                                    <a v-if="status.url" :href="status.url" target="_blank" rel="noopener" :title="status.url"
                                        class="ml-auto inline-flex items-center gap-1 text-xs font-medium text-brand-700 hover:text-brand-800">
                                        View post <ExternalLink class="h-3.5 w-3.5" />
                                    </a>
                                </div>
                                <p v-if="status.message" class="mt-1.5 text-xs italic text-slate-500">{{ status.message }}</p>
                                <p v-if="status.error && status.status === 'failed'" class="mt-1.5 rounded-md bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-700">
                                    {{ status.error }}
                                </p>
                            </div>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- Scheduled posts -->
            <section class="card self-start">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="card-title">Scheduled posts</h2>
                </div>
                <div v-if="loading" class="space-y-3 p-5">
                    <div v-for="n in 2" :key="n" class="h-14 animate-pulse rounded-lg bg-slate-100"></div>
                </div>
                <div v-else-if="schedules.length === 0" class="px-5 py-10 text-center">
                    <CalendarClock class="mx-auto h-8 w-8 text-slate-300" />
                    <p class="mt-3 text-sm text-slate-500">No scheduled posts</p>
                </div>
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="schedule in schedules" :key="schedule.id" class="flex items-start gap-3 p-5">
                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-2 break-words text-sm text-slate-800">{{ schedule.post.content }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                <CalendarClock class="h-3.5 w-3.5" />
                                {{ formatDate(schedule.scheduled_at) }}
                                <span class="badge capitalize" :class="statusBadge(schedule.status)">{{ schedule.status }}</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-danger-soft p-2!" aria-label="Delete schedule" title="Delete schedule" @click="deleteSchedule(schedule.id)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { AlertTriangle, CalendarClock, CheckCircle2, Clock, ExternalLink, FileText, Link2, Plus, Trash2 } from 'lucide-vue-next';
import PageHeader from '../components/PageHeader.vue';
import PlatformIcon from '../components/PlatformIcon.vue';
import { confirmDialog, toast } from '../feedback.js';
import { platformLabel } from '../platforms.js';

export default {
    name: 'Dashboard',
    components: { AlertTriangle, CalendarClock, CheckCircle2, Clock, ExternalLink, FileText, Plus, Trash2, PageHeader, PlatformIcon },
    setup() {
        const posts = ref([]);
        const schedules = ref([]);
        const platforms = ref([]);
        const loading = ref(true);

        const loadData = async () => {
            try {
                const [postsRes, schedulesRes, platformsRes] = await Promise.all([
                    axios.get('/posts'),
                    axios.get('/schedules'),
                    axios.get('/platforms'),
                ]);
                posts.value = postsRes.data.data || postsRes.data;
                schedules.value = schedulesRes.data.data || schedulesRes.data;
                platforms.value = platformsRes.data;
            } catch (error) {
                console.error('Error loading dashboard data:', error);
            } finally {
                loading.value = false;
            }
        };

        const deleteSchedule = async (id) => {
            const confirmed = await confirmDialog({
                title: 'Delete schedule?',
                message: 'This scheduled post will not be published.',
                confirmText: 'Delete',
                danger: true,
            });
            if (!confirmed) return;
            
            try {
                await axios.delete(`/schedules/${id}`);
                schedules.value = schedules.value.filter(s => s.id !== id);
                toast('Schedule deleted', 'success');
            } catch (error) {
                toast('Failed to delete schedule', 'error');
            }
        };

        const formatDate = (date) => {
            return new Date(date).toLocaleString();
        };

        const getPlatformName = (platformId) => {
            const platform = platforms.value.find(p => p.id === parseInt(platformId));
            if (platform) {
                return platformLabel(platform.platform_type);
            }
            return `Platform ${platformId}`;
        };

        const getPlatformType = (platformId) => {
            return platforms.value.find(p => p.id === parseInt(platformId))?.platform_type || '';
        };

        const statusBadge = (status) => {
            if (status === 'published' || status === 'completed') return 'badge-success';
            if (status === 'scheduled' || status === 'pending') return 'badge-warning';
            if (status === 'failed') return 'badge-danger';
            return 'badge-neutral';
        };

        const failedCount = computed(() => posts.value.filter(p => p.status === 'failed').length);

        const stats = computed(() => [
            { label: 'Total posts', value: posts.value.length, icon: FileText, tone: 'bg-brand-50 text-brand-600' },
            { label: 'Published', value: posts.value.filter(p => p.status === 'published').length, icon: CheckCircle2, tone: 'bg-emerald-50 text-emerald-600' },
            { label: 'Scheduled', value: schedules.value.length, icon: CalendarClock, tone: 'bg-amber-50 text-amber-600' },
            { label: 'Connected platforms', value: platforms.value.length, icon: Link2, tone: 'bg-violet-50 text-violet-600' },
        ]);

        onMounted(loadData);

        return {
            posts,
            schedules,
            platforms,
            loading,
            deleteSchedule,
            formatDate,
            getPlatformName,
            getPlatformType,
            statusBadge,
            failedCount,
            stats,
        };
    },
};
</script>
