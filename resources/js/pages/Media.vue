<template>
    <div>
        <PageHeader title="Media" :description="description" />

        <div v-if="loading" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="n in 8" :key="n" class="card h-52 animate-pulse bg-slate-100"></div>
        </div>

        <div v-else-if="files.length === 0" class="card px-5 py-14 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                <Images class="h-6 w-6" />
            </div>
            <p class="mt-4 font-medium text-slate-900">No files yet</p>
            <p class="mt-1 text-sm text-slate-500">Files you upload while creating a post show up here.</p>
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
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { ChevronLeft, ChevronRight, Film, Images, Music, Trash2 } from 'lucide-vue-next';
import PageHeader from '../components/PageHeader.vue';
import { confirmDialog, toast } from '../feedback.js';
import { siteContent, loadSiteContent } from '../siteContent.js';

export default {
    name: 'Media',
    components: { ChevronLeft, ChevronRight, Film, Images, Music, Trash2, PageHeader },
    setup() {
        const files = ref([]);
        const page = ref(1);
        const lastPage = ref(1);
        const loading = ref(true);

        // Admin setting: media is either removed after publishing or kept for reuse
        const description = computed(() => siteContent.value?.delete_media_after_publish === false
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

        return { files, page, lastPage, loading, description, goToPage, remove, formatSize, formatDate };
    },
};
</script>
