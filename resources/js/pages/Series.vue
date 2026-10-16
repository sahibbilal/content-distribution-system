<template>
    <div>
        <PageHeader title="Series" description="Group posts into series, and see how many you posted in each and the last two.">
            <router-link to="/create-post" class="btn btn-primary"><Plus class="h-4 w-4" /> New post</router-link>
        </PageHeader>

        <form class="card mb-6 flex flex-wrap items-center gap-3 p-4" @submit.prevent="add">
            <input v-model="name" class="input sm:max-w-sm" maxlength="100" placeholder="New series name, e.g. GKMBA series" aria-label="New series name" />
            <button type="submit" class="btn btn-primary" :disabled="busy || !name.trim()"><Plus class="h-4 w-4" /> Add series</button>
            <p v-if="error" class="w-full text-sm text-red-700">{{ error }}</p>
        </form>

        <div v-if="loading" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div v-for="n in 3" :key="n" class="card h-48 animate-pulse bg-slate-100"></div>
        </div>

        <div v-else-if="!series.length" class="card px-5 py-14 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-600"><Layers class="h-6 w-6" /></div>
            <p class="mt-4 font-medium text-slate-900">No series yet</p>
            <p class="mt-1 text-sm text-slate-500">Add one above, then pick it when you create a post.</p>
        </div>

        <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <section v-for="s in series" :key="s.id" class="card flex flex-col p-5">
                <div class="flex items-start justify-between gap-3">
                    <div v-if="editing === s.id" class="flex min-w-0 flex-1 gap-2">
                        <input v-model="editName" class="input" maxlength="100" aria-label="Series name" @keydown.enter.prevent="rename(s)" />
                        <button type="button" class="btn btn-primary btn-sm" :disabled="!editName.trim()" @click="rename(s)">Save</button>
                    </div>
                    <h2 v-else class="min-w-0 truncate text-lg font-semibold text-slate-900">{{ s.name }}</h2>
                    <div class="flex shrink-0 gap-1">
                        <button type="button" class="btn btn-ghost p-2!" aria-label="Rename series" @click="startEdit(s)"><Pencil class="h-4 w-4" /></button>
                        <button type="button" class="btn btn-ghost p-2!" aria-label="Delete series" @click="remove(s)"><Trash2 class="h-4 w-4" /></button>
                    </div>
                </div>

                <div class="mt-3 flex gap-6">
                    <div><p class="text-2xl font-bold text-slate-900">{{ s.published_count }}</p><p class="text-xs text-slate-500">Posted</p></div>
                    <div><p class="text-2xl font-bold text-slate-900">{{ s.scheduled_count }}</p><p class="text-xs text-slate-500">Scheduled</p></div>
                </div>

                <div class="mt-4 flex-1 space-y-3 border-t border-slate-100 pt-4">
                    <p v-if="!s.latest.length" class="text-sm text-slate-500">Nothing posted in this series yet.</p>
                    <router-link v-for="(p, i) in s.latest" :key="p.id" :to="`/posts/${p.id}`" class="block rounded-lg p-2 transition hover:bg-slate-50">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ i === 0 ? 'Last post' : 'Second last' }} &middot; {{ formatDate(p.published_at) }}</p>
                        <p class="mt-0.5 line-clamp-2 whitespace-pre-line break-words text-sm text-slate-800" dir="auto">{{ p.content }}</p>
                    </router-link>
                </div>

                <router-link :to="{ path: '/posts', query: { series: s.id } }" class="mt-4 text-sm font-medium text-brand-700 hover:text-brand-800">See all posts in this series</router-link>
            </section>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { Layers, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import PageHeader from '../components/PageHeader.vue';
import { confirmDialog, toast } from '../feedback.js';

export default {
    name: 'Series',
    components: { Layers, Pencil, Plus, Trash2, PageHeader },
    setup() {
        const series = ref([]);
        const loading = ref(true);
        const name = ref('');
        const busy = ref(false);
        const error = ref('');
        const editing = ref(null);
        const editName = ref('');

        const message = (err, fallback) => err.response?.data?.errors?.name?.[0] || err.response?.data?.message || fallback;

        const load = async () => {
            try {
                series.value = (await axios.get('/post-series')).data;
            } catch (err) {
                error.value = message(err, 'Could not load your series.');
            } finally {
                loading.value = false;
            }
        };

        const add = async () => {
            busy.value = true;
            error.value = '';
            try {
                await axios.post('/post-series', { name: name.value });
                name.value = '';
                await load();
            } catch (err) {
                error.value = message(err, 'Could not add the series.');
            } finally {
                busy.value = false;
            }
        };

        const startEdit = (s) => {
            editing.value = s.id;
            editName.value = s.name;
        };

        const rename = async (s) => {
            try {
                await axios.put(`/post-series/${s.id}`, { name: editName.value });
                editing.value = null;
                await load();
            } catch (err) {
                toast(message(err, 'Could not rename the series.'), 'error');
            }
        };

        const remove = async (s) => {
            const ok = await confirmDialog({ title: `Delete "${s.name}"?`, message: 'The posts stay; they just no longer belong to a series.', confirmText: 'Delete', danger: true });
            if (!ok) return;
            try {
                await axios.delete(`/post-series/${s.id}`);
                await load();
            } catch (err) {
                toast(message(err, 'Could not delete the series.'), 'error');
            }
        };

        const formatDate = (d) => (d ? new Date(d).toLocaleDateString() : '');

        onMounted(load);

        return { series, loading, name, busy, error, editing, editName, add, startEdit, rename, remove, formatDate };
    },
};
</script>
