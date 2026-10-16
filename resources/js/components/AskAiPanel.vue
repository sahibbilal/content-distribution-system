<template>
    <section class="card border-l-4 border-brand-600 p-5">
        <div class="flex items-center gap-2">
            <Sparkles class="h-4 w-4 text-brand-600" />
            <h2 class="font-semibold text-slate-900">Ask AI</h2>
        </div>
        <p class="mt-1 text-sm text-slate-600">
            Describe the post. The draft fills the form below (a long version, a short one for X, hashtags and an image if you ask). You can change anything before posting.
        </p>

        <!-- Skills: which rules and which project this chat follows -->
        <div class="mt-4 rounded-lg bg-slate-50 p-4 ring-1 ring-slate-900/5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-sm font-medium text-slate-800">Skills for this chat</span>
                <router-link to="/ai-assistant" class="text-sm font-medium text-brand-700 hover:text-brand-800">Edit instructions and projects</router-link>
            </div>

            <p class="mt-3 text-xs font-medium uppercase tracking-wide text-slate-500">Instructions to follow</p>
            <div class="mt-1.5 flex flex-wrap gap-2">
                <label v-for="s in chatSections" :key="s.key" class="block cursor-pointer" :title="s.help">
                    <input v-model="instructionKeys" type="checkbox" :value="s.key" :disabled="busy" class="peer sr-only" />
                    <span class="flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-sm text-slate-700 ring-1 ring-inset ring-slate-300 transition peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:ring-2 peer-checked:ring-brand-600 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-600">
                        <ScrollText class="h-3.5 w-3.5" /> {{ s.title }}
                    </span>
                </label>
                <span v-if="!chatSections.length" class="text-sm text-slate-500">Loading...</span>
            </div>

            <p class="mt-3 text-xs font-medium uppercase tracking-wide text-slate-500">Project</p>
            <div class="mt-1.5 flex flex-wrap gap-2">
                <button type="button" class="flex items-center gap-1.5 rounded-full px-3 py-1 text-sm ring-1 ring-inset transition" :disabled="busy"
                    :class="!projectId ? 'bg-brand-50 text-brand-700 ring-2 ring-brand-600' : 'bg-white text-slate-700 ring-slate-300 hover:bg-slate-50'" @click="projectId = ''">
                    No project
                </button>
                <button v-for="p in projects" :key="p.id" type="button" class="flex items-center gap-1.5 rounded-full px-3 py-1 text-sm ring-1 ring-inset transition" :disabled="busy"
                    :class="projectId === p.id ? 'bg-brand-50 text-brand-700 ring-2 ring-brand-600' : 'bg-white text-slate-700 ring-slate-300 hover:bg-slate-50'" @click="projectId = p.id">
                    <FolderOpen class="h-3.5 w-3.5" /> {{ p.name }}
                </button>
            </div>
            <p class="mt-2 text-xs text-slate-500">A project has its own instructions and remembers what it already wrote, so it does not repeat itself.</p>
        </div>

        <label class="label mt-4" for="ai-request">What should it write about?</label>
        <textarea id="ai-request" v-model="prompt" rows="3" maxlength="2000" dir="auto" class="input" :disabled="busy"
            placeholder="e.g. Search the latest news about Laravel 12 and write a post for developers"></textarea>

        <div class="mt-4 grid gap-2 sm:grid-cols-3">
            <label class="check"><input v-model="options.web_search" type="checkbox" :disabled="busy" /> Search the web first</label>
            <label class="check"><input v-model="options.hashtags" type="checkbox" :disabled="busy" /> Add hashtags</label>
            <label class="check"><input v-model="options.make_image" type="checkbox" :disabled="busy" /> Make an image</label>
        </div>
        <p v-if="hasImages" class="mt-2 text-xs text-slate-500">The AI will also look at the images you uploaded and write about them.</p>

        <div class="mt-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="label" for="ai-engine">AI</label>
                <select id="ai-engine" v-model="engine" class="input" :disabled="busy">
                    <option value="anthropic">Anthropic API (fast, a few seconds)</option>
                    <option value="worker">Claude worker (slower, minutes)</option>
                </select>
            </div>
            <button type="button" class="btn btn-primary" :disabled="busy || !prompt.trim()" @click="generate">
                <Loader2 v-if="busy" class="h-4 w-4 animate-spin" />
                <Sparkles v-else class="h-4 w-4" />
                {{ busy ? (step ? 'Working: ' + step : 'Writing...') : 'Generate' }}
            </button>
            <button v-if="busy && engine === 'worker'" type="button" class="btn btn-ghost" @click="stop">Stop waiting</button>
        </div>
        <p v-if="!platforms.length" class="mt-2 text-xs text-amber-700">Tip: choose the channels first, so the AI writes the right versions (for example a short one for X).</p>

        <p v-if="error" class="alert alert-error mt-4">{{ error }}</p>
        <template v-if="done">
            <p v-if="done.image_check && !done.image_check.ok" class="alert alert-warning mt-4">
                <CircleAlert class="mt-0.5 h-4 w-4 shrink-0" /> Check your image: {{ done.image_check.note }}
            </p>
            <p v-else-if="done.image_check?.note" class="alert alert-info mt-4">Image check: {{ done.image_check.note }}</p>
            <p v-if="done.image_error" class="alert alert-warning mt-4">The text is ready, but the image could not be made: {{ done.image_error }}</p>
            <p v-for="n in done.notes" :key="n" class="alert alert-info mt-4">{{ n }}</p>
        </template>
    </section>
</template>

<script>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { CircleAlert, FolderOpen, Loader2, ScrollText, Sparkles } from 'lucide-vue-next';

// Sends the request to the server and hands the draft to the page (it does not post anything).
// The Anthropic API answers in one request; the Claude worker takes minutes, so its result is polled.
export default {
    name: 'AskAiPanel',
    components: { CircleAlert, FolderOpen, Loader2, ScrollText, Sparkles },
    props: {
        platforms: { type: Array, default: () => [] }, // selected platform ids
        media: { type: Array, default: () => [] }, // uploaded media ids (the AI looks at the images)
        hasImages: { type: Boolean, default: false },
    },
    emits: ['draft', 'project'],
    setup(props, { emit }) {
        const prompt = ref('');
        const projects = ref([]);
        const projectId = ref('');
        const sections = ref([]);
        const instructionKeys = ref([]);
        // the rules that apply to a post (images are added by itself when "Make an image" is on; the rest are for later steps)
        const chatSections = computed(() => sections.value.filter(s => s.key !== 'images' && !s.used_by.includes('next')));
        const engine = ref('anthropic');
        const options = ref({ web_search: true, hashtags: true, make_image: false });
        const busy = ref(false);
        const step = ref('');
        const error = ref('');
        const done = ref(null);
        let timer = null;

        // picking a project applies its starting choices (and tells the page its default channels)
        watch(projectId, (id) => {
            const project = projects.value.find(p => p.id === id);
            if (!project) return;
            options.value = { ...options.value, ...project.options };
            emit('project', project);
        });

        onMounted(async () => {
            try {
                const { data } = await axios.get('/ai/assistant');
                projects.value = data.projects;
                sections.value = data.sections;
                instructionKeys.value = data.sections.filter(s => s.used_by === 'Every post').map(s => s.key);
            } catch (err) {
                // Ask AI still works without projects
            }
        });

        const finish = (result) => {
            busy.value = false;
            step.value = '';
            done.value = result;
            emit('draft', result);
        };

        const fail = (err) => {
            busy.value = false;
            step.value = '';
            error.value = err.response?.data?.message || err.message || 'The AI could not write the draft.';
        };

        const poll = (id, startedAt) => {
            timer = setTimeout(async () => {
                try {
                    const { data } = await axios.get(`/ai/draft/${id}`);
                    if (data.status === 'ready') return finish(data);
                    if (data.status === 'failed') return fail({ message: data.message });
                    step.value = data.step === 'importing' ? 'getting the image' : (data.step || '');
                    if (Date.now() - startedAt > 15 * 60 * 1000) return fail({ message: 'The worker is taking too long. Try again, or use the Anthropic API.' });
                    poll(id, startedAt);
                } catch (err) {
                    fail(err);
                }
            }, 4000);
        };

        const generate = async () => {
            busy.value = true;
            error.value = '';
            done.value = null;
            try {
                const { data, status } = await axios.post('/ai/draft', {
                    prompt: prompt.value,
                    engine: engine.value,
                    project_id: projectId.value || null,
                    instructions: instructionKeys.value,
                    platforms: props.platforms,
                    media: props.media,
                    ...options.value,
                }, { timeout: 150000 });
                if (status === 202) {
                    step.value = 'starting';
                    poll(data.episode_id, Date.now());
                } else {
                    finish(data);
                }
            } catch (err) {
                fail(err);
            }
        };

        const stop = () => {
            clearTimeout(timer);
            busy.value = false;
            step.value = '';
        };

        onBeforeUnmount(() => clearTimeout(timer));

        return { prompt, projects, projectId, chatSections, instructionKeys, engine, options, busy, step, error, done, generate, stop };
    },
};
</script>

<style scoped>
.check { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: #334155; cursor: pointer; }
</style>
