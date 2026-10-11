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

        <div class="mb-5 grid gap-3 sm:grid-cols-3">
            <div class="relative sm:col-span-2">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input v-model="search" type="search" class="input pl-9" placeholder="Search post content..." aria-label="Search posts" />
            </div>
            <select v-model="platform" class="input" aria-label="Filter by platform">
                <option value="">All platforms</option>
                <option v-for="type in platformOptions" :key="type" :value="type">{{ platformLabel(type) }}</option>
            </select>
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
                <button v-if="filtered" type="button" class="btn btn-secondary mt-5" @click="clearFilters">Clear filters</button>
                <router-link v-else to="/create-post" class="btn btn-primary mt-5">Create a post</router-link>
            </div>

            <ul v-else class="divide-y divide-slate-100">
                <li v-for="post in posts" :key="post.id" data-post class="flex items-start transition hover:bg-slate-50">
                    <router-link :to="`/posts/${post.id}`" class="flex min-w-0 flex-1 items-start gap-4 p-5">
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
                    <router-link :to="{ path: '/create-post', query: { copy: post.id } }" class="btn btn-secondary btn-sm mr-5 mt-5 shrink-0" title="Start a new post with this content">
                        <Copy class="h-3.5 w-3.5" /> Use as new post
                    </router-link>
                </li>
            </ul>

            <div v-if="!loading && lastPage > 1" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-3">
                <button type="button" class="btn btn-secondary btn-sm" :disabled="page <= 1" @click="goToPage(page - 1)">
                    <ChevronLeft class="h-4 w-4" /> Previous
                </button>
                <div class="flex items-center gap-1" data-pages>
                    <template v-for="(n, i) in pageNumbers" :key="i">
                        <span v-if="n === '…'" class="px-1 text-sm text-slate-400">…</span>
                        <button v-else type="button" class="h-8 min-w-8 rounded-md px-2 text-sm font-medium transition"
                            :class="n === page ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                            :aria-current="n === page ? 'page' : null" @click="goToPage(n)">{{ n }}</button>
                    </template>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" :disabled="page >= lastPage" @click="goToPage(page + 1)">
                    Next <ChevronRight class="h-4 w-4" />
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { CalendarClock, ChevronLeft, ChevronRight, Clock, Copy, FileText, Paperclip, Pencil, Plus, Search } from 'lucide-vue-next';
import PageHeader from '../components/PageHeader.vue';
import PlatformIcon from '../components/PlatformIcon.vue';
import { platformLabel } from '../platforms.js';

export default {
    name: 'Posts',
    components: { CalendarClock, ChevronLeft, ChevronRight, Clock, Copy, FileText, Paperclip, Pencil, Plus, Search, PageHeader, PlatformIcon },
    setup() {
        const tabs = [
            { value: '', label: 'All' },
            { value: 'published', label: 'Published' },
            { value: 'scheduled', label: 'Scheduled' },
            { value: 'failed', label: 'Failed' },
        ];
        const route = useRoute();
        const router = useRouter();
        // filters and page live in the URL (?status=scheduled&platform=tiktok&q=text&page=2) so they survive navigation
        const status = ref(tabs.some(t => t.value === route.query.status) ? route.query.status : '');
        const platform = ref(typeof route.query.platform === 'string' ? route.query.platform : '');
        const search = ref(typeof route.query.q === 'string' ? route.query.q : '');
        const page = ref(Math.max(1, parseInt(route.query.page, 10) || 1));
        const posts = ref([]);
        const platforms = ref([]);
        const lastPage = ref(1);
        const loading = ref(true);
        let requestId = 0;
        let searchTimer;

        const platformOptions = computed(() => [...new Set(platforms.value.map(p => p.platform_type))]);
        const filtered = computed(() => !!(status.value || platform.value || search.value.trim()));

        const syncUrl = () => router.replace({
            query: {
                ...(status.value && { status: status.value }),
                ...(platform.value && { platform: platform.value }),
                ...(search.value.trim() && { q: search.value.trim() }),
                ...(page.value > 1 && { page: page.value }),
            },
        });

        const loadPosts = async () => {
            const id = ++requestId;
            loading.value = true;
            try {
                const response = await axios.get('/posts', {
                    params: {
                        status: status.value || undefined,
                        platform: platform.value || undefined,
                        search: search.value.trim() || undefined,
                        page: page.value,
                    },
                });
                if (id !== requestId) return; // a newer filter/page was requested meanwhile
                // a stale page number (e.g. from an old link) steps back to the last real page
                if (response.data.last_page && page.value > response.data.last_page) {
                    page.value = response.data.last_page;
                    syncUrl();
                    return loadPosts();
                }
                posts.value = response.data.data;
                lastPage.value = response.data.last_page;
            } catch (error) {
                if (id === requestId) posts.value = [];
                console.error('Error loading posts:', error);
            } finally {
                if (id === requestId) loading.value = false;
            }
        };

        const applyFilters = () => {
            page.value = 1;
            syncUrl();
            loadPosts();
        };

        const setStatus = (value) => {
            if (status.value === value) return;
            status.value = value;
            applyFilters();
        };

        const clearFilters = () => {
            clearTimeout(searchTimer);
            status.value = '';
            platform.value = '';
            search.value = '';
            applyFilters();
        };

        watch(platform, applyFilters);
        // wait for a pause in typing before searching
        watch(search, () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(applyFilters, 350);
        });
        onBeforeUnmount(() => clearTimeout(searchTimer));

        const goToPage = (n) => {
            page.value = n;
            syncUrl();
            loadPosts();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        };

        // 1 … 4 5 [6] 7 8 … 20
        const pageNumbers = computed(() => {
            const last = lastPage.value;
            const keep = new Set([1, last, page.value - 1, page.value, page.value + 1]);
            const numbers = [...keep].filter(n => n >= 1 && n <= last).sort((a, b) => a - b);
            return numbers.flatMap((n, i) => (i && n - numbers[i - 1] > 1 ? ['…', n] : [n]));
        });

        const platformTypes = (post) => [...new Set((post.platforms || [])
            .map(id => platforms.value.find(p => p.id === id)?.platform_type)
            .filter(Boolean))];

        const statusBadge = (value) => ({
            published: 'badge-success',
            scheduled: 'badge-warning',
            failed: 'badge-danger',
        })[value] || 'badge-neutral';

        const formatDate = (date) => new Date(date).toLocaleString();

        const emptyText = computed(() => {
            if (search.value.trim()) return 'No posts match your search';
            if (filtered.value) return 'No posts match these filters';
            return 'No posts yet';
        });

        onMounted(async () => {
            loadPosts();
            try {
                platforms.value = (await axios.get('/platforms')).data;
            } catch (error) {
                console.error('Error loading platforms:', error);
            }
        });

        return {
            tabs, status, platform, search, posts, page, lastPage, loading, emptyText, filtered, platformOptions, pageNumbers,
            platformLabel, setStatus, clearFilters, goToPage, platformTypes, statusBadge, formatDate,
        };
    },
};
</script>
