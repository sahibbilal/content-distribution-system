<template>
    <div>
        <PageHeader title="AI assistant" description="Your skills for Ask AI. Instructions are rules it follows; a project is a kind of content it keeps writing. Pick them in Ask AI on Create post." />

        <p v-if="error" class="alert alert-error mb-5">{{ error }}</p>

        <div v-if="loading" class="grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
            <div class="card h-72 animate-pulse bg-slate-100"></div>
            <div class="card h-72 animate-pulse bg-slate-100"></div>
        </div>

        <div v-else class="grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
            <!-- Side bar: two tabs, each a list of skills -->
            <aside class="card p-3 lg:sticky lg:top-8 lg:self-start">
                <div class="grid grid-cols-2 rounded-lg bg-slate-100 p-1" role="tablist">
                    <button v-for="t in tabs" :key="t.value" type="button" role="tab" :aria-selected="tab === t.value"
                        class="flex items-center justify-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition"
                        :class="tab === t.value ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'" @click="tab = t.value">
                        <component :is="t.icon" class="h-4 w-4" /> {{ t.label }}
                    </button>
                </div>
                <p class="mt-3 px-1 text-xs text-slate-500">{{ tab === 'instructions' ? 'Rules the AI follows. Tick the ones you want in each Ask AI chat.' : 'A kind of content with its own rules and memory. Pick one in Ask AI.' }}</p>

                <ul v-if="tab === 'instructions'" class="mt-3 space-y-1">
                    <li v-for="s in sections" :key="s.key">
                        <button type="button" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition"
                            :class="currentKey === s.key ? 'bg-brand-50 font-medium text-brand-700' : 'text-slate-700 hover:bg-slate-50'" @click="currentKey = s.key">
                            <ScrollText class="h-4 w-4 shrink-0 text-slate-400" />
                            <span class="min-w-0 flex-1 truncate">{{ s.title }}</span>
                            <span v-if="s.edited || drafts[s.key] !== s.body" class="h-2 w-2 shrink-0 rounded-full bg-amber-500" title="Edited"></span>
                        </button>
                    </li>
                </ul>

                <ul v-else class="mt-3 space-y-1">
                    <li v-for="p in projects" :key="p.id">
                        <button type="button" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition"
                            :class="selected?.id === p.id ? 'bg-brand-50 font-medium text-brand-700' : 'text-slate-700 hover:bg-slate-50'" @click="select(p)">
                            <FolderOpen class="h-4 w-4 shrink-0 text-slate-400" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate">{{ p.name }}</span>
                                <span class="block text-xs font-normal text-slate-500">{{ typeLabel(p.type) }} &middot; {{ p.memory_count }} remembered</span>
                            </span>
                        </button>
                    </li>
                    <li>
                        <button type="button" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium text-brand-700 transition hover:bg-brand-50"
                            :class="{ 'bg-brand-50': form && !form.id }" @click="newProject">
                            <Plus class="h-4 w-4 shrink-0" /> New project
                        </button>
                    </li>
                </ul>
            </aside>

            <!-- Editor for the item chosen in the side bar -->
            <div class="min-w-0">
                <section v-if="tab === 'instructions' && current" class="card p-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-lg font-semibold text-slate-900">{{ current.title }}</h2>
                        <span class="badge" :class="current.used_by.includes('next') ? 'badge-neutral' : 'badge-success'">{{ current.used_by }}</span>
                        <span v-if="current.edited || drafts[current.key] !== current.body" class="badge badge-warning">{{ drafts[current.key] !== current.body ? 'Unsaved changes' : 'Edited' }}</span>
                    </div>
                    <p class="mt-1 text-sm text-slate-600">{{ current.help }}</p>
                    <textarea v-model="drafts[current.key]" rows="16" dir="auto" class="input mt-4 font-mono text-sm leading-relaxed" :aria-label="current.title"></textarea>
                    <div class="mt-3 flex flex-wrap gap-3">
                        <button type="button" class="btn btn-primary" :disabled="busy === current.key || drafts[current.key] === current.body || !drafts[current.key].trim()" @click="saveSection(current)">
                            <Save class="h-4 w-4" /> Save
                        </button>
                        <button type="button" class="btn btn-secondary" :disabled="busy === current.key || (drafts[current.key] === current.default)" @click="resetSection(current)">
                            <RotateCcw class="h-4 w-4" /> Reset to starter text
                        </button>
                    </div>
                </section>

                <template v-else-if="tab === 'projects'">
                    <section v-if="form" class="card space-y-5 p-5">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label class="label" for="p-name">Name</label>
                                <input id="p-name" v-model="form.name" class="input" maxlength="100" placeholder="e.g. Jokes" />
                            </div>
                            <div>
                                <label class="label" for="p-type">Kind</label>
                                <select id="p-type" v-model="form.type" class="input">
                                    <option value="trends">Latest trends</option>
                                    <option value="jokes">Jokes</option>
                                    <option value="custom">Something else</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="label" for="p-inst">What this project creates</label>
                            <textarea id="p-inst" v-model="form.instructions" rows="10" dir="auto" class="input font-mono text-sm leading-relaxed"
                                placeholder="Describe the posts: topic, style, length, what to avoid..."></textarea>
                        </div>

                        <div>
                            <span class="label">Starting choices in Ask AI</span>
                            <div class="grid gap-2 sm:grid-cols-3">
                                <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700"><input v-model="form.options.web_search" type="checkbox" /> Search the web</label>
                                <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700"><input v-model="form.options.hashtags" type="checkbox" /> Add hashtags</label>
                                <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700"><input v-model="form.options.make_image" type="checkbox" /> Make an image</label>
                            </div>
                        </div>

                        <div>
                            <span class="label">Default channels</span>
                            <p v-if="!platforms.length" class="text-sm text-slate-500">No connected platforms yet.</p>
                            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                <label v-for="pl in platforms" :key="pl.id" class="block cursor-pointer">
                                    <input v-model="form.platform_ids" type="checkbox" :value="pl.id" class="peer sr-only" />
                                    <span class="flex items-center gap-3 rounded-lg bg-white px-3.5 py-2.5 ring-1 ring-inset ring-slate-300 transition peer-checked:bg-brand-50 peer-checked:ring-2 peer-checked:ring-brand-600">
                                        <PlatformIcon :type="pl.platform_type" :size="20" />
                                        <span class="text-sm font-medium text-slate-800">{{ platformLabel(pl.platform_type) }}</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <button type="button" class="btn btn-primary" :disabled="busy === 'project' || !form.name.trim()" @click="saveProject"><Save class="h-4 w-4" /> {{ form.id ? 'Save project' : 'Create project' }}</button>
                            <button v-if="form.id" type="button" class="btn btn-danger-soft" :disabled="busy === 'project'" @click="removeProject"><Trash2 class="h-4 w-4" /> Delete project</button>
                        </div>

                        <!-- Memory -->
                        <div v-if="form.id" class="border-t border-slate-100 pt-5">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="font-semibold text-slate-900">Memory</h3>
                                <button v-if="memory.length" type="button" class="btn btn-ghost btn-sm" @click="clearMemory">Clear memory</button>
                            </div>
                            <p class="mt-1 text-sm text-slate-600">What this project already wrote. The AI is told not to repeat these.</p>
                            <p v-if="!memory.length" class="mt-3 text-sm text-slate-500">Nothing yet. Drafts you generate with this project appear here.</p>
                            <ul v-else class="mt-3 max-h-80 divide-y divide-slate-100 overflow-y-auto rounded-lg ring-1 ring-slate-900/5">
                                <li v-for="m in memory" :key="m.id" class="flex items-start gap-3 px-3 py-2.5 text-sm">
                                    <span class="min-w-0 flex-1 break-words text-slate-800" dir="auto">{{ m.hook }}</span>
                                    <span class="badge shrink-0" :class="m.posted ? 'badge-success' : 'badge-neutral'">{{ m.posted ? 'Posted' : 'Draft' }}</span>
                                </li>
                            </ul>
                        </div>
                    </section>
                    <p v-else class="card px-5 py-14 text-center text-sm text-slate-500">Choose a project on the left, or create a new one.</p>
                </template>

                <p class="mt-4 text-sm text-slate-500">
                    Ready to use them?
                    <router-link to="/create-post" class="font-medium text-brand-700 hover:text-brand-800">Open Create post</router-link>
                    and choose Ask AI: the instructions and project are picked there.
                </p>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { FolderOpen, Plus, RotateCcw, Save, ScrollText, Trash2 } from 'lucide-vue-next';
import PageHeader from '../components/PageHeader.vue';
import PlatformIcon from '../components/PlatformIcon.vue';
import { confirmDialog, toast } from '../feedback.js';
import { platformLabel } from '../platforms.js';

const TYPES = { trends: 'Latest trends', jokes: 'Jokes', custom: 'Custom' };
const blank = () => ({ id: null, name: '', type: 'custom', instructions: '', platform_ids: [], options: { web_search: false, hashtags: true, make_image: false } });

export default {
    name: 'AiAssistant',
    components: { FolderOpen, Plus, RotateCcw, Save, ScrollText, Trash2, PageHeader, PlatformIcon },
    setup() {
        const tabs = [{ value: 'instructions', label: 'Instructions', icon: ScrollText }, { value: 'projects', label: 'Projects', icon: FolderOpen }];
        const tab = ref('instructions');
        const currentKey = ref('');
        const loading = ref(true);
        const error = ref('');
        const busy = ref('');
        const sections = ref([]);
        const drafts = reactive({});
        const projects = ref([]);
        const platforms = ref([]);
        const selected = ref(null);
        const form = ref(null);
        const memory = ref([]);
        const current = computed(() => sections.value.find(s => s.key === currentKey.value));

        const fail = (err, fallback) => {
            const data = err.response?.data || {};
            error.value = data.errors ? Object.values(data.errors).flat().join('\n') : data.message || fallback;
        };

        const load = async () => {
            try {
                const [assistant, plat] = await Promise.all([axios.get('/ai/assistant'), axios.get('/platforms')]);
                sections.value = assistant.data.sections;
                sections.value.forEach(s => { drafts[s.key] = s.body; });
                currentKey.value = sections.value[0]?.key || '';
                projects.value = assistant.data.projects;
                platforms.value = plat.data.filter(p => p.is_active);
            } catch (err) {
                fail(err, 'Could not load the assistant.');
            } finally {
                loading.value = false;
            }
        };

        const saveSection = async (s) => {
            busy.value = s.key;
            error.value = '';
            try {
                await axios.put(`/ai/instructions/${s.key}`, { body: drafts[s.key] });
                s.body = drafts[s.key];
                s.edited = s.body !== s.default;
                toast(`${s.title} saved`, 'success');
            } catch (err) {
                fail(err, 'Could not save.');
            } finally {
                busy.value = '';
            }
        };

        const resetSection = async (s) => {
            const ok = await confirmDialog({ title: 'Reset to the starter text?', message: `Your changes to "${s.title}" are replaced.`, confirmText: 'Reset', danger: true });
            if (!ok) return;
            busy.value = s.key;
            try {
                const { data } = await axios.delete(`/ai/instructions/${s.key}`);
                s.body = data.body;
                s.edited = false;
                drafts[s.key] = data.body;
                toast('Reset to the starter text', 'success');
            } catch (err) {
                fail(err, 'Could not reset.');
            } finally {
                busy.value = '';
            }
        };

        const loadMemory = async (id) => {
            try {
                memory.value = (await axios.get(`/ai/projects/${id}/memory`)).data;
            } catch (err) {
                memory.value = [];
            }
        };

        const select = (p) => {
            selected.value = p;
            form.value = JSON.parse(JSON.stringify(p));
            loadMemory(p.id);
        };

        const newProject = () => {
            selected.value = null;
            form.value = blank();
            memory.value = [];
        };

        const saveProject = async () => {
            busy.value = 'project';
            error.value = '';
            try {
                const body = { name: form.value.name, type: form.value.type, instructions: form.value.instructions, platform_ids: form.value.platform_ids, options: form.value.options };
                const { data } = form.value.id ? await axios.put(`/ai/projects/${form.value.id}`, body) : await axios.post('/ai/projects', body);
                const at = projects.value.findIndex(p => p.id === data.id);
                if (at === -1) projects.value.push(data);
                else projects.value[at] = data;
                select(data);
                toast('Project saved', 'success');
            } catch (err) {
                fail(err, 'Could not save the project.');
            } finally {
                busy.value = '';
            }
        };

        const removeProject = async () => {
            const ok = await confirmDialog({ title: `Delete "${form.value.name}"?`, message: 'Its instructions are removed. Posts already made stay.', confirmText: 'Delete', danger: true });
            if (!ok) return;
            busy.value = 'project';
            try {
                await axios.delete(`/ai/projects/${form.value.id}`);
                projects.value = projects.value.filter(p => p.id !== form.value.id);
                selected.value = null;
                form.value = null;
                toast('Project deleted', 'success');
            } catch (err) {
                fail(err, 'Could not delete the project.');
            } finally {
                busy.value = '';
            }
        };

        const clearMemory = async () => {
            const ok = await confirmDialog({ title: 'Clear the memory?', message: 'The AI may repeat earlier ideas afterwards.', confirmText: 'Clear', danger: true });
            if (!ok) return;
            await axios.delete(`/ai/projects/${form.value.id}/memory`);
            memory.value = [];
            selected.value.memory_count = 0;
        };

        onMounted(load);

        return {
            tabs, tab, currentKey, current, loading, error, busy, sections, drafts, projects, platforms, selected, form, memory,
            saveSection, resetSection, select, newProject, saveProject, removeProject, clearMemory, typeLabel: (t) => TYPES[t] || t, platformLabel,
        };
    },
};
</script>
