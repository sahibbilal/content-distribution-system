<template>
    <div>
        <PageHeader title="Posts" description="Everything you've published or scheduled.">
            <router-link to="/create-post" class="btn btn-primary">
                <Plus class="h-4 w-4" /> New post
            </router-link>
        </PageHeader>

        <div class="mb-5 flex flex-wrap gap-2" role="tablist">
            <button v-for="tab in tabs" :key="tab.value" type="button" role="tab" :aria-selected="status === tab.value"
                class="rounded-full px-4 py-1.5 text-sm font-medium transition"
                :class="status === tab.value ? 'bg-brand-600 text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-inset ring-slate-300 hover:bg-slate-50'"
                @click="setStatus(tab.value)">
                {{ tab.label }}
            </button>
        </div>

        <div class="card">
            <div v-if="loading" class="space-y-4 p-5">
                <div v-for="n in 4" :key="n" class="space-y-2">
                    <div class="h-4 w-3/4 animate-pulse rounded bg-slate-200"></div>
                    <div class="h-3 w-1/3 animate-pulse rounded bg-slate-100"></div>
                </div>
            </div>

            <div v-else-if="posts.length === 0" class="px-5 py-14 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                    <FileText class="h-6 w-6" />
                </div>
                <p class="mt-4 font-medium text-slate-900">{{ emptyText }}</p>
                <router-link to="/create-post" class="btn btn-primary mt-5">Create a post</router-link>
            </div>

            <ul v-else class="divide-y divide-slate-100">
                <li v-for="post in posts" :key="post.id" data-post>
                    <router-link :to="`/posts/${post.id}`" class="flex items-start gap-4 p-5 transition hover:bg-slate-50">
                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-2 whitespace-pre-line break-words text-sm font-medium text-slate-900">{{ post.content }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-slate-500">
                                <span class="inline-flex items-center gap-1.5">
                                    <CalendarClock v-if="post.status === 'scheduled'" class="h-3.5 w-3.5" />
                                    <Clock v-else class="h-3.5 w-3.5" />
                                    {{ formatDate(post.status === 'scheduled' ? post.scheduled_at : (post.published_at || post.created_at)) }}
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <PlatformIcon v-for="type in platformTypes(post)" :key="type" :type="type" :size="16" />
                                </span>
                                <span v-if="post.media?.length" class="inline-flex items-center gap-1">
                                    <Paperclip class="h-3.5 w-3.5" /> {{ post.media.length }}
                                </span>
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <span class="badge capitalize" :class="statusBadge(post.status)">{{ post.status }}</span>
                            <span class="inline-flex items-center gap-1 text-xs font-medium text-brand-700">
                                <template v-if="post.status === 'scheduled'"><Pencil class="h-3.5 w-3.5" /> Edit</template>
                                <template v-else>View <ChevronRight class="h-3.5 w-3.5" /></template>
                            </span>
                        </div>
                    </router-link>
                </li>
            </ul>

            <div v-if="!loading && lastPage > 1" class="flex items-center justify-between border-t border-slate-100 px-5 py-3">
                <button type="button" class="btn btn-secondary btn-sm" :disabled="page <= 1" @click="goToPage(page - 1)">
                    <ChevronLeft class="h-4 w-4" /> Previous
                </button>
                <span class="text-sm text-slate-500">Page {{ page }} of {{ lastPage }}</span>
                <button type="button" class="btn btn-secondary btn-sm" :disabled="page >= lastPage" @click="goToPage(page + 1)">
                    Next <ChevronRight class="h-4 w-4" />
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { CalendarClock, ChevronLeft, ChevronRight, Clock, FileText, Paperclip, Pencil, Plus } from 'lucide-vue-next';
import PageHeader from '../components/PageHeader.vue';
import PlatformIcon from '../components/PlatformIcon.vue';

export default {
    name: 'Posts',
    components: { CalendarClock, ChevronLeft, ChevronRight, Clock, FileText, Paperclip, Pencil, Plus, PageHeader, PlatformIcon },
    setup() {
        const tabs = [
            { value: '', label: 'All' },
            { value: 'published', label: 'Published' },
            { value: 'scheduled', label: 'Scheduled' },
            { value: 'failed', label: 'Failed' },
        ];
        const route = useRoute();
        const router = useRouter();
        // the filter lives in the URL (?status=scheduled) so it survives navigation and can be linked to
        const status = ref(tabs.some(t => t.value === route.query.status) ? route.query.status : '');
        const posts = ref([]);
        const platforms = ref([]);
        const page = ref(1);
        const lastPage = ref(1);
        const loading = ref(true);
        let requestId = 0;

        const loadPosts = async () => {
            const id = ++requestId;
            loading.value = true;
            try {
                const response = await axios.get('/posts', { params: { status: status.value || undefined, page: page.value } });
                if (id !== requestId) return; // a newer filter/page was requested meanwhile
                posts.value = response.data.data;
                lastPage.value = response.data.last_page;
            } catch (error) {
                if (id === requestId) posts.value = [];
                console.error('Error loading posts:', error);
            } finally {
                if (id === requestId) loading.value = false;
            }
        };

        const setStatus = (value) => {
            if (status.value === value) return;
            status.value = value;
            page.value = 1;
            router.replace({ query: value ? { status: value } : {} });
            loadPosts();
        };

        const goToPage = (n) => {
            page.value = n;
            loadPosts();
        };

        const platformTypes = (post) => [...new Set((post.platforms || [])
            .map(id => platforms.value.find(p => p.id === id)?.platform_type)
            .filter(Boolean))];

        const statusBadge = (value) => ({
            published: 'badge-success',
            scheduled: 'badge-warning',
            failed: 'badge-danger',
        })[value] || 'badge-neutral';

        const formatDate = (date) => new Date(date).toLocaleString();

        const emptyText = computed(() => status.value ? `No ${status.value} posts yet` : 'No posts yet');

        onMounted(async () => {
            loadPosts();
            try {
                platforms.value = (await axios.get('/platforms')).data;
            } catch (error) {
                console.error('Error loading platforms:', error);
            }
        });

        return { tabs, status, posts, page, lastPage, loading, emptyText, setStatus, goToPage, platformTypes, statusBadge, formatDate };
    },
};
</script>
