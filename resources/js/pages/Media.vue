<template>
    <div>
        <PageHeader title="Media" :description="description">
            <label class="btn btn-primary cursor-pointer" :class="{ 'pointer-events-none opacity-60': uploading }">
                <Upload class="h-4 w-4" /> {{ uploading ? 'Uploading...' : 'Upload files' }}
                <input type="file" class="sr-only" multiple accept="image/*,video/*,audio/*" :disabled="uploading" @change="handleUpload" />
            </label>
        </PageHeader>

        <!-- Files upload one at a time; the server checks each file's real content -->
        <ul v-if="uploads.length" class="mb-5 space-y-2" data-uploads>
            <li v-for="item in uploads" :key="item.key" class="card p-3" :class="{ 'bg-red-50! ring-red-200!': item.status === 'error' }" :data-status="item.status">
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="truncate font-medium text-slate-800">{{ item.name }}</span>
                    <span class="shrink-0 text-xs" :class="item.status === 'error' ? 'text-red-700' : item.status === 'done' ? 'text-emerald-700' : 'text-slate-500'">
                        {{ item.status === 'queued' ? 'Waiting' : item.status === 'uploading' ? `Uploading ${item.progress}%` : item.status === 'verifying' ? 'Checking file...' : item.status === 'done' ? 'Uploaded' : 'Failed' }}
                    </span>
                </div>
                <div v-if="item.status !== 'error'" class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200" role="progressbar" :aria-valuenow="item.progress" aria-valuemin="0" aria-valuemax="100">
                    <div class="h-full rounded-full transition-all duration-200" :class="item.status === 'done' ? 'bg-emerald-500' : 'bg-brand-600'" :style="{ width: item.progress + '%' }"></div>
                </div>
                <p v-else class="mt-1.5 whitespace-pre-line text-xs text-red-800">{{ item.error }}</p>
            </li>
        </ul>

        <div v-if="loading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="n in 8" :key="n" class="card h-52 animate-pulse bg-slate-100"></div>
        </div>

        <div v-else-if="files.length === 0" class="card px-5 py-14 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                <Images class="h-6 w-6" />
            </div>
            <p class="mt-4 font-medium text-slate-900">No files yet</p>
            <p class="mt-1 text-sm text-slate-500">Use "Upload files" above, or upload while creating a post.</p>
            <router-link to="/create-post" class="btn btn-primary mt-5">Create a post</router-link>
        </div>

        <template v-else>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-500">{{ files.length }} file{{ files.length === 1 ? '' : 's' }} on this page. Click a file to view it larger.</p>
                <div class="flex items-center gap-2" role="group" aria-label="Files per row">
                    <span class="text-sm text-slate-600">Per row</span>
                    <div class="inline-flex rounded-lg bg-slate-100 p-1">
                        <button v-for="n in columnChoices" :key="n" type="button" class="rounded-md px-2.5 py-1 text-sm font-medium transition"
                            :class="cols === n ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'" :aria-pressed="cols === n" @click="setCols(n)">{{ n }}</button>
                    </div>
                </div>
            </div>
            <ul class="grid grid-cols-2 gap-4 sm:[grid-template-columns:repeat(var(--cols),minmax(0,1fr))]" :style="{ '--cols': cols }">
                <li v-for="(file, index) in files" :key="file.id" class="card overflow-hidden" data-media>
                    <button type="button" class="relative flex aspect-[4/3] w-full items-center justify-center bg-slate-100" :aria-label="'View ' + file.filename" @click="open(index)">
                        <img v-if="file.type === 'image'" :src="file.url" :alt="file.filename" class="h-full w-full object-cover" loading="lazy" />
                        <template v-else-if="file.type === 'video'">
                            <video :src="file.url + '#t=0.1'" preload="metadata" muted playsinline class="pointer-events-none h-full w-full object-cover"></video>
                            <span class="absolute flex h-11 w-11 items-center justify-center rounded-full bg-black/55 text-white"><Play class="h-5 w-5" /></span>
                        </template>
                        <Music v-else class="h-10 w-10 text-slate-400" />
                    </button>
                    <div class="p-3">
                        <p class="truncate text-sm font-medium text-slate-900" :title="file.filename">{{ file.filename }}</p>
                        <p class="mt-0.5 text-xs text-slate-500 capitalize">{{ file.type }} &middot; {{ formatSize(file.size) }} &middot; {{ formatDate(file.created_at) }}</p>
                        <div class="mt-3 flex items-center justify-between gap-2">
                            <span v-if="file.in_use" class="badge badge-warning">On a scheduled post</span>
                            <span v-else></span>
                            <button type="button" class="btn btn-danger-soft btn-sm" :disabled="file.in_use" :title="file.in_use ? 'Edit or delete the scheduled post first' : 'Delete file'"
                                @click="remove(file)">
                                <Trash2 class="h-3.5 w-3.5" /> Delete
                            </button>
                        </div>
                    </div>
                </li>
            </ul>

            <div v-if="lastPage > 1" class="mt-6 flex items-center justify-between">
                <button type="button" class="btn btn-secondary btn-sm" :disabled="page <= 1" @click="goToPage(page - 1)"><ChevronLeft class="h-4 w-4" /> Previous</button>
                <span class="text-sm text-slate-500">Page {{ page }} of {{ lastPage }}</span>
                <button type="button" class="btn btn-secondary btn-sm" :disabled="page >= lastPage" @click="goToPage(page + 1)">Next <ChevronRight class="h-4 w-4" /></button>
            </div>
        </template>

        <!-- Viewer: image, video or audio in a larger window; arrows (or the arrow keys) move between files -->
        <div v-if="viewing !== null && current" class="fixed inset-0 z-50 flex flex-col bg-slate-950/90" role="dialog" aria-modal="true" :aria-label="current.filename" @click.self="close">
            <div class="flex items-center justify-between gap-3 px-4 py-3 text-white">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium">{{ current.filename }}</p>
                    <p class="text-xs text-slate-300 capitalize">{{ current.type }} &middot; {{ formatSize(current.size) }} &middot; {{ viewing + 1 }} of {{ files.length }}</p>
                </div>
                <button type="button" class="rounded-full p-2 hover:bg-white/10" aria-label="Close" @click="close"><X class="h-6 w-6" /></button>
            </div>
            <div class="relative flex min-h-0 flex-1 items-center justify-center px-14 pb-6" @click.self="close">
                <button v-if="viewing > 0" type="button" class="absolute left-2 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" aria-label="Previous file" @click="step(-1)"><ChevronLeft class="h-7 w-7" /></button>
                <img v-if="current.type === 'image'" :key="current.id" :src="current.url" :alt="current.filename" class="max-h-full max-w-full rounded-lg object-contain" />
                <video v-else-if="current.type === 'video'" :key="current.id" :src="current.url" controls autoplay playsinline class="max-h-full max-w-full rounded-lg bg-black"></video>
                <div v-else class="w-full max-w-xl rounded-xl bg-white p-8 text-center">
                    <Music class="mx-auto h-12 w-12 text-slate-400" />
                    <p class="mt-3 truncate text-sm font-medium text-slate-900">{{ current.filename }}</p>
                    <audio :key="current.id" :src="current.url" controls autoplay class="mt-4 w-full"></audio>
                </div>
                <button v-if="viewing < files.length - 1" type="button" class="absolute right-2 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" aria-label="Next file" @click="step(1)"><ChevronRight class="h-7 w-7" /></button>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, reactive, markRaw, computed, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { ChevronLeft, ChevronRight, Images, Music, Play, Trash2, Upload, X } from 'lucide-vue-next';
import PageHeader from '../components/PageHeader.vue';
import { confirmDialog, toast } from '../feedback.js';
import { siteContent, loadSiteContent } from '../siteContent.js';

export default {
    name: 'Media',
    components: { ChevronLeft, ChevronRight, Images, Music, Play, Trash2, Upload, X, PageHeader },
    setup() {
        const files = ref([]);
        const page = ref(1);
        const lastPage = ref(1);
        const loading = ref(true);

        // How many files sit in one row (remembered in this browser)
        const columnChoices = [2, 3, 4, 5, 6];
        const readCols = () => {
            try {
                const saved = Number(localStorage.getItem('media-columns'));
                return columnChoices.includes(saved) ? saved : 4;
            } catch (e) {
                return 4;
            }
        };
        const cols = ref(readCols());
        const setCols = (n) => {
            cols.value = n;
            try { localStorage.setItem('media-columns', String(n)); } catch (e) { /* not saved */ }
        };

        // Viewer
        const viewing = ref(null);
        const current = computed(() => (viewing.value === null ? null : files.value[viewing.value]));
        const open = (index) => { viewing.value = index; };
        const close = () => { viewing.value = null; };
        const step = (by) => {
            const next = viewing.value + by;
            if (next >= 0 && next < files.value.length) viewing.value = next;
        };
        const onKey = (e) => {
            if (viewing.value === null) return;
            if (e.key === 'Escape') close();
            else if (e.key === 'ArrowLeft') step(-1);
            else if (e.key === 'ArrowRight') step(1);
        };

        // Admin setting: media is either removed after publishing or kept for reuse
        // blank until the admin setting is known, so the wrong wording never flashes
        const description = computed(() => !siteContent.value ? '' : siteContent.value.delete_media_after_publish === false
            ? 'Your uploaded files are kept here so you can reuse them in new posts. Only you can see them.'
            : 'Files you uploaded but have not published yet. They are removed automatically once a post is published. Only you can see them.');

        const load = async () => {
            loading.value = true;
            try {
                const response = await axios.get('/media', { params: { page: page.value } });
                files.value = response.data.data;
                lastPage.value = response.data.last_page;
                // deleting the last file on a later page leaves it empty: step back
                if (!files.value.length && page.value > 1) {
                    page.value -= 1;
                    return load();
                }
            } catch (error) {
                console.error('Error loading media:', error);
            } finally {
                loading.value = false;
            }
        };

        // Upload queue: one file at a time, each checked server-side (real content type, valid image, size)
        const uploads = ref([]);
        const uploading = ref(false);
        let nextKey = 1;

        const handleUpload = async (event) => {
            const picked = Array.from(event.target.files);
            event.target.value = '';
            if (!picked.length || uploading.value) return;

            uploads.value = picked.map(file => reactive({
                key: nextKey++, file: markRaw(file), name: file.name, status: 'queued', progress: 0, error: '',
            }));
            uploading.value = true;

            for (const item of uploads.value) {
                item.status = 'uploading';
                const formData = new FormData();
                formData.append('file', item.file);
                try {
                    await axios.post('/media/upload', formData, {
                        timeout: 600000,
                        onUploadProgress: (e) => {
                            if (!e.total) return;
                            item.progress = Math.round((e.loaded * 100) / e.total);
                            if (e.loaded >= e.total) item.status = 'verifying';
                        },
                    });
                    item.progress = 100;
                    item.status = 'done';
                } catch (err) {
                    const data = err.response?.data || {};
                    item.status = 'error';
                    item.error = data.error || data.message || err.message;
                    if (data.errors?.file) item.error = data.errors.file.join(' ');
                }
            }

            uploading.value = false;
            page.value = 1; // newest files are listed first
            load();
            const failed = uploads.value.filter(u => u.status === 'error').length;
            toast(failed ? `${uploads.value.length - failed} uploaded, ${failed} rejected` : 'Upload complete', failed ? 'error' : 'success');
            // finished successes clear away; rejected files stay so the reason can be read
            uploads.value = uploads.value.filter(u => u.status === 'error');
        };

        const goToPage = (n) => {
            page.value = n;
            load();
        };

        const remove = async (file) => {
            const confirmed = await confirmDialog({
                title: 'Delete this file?',
                message: `${file.filename} will be permanently removed.`,
                confirmText: 'Delete',
                danger: true,
            });
            if (!confirmed) return;
            try {
                await axios.delete(`/media/${file.id}`);
                toast('File deleted', 'success');
                load();
            } catch (err) {
                toast(err.response?.data?.message || 'Could not delete the file', 'error');
            }
        };

        const formatSize = (bytes) => (bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB');
        const formatDate = (date) => new Date(date).toLocaleDateString();

        onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
        onMounted(() => {
            window.addEventListener('keydown', onKey);
            load();
            loadSiteContent().catch(() => {});
        });

        return { columnChoices, cols, setCols, viewing, current, open, close, step, files, page, lastPage, loading, description, uploads, uploading, handleUpload, goToPage, remove, formatSize, formatDate };
    },
};
</script>
