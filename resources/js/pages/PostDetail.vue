<template>
    <div>
        <button class="btn btn-ghost -ml-3 mb-4 px-3! py-1.5!" @click="goBack">
            <ArrowLeft class="h-4 w-4" /> Back
        </button>

        <div v-if="loading" class="space-y-4">
            <div class="card h-24 animate-pulse bg-slate-100"></div>
            <div class="card h-72 animate-pulse bg-slate-100"></div>
        </div>

        <div v-else-if="notFound" class="card px-5 py-14 text-center">
            <p class="font-medium text-slate-900">Post not found</p>
            <router-link to="/posts" class="btn btn-primary mt-5">Back to posts</router-link>
        </div>

        <template v-else>
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ editable ? 'Edit scheduled post' : 'Post' }}</h1>
                <span class="badge capitalize" :class="statusBadge(post.status)">{{ post.status }}</span>
            </div>

            <!-- Editable: still waiting to be published -->
            <form v-if="editable" class="space-y-6" @submit.prevent="save">
                <section class="card p-5">
                    <span class="label">Content</span>
                    <RichTextEditor v-model="form.content_html" v-model:text="form.content" />
                    <p class="mt-1.5 text-right text-xs text-slate-400">{{ form.content.length }} characters</p>
                </section>

                <section class="card p-5">
                    <span class="label">Hashtags <span class="font-normal text-slate-400">(optional)</span></span>
                    <TagsInput v-model="form.hashtags" hashtag placeholder="#hashtag, press Enter or space to add" hint="Added to the end of the text on every selected channel." />
                    <div class="mt-4 space-y-4">
                        <ChannelTexts mode="text" :platforms="selectedPlatforms" :content="form.content" :hashtags="form.hashtags" v-model:overrides="form.overrides" />
                        <ChannelTexts mode="hashtags" :platforms="selectedPlatforms" :content="form.content" :hashtags="form.hashtags" v-model:overrides="form.overrides" />
                    </div>
                </section>

                <section v-if="selectedPlatforms.some(p => p.platform_type === 'website')" class="card p-5">
                    <span class="label">Website tags <span class="font-normal text-slate-400">(optional)</span></span>
                    <TagsInput v-model="form.tags" />
                </section>

                <section class="card p-5">
                    <h2 class="label">Series <span class="font-normal text-slate-400">(optional)</span></h2>
                    <SeriesPicker v-model="form.series_id" />
                </section>

                <section class="card p-5">
                    <h2 class="label">Platforms</h2>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label v-for="platform in platforms" :key="platform.id" class="block cursor-pointer">
                            <input v-model="form.platforms" type="checkbox" :value="platform.id" class="peer sr-only" />
                            <span class="flex items-center gap-3 rounded-lg bg-white px-3.5 py-3 ring-1 ring-inset ring-slate-300 transition peer-checked:bg-brand-50 peer-checked:ring-2 peer-checked:ring-brand-600">
                                <PlatformIcon :type="platform.platform_type" :size="22" />
                                <span class="flex-1 text-sm font-medium text-slate-800">{{ platformLabel(platform.platform_type) }}</span>
                            </span>
                        </label>
                    </div>
                </section>

                <section class="card p-5">
                    <label class="label" for="edit-when">Publish at</label>
                    <input id="edit-when" v-model="form.scheduled_at" type="datetime-local" class="input sm:max-w-xs" required />
                </section>

                <section v-if="post.media?.length" class="card p-5">
                    <h2 class="label">Attached media</h2>
                    <ul class="grid gap-3 sm:grid-cols-2">
                        <li v-for="m in post.media" :key="m.id" class="flex items-center gap-3 rounded-lg bg-slate-50 p-2.5 ring-1 ring-slate-900/5">
                            <img v-if="m.type === 'image'" :src="mediaUrl(m)" :alt="m.original_filename" class="h-12 w-12 shrink-0 rounded-md object-cover" />
                            <span v-else class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md bg-slate-200 text-slate-500">
                                <Film v-if="m.type === 'video'" class="h-5 w-5" /><Music v-else class="h-5 w-5" />
                            </span>
                            <span class="min-w-0 flex-1 truncate text-sm text-slate-700">{{ m.original_filename }}</span>
                        </li>
                    </ul>
                    <p class="hint">Files can't be changed on a scheduled post. Delete it and create a new one to use different files.</p>
                </section>

                <div v-if="error" class="alert alert-error">{{ error }}</div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <button type="button" class="btn btn-danger-soft" @click="remove"><Trash2 class="h-4 w-4" /> Delete post</button>
                    <button type="submit" class="btn btn-primary" :disabled="saving || form.platforms.length === 0 || !canSave || lengthProblems.length > 0">
                        <Save class="h-4 w-4" /> {{ saving ? 'Saving...' : 'Save changes' }}
                    </button>
                </div>
            </form>

            <!-- Read-only: already published, failed or draft -->
            <div v-else class="space-y-6">
                <section class="card p-5">
                    <h2 class="label">Content</h2>
                    <p class="whitespace-pre-line break-words text-sm text-slate-800">{{ post.content }}</p>
                    <p class="mt-4 flex items-center gap-1.5 text-xs text-slate-500">
                        <Clock class="h-3.5 w-3.5" /> {{ formatDate(post.published_at || post.created_at) }}
                    </p>
                </section>

                <section v-if="statuses.length" class="card p-5">
                    <h2 class="label">Platforms</h2>
                    <ul class="space-y-2">
                        <li v-for="s in statuses" :key="s.id" class="rounded-lg bg-slate-50 px-3 py-2 ring-1 ring-slate-900/5">
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <PlatformIcon :type="s.type" :size="18" />
                                <span class="font-medium text-slate-700">{{ platformLabel(s.type) }}</span>
                                <span class="badge capitalize" :class="statusBadge(s.status)">{{ s.status }}</span>
                                <a v-if="s.url" :href="s.url" target="_blank" rel="noopener" class="ml-auto inline-flex items-center gap-1 text-xs font-medium text-brand-700 hover:text-brand-800">
                                    View post <ExternalLink class="h-3.5 w-3.5" />
                                </a>
                            </div>
                            <p v-if="s.error" class="mt-1.5 rounded-md bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-700">{{ s.error }}</p>
                        </li>
                    </ul>
                </section>

                <p class="alert alert-info">Published posts can't be edited because the content is already live on the platforms.</p>

                <div class="flex justify-end">
                    <button type="button" class="btn btn-danger-soft" @click="remove"><Trash2 class="h-4 w-4" /> Delete from history</button>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { ArrowLeft, Clock, ExternalLink, Film, Music, Save, Trash2 } from 'lucide-vue-next';
import ChannelTexts from '../components/ChannelTexts.vue';
import PlatformIcon from '../components/PlatformIcon.vue';
import RichTextEditor from '../components/RichTextEditor.vue';
import SeriesPicker from '../components/SeriesPicker.vue';
import TagsInput from '../components/TagsInput.vue';
import { confirmDialog, toast } from '../feedback.js';
import { platformLabel } from '../platforms.js';
import { forPlatform, length as textLength, limitFor } from '../postText.js';

// ISO (UTC) <-> the value a datetime-local input uses (local time)
const toLocalInput = (iso) => {
    const d = new Date(iso);
    return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
};

export default {
    name: 'PostDetail',
    components: { ArrowLeft, Clock, ExternalLink, Film, Music, Save, Trash2, ChannelTexts, PlatformIcon, RichTextEditor, SeriesPicker, TagsInput },
    setup() {
        const route = useRoute();
        const router = useRouter();
        const post = ref(null);
        const platforms = ref([]);
        const form = ref({ content: '', content_html: '', tags: [], hashtags: [], overrides: {}, platforms: [], scheduled_at: '' });
        const loading = ref(true);
        const notFound = ref(false);
        const saving = ref(false);
        const error = ref('');

        const editable = computed(() => post.value?.status === 'scheduled');
        const selectedPlatforms = computed(() => platforms.value.filter(p => form.value.platforms.includes(p.id)));
        // text (shared or a channel's own) or an attached file is enough; an image alone is fine
        const canSave = computed(() => form.value.content.trim() !== '' || (post.value?.media?.length || 0) > 0
            || Object.values(form.value.overrides).some(o => (o.content || '').trim() !== ''));
        const lengthProblems = computed(() => selectedPlatforms.value.flatMap((p) => {
            const limit = limitFor(p);
            const used = textLength(forPlatform(form.value.content, form.value.hashtags, form.value.overrides, p.id));
            return limit && used > limit ? [`${platformLabel(p.platform_type)} is ${used - limit} characters over its ${limit} limit.`] : [];
        }));

        const statuses = computed(() => Object.entries(post.value?.platform_statuses || {}).map(([id, s]) => ({
            id,
            type: s.platform || platforms.value.find(p => p.id === parseInt(id))?.platform_type || '',
            status: s.status,
            url: s.url,
            error: s.status === 'failed' ? (s.error || s.message) : '',
        })));

        const load = async () => {
            try {
                const [postRes, platformRes] = await Promise.all([axios.get(`/posts/${route.params.id}`), axios.get('/platforms')]);
                post.value = postRes.data;
                platforms.value = platformRes.data.filter(p => p.is_active || (postRes.data.platforms || []).includes(p.id));
                const escape = (t) => t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                form.value = {
                    content: post.value.content,
                    // posts saved before the editor existed only have plain text
                    content_html: post.value.content_html
                        || post.value.content.split(/\n{2,}/).map(p => `<p>${escape(p).replace(/\n/g, '<br>')}</p>`).join(''),
                    tags: [...(post.value.tags || [])],
                    hashtags: [...(post.value.hashtags || [])],
                    series_id: post.value.series_id || null,
                    overrides: JSON.parse(JSON.stringify(post.value.platform_overrides || {})),
                    platforms: [...(post.value.platforms || [])],
                    scheduled_at: post.value.scheduled_at ? toLocalInput(post.value.scheduled_at) : '',
                };
            } catch (err) {
                notFound.value = true;
            } finally {
                loading.value = false;
            }
        };

        const save = async () => {
            saving.value = true;
            error.value = '';
            try {
                await axios.put(`/posts/${post.value.id}`, {
                    content: form.value.content,
                    content_html: form.value.content_html,
                    tags: form.value.tags,
                    hashtags: form.value.hashtags,
                    series_id: form.value.series_id,
                    platform_overrides: Object.fromEntries(Object.entries(form.value.overrides)
                        .filter(([id]) => form.value.platforms.includes(Number(id)))
                        .map(([id, o]) => [id, { ...(o.content?.trim() ? { content: o.content } : {}), ...(o.hashtags ? { hashtags: o.hashtags } : {}) }])
                        .filter(([, o]) => Object.keys(o).length)),
                    platforms: form.value.platforms,
                    scheduled_at: new Date(form.value.scheduled_at).toISOString(),
                });
                toast('Changes saved', 'success');
                router.push('/posts?status=scheduled');
            } catch (err) {
                const data = err.response?.data || {};
                error.value = data.errors && !data.errors.media ? Object.values(data.errors).flat().join('\n') : (data.message || 'Could not save the changes.');
            } finally {
                saving.value = false;
            }
        };

        const remove = async () => {
            const confirmed = await confirmDialog({
                title: editable.value ? 'Delete this scheduled post?' : 'Delete this post?',
                message: editable.value ? 'It will not be published.' : 'It is removed from your history. Content already on the platforms stays there.',
                confirmText: 'Delete',
                danger: true,
            });
            if (!confirmed) return;
            try {
                await axios.delete(`/posts/${post.value.id}`);
                toast('Post deleted', 'success');
                router.push('/posts');
            } catch (err) {
                toast(err.response?.data?.message || 'Could not delete the post', 'error');
            }
        };

        const goBack = () => (window.history.length > 1 ? router.back() : router.push('/posts'));
        const mediaUrl = (m) => '/storage/' + m.path;
        const formatDate = (date) => new Date(date).toLocaleString();
        const statusBadge = (value) => ({
            published: 'badge-success',
            scheduled: 'badge-warning',
            failed: 'badge-danger',
        })[value] || 'badge-neutral';

        onMounted(load);

        return { post, platforms, form, loading, notFound, saving, error, editable, selectedPlatforms, canSave, lengthProblems, statuses, save, remove, goBack, mediaUrl, formatDate, statusBadge, platformLabel };
    },
};
</script>
