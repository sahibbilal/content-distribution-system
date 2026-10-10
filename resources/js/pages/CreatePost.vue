<template>
    <div>
        <PageHeader title="Create post" description="Write once, then choose where it goes." />

        <form @submit.prevent="handleSubmit" class="grid gap-6 lg:grid-cols-3">
            <!-- Composer -->
            <div class="space-y-6 lg:col-span-2">
                <section class="card p-5">
                    <div class="mb-1.5 flex items-center justify-between">
                        <label class="label mb-0" for="post-content">Content</label>
                        <span class="text-xs text-slate-400">{{ form.content.length }} characters</span>
                    </div>
                    <textarea id="post-content" v-model="form.content" rows="9" class="input resize-y" required placeholder="What would you like to post?"></textarea>
                </section>

                <section class="card p-5">
                    <label class="label">Media <span class="font-normal text-slate-400">(optional)</span></label>
                    <label
                        class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center transition hover:border-brand-400 hover:bg-brand-50/40"
                        @dragover.prevent
                        @drop.prevent="handleFileUpload({ target: { files: $event.dataTransfer.files } })"
                    >
                        <UploadCloud class="h-8 w-8 text-slate-400" />
                        <span class="mt-3 text-sm font-medium text-slate-700">
                            <span class="text-brand-700">Click to upload</span> or drag and drop
                        </span>
                        <span class="mt-1 text-xs text-slate-500">Images and videos</span>
                        <input type="file" class="sr-only" multiple :accept="getFileAcceptTypes()" @change="handleFileUpload" />
                    </label>

                    <ul v-if="uploadedMedia.length > 0" class="mt-4 grid gap-3 sm:grid-cols-2">
                        <li v-for="media in uploadedMedia" :key="media.id" class="flex items-center gap-3 rounded-lg bg-slate-50 p-2.5 ring-1 ring-slate-900/5">
                            <img v-if="media.type === 'image'" :src="media.url" :alt="media.filename" class="h-12 w-12 shrink-0 rounded-md object-cover" />
                            <span v-else class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md bg-slate-200 text-slate-500">
                                <Film class="h-5 w-5" />
                            </span>
                            <span class="min-w-0 flex-1 truncate text-sm text-slate-700">{{ media.filename }}</span>
                            <button type="button" @click="removeMedia(media.id)" class="btn btn-ghost p-2!" aria-label="Remove file">
                                <X class="h-4 w-4" />
                            </button>
                        </li>
                    </ul>
                </section>
            </div>

            <!-- Publish settings -->
            <div class="space-y-6 lg:sticky lg:top-8 lg:self-start">
                <section class="card p-5">
                    <h2 class="label">Platforms</h2>
                    <div v-if="loadingPlatforms" class="space-y-2">
                        <div v-for="n in 2" :key="n" class="h-12 animate-pulse rounded-lg bg-slate-100"></div>
                    </div>
                    <div v-else-if="platforms.length === 0" class="alert alert-warning">
                        <span>No platforms connected. <router-link to="/settings" class="font-semibold underline">Connect platforms</router-link></span>
                    </div>
                    <div v-else class="space-y-2">
                        <label v-for="platform in platforms" :key="platform.id" class="block cursor-pointer">
                            <input type="checkbox" :value="platform.id" v-model="form.platforms" class="peer sr-only" />
                            <span class="flex items-center gap-3 rounded-lg bg-white px-3.5 py-3 ring-1 ring-inset ring-slate-300 transition peer-checked:bg-brand-50 peer-checked:ring-2 peer-checked:ring-brand-600 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-600">
                                <PlatformIcon :type="platform.platform_type" :size="22" />
                                <span class="flex-1 text-sm font-medium text-slate-800">{{ platformLabel(platform.platform_type) }}</span>
                                <span class="flex h-5 w-5 items-center justify-center rounded-full ring-1 ring-inset transition"
                                    :class="form.platforms.includes(platform.id) ? 'bg-brand-600 text-white ring-brand-600' : 'text-transparent ring-slate-300'">
                                    <Check class="h-3.5 w-3.5" />
                                </span>
                            </span>
                        </label>
                    </div>
                </section>

                <section class="card p-5">
                    <label class="flex cursor-pointer items-center justify-between gap-3">
                        <span class="flex items-center gap-2 text-sm font-medium text-slate-700">
                            <CalendarClock class="h-4 w-4 text-slate-400" /> Schedule for later
                        </span>
                        <input type="checkbox" v-model="form.schedule" class="peer sr-only" />
                        <span class="relative h-6 w-11 rounded-full bg-slate-300 transition peer-checked:bg-brand-600 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-600 after:absolute after:top-0.5 after:left-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5"></span>
                    </label>
                    <input v-if="form.schedule" v-model="form.scheduled_at" type="datetime-local" class="input mt-4" required />
                </section>

                <button type="submit" class="btn btn-primary w-full py-3" :disabled="loading || form.platforms.length === 0">
                    <Send class="h-4 w-4" />
                    {{ loading ? 'Publishing...' : (form.schedule ? 'Schedule post' : 'Publish now') }}
                </button>
                <p v-if="form.platforms.length === 0 && !loadingPlatforms && platforms.length" class="-mt-3 text-center text-xs text-slate-500">Select at least one platform.</p>

                <div v-if="error" class="alert alert-error">{{ error }}</div>
                <div v-if="success" class="alert alert-success">{{ success }}</div>
            </div>
        </form>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { CalendarClock, Check, Film, Send, UploadCloud, X } from 'lucide-vue-next';
import PageHeader from '../components/PageHeader.vue';
import PlatformIcon from '../components/PlatformIcon.vue';
import { platformLabel } from '../platforms.js';

export default {
    name: 'CreatePost',
    components: { CalendarClock, Check, Film, Send, UploadCloud, X, PageHeader, PlatformIcon },
    setup() {
        const router = useRouter();
        const form = ref({
            content: '',
            platforms: [],
            media: [],
            schedule: false,
            scheduled_at: '',
        });
        const platforms = ref([]);
        const uploadedMedia = ref([]);
        const loading = ref(false);
        const loadingPlatforms = ref(true);
        const error = ref('');
        const success = ref('');

        const loadPlatforms = async () => {
            try {
                const response = await axios.get('/platforms');
                platforms.value = response.data.filter(p => p.is_active);
            } catch (error) {
                console.error('Error loading platforms:', error);
            } finally {
                loadingPlatforms.value = false;
            }
        };

        const getFileAcceptTypes = () => {
            // Images and videos only (the server verifies the real file content)
            return 'image/jpeg,image/png,image/gif,image/webp,image/bmp,video/*';
        };

        const handleFileUpload = async (event) => {
            const files = Array.from(event.target.files);
            loading.value = true;
            error.value = '';

            try {
                for (const file of files) {
                    const formData = new FormData();
                    formData.append('file', file);

                    const response = await axios.post('/media/upload', formData, {
                        // Don't set Content-Type - let axios/browser set it automatically with boundary
                        headers: {
                            // Remove Content-Type to let browser set it with boundary
                        },
                        timeout: 120000, // 2 minutes for large file uploads
                        onUploadProgress: (progressEvent) => {
                            if (progressEvent.total) {
                                const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                                console.log(`Upload progress: ${percentCompleted}%`);
                            }
                        },
                    });
                    
                    uploadedMedia.value.push(response.data);
                    form.value.media.push(response.data.id);
                }
            } catch (err) {
                const errorData = err.response?.data || {};
                let errorMsg = errorData.error || errorData.message || err.message;
                
                if (errorData.allowed_types) {
                    errorMsg += '\n\nAllowed file types: ' + errorData.allowed_types.join(', ');
                }
                
                error.value = errorMsg;
                console.error('File upload error:', errorData);
            } finally {
                loading.value = false;
            }
        };

        const removeMedia = (mediaId) => {
            uploadedMedia.value = uploadedMedia.value.filter(m => m.id !== mediaId);
            form.value.media = form.value.media.filter(id => id !== mediaId);
        };

        const handleSubmit = async () => {
            if (form.value.platforms.length === 0) {
                error.value = 'Please select at least one platform';
                return;
            }

            loading.value = true;
            error.value = '';
            success.value = '';

            try {
                const payload = {
                    content: form.value.content,
                    platforms: form.value.platforms,
                    media: form.value.media,
                };

                if (form.value.schedule) {
                    payload.scheduled_at = new Date(form.value.scheduled_at).toISOString();
                }

                const response = await axios.post('/posts', payload);
                
                // Check if there's a warning about some platforms failing
                if (response.data.warning) {
                    // Some platforms failed but post was saved
                    error.value = response.data.warning + '. Check platform statuses in Dashboard.';
                    // Don't redirect - let user see the error
                    return;
                }
                
                // Check if response indicates failure (status 422 or error field)
                if (response.status === 422 || response.data.error) {
                    // All platforms failed - post was not saved
                    const errorMsg = response.data.message || response.data.error || 'Failed to publish post to all platforms';
                    error.value = errorMsg;
                    
                    // Show detailed platform errors if available
                    if (response.data.platform_statuses) {
                        const platformErrors = Object.entries(response.data.platform_statuses)
                            .map(([platformId, status]) => {
                                const platformType = status.platform || 'Unknown';
                                const error = status.error || status.message || 'Failed';
                                return `${platformType}: ${error}`;
                            })
                            .join('\n');
                        error.value += '\n\n' + platformErrors;
                    }
                    
                    // Don't redirect - stay on page so user can fix and retry
                    return;
                }
                
                // Success - all platforms published successfully
                success.value = form.value.schedule ? 'Post scheduled successfully!' : 'Post published successfully!';
                
                // Only redirect on complete success
                setTimeout(() => {
                    router.push('/dashboard');
                }, 2000);
            } catch (err) {
                // Handle HTTP errors (422, 500, etc.)
                const errorData = err.response?.data || {};
                let errorMsg = errorData.message || errorData.error || 'Failed to create post';
                
                // Add detailed platform errors if available
                if (errorData.platform_statuses) {
                    const platformErrors = Object.entries(errorData.platform_statuses)
                        .map(([platformId, status]) => {
                            const platformType = status.platform || 'Unknown';
                            const error = status.error || status.message || 'Failed';
                            return `${platformType}: ${error}`;
                        })
                        .join('\n');
                    errorMsg += '\n\nPlatform Errors:\n' + platformErrors;
                }
                
                error.value = errorMsg;
                // Don't redirect on error - stay on page
            } finally {
                loading.value = false;
            }
        };

        onMounted(loadPlatforms);

        return {
            platformLabel,
            form,
            platforms,
            uploadedMedia,
            loading,
            loadingPlatforms,
            error,
            success,
            getFileAcceptTypes,
            handleFileUpload,
            removeMedia,
            handleSubmit,
        };
    },
};
</script>
