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
            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <li v-for="file in files" :key="file.id" class="card overflow-hidden" data-media>
                    <div class="flex h-36 items-center justify-center bg-slate-100">
                        <img v-if="file.type === 'image'" :src="file.url" :alt="file.filename" class="h-full w-full object-cover" loading="lazy" />
                        <Film v-else-if="file.type === 'video'" class="h-10 w-10 text-slate-400" />
                        <Music v-else class="h-10 w-10 text-slate-400" />
                    </div>
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
    </div>
</template>

<script>
import { ref, reactive, markRaw, computed, onMounted } from 'vue';
import axios from 'axios';
import { ChevronLeft, ChevronRight, Film, Images, Music, Trash2, Upload } from 'lucide-vue-next';
import PageHeader from '../components/PageHeader.vue';
import { confirmDialog, toast } from '../feedback.js';
import { siteContent, loadSiteContent } from '../siteContent.js';

export default {
    name: 'Media',
    components: { ChevronLeft, ChevronRight, Film, Images, Music, Trash2, Upload, PageHeader },
    setup() {
        const files = ref([]);
        const page = ref(1);
        const lastPage = ref(1);
        const loading = ref(true);

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

        onMounted(() => {
            load();
            loadSiteContent().catch(() => {});
        });

        return { files, page, lastPage, loading, description, uploads, uploading, handleUpload, goToPage, remove, formatSize, formatDate };
    },
};
</script>
