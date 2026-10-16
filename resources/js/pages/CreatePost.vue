<template>
    <div>
        <PageHeader title="Create post" description="Write it yourself or ask the AI, then choose where it goes." />

        <form @submit.prevent="handleSubmit" class="grid gap-6 lg:grid-cols-3">
            <!-- Composer -->
            <div class="space-y-6 lg:col-span-2">
                <div class="inline-flex rounded-lg bg-slate-100 p-1" role="tablist" aria-label="How to write the post">
                    <button type="button" role="tab" :aria-selected="mode === 'write'" class="flex items-center gap-2 rounded-md px-4 py-1.5 text-sm font-medium transition"
                        :class="mode === 'write' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'" @click="mode = 'write'">
                        <PenLine class="h-4 w-4" /> Write it myself
                    </button>
                    <button type="button" role="tab" :aria-selected="mode === 'ai'" class="flex items-center gap-2 rounded-md px-4 py-1.5 text-sm font-medium transition"
                        :class="mode === 'ai' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'" @click="mode = 'ai'">
                        <Sparkles class="h-4 w-4" /> Ask AI
                    </button>
                </div>

                <AskAiPanel v-show="mode === 'ai'" :platforms="form.platforms" :media="imageMediaIds" :has-images="imageMediaIds.length > 0" @draft="applyDraft" @project="useProjectChannels" />

                <section class="card p-5">
                    <div class="mb-1.5 flex items-center justify-between">
                        <span class="label mb-0">Content <span class="font-normal text-slate-400">(optional when you add an image or video)</span></span>
                        <span class="text-xs text-slate-400">{{ form.content.length }} characters</span>
                    </div>
                    <RichTextEditor v-model="form.content_html" v-model:text="form.content" placeholder="What would you like to post?" />
                    <p class="hint">Platforms publish plain text: bold and italic are kept in your draft, but lists become bullets and links show their address.</p>
                    <div class="mt-4">
                        <ChannelTexts mode="text" :platforms="selectedPlatforms" :content="form.content" :hashtags="form.hashtags" v-model:overrides="form.overrides" />
                    </div>
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
                        <span class="mt-1 text-xs text-slate-500">Images, videos and audio</span>
                        <input type="file" class="sr-only" multiple :accept="getFileAcceptTypes()" @change="handleFileUpload" />
                    </label>

                    <button type="button" class="btn btn-secondary btn-sm mt-3" data-library-toggle @click="toggleLibrary">
                        <Images class="h-4 w-4" /> {{ libraryOpen ? 'Hide your library' : 'Choose from your library' }}
                    </button>
                    <div v-if="libraryOpen" class="mt-3 rounded-lg bg-slate-50 p-3 ring-1 ring-slate-900/5" data-library>
                        <p v-if="libraryLoading" class="text-sm text-slate-500">Loading your files...</p>
                        <p v-else-if="libraryChoices.length === 0" class="text-sm text-slate-500">No saved files to add yet.</p>
                        <template v-else>
                            <ul class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                                <li v-for="file in libraryChoices" :key="file.id">
                                    <label class="block" :class="file.in_use ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'" :title="file.in_use ? 'On a scheduled post' : file.filename">
                                        <input v-model="librarySelected" type="checkbox" :value="file.id" :disabled="file.in_use" class="peer sr-only" />
                                        <span class="flex aspect-square items-center justify-center overflow-hidden rounded-md bg-slate-200 text-slate-500 ring-2 ring-transparent transition peer-checked:ring-brand-600 peer-focus-visible:ring-brand-400">
                                            <img v-if="file.type === 'image'" :src="file.url" :alt="file.filename" class="h-full w-full object-cover" loading="lazy" />
                                            <Film v-else-if="file.type === 'video'" class="h-6 w-6" />
                                            <Music v-else class="h-6 w-6" />
                                        </span>
                                        <span class="mt-1 block truncate text-[11px] text-slate-600">{{ file.filename }}</span>
                                    </label>
                                </li>
                            </ul>
                            <button type="button" class="btn btn-primary btn-sm mt-3" :disabled="librarySelected.length === 0" @click="addFromLibrary">
                                Add {{ librarySelected.length || '' }} selected
                            </button>
                        </template>
                    </div>

                    <p v-if="uploading" class="mt-4 text-xs font-medium text-slate-500">
                        Uploading file {{ uploadPosition }} of {{ uploads.length }}. Files upload one at a time.
                    </p>
                    <ul v-if="uploads.length > 0" class="mt-3 space-y-3">
                        <li v-for="item in uploads" :key="item.key" class="rounded-lg bg-slate-50 p-3 ring-1 ring-slate-900/5"
                            :class="{ 'bg-red-50! ring-red-200!': item.status === 'error' }" :data-status="item.status">
                            <div class="flex items-center gap-3">
                                <img v-if="item.preview" :src="item.preview" :alt="item.name" class="h-12 w-12 shrink-0 rounded-md object-cover" />
                                <span v-else class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md bg-slate-200 text-slate-500">
                                    <Film v-if="item.kind === 'video'" class="h-5 w-5" />
                                    <Music v-else-if="item.kind === 'audio'" class="h-5 w-5" />
                                    <FileIcon v-else class="h-5 w-5" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-800">{{ item.name }}</p>
                                    <p class="text-xs" :class="item.status === 'error' ? 'text-red-700' : item.status === 'done' ? 'text-emerald-700' : 'text-slate-500'">
                                        {{ formatSize(item.size) }} &middot; {{ statusText(item) }}
                                    </p>
                                </div>
                                <button type="button" @click="removeUpload(item)" class="btn btn-ghost p-2!" aria-label="Remove file"
                                    :disabled="item.status === 'uploading' || item.status === 'verifying'">
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                            <div v-if="item.status !== 'error'" class="mt-2.5 h-2 overflow-hidden rounded-full bg-slate-200" role="progressbar"
                                :aria-valuenow="item.progress" aria-valuemin="0" aria-valuemax="100">
                                <div class="h-full rounded-full transition-all duration-200"
                                    :class="[item.status === 'done' ? 'bg-emerald-500' : 'bg-brand-600', item.status === 'verifying' ? 'animate-pulse' : '']"
                                    :style="{ width: item.progress + '%' }"></div>
                            </div>
                            <p v-else class="mt-2 whitespace-pre-line text-xs text-red-800">{{ item.error }}</p>
                        </li>
                    </ul>

                    <!-- Asked once a video has finished uploading -->
                    <div v-if="hasVideo" class="mt-5 rounded-lg bg-slate-50 p-4 ring-1 ring-slate-900/5" data-thumbnail>
                        <p class="text-sm font-medium text-slate-800">Do you want to upload a thumbnail for this video?</p>
                        <div class="mt-3 flex gap-2">
                            <button type="button" class="btn" :class="thumbChoice === 'yes' ? 'btn-primary' : 'btn-secondary'" @click="thumbChoice = 'yes'">Yes, upload one</button>
                            <button type="button" class="btn" :class="thumbChoice === 'no' ? 'btn-primary' : 'btn-secondary'" @click="chooseNoThumbnail">No thanks</button>
                        </div>

                        <template v-if="thumbChoice === 'yes'">
                            <label v-if="!thumb" class="mt-3 flex cursor-pointer items-center gap-2 rounded-lg border-2 border-dashed border-slate-300 bg-white px-4 py-4 text-sm text-slate-600 hover:border-brand-400">
                                <ImageIcon class="h-5 w-5 text-slate-400" />
                                <span><span class="font-medium text-brand-700">Choose a thumbnail</span> (JPG or PNG, up to 2MB)</span>
                                <input type="file" class="sr-only" accept="image/jpeg,image/png" @change="handleThumbnail" />
                            </label>
                            <div v-else class="mt-3 rounded-lg bg-white p-3 ring-1 ring-slate-900/5" :class="{ 'bg-red-50! ring-red-200!': thumb.status === 'error' }" :data-status="thumb.status">
                                <div class="flex items-center gap-3">
                                    <img :src="thumb.preview" :alt="thumb.name" class="h-12 w-20 shrink-0 rounded-md object-cover" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-slate-800">{{ thumb.name }}</p>
                                        <p class="text-xs" :class="thumb.status === 'error' ? 'text-red-700' : thumb.status === 'done' ? 'text-emerald-700' : 'text-slate-500'">
                                            {{ formatSize(thumb.size) }} &middot; {{ statusText(thumb) }}
                                        </p>
                                    </div>
                                    <button type="button" @click="removeThumbnail" class="btn btn-ghost p-2!" aria-label="Remove thumbnail"
                                        :disabled="thumb.status === 'uploading' || thumb.status === 'verifying'">
                                        <X class="h-4 w-4" />
                                    </button>
                                </div>
                                <div v-if="thumb.status !== 'error'" class="mt-2.5 h-2 overflow-hidden rounded-full bg-slate-200" role="progressbar"
                                    :aria-valuenow="thumb.progress" aria-valuemin="0" aria-valuemax="100">
                                    <div class="h-full rounded-full transition-all duration-200"
                                        :class="[thumb.status === 'done' ? 'bg-emerald-500' : 'bg-brand-600', thumb.status === 'verifying' ? 'animate-pulse' : '']"
                                        :style="{ width: thumb.progress + '%' }"></div>
                                </div>
                                <p v-else class="mt-2 whitespace-pre-line text-xs text-red-800">{{ thumb.error }}</p>
                            </div>
                            <p v-if="tiktokSelected" class="mt-3 text-xs text-slate-500">
                                TikTok doesn't allow a custom thumbnail through its API, so it will use a frame from the video instead.
                            </p>
                        </template>
                    </div>
                </section>

                <section class="card p-5">
                    <span class="label">Hashtags <span class="font-normal text-slate-400">(optional)</span></span>
                    <TagsInput v-model="form.hashtags" hashtag placeholder="#hashtag, press Enter or space to add" hint="Added to the end of the text on every selected channel. Use the options below for a channel that needs different ones." />
                    <div class="mt-4">
                        <ChannelTexts mode="hashtags" :platforms="selectedPlatforms" :content="form.content" :hashtags="form.hashtags" v-model:overrides="form.overrides" />
                    </div>
                </section>

                <section class="card p-5">
                    <span class="label">Tags for your website <span class="font-normal text-slate-400">(optional)</span></span>
                    <TagsInput v-model="form.tags" :hint="websiteSelected ? 'Press Enter or comma to add a tag. Sent to your Website as the post tags. Different from hashtags, which go to social channels.' : 'Press Enter or comma to add a tag. Only sent when the Website platform is selected. Different from hashtags, which go to social channels.'" />
                </section>
            </div>

            <!-- Publish settings -->
            <div class="space-y-6 lg:sticky lg:top-8 lg:self-start">
                <section class="card p-5">
                    <h2 class="label">Series <span class="font-normal text-slate-400">(optional)</span></h2>
                    <SeriesPicker v-model="form.series_id" />
                </section>

                <section class="card p-5">
                    <h2 class="label">Platforms</h2>
                    <div v-if="loadingPlatforms" class="space-y-2">
                        <div v-for="n in 2" :key="n" class="h-12 animate-pulse rounded-lg bg-slate-100"></div>
                    </div>
                    <div v-else-if="platforms.length === 0" class="alert alert-warning">
                        <span>No platforms connected. <router-link to="/settings" class="font-semibold underline">Connect platforms</router-link></span>
                    </div>
                    <div v-else class="space-y-2">
                        <div v-for="platform in platforms" :key="platform.id">
                            <label class="block cursor-pointer">
                                <input type="checkbox" :value="platform.id" v-model="form.platforms" class="peer sr-only" />
                                <span class="flex items-center gap-3 rounded-lg bg-white px-3.5 py-3 ring-1 ring-inset ring-slate-300 transition peer-checked:bg-brand-50 peer-checked:ring-2 peer-checked:ring-brand-600 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-600"
                                    :class="{ 'peer-checked:bg-red-50! peer-checked:ring-red-500!': issues[platform.id] }">
                                    <PlatformIcon :type="platform.platform_type" :size="22" />
                                    <span class="flex-1 text-sm font-medium text-slate-800">{{ platformLabel(platform.platform_type) }}</span>
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full ring-1 ring-inset transition"
                                        :class="form.platforms.includes(platform.id) ? 'bg-brand-600 text-white ring-brand-600' : 'text-transparent ring-slate-300'">
                                        <Check class="h-3.5 w-3.5" />
                                    </span>
                                </span>
                            </label>
                            <ul v-if="issues[platform.id]" class="mt-1.5 space-y-1" data-issues>
                                <li v-for="message in issues[platform.id]" :key="message" class="flex items-start gap-1.5 text-xs text-red-700">
                                    <CircleAlert class="mt-0.5 h-3.5 w-3.5 shrink-0" /> {{ message }}
                                </li>
                            </ul>
                        </div>
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

                <button type="submit" class="btn btn-primary w-full py-3" :disabled="loading || uploading || hasIssues || form.platforms.length === 0 || !canPost || lengthProblems.length > 0">
                    <Send class="h-4 w-4" />
                    {{ loading ? 'Publishing...' : (form.schedule ? 'Schedule post' : 'Publish now') }}
                </button>
                <p v-if="uploading" class="-mt-3 text-center text-xs text-slate-500">Wait for the uploads to finish.</p>
                <p v-else-if="hasIssues" class="-mt-3 text-center text-xs text-red-700">Fix the file problems above, or deselect that platform.</p>
                <p v-else-if="lengthProblems.length" class="-mt-3 text-center text-xs text-red-700">{{ lengthProblems.join(" ") }}</p>
                <p v-else-if="form.platforms.length && !canPost" class="-mt-3 text-center text-xs text-slate-500">Write some text or add an image or video.</p>
                <p v-else-if="form.platforms.length === 0 && !loadingPlatforms && platforms.length" class="-mt-3 text-center text-xs text-slate-500">Select at least one platform.</p>

                <div v-if="error" class="alert alert-error">{{ error }}</div>
                <ul v-if="failures.length" class="space-y-2">
                    <li v-for="f in failures" :key="f.id" class="flex items-start gap-3 rounded-lg bg-red-50 p-3 ring-1 ring-inset ring-red-200">
                        <PlatformIcon v-if="f.type" :type="f.type" :size="20" class="mt-0.5 shrink-0" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-red-900">{{ f.type ? platformLabel(f.type) : 'Platform' }}</p>
                            <p class="mt-0.5 break-words text-sm text-red-800">{{ f.message }}</p>
                            <router-link v-if="f.type" :to="`/platforms/${f.type}`" class="mt-1.5 inline-block text-sm font-semibold text-red-900 underline">
                                Check {{ platformLabel(f.type) }} connection
                            </router-link>
                        </div>
                    </li>
                </ul>
                <div v-if="success" class="alert alert-success">{{ success }}</div>
            </div>
        </form>
    </div>
</template>

<script>
import { ref, reactive, markRaw, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { CalendarClock, Check, CircleAlert, File as FileIcon, Film, Image as ImageIcon, Images, Music, PenLine, Send, Sparkles, UploadCloud, X } from 'lucide-vue-next';
import AskAiPanel from '../components/AskAiPanel.vue';
import ChannelTexts from '../components/ChannelTexts.vue';
import PageHeader from '../components/PageHeader.vue';
import PlatformIcon from '../components/PlatformIcon.vue';
import RichTextEditor from '../components/RichTextEditor.vue';
import SeriesPicker from '../components/SeriesPicker.vue';
import TagsInput from '../components/TagsInput.vue';
import { platformLabel } from '../platforms.js';
import { cleanHashtags, forPlatform, length as textLength, limitFor } from '../postText.js';

export default {
    name: 'CreatePost',
    components: { CalendarClock, Check, CircleAlert, FileIcon, Film, ImageIcon, Images, Music, PenLine, Send, Sparkles, UploadCloud, X, AskAiPanel, ChannelTexts, PageHeader, PlatformIcon, RichTextEditor, SeriesPicker, TagsInput },
    setup() {
        const router = useRouter();
        const route = useRoute();
        const form = ref({
            content: '', // plain text, filled in by the editor
            content_html: '',
            tags: [], // website tags
            series_id: null,
            ai_generation_id: null, // the AI draft this post came from (kept in the project's memory)
            hashtags: [], // for the social channels
            overrides: {}, // {platformId: {content?, hashtags?}}: a channel's own text or hashtags
            platforms: [],
            media: [],
            thumbnail_media_id: null,
            schedule: false,
            scheduled_at: '',
        });
        const mode = ref('write'); // 'write' | 'ai'
        const platforms = ref([]);
        const loading = ref(false);
        const loadingPlatforms = ref(true);
        const error = ref('');
        const failures = ref([]);
        const success = ref('');

        // One entry per platform that failed, so each error is shown once and labelled
        const collectFailures = (statuses) => Object.entries(statuses || {})
            .filter(([, s]) => s.status === 'failed')
            .map(([id, s]) => ({ id, type: s.platform || null, message: s.error || s.message || 'Failed' }));

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
            // Images, video and audio (the server verifies the real file content)
            return 'image/*,video/*,audio/*';
        };

        // Each selected file becomes a queue item; items upload one at a time, in order.
        // An item is "verifying" once its bytes are sent and the server is still checking the file.
        const uploads = ref([]);
        const issues = ref({});
        // Optional custom thumbnail for the video: asked after a video is uploaded (thumbChoice: null | 'yes' | 'no')
        const thumb = ref(null);
        const thumbChoice = ref(null);
        const hasVideo = computed(() => uploads.value.some(u => u.kind === 'video' && u.status === 'done'));
        const tiktokSelected = computed(() => platforms.value.some(p => p.platform_type === 'tiktok' && form.value.platforms.includes(p.id)));
        const uploading = computed(() => [...uploads.value, thumb.value].some(u => u && ['queued', 'uploading', 'verifying'].includes(u.status)));
        const uploadPosition = computed(() => {
            const active = uploads.value.findIndex(u => ['uploading', 'verifying'].includes(u.status));
            return (active === -1 ? uploads.value.findIndex(u => u.status === 'queued') : active) + 1;
        });
        const hasIssues = computed(() => Object.keys(issues.value).length > 0);

        const selectedPlatforms = computed(() => platforms.value.filter(p => form.value.platforms.includes(p.id)));
        const websiteSelected = computed(() => selectedPlatforms.value.some(p => p.platform_type === 'website'));
        const imageMediaIds = computed(() => uploads.value.filter(u => u.kind === 'image' && u.status === 'done' && u.media).map(u => u.media.id));
        // a post needs text (the shared one or a channel's own) or a file; an image alone is fine, e.g. on Instagram
        const canPost = computed(() => form.value.content.trim() !== ''
            || form.value.media.length > 0
            || Object.values(form.value.overrides).some(o => (o.content || '').trim() !== ''));
        // channels whose final text (with hashtags) is longer than they allow, e.g. X over 280
        const lengthProblems = computed(() => selectedPlatforms.value.flatMap((p) => {
            const limit = limitFor(p);
            const used = textLength(forPlatform(form.value.content, form.value.hashtags, form.value.overrides, p.id));
            return limit && used > limit ? [`${platformLabel(p.platform_type)} is ${used - limit} characters over its ${limit} limit.`] : [];
        }));
        let queueRunning = false;
        let nextKey = 1;

        const formatSize = (bytes) => {
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
            return Math.max(1, Math.round(bytes / 1024)) + ' KB';
        };

        const statusText = (item) => ({
            queued: 'Waiting',
            uploading: `Uploading ${item.progress}%`,
            verifying: 'Checking file...',
            done: 'Uploaded',
            error: 'Failed',
        })[item.status];

        const uploadOne = async (item) => {
            item.status = 'uploading';
            item.progress = 0;

            const formData = new FormData();
            formData.append('file', item.file);

            try {
                const response = await axios.post('/media/upload', formData, {
                    timeout: 600000, // large videos on slow connections
                    onUploadProgress: (progressEvent) => {
                        if (!progressEvent.total) return;
                        item.progress = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                        if (progressEvent.loaded >= progressEvent.total) item.status = 'verifying';
                    },
                });
                item.media = response.data;
                item.progress = 100;
                item.status = 'done';
                if (item === thumb.value) {
                    form.value.thumbnail_media_id = response.data.id;
                } else {
                    form.value.media.push(response.data.id);
                }
            } catch (err) {
                const errorData = err.response?.data || {};
                let message = errorData.error || errorData.message || err.message;
                if (errorData.allowed_types) {
                    message += '\n\nAllowed file types: ' + errorData.allowed_types.join(', ');
                }
                item.status = 'error';
                item.error = message;
            }
        };

        // Start the next file only after the previous one is fully uploaded and verified
        const runQueue = async () => {
            if (queueRunning) return;
            queueRunning = true;
            let item;
            while ((item = uploads.value.find(u => u.status === 'queued'))) {
                await uploadOne(item);
            }
            queueRunning = false;
        };

        const handleFileUpload = (event) => {
            const files = Array.from(event.target.files);
            if (event.target.value) event.target.value = ''; // lets the same file be picked again

            for (const file of files) {
                uploads.value.push(reactive({
                    key: nextKey++,
                    file: markRaw(file), // a File must not be wrapped in a reactive proxy
                    name: file.name,
                    size: file.size,
                    kind: file.type.split('/')[0],
                    preview: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
                    status: 'queued',
                    progress: 0,
                    error: '',
                    media: null,
                }));
            }
            runQueue();
        };

        const removeUpload = (item) => {
            if (item.media) {
                form.value.media = form.value.media.filter(id => id !== item.media.id);
            }
            if (item.preview) URL.revokeObjectURL(item.preview);
            uploads.value = uploads.value.filter(u => u !== item);
        };

        const handleThumbnail = (event) => {
            const file = event.target.files[0];
            event.target.value = '';
            if (!file) return;

            thumb.value = reactive({
                key: nextKey++,
                file: markRaw(file),
                name: file.name,
                size: file.size,
                kind: 'image',
                preview: URL.createObjectURL(file),
                status: 'queued',
                progress: 0,
                error: '',
                media: null,
            });

            if (!['image/jpeg', 'image/png'].includes(file.type)) {
                thumb.value.status = 'error';
                thumb.value.error = 'The thumbnail must be a JPG or PNG image.';
            } else if (file.size > 2 * 1024 * 1024) {
                thumb.value.status = 'error';
                thumb.value.error = 'The thumbnail must be 2MB or smaller.';
            } else {
                uploadOne(thumb.value);
            }
        };

        const removeThumbnail = () => {
            if (thumb.value?.preview) URL.revokeObjectURL(thumb.value.preview);
            thumb.value = null;
            form.value.thumbnail_media_id = null;
        };

        const chooseNoThumbnail = () => {
            removeThumbnail();
            thumbChoice.value = 'no';
        };

        // Removing the last video also drops the question and any chosen thumbnail
        watch(hasVideo, (has) => {
            if (!has) {
                removeThumbnail();
                thumbChoice.value = null;
            }
        });

        // Reuse files kept in the user's library (admin setting: keep media after publishing)
        const libraryOpen = ref(false);
        const library = ref([]);
        const libraryLoading = ref(false);
        const librarySelected = ref([]);
        const libraryChoices = computed(() => library.value.filter(f => !uploads.value.some(u => u.media?.id === f.id)));

        const toggleLibrary = async () => {
            libraryOpen.value = !libraryOpen.value;
            if (!libraryOpen.value) return;
            libraryLoading.value = true;
            try {
                library.value = (await axios.get('/media')).data.data;
            } catch (err) {
                library.value = [];
            } finally {
                libraryLoading.value = false;
            }
        };

        const addFromLibrary = () => {
            for (const file of libraryChoices.value.filter(f => librarySelected.value.includes(f.id))) {
                uploads.value.push(reactive({
                    key: nextKey++,
                    name: file.filename,
                    size: file.size,
                    kind: file.type,
                    preview: file.type === 'image' ? file.url : null,
                    status: 'done',
                    progress: 100,
                    error: '',
                    media: file,
                }));
                form.value.media.push(file.id);
            }
            librarySelected.value = [];
            libraryOpen.value = false;
        };

        // Ask the server whether each selected platform can publish the uploaded files
        let checkId = 0;
        const refreshIssues = async () => {
            const id = ++checkId;
            if (form.value.platforms.length === 0) {
                issues.value = {};
                return;
            }
            try {
                const response = await axios.post('/media/check', {
                    platforms: form.value.platforms,
                    media: form.value.media,
                });
                if (id === checkId) issues.value = response.data.issues || {};
            } catch (err) {
                if (id === checkId) issues.value = {};
            }
        };
        watch(() => [[...form.value.platforms], [...form.value.media]], refreshIssues);

        onBeforeUnmount(() => [...uploads.value, thumb.value].forEach(u => u?.preview && URL.revokeObjectURL(u.preview)));

        const handleSubmit = async () => {
            if (form.value.platforms.length === 0) {
                error.value = 'Please select at least one platform';
                return;
            }

            loading.value = true;
            error.value = '';
            failures.value = [];
            success.value = '';

            try {
                const payload = {
                    content: form.value.content,
                    content_html: form.value.content_html,
                    tags: form.value.tags,
                    hashtags: form.value.hashtags,
                    series_id: form.value.series_id,
                    ...(form.value.ai_generation_id ? { ai_generation_id: form.value.ai_generation_id } : {}),
                    // only the channels that are selected, and only an own text that is not empty
                    platform_overrides: Object.fromEntries(Object.entries(form.value.overrides)
                        .filter(([id]) => form.value.platforms.includes(Number(id)))
                        .map(([id, o]) => [id, { ...(o.content?.trim() ? { content: o.content } : {}), ...(o.hashtags ? { hashtags: o.hashtags } : {}) }])
                        .filter(([, o]) => Object.keys(o).length)),
                    platforms: form.value.platforms,
                    media: form.value.media,
                };

                if (form.value.thumbnail_media_id) {
                    payload.thumbnail_media_id = form.value.thumbnail_media_id;
                }

                if (form.value.schedule) {
                    payload.scheduled_at = new Date(form.value.scheduled_at).toISOString();
                }

                const response = await axios.post('/posts', payload);
                
                // Check if there's a warning about some platforms failing
                if (response.data.warning) {
                    // Some platforms failed but post was saved
                    error.value = response.data.warning + '. Check platform statuses in Dashboard.';
                    failures.value = collectFailures(response.data.platform_statuses);
                    // Don't redirect - let user see the error
                    return;
                }
                
                // Check if response indicates failure (status 422 or error field)
                if (response.status === 422 || response.data.error) {
                    // All platforms failed - post was not saved
                    failures.value = collectFailures(response.data.platform_statuses);
                    error.value = failures.value.length
                        ? (response.data.error || 'Failed to publish post')
                        : (response.data.message || response.data.error || 'Failed to publish post to all platforms');

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
                failures.value = collectFailures(errorData.platform_statuses);
                // With per-platform failures listed below, the headline stays generic (no duplicate message)
                error.value = failures.value.length
                    ? (errorData.error || 'Failed to publish post')
                    : (errorData.message || errorData.error || 'Failed to create post');
                // Don't redirect on error - stay on page
            } finally {
                loading.value = false;
            }
        };

        // "Use as new post" on the Posts page: start from an existing post's text
        const copyContent = async () => {
            if (!route.query.copy) return;
            try {
                const response = await axios.get(`/posts/${encodeURIComponent(route.query.copy)}`);
                // posts written before the editor existed only have plain text: keep its line breaks
                const escape = (t) => t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                form.value.content_html = response.data.content_html
                    || response.data.content.split(/\n{2,}/).map(p => `<p>${escape(p).replace(/\n/g, '<br>')}</p>`).join('');
                form.value.tags = response.data.tags || [];
                form.value.hashtags = response.data.hashtags || [];
                form.value.series_id = response.data.series_id || null;
                form.value.overrides = response.data.platform_overrides || {};
            } catch (err) {
                error.value = 'Could not load the post to copy.';
            }
        };

        // The AI's draft fills the form: long text for everyone, a separate short text (and hashtags) for X, the image if made.
        // The user can change all of it, nothing is posted from here.
        // a project's default channels are used when none is chosen yet
        const useProjectChannels = (project) => {
            if (form.value.platforms.length) return;
            form.value.platforms = platforms.value.filter(p => (project.platform_ids || []).includes(p.id)).map(p => p.id);
        };

        const applyDraft = (draft) => {
            form.value.ai_generation_id = draft.generation_id || null;
            const escape = (t) => t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const long = draft.versions?.long || '';
            form.value.content_html = long.split(/\n{2,}/).map(par => `<p>${escape(par).replace(/\n/g, '<br>')}</p>`).join('');
            form.value.hashtags = cleanHashtags(draft.hashtags);

            const overrides = {};
            if (draft.versions?.short) {
                for (const p of platforms.value.filter(x => x.platform_type === 'twitter' && form.value.platforms.includes(x.id))) {
                    overrides[p.id] = { content: draft.versions.short, hashtags: cleanHashtags(draft.hashtags_short) };
                }
            }
            form.value.overrides = overrides;

            if (draft.image && !form.value.media.includes(draft.image.id)) {
                uploads.value.push(reactive({
                    key: nextKey++, name: draft.image.filename || 'AI image', size: draft.image.size || 0, kind: 'image',
                    preview: draft.image.url, status: 'done', progress: 100, error: '', media: draft.image,
                }));
                form.value.media.push(draft.image.id);
            }
        };

        onMounted(() => {
            loadPlatforms();
            copyContent();
        });

        return {
            platformLabel,
            mode,
            selectedPlatforms,
            websiteSelected,
            imageMediaIds,
            canPost,
            lengthProblems,
            applyDraft,
            useProjectChannels,
            form,
            platforms,
            uploads,
            libraryOpen,
            libraryLoading,
            libraryChoices,
            librarySelected,
            toggleLibrary,
            addFromLibrary,
            thumb,
            thumbChoice,
            hasVideo,
            tiktokSelected,
            handleThumbnail,
            removeThumbnail,
            chooseNoThumbnail,
            issues,
            uploading,
            uploadPosition,
            hasIssues,
            formatSize,
            statusText,
            loading,
            loadingPlatforms,
            error,
            failures,
            success,
            getFileAcceptTypes,
            handleFileUpload,
            removeUpload,
            handleSubmit,
        };
    },
};
</script>
